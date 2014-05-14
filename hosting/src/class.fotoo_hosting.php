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
					album TEXT NULL
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
		$client_resize = false;

		if (isset($file['content']) && $this->_processEncodedUpload($file))
		{
			$client_resize = true;
		}

		if (!isset($file['error']))
		{
			return false;
		}

		if ($file['error'] != UPLOAD_ERR_OK)
		{
			throw new FotooException("Upload error.", $file['error']);
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

		$options = array();
		$options[image::USE_GD_FAST_RESIZE_TRICK] = true;

		$img = image::identify($file['tmp_name'], $options);

		if (empty($img) || empty($img['format']) || empty($img['width']) || empty($img['height'])
			|| !in_array($img['format'], $this->config->allowed_formats))
		{
			@unlink($file['tmp_name']);
			throw new FotooException("Invalid image format.", UPLOAD_ERR_INVALID_IMAGE);
		}

		if ($img['format'] != 'PNG' && $img['format'] != 'JPEG' && $img['format'] != 'GIF')
		{
			$options[image::FORCE_IMAGICK] = true;
		}

		$size = filesize($file['tmp_name']);

		$hash = md5($file['tmp_name'] . time() . $img['width'] . $img['height'] . $img['format'] . $size . $file['name']);
		$dest = $this->config->storage_path . substr($hash, -2);

		if (!file_exists($dest))
			mkdir($dest);

		$base = self::baseConv(hexdec(uniqid()));
		$dest .= '/' . $base;
		$ext = '.' . strtolower($img['format']);

		if (trim($name) && !empty($name))
			$dest .= '.' . $name;

		$max_mp = $this->config->max_width * $this->config->max_width;
		$img_mp = $img['width'] * $img['height'];

		if ($img_mp > $max_mp)
		{
			$ratio = $img_mp / $max_mp;
			$width = round($img['width'] / $ratio);
			$height = round($img['height'] / $ratio);
			$resize = true;
		}
		else
		{
			$width = $img['width'];
			$height = $img['height'];
			$resize = false;
		}

		// If JPEG or big PNG/GIF, then resize (always resize JPEG to reduce file size)
		if ($resize || ($img['format'] == 'JPEG' && !$client_resize)
			|| (($img['format'] == 'GIF' || $img['format'] == 'PNG') && $file['size'] > (1024 * 1024)))
		{
			$res = image::resize(
				$file['tmp_name'],
				$dest . $ext,
				$width,
				$height,
				array(
					image::JPEG_QUALITY => 80,
					image::USE_GD_FAST_RESIZE_TRICK => true
				)
			);

			if (!$res)
			{
				return false;
			}
			else
			{
				list($width, $height) = $res;
			}
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
			|| $size > (100 * 1024) || ($img['format'] != 'JPEG' && $img['format'] != 'PNG'))
		{
			$options[image::JPEG_QUALITY] = 70;
			$thumb_ext = '.s.' . strtolower($img['format']);

			if ($img['format'] != 'JPEG' && $img['format'] != 'PNG')
			{
				$options[image::FORCE_OUTPUT_FORMAT] = 'JPEG';
				$thumb_ext = '.s.jpeg';
			}

			image::resize(
				$dest . $ext,
				$dest . $thumb_ext,
				($width > $this->config->thumb_width) ? $this->config->thumb_width : $width,
				($height > $this->config->thumb_width) ? $this->config->thumb_width : $height,
				$options
			);

			$thumb = true;
		}
		else
		{
			$thumb = false;
		}

		$hash = substr($hash, -2) . '/' . $base;

		$this->db->exec('INSERT INTO pictures (hash, filename, date, format,
			width, height, thumb, private, size, album)
			VALUES (
				\''.$this->db->escapeString($hash).'\',
				\''.$this->db->escapeString($name).'\',
				\''.time().'\',
				\''.$img['format'].'\',
				\''.(int)$width.'\',
				\''.(int)$height.'\',
				\''.(int)$thumb.'\',
				\''.($private ? '1' : '0').'\',
				\''.(int)$size.'\',
				'.(is_null($album) ? 'NULL' : '\''.$this->db->escapeString($album).'\'').'
			);');

		return $hash;
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

	public function getList($page)
	{
		$begin = ($page - 1) * $this->config->nb_pictures_by_page;
		$where = $this->logged() ? '' : 'AND private != 1';

		$out = array();
		$res = $this->db->query('SELECT * FROM pictures WHERE album IS NULL '.$where.' ORDER BY date DESC LIMIT '.$begin.','.$this->config->nb_pictures_by_page.';');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function makeRemoveId($hash)
	{
		return sha1($this->config->storage_path . $hash);
	}

	public function checkRemoveId($hash, $id)
	{
		return sha1($this->config->storage_path . $hash) === $id;
	}

	public function countList()
	{
		$where = $this->logged() ? '' : 'AND private != 1';
		return $this->db->querySingle('SELECT COUNT(*) FROM pictures WHERE album IS NULL '.$where.';');
	}

	public function getAlbumList($page)
	{
		$begin = ($page - 1) * round($this->config->nb_pictures_by_page / 2);
		$where = $this->logged() ? '' : 'WHERE private != 1';

		$out = array();
		$res = $this->db->query('SELECT * FROM albums '.$where.' ORDER BY date DESC LIMIT '.$begin.','.round($this->config->nb_pictures_by_page / 2).';');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$row['extract'] = $this->getAlbumExtract($row['hash']);
			$out[] = $row;
		}

		return $out;
	}

	public function getAlbumPrevNext($album, $current, $order = -1)
	{
		$st = $this->db->prepare('SELECT * FROM pictures WHERE album = :album
			AND date '.($order > 0 ? '>' : '<').' (SELECT date FROM pictures WHERE hash = :img)
			ORDER BY date '.($order > 0 ? 'ASC': 'DESC').' LIMIT 1;');
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

		if ($img['format'] != 'JPEG' && $img['format'] != 'PNG')
		{
			$format = 'jpeg';
		}
		else
		{
			$format = strtolower($img['format']);
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

		if (!$album)
		{
			return false;
		}

		return $this->upload($file, $name, $album['private'], $album['hash']);
	}
}

?>