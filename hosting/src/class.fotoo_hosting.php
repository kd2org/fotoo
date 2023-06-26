<?php

class Fotoo_Hosting
{
	static private $base_index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	private $db = null;
	private $config = null;

	public function __construct(&$config)
	{
		$init = file_exists($config->db_file) ? false : true;
		$this->db = new SQLite3($config->db_file);

		if (!$this->db)
		{
			throw new FotooException("SQLite database init error.");
		}

		if ($init)
		{
			$this->db->exec('
				CREATE TABLE pictures (
					hash TEXT PRIMARY KEY NOT NULL,
					filename TEXT NOT NULL,
					date INT NOT NULL,
					format TEXT NOT NULL,
					width INT NOT NULL,
					height INT NOT NULL,
					thumb INT NOT NULL DEFAULT 0,
					private INT NOT NULL DEFAULT 0,
					size INT NOT NULL DEFAULT 0,
					album TEXT NULL,
					ip TEXT NULL
				);

				CREATE INDEX date ON pictures (private, date);
				CREATE INDEX album ON pictures (album);

				CREATE TABLE albums (
					hash TEXT PRIMARY KEY NOT NULL,
					title TEXT NOT NULL,
					date INT NOT NULL,
					private INT NOT NULL DEFAULT 0
				);
			');
		}

		$this->config =& $config;

		if (!file_exists($config->storage_path))
			mkdir($config->storage_path);
	}

    public function isClientBanned()
    {
    	if (!empty($_COOKIE['bstats']))
    		return true;

    	if (count($this->config->banned_ips) < 1)
    		return false;

        if (!empty($_SERVER['REMOTE_ADDR']) && self::isIpBanned($_SERVER['REMOTE_ADDR'], $this->config->banned_ips))
        {
        	return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP) 
        	&& self::isIpBanned($_SERVER['HTTP_X_FORWARDED_FOR'], $this->config->banned_ips))
        {
        	return true;
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP) 
        	&& self::isIpBanned($_SERVER['HTTP_CLIENT_IP'], $this->config->banned_ips))
        {
        	return true;
        }

        return false;
    }

    public function setBanCookie()
    {
    	return setcookie('bstats', md5(time()), time()+10*365*24*3600, '/');
    }

    static public function getIPAsString()
    {
    	$out = '';

        if (!empty($_SERVER['REMOTE_ADDR']))
        {
            $out .= $_SERVER['REMOTE_ADDR'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
        {
        	$out .= (!empty($out) ? ', ' : '') . 'X-Forwarded-For: ' . $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
        {
        	$out .= (!empty($out) ? ', ' : '') . 'Client-IP: ' . $_SERVER['HTTP_CLIENT_IP'];
        }

        return $out;
    }

    /**
     * Returns an integer if $ip is in addresses given in $check array
     * This integer may be used to store the IP address in database eventually
     *
     * Examples:
     * - check_ip('192.168.1.102', array('192.168.1.*'))
     * - check_ip('2a01:e34:ee89:c060:503f:d19b:b8fa:32fd', array('2a01::*'))
     * - check_ip('2a01:e34:ee89:c060:503f:d19b:b8fa:32fd', array('2a01:e34:ee89:c06::/64'))
     */
    static public function isIpBanned($ip, $check)
    {
        $ip = strtolower(is_null($ip) ? $_SERVER['REMOTE_ADDR'] : $ip);

        if (strpos($ip, ':') === false)
        {
            $ipv6 = false;
            $ip = ip2long($ip);
        }
        else
        {
            $ipv6 = true;
            $ip = bin2hex(inet_pton($ip));
        }

        foreach ($check as $c)
        {
            if (strpos($c, ':') === false)
            {
                if ($ipv6)
                {
                    continue;
                }

                // Check against mask
                if (strpos($c, '/') !== false)
                {
                    list($c, $mask) = explode('/', $c);
                    $c = ip2long($c);
                    $mask = ~((1 << (32 - $mask)) - 1);

                    if (($ip & $mask) == $c)
                    {
                        return $c;
                    }
                }
                elseif (strpos($c, '*') !== false)
                {
                    $c = substr($c, 0, -1);
                    $mask = substr_count($c, '.');
                    $c .= '0' . str_repeat('.0', (3 - $mask));
                    $c = ip2long($c);
                    $mask = ~((1 << (32 - ($mask * 8))) - 1);

                    if (($ip & $mask) == $c)
                    {
                        return $c;
                    }
                }
                else
                {
                    if ($ip == ip2long($c))
                    {
                        return $c;
                    }
                }
            }
            else
            {
                if (!$ipv6)
                {
                    continue;
                }

                // Check against mask
                if (strpos($c, '/') !== false)
                {
                    list($c, $mask) = explode('/', $c);
                    $c = bin2hex(inet_pton($c));
                    $mask = $mask / 4;
                    $c = substr($c, 0, $mask);

                    if (substr($ip, 0, $mask) == $c)
                    {
                        return $c;
                    }
                }
                elseif (strpos($c, '*') !== false)
                {
                    $c = substr($c, 0, -1);
                    $c = bin2hex(inet_pton($c));
                    $c = rtrim($c, '0');

                    if (substr($ip, 0, strlen($c)) == $c)
                    {
                        return $c;
                    }
                }
                else
                {
                    if ($ip == inet_pton($c))
                    {
                        return $c;
                    }
                }
            }
        }

        return false;
    }

	static private function baseConv($num, $base=null)
	{
		if (is_null($base))
			$base = strlen(self::$base_index);

		$index = substr(self::$base_index, 0, $base);

		$out = "";
		for ($t = floor(log10($num) / log10($base)); $t >= 0; $t--)
		{
			$a = floor($num / pow($base, $t));
			$out = $out . substr($index, $a, 1);
			$num = $num - ($a * pow($base, $t));
		}

		return $out;
	}

	static public function getErrorMessage($error)
	{
		switch ($error)
		{
			case UPLOAD_ERR_INI_SIZE:
				return 'The uploaded file exceeds the allowed file size (ini).';
			case UPLOAD_ERR_FORM_SIZE:
				return 'The uploaded file exceeds the allowed file size (html).';
			case UPLOAD_ERR_PARTIAL:
				return 'The uploaded file was only partially uploaded.';
			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded.';
			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Missing a temporary folder.';
			case UPLOAD_ERR_CANT_WRITE:
				return 'Failed to write file to disk.';
			case UPLOAD_ERR_EXTENSION:
				return 'A server extension stopped the file upload.';
			case UPLOAD_ERR_INVALID_IMAGE:
				return 'Invalid image format.';
			default:
				return 'Unknown error.';
		}
	}

	protected function _processEncodedUpload(&$file)
	{
		if (!is_array($file))
		{
			return false;
		}

		$file['error'] = $file['size'] = 0;

		if (empty($file['content']))
		{
			$file['error'] = UPLOAD_ERR_NO_FILE;
			return false;
		}

		if (!is_string($file['content']))
		{
			$file['error'] = UPLOAD_ERR_NO_FILE;
			return false;
		}

		$file['content'] = base64_decode($file['content'], true);

		if (empty($file['content']))
		{
			$file['error'] = UPLOAD_ERR_PARTIAL;
			return false;
		}

		$file['size'] = strlen($file['content']);

		if ($file['size'] == 0)
		{
			$file['error'] = UPLOAD_ERR_FORM_SIZE;
			return false;
		}

		$file['tmp_name'] = tempnam(ini_get('upload_tmp_dir') ?: sys_get_temp_dir(), 'tmp_file_');

		if (!$file['tmp_name'])
		{
			$file['error'] = UPLOAD_ERR_NO_TMP_DIR;
			return false;
		}

		if (!file_put_contents($file['tmp_name'], $file['content']))
		{
			$file['error'] = UPLOAD_ERR_CANT_WRITE;
			return false;
		}

		unset($file['content']);

		return true;
	}

	public function upload($file, $name = '', $private = false, $album = null)
	{
		if ($this->isClientBanned())
		{
			throw new FotooException('Upload error: upload not permitted.', -42);
		}

		$client_resize = false;

		if (isset($file['content']) && $this->_processEncodedUpload($file))
		{
			$client_resize = true;
		}

		if (!isset($file['error'])) {
			throw new FotooException("Upload error.", UPLOAD_ERR_NO_FILE);
		}

		if ($file['error'] != UPLOAD_ERR_OK) {
			throw new FotooException("Upload error.", $file['error']);
		}

		if (empty($file['tmp_name'])) {
			throw new FotooException("Upload error.", UPLOAD_ERR_NO_FILE);
		}

		// Make sure tmp_name is from us
		if (!file_exists($file['tmp_name'])
			|| !(is_uploaded_file($file['tmp_name']) || $client_resize)) {
			throw new FotooException("Upload error.", UPLOAD_ERR_NO_FILE);
		}

		if (!empty($name))
		{
			$name = preg_replace('!\s+!', '-', $name);
			$name = preg_replace('![^a-z0-9_.-]!i', '', $name);
			$name = preg_replace('!([_.-]){2,}!', '\\1', $name);
			$name = substr($name, 0, 30);
		}

		if (!trim($name))
		{
			$name = '';
		}

		try {
			$img = new Image($file['tmp_name']);
			$format = strtolower($img->format());

			if (empty($img->getSize()[0]) || !$img->format()
				|| !in_array($format, array_map('strtolower', $this->config->allowed_formats))) {
				throw new \RuntimeException('Invalid image');
			}
		}
		catch (\RuntimeException $e) {
			@unlink($file['tmp_name']);
			throw new FotooException("Invalid image format.", UPLOAD_ERR_INVALID_IMAGE);
		}

		list($width, $height) = $img->getSize();
		$format = $img->format();
		$size = filesize($file['tmp_name']);

		$hash = md5($file['tmp_name'] . time() . $width . $height . $format . $size . $file['name']);
		$dest = $this->config->storage_path . substr($hash, -2);

		if (!file_exists($dest)) {
			mkdir($dest);
		}

		$base = self::baseConv(hexdec(uniqid()));
		$dest .= '/' . $base;
		$ext = '.' . strtolower($format);

		if (trim($name) && !empty($name)) {
			$dest .= '.' . $name;
		}

		$max_mp = $this->config->max_width * $this->config->max_width;
		$img_mp = $width * $height;

		if ($img_mp > $max_mp)
		{
			$ratio = $img_mp / $max_mp;
			$width = round($width / $ratio);
			$height = round($height / $ratio);
			$resize = true;
		}
		else
		{
			$width = $width;
			$height = $height;
			$resize = false;
		}

		// If JPEG or big PNG/GIF, then resize (always resize JPEG to reduce file size)
		if (($format == 'jpeg' || $format == 'webp') && !$client_resize) {
			$resize = true;
		}
		elseif (($format == 'gif' || $format == 'png') && $file['size'] > (1024 * 1024)) {
			$resize = true;
		}

		if ($resize)
		{
			$img->resize($width, $height);
			$img->jpeg_quality = 80;
			$img->save($dest . $ext);
			list($width, $height) = $img->getSize();
			unset($img);
		}
		elseif ($client_resize)
		{
			rename($file['tmp_name'], $dest . $ext);
		}
		else
		{
			move_uploaded_file($file['tmp_name'], $dest . $ext);
		}

		$size = filesize($dest . $ext);

		// Create thumb when needed
		if ($width > $this->config->thumb_width || $height > $this->config->thumb_width
			|| $size > (100 * 1024) || !in_array($format, ['jpeg', 'png', 'webp']))
		{
			$img = new Image($dest . $ext);
			$img->jpeg_quality = 70;
			$img->webp_quality = 70;

			if (in_array('webp', $img->getSupportedFormats())) {
				$thumb_format = 'webp';
				$thumb_ext = '.s.webp';
			}
			elseif ($format !== 'png') {
				$thumb_format = 'jpeg';
				$thumb_ext = '.s.jpeg';
			}
			else {
				$thumb_format = $format;
				$thumb_ext = '.s.' . $format;
			}

			$img->resize(
				($width > $this->config->thumb_width) ? $this->config->thumb_width : $width,
				($height > $this->config->thumb_width) ? $this->config->thumb_width : $height
			);

			$img->save($dest . $thumb_ext, $thumb_format);

			$thumb = true;
		}
		else
		{
			$thumb = false;
			$thumb_format = 0;
		}

		$hash = substr($hash, -2) . '/' . $base;

		$req = $this->db->prepare('INSERT INTO pictures 
			(hash, filename, date, format, width, height, thumb, private, size, album, ip)
			VALUES (:hash, :filename, :date, :format, :width, :height, :thumb, :private, :size, :album, :ip);');

		$req->bindValue(':hash', $hash);
		$req->bindValue(':filename', $name);
		$req->bindValue(':date', time());
		$req->bindValue(':format', strtoupper($format));
		$req->bindValue(':width', (int)$width);
		$req->bindValue(':height', (int)$height);
		$req->bindValue(':thumb', $thumb_format === 0 ? $thumb_format : strtoupper($thumb_format));
		$req->bindValue(':private', $private ? '1' : '0');
		$req->bindValue(':size', (int)$size);
		$req->bindValue(':album', is_null($album) ? NULL : $album);
		$req->bindValue(':ip', self::getIPAsString());

		$req->execute();

		// Automated deletion of IP addresses to comply with local low
		$expiration = time() - ($this->config->ip_storage_expiration * 24 * 3600);
		$this->db->query('UPDATE pictures SET ip = "R" WHERE date < ' . (int)$expiration . ';');

		$url = $this->getUrl(['hash' => $hash, 'filename' => $name, 'format' => strtoupper($format)], true);

		return $url;
	}

	public function get($hash)
	{
		$res = $this->db->querySingle(
			'SELECT * FROM pictures WHERE hash = \''.$this->db->escapeString($hash).'\';',
			true
		);

		if (empty($res))
			return false;

		$file = $this->_getPath($res);
		$th = $this->_getPath($res, 's');

		if (!file_exists($file))
		{
			if (file_exists($th))
				@unlink($th);

			$this->db->exec('DELETE FROM pictures WHERE hash = \''.$res['hash'].'\';');
			return false;
		}

		return $res;
	}

	public function remove($hash, $id = null)
	{
		if (!$this->logged() && !$this->checkRemoveId($hash, $id))
			return false;

		$img = $this->get($hash);

		$file = $this->_getPath($img);

		if (file_exists($file))
			unlink($file);

		return $this->get($hash) ? false : true;
	}

	protected function getListQuery(bool $private = false)
	{
		$where = $private ? '' : 'AND private != 1';
		return sprintf('
			SELECT p.*, COUNT(*) AS count, a.title, a.private AS private
				FROM pictures p
				INNER JOIN albums a ON a.hash = p.album
				WHERE %s
				GROUP BY p.album
			UNION ALL
				SELECT *, 1 AS count, NULL AS title, p.private AS private
				FROM pictures p
				WHERE album IS NULL AND %s',
			$private ? '1' : 'a.private != 1',
			$private ? '1' : 'p.private != 1'
		);
	}

	public function getList($page)
	{
		$begin = ($page - 1) * $this->config->nb_pictures_by_page;
		$private = $this->logged();

		$out = [];
		$res = $this->db->query(sprintf(
			'SELECT * FROM (%s) ORDER BY date DESC LIMIT %d,%d;',
			$this->getListQuery($private),
			$begin,
			$this->config->nb_pictures_by_page
		));

		if (!$res) {
			throw new \RuntimeException($this->db->lastErrorMsg());
		}

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function countList()
	{
		return $this->db->querySingle(sprintf('SELECT COUNT(*) FROM (%s);', $this->getListQuery($this->logged())));
	}

	public function makeRemoveId($hash)
	{
		return sha1($this->config->storage_path . $hash);
	}

	public function checkRemoveId($hash, $id)
	{
		return sha1($this->config->storage_path . $hash) === $id;
	}

	public function getAlbumPrevNext($album, $current, $order = -1)
	{
		$st = $this->db->prepare('SELECT * FROM pictures WHERE album = :album
			AND rowid '.($order > 0 ? '>' : '<').' (SELECT rowid FROM pictures WHERE hash = :img)
			ORDER BY rowid '.($order > 0 ? 'ASC': 'DESC').' LIMIT 1;');
		$st->bindValue(':album', $album);
		$st->bindValue(':img', $current);
		$res = $st->execute();

		if ($res)
			return $res->fetchArray(SQLITE3_ASSOC);

		return false;
	}

	public function getAlbumExtract($hash)
	{
		$out = array();
		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\' ORDER BY RANDOM() LIMIT 2;');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function countAlbumList()
	{
		$where = $this->logged() ? '' : 'WHERE private != 1';
		return $this->db->querySingle('SELECT COUNT(*) FROM albums '.$where.';');
	}

	public function getAlbum($hash)
	{
		return $this->db->querySingle('SELECT *, strftime(\'%s\', date) AS date FROM albums WHERE hash = \''.$this->db->escapeString($hash).'\';', true);
	}

	public function getAlbumPictures($hash, $page)
	{
		$begin = ($page - 1) * $this->config->nb_pictures_by_page;

		$out = array();
		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\' ORDER BY date LIMIT '.$begin.','.$this->config->nb_pictures_by_page.';');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function getAllAlbumPictures($hash)
	{
		$out = array();
		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\' ORDER BY date;');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function downloadAlbum($hash)
	{
		$album = $this->getAlbum($hash);

		header('Content-Type: application/zip');
		header(sprintf('Content-Disposition: attachment; filename=%s.zip', preg_replace('/[^\w_-]+/U', '', $album['title'])));

		$zip = new ZipWriter('php://output');

		foreach ($this->getAllAlbumPictures($hash) as $picture) {
			$zip->add(sprintf('%s.%s', $picture['filename'], strtolower($picture['format'])), null, $this->_getPath($picture));
		}

		$zip->close();
	}

	public function countAlbumPictures($hash)
	{
		return $this->db->querySingle('SELECT COUNT(*) FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\';');
	}

	public function removeAlbum($hash, $id = null)
	{
		if (!$this->logged() && !$this->checkRemoveId($hash, $id))
			return false;

		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\';');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$file = $this->_getPath($row);

			if (file_exists($file))
				unlink($file);

			if ($this->get($row['hash']))
				return false;
		}

		$this->db->exec('DELETE FROM albums WHERE hash = \''.$this->db->escapeString($hash).'\';');
		return true;
	}

	protected function _getPath($img, $optional = '')
	{
		return $this->config->storage_path . $img['hash']
			. ($img['filename'] ? '.' . $img['filename'] : '')
			. ($optional ? '.' . $optional : '')
			. '.' . strtolower($img['format']);
	}

	public function getUrl($img, $append_id = false)
	{
		$url = $this->config->image_page_url
			. $img['hash']
			. ($img['filename'] ? '.' . $img['filename'] : '')
			. '.' . strtolower($img['format']);

		if ($append_id)
		{
			$id = $this->makeRemoveId($img['hash']);
			$url .= (strpos($url, '?') !== false) ? '&c=' . $id : '?c=' . $id;
		}

		return $url;
	}

	public function getImageUrl($img)
	{
		$url = $this->config->storage_url . $img['hash'];
		$url.= !empty($img['filename']) ? '.' . $img['filename'] : '';
		$url.= '.' . strtolower($img['format']);
		return $url;
	}

	public function getImageThumbUrl($img)
	{
		if (!$img['thumb'])
		{
			return $this->getImageUrl($img);
		}

		$format = strtolower($img['format']);

		if ((int)$img['thumb'] !== 1) {
			$format = strtolower($img['thumb']);
		}
		elseif ($format != 'jpeg' && $format != 'png')
		{
			$format = 'jpeg';
		}

		$url = $this->config->storage_url . $img['hash'];
		$url.= !empty($img['filename']) ? '.' . $img['filename'] : '';
		$url.= '.s.' . $format;
		return $url;
	}

	public function getShortImageUrl($img)
	{
		return $this->config->image_page_url
			. 'r.' . $img['hash'];
	}

	public function login($password)
	{
		if ($this->config->admin_password === $password)
		{
			@session_start();
			$_SESSION['logged'] = true;
			return true;
		}
		else
		{
			return false;
		}
	}

	public function logged()
	{
		if (array_key_exists(session_name(), $_COOKIE) && !isset($_SESSION))
		{
			session_start();
		}

		return empty($_SESSION['logged']) ? false : true;
	}

	public function logout()
	{
		$this->logged();
		$_SESSION = null;
		session_destroy();
		return true;
	}

	public function createAlbum($title, $private = false)
	{
		if ($this->isClientBanned())
		{
			throw new FotooException('Upload error: upload not permitted.');
		}

		$hash = self::baseConv(hexdec(uniqid()));
		$this->db->exec('INSERT INTO albums (hash, title, date, private)
			VALUES (\''.$this->db->escapeString($hash).'\',
			\''.$this->db->escapeString(trim($title)).'\',
			datetime(\'now\'), \''.(int)(bool)$private.'\');');
		return $hash;
	}

	public function appendToAlbum($album, $name, $file)
	{
		$album = $this->db->querySingle('SELECT * FROM albums WHERE hash = \''.$this->db->escapeString($album).'\';', true);

		if (!$album) {
			throw new FotooException('ALbum not found');
		}

		return $this->upload($file, $name, $album['private'], $album['hash']);
	}
}

?>