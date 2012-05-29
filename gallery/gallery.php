<?php
/**********************************************************************
Fotoo Gallery v2.3.1
Copyright 2004-2011 BohwaZ - http://dev.kd2.org/
Licensed under the GNU AGPLv3

This software is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This software is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this software. If not, see <http://www.gnu.org/licenses/>.
**********************************************************************/

// This check is useless, if you have PHP < 5 you will get a parse error
if (!version_compare(phpversion(), '5.2', '>='))
{
    die("You need at least PHP 5.2 to use this application.");
}

if (!extension_loaded('pdo_sqlite'))
{
    die("You need PHP PDO SQLite extension to use this application.");
}

error_reporting(E_ALL);



class fotooManager
{
    private $db = false;
    public $html_tags = array('wp' => 'http://en.wikipedia.org/wiki/KEYWORD');

    public function getThumbPath($hash)
    {
        $dir = CACHE_DIR . '/' . $hash[0];

        if (!file_exists($dir))
            mkdir($dir, 0777);

        return $dir . '/' . $hash . '_thumb.jpg';
    }

    public function getSmallPath($hash)
    {
        $dir = CACHE_DIR . '/' . $hash[0];

        if (!file_exists($dir))
            mkdir($dir, 0777);

        return $dir . '/' . $hash . '_small.jpg';
    }

    public function getTagId($name)
    {
        $name = htmlentities($name, ENT_QUOTES, 'UTF-8');
        $name = preg_replace('!&([aeiouyAEIOUYNcC]|ae)(?:circ|grave|acute|circ|tilde|uml|ring|slash|cedil|lig);!', '\\1', $name);
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        $name = strtolower($name);
        return $name;
    }

    public function __construct()
    {
        $init = $upgrade = false;

        if (!file_exists(CACHE_DIR))
        {
            if (!mkdir(CACHE_DIR, 0777))
            {
                echo '<hr />';
                echo 'Please create the directory '.CACHE_DIR.' and make it writable by this script.';
                exit;
            }
        }

        if (!is_writable(CACHE_DIR))
        {
            echo 'Please make the directory '.CACHE_DIR.' writable by this script.';
            exit;
        }

        if (file_exists(CACHE_DIR . '/photos.db') && !file_exists(CACHE_DIR . '/photos.sqlite'))
            $upgrade = true;
        elseif (!file_exists(CACHE_DIR . '/photos.sqlite'))
            $init = true;

        $this->db = new PDO('sqlite:' . CACHE_DIR . '/photos.sqlite');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($upgrade)
        {
            $this->upgradeDBv3();
        }
        elseif ($init)
        {
            header('Location: '.SELF_URL.'?index_all');
            $this->initDB();
            exit;
        }
    }

    protected function upgradeDBv3()
    {
        $this->initDB();

        if (class_exists('SQLiteDatabase'))
        {
            $this->db->beginTransaction();

            $old = new SQLiteDatabase(CACHE_DIR . '/photos.db');
            $res = $old->query('SELECT * FROM photos;');

            while ($row = $res->fetch(SQLITE_NUM))
            {
                $row = array_map(array($this->db, 'quote'), $row);
                $this->db->exec('INSERT INTO photos VALUES ('.implode(',', $row).');');
            }

            $res = $old->query('SELECT * FROM tags;');

            while ($row = $res->fetch(SQLITE_NUM))
            {
                $row = array_map(array($this->db, 'quote'), $row);
                $this->db->exec('INSERT INTO tags VALUES ('.implode(',', $row).');');
            }

            $this->db->commit();
        }
        else
        {
            // If there is no SQLite v2 driver, we just erease the old DB
            // and re-create index
            header('Location: '.SELF_URL.'?index_all');
            unlink(CACHE_DIR . '/photos.db');
            exit;
        }

        unlink(CACHE_DIR . '/photos.db');
        return true;
    }

    protected function initDB()
    {
        $this->db->exec('
            CREATE TABLE photos (
                id INTEGER PRIMARY KEY,
                filename TEXT,
                path TEXT,
                width INTEGER,
                height INTEGER,
                size INTEGER,
                year INTEGER,
                month INTEGER,
                day INTEGER,
                time INTEGER,
                comment TEXT,
                details TEXT,
                hash TEXT
            );

            CREATE UNIQUE INDEX hash ON photos (hash);
            CREATE INDEX file ON photos (filename, path);
            CREATE INDEX date ON photos (year, month, day);

            CREATE TABLE tags (
                name TEXT,
                name_id TEXT,
                photo INTEGER,
                PRIMARY KEY (name_id, photo)
            );
        ');
    }

    // Returns informations on a photo
    public function getInfos($filename, $path, $from_list=false)
    {
        $query = $this->db->prepare('SELECT * FROM photos WHERE filename = ? AND path = ?;');
        $query->execute(array($filename, $path));

        $pic = $query->fetch(PDO::FETCH_ASSOC);

        if (!$pic)
            return false;

        $file = BASE_DIR . '/' . ($path ? $path . '/' : '') . $filename;

        // If the file doesn't exists anymore, we just delete it's informations
        if (!file_exists($file))
        {
            $this->cleanInfos($pic['id'], $pic['hash']);
            return -1;
        }

        // Check if the file hash changed and if it's the case, delete the existing informations
        $hash = $this->getHash($filename, $path, filesize($file), filemtime($file));
        if ($hash != $pic['hash'])
        {
            $this->cleanInfos($pic['id'], $pic['hash']);

            if (!$from_list && $pic = $this->addInfos($filename, $path))
                return $pic;

            return false;
        }

        $query = $this->db->prepare('SELECT name, name_id FROM tags WHERE photo = ?;');
        $query->execute(array($pic['id']));

        $pic['tags'] = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $pic['tags'][$row['name_id']] = $row['name'];
        }

        $small_path = $this->getSmallPath($hash);
        if (GEN_SMALL == 2 && !$from_list && !file_exists($small_path) && $pic['width'] <= MAX_IMAGE_SIZE && $pic['height'] <= MAX_IMAGE_SIZE)
        {
            $this->resizeImage($file, $small_path, $pic['width'], $pic['height'], SMALL_IMAGE_SIZE);
        }

        return $pic;
    }

    public function getPrevAndNext($dir, $file)
    {
        $query = $this->db->prepare('SELECT id, hash, path, filename FROM photos
            WHERE path = ? AND filename < ? ORDER BY filename COLLATE NOCASE DESC LIMIT 0,1;');
        $query->execute(array($dir, $file));

        $prev = $query->fetch(PDO::FETCH_ASSOC);

        $query = $this->db->prepare('SELECT id, hash, path, filename FROM photos
            WHERE path = ? AND filename > ? ORDER BY filename COLLATE NOCASE ASC LIMIT 0,1;');
        $query->execute(array($dir, $file));

        $next = $query->fetch(PDO::FETCH_ASSOC);

        return array($prev, $next);
    }

    // Delete photo informations and thumb
    private function cleanInfos($id, $hash)
    {
        $this->db->exec('DELETE FROM photos WHERE id="'.(int)$id.'";');
        $this->db->exec('DELETE FROM tags WHERE photo="'.(int)$id.'";');

        $thumb = $this->getThumbPath($hash);
        if (file_exists($thumb))
            unlink($thumb);

        $small = $this->getSmallPath($hash);
        if (file_exists($small))
            unlink($small);

        return true;
    }

    // Delete all photos in DB which are deleted in filesystem
    public function cleanDB()
    {
        $query = $this->db->query('SELECT id, hash, path, filename FROM photos ORDER BY id;');

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $file = BASE_DIR . '/' . ($row['path'] ? $row['path'] . '/' : '') . $row['filename'];

            if (!file_exists($file))
            {
                $this->cleanInfos($row['id'], $row['hash']);
            }
        }

        unset($query);
    }

    public function getNewPhotos($nb=10)
    {
        $query = $this->db->query('SELECT * FROM photos ORDER BY time DESC LIMIT 0,'.(int)$nb.';');
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getHash($file, $path, $size, $time)
    {
        return md5($file . $path . $size . $time);
    }

    // Extract informations about a photo
    public function addInfos($filename, $path)
    {
        $file = BASE_DIR . '/' . ($path ? $path . '/' : '') . $filename;

        if (!file_exists($file))
        {
            return false;
        }

        $file_time = @filemtime($file);

        if (!$file_time)
            return false;

        $file_size = filesize($file);

        $hash = $this->getHash($filename, $path, $file_size, $file_time);

        $query = $this->db->prepare('SELECT 1 FROM photos WHERE hash = ? LIMIT 1;');
        $query->execute(array($hash));

        if ($query->fetchColumn())
        {
            return false;
        }

        $size = getimagesize($file, $infos);

        $width = $size[0];
        $height = $size[1];
        $comment = '';
        $tags = array();
        $date = false;
        $details = array();

        // IPTC contains tags
        if (!empty($infos['APP13']))
        {
            $iptc = iptcparse($infos['APP13']);

            if (!empty($iptc['2#025']))
            {
                foreach ($iptc['2#025'] as $tag)
                {
                    $tags[] = $tag;
                }
            }

            unset($iptc);
        }

        unset($infos, $size);

        // EXIF contains date, comment and thumbnail and other details
        $exif = @exif_read_data($file, 0, true, true);
        if (!empty($exif))
        {
            if (!empty($exif['IFD0']['DateTimeOriginal']))
                $date = strtotime($exif['IDF0']['DateTimeOriginal']);
            elseif (!empty($exif['EXIF']['DateTimeOriginal']))
                $date = strtotime($exif['EXIF']['DateTimeOriginal']);
            elseif (!empty($exif['IFD0']['DateTime']))
                $date = strtotime($exif['IFD0']['DateTime']);
            elseif (!empty($exif['FILE']['FileDateTime']))
                $date = (int) $exif['FILE']['FileDateTime'];

            if (!empty($exif['COMMENT']))
            {
                $comment = implode("\n", $exif['COMMENT']);
                $comment = trim($comment);
            }

            if (!empty($exif['THUMBNAIL']['THUMBNAIL']))
            {
                $thumb = $exif['THUMBNAIL']['THUMBNAIL'];
            }

            if (!empty($exif['IFD0']['Make']))
            {
                $details['maker'] = trim($exif['IFD0']['Make']);
            }

            if (!empty($exif['IFD0']['Model']))
            {
                if (!empty($details['maker']))
                {
                    $exif['IFD0']['Model'] = str_replace($details['maker'], '', $exif['IFD0']['Model']);
                }

                $details['model'] = trim($exif['IFD0']['Model']);
            }

            if (!empty($exif['EXIF']['ExposureTime']))
            {
                $details['exposure'] = $exif['EXIF']['ExposureTime'];

                // To display a human readable number
                if (preg_match('!^([0-9.]+)/([0-9.]+)$!', $details['exposure'], $match)
                    && (float)$match[1] > 0 && (float)$match[2] > 0)
                {
                    $result = round((float)$match[1] / (float)$match[2], 3);
                    $details['exposure'] = $result;
                }
            }

            if (!empty($exif['EXIF']['FNumber']))
            {
                $details['fnumber'] = $exif['EXIF']['FNumber'];

                if (preg_match('!^([0-9.]+)/([0-9.]+)$!', $details['fnumber'], $match))
                {
                    $details['fnumber'] = round($match[1] / $match[2], 1);
                }
            }

            if (!empty($exif['EXIF']['ISOSpeedRatings']))
            {
                $details['iso'] = $exif['EXIF']['ISOSpeedRatings'];
            }

            if (!empty($exif['EXIF']['Flash']))
            {
                $details['flash'] = ($exif['EXIF']['Flash'] & 0x01) ? true : false;
            }

            if (!empty($exif['EXIF']['ExifImageWidth']) && !empty($exif['EXIF']['ExifImageLength']))
            {
                $details['resolution'] = (int)$exif['EXIF']['ExifImageWidth'] . ' x ' . $exif['EXIF']['ExifImageLength'];
            }

            if (!empty($exif['EXIF']['FocalLength']))
            {
                $details['focal'] = $exif['EXIF']['FocalLength'];

                if (preg_match('!^([0-9.]+)/([0-9.]+)$!', $details['focal'], $match))
                {
                    $details['focal'] = round($match[1] / $match[2], 1);
                }
            }
        }

        unset($exif);

        if (!$date)
            $date = $file_time;

        $can_resize = ($width <= MAX_IMAGE_SIZE && $height <= MAX_IMAGE_SIZE);

        if (isset($thumb))
        {
            file_put_contents($this->getThumbPath($hash), $thumb);
        }
        elseif ($can_resize)
        {
            $this->resizeImage($file, $this->getThumbPath($hash), $width, $height, 160);
        }

        if (GEN_SMALL == 1 && $can_resize)
        {
            $this->resizeImage($file, $this->getSmallPath($hash), $width, $height, SMALL_IMAGE_SIZE);
        }

        if (!empty($details))
            $details = json_encode($details);
        else
            $details = '';

        $pic = array(
            'filename'  =>  $filename,
            'path'      =>  $path,
            'width'     =>  (int)$width,
            'height'    =>  (int)$height,
            'size'      =>  (int)$file_size,
            'year'      =>  date('Y', $date),
            'month'     =>  date('m', $date),
            'day'       =>  date('d', $date),
            'time'      =>  (int) $date,
            'comment'   =>  $comment,
            'details'   =>  $details,
            'hash'      =>  $hash,
        );

        $query = $this->db->prepare('INSERT INTO photos
            (id, filename, path, width, height, size, year, month, day, time, comment, details, hash)
            VALUES (NULL, :filename, :path, :width, :height, :size, :year, :month, :day,
            :time, :comment, :details, :hash);');
        $query->execute($pic);

        $pic['id'] = $this->db->lastInsertId();

        if (!$pic['id'])
        {
            return false;
        }

        if (!empty($tags))
        {
            $this->db->beginTransaction();
            $query = $this->db->prepare('INSERT OR IGNORE INTO tags (name, name_id, photo) VALUES (?, ?, ?);');

            foreach ($tags as $tag)
            {
                $query->execute(array($tag, $this->getTagId($tag), (int)$pic['id']));
            }

            $this->db->commit();
        }

        $pic['tags'] = $tags;

        return $pic;
    }

    static public function getValidDirectory($path)
    {
        $path = preg_replace('!(^/+|/+$)!', '', $path);

        if (preg_match('![.]{2,}!', $path))
            return false;

        if ($path == '.')
            return '';

        return $path;
    }

    // Returns directories and pictures inside a directory
    public function getDirectory($path='', $dont_check = false)
    {
        $path = self::getValidDirectory($path);

        if ($path === false)
            return false;

        if ($path == '.' || empty($path))
            $dir_path = BASE_DIR . '/';
        else
            $dir_path = BASE_DIR . '/' . $path . '/';

        $dir = @dir($dir_path);
        if (!$dir) return false;

        $dirs = array();
        $pictures = array();
        $to_update = array();

        while ($file = $dir->read())
        {
            $file_path = $dir_path . $file;

            if ($file[0] == '.' || $file_path == CACHE_DIR)
                continue;

            if (is_dir($file_path))
            {
                $dirs[] = $file;
            }
            elseif (!preg_match('!\.jpe?g$!i', $file))
            {
                continue;
            }
            // Don't detect updates when directory has already been updated
            // (used in 'index_all' process only, to avoid server load)
            elseif ($dont_check)
            {
                continue;
            }
            elseif ($pic = $this->getInfos($file, $path, true))
            {
                if (is_array($pic)) $pictures[$file] = $pic;
            }
            else
            {
                $to_update[] = $file;
            }
        }

        $dir->close();

        sort($dirs);
        ksort($pictures);

        if (file_exists($dir_path . 'README'))
        {
            $description = file_get_contents($dir_path . 'README');
        }
        else
        {
            $description = false;
        }

        return array($dirs, $pictures, $to_update, $description);
    }

    public function getByDate($y=false, $m=false, $d=false)
    {
        if ($d)
        {
            $query = $this->db->prepare('SELECT * FROM photos WHERE year = ? AND month = ? AND day = ? ORDER BY time;');
            $query->execute(array((int)$y, (int)$m, (int)$d));
            return $query->fetchAll(PDO::FETCH_ASSOC);
        }
        else
        {
            // Get all days for a month view, all months for a year view or all years for a global view
            $req = 'SELECT day, month, year, COUNT(*) AS nb FROM photos WHERE 1 ';

            if ($y && $m)
                $req .= 'AND year="'.(int)$y.'" AND month="'.(int)$m.'" GROUP BY day ORDER BY day;';
            elseif ($y)
                $req .= 'AND year="'.(int)$y.'" GROUP BY month ORDER BY month;';
            else
                $req .= 'GROUP BY year ORDER BY year, month;';

            $query = $this->db->query($req);

            $list = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC))
            {
                $start = 0;

                if ($row['nb'] > 5)
                {
                    $start = mt_rand(0, $row['nb'] - 5);
                }

                // Get 5 random pictures for each line
                $subquery = 'SELECT * FROM photos WHERE year = '.$this->db->quote((int)$row['year']);

                if ($y)
                    $subquery .= ' AND month = '.$this->db->quote((int)$row['month']);

                if ($m)
                    $subquery .= ' AND day = '.$this->db->quote((int)$row['day']);

                $subquery .= 'ORDER BY time LIMIT '.(int)$start.', 5;';
                $subquery = $this->db->query($subquery)->fetchAll(PDO::FETCH_ASSOC);

                if ($row['nb'] > 5)
                {
                    $more = $row['nb'] - 5;
                    foreach ($subquery as &$row_sub)
                    {
                        $row_sub['nb'] = $row['nb'];
                        $row_sub['more'] = $more;
                    }
                }

                $list = array_merge($list, $subquery);
            }

            return $list;
        }
    }

    public function getTagList()
    {
        $query = $this->db->query('SELECT COUNT(photo) AS nb, name, name_id FROM tags
            GROUP BY name_id ORDER BY name_id COLLATE NOCASE;');

        $tags = array();
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $tags[$row['name']] = $row['nb'];
        }

        return $tags;
    }

    public function getByTag($tag)
    {
        $query = $this->db->prepare('SELECT photos.* FROM photos
            INNER JOIN tags ON tags.photo = photos.id
            WHERE tags.name_id = ? ORDER BY photos.time, photos.filename COLLATE NOCASE;');
        $query->execute(array($this->getTagId($tag)));
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNearTags($tag)
    {
        $query = $this->db->prepare('SELECT t2.name, COUNT(t2.photo) AS nb FROM tags
            INNER JOIN tags AS t2 ON tags.photo = t2.photo
            WHERE tags.name_id = ? AND t2.name_id != tags.name_id
            GROUP BY t2.name_id ORDER BY nb DESC;');
        $query->execute(array($this->getTagId($tag)));

        $tags = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $tags[$row['name']] = $row['nb'];
        }

        return $tags;
    }

    public function getTagNameFromId($tag)
    {
        $query = $this->db->prepare('SELECT name FROM tags WHERE name_id = ? LIMIT 1;');
        $query->execute(array($tag));
        return $query->fetchColumn();
    }

    private function seems_utf8($str)
    {
        $length = strlen($str);
        for ($i=0; $i < $length; $i++)
        {
            $c = ord($str[$i]);
            if ($c < 0x80) $n = 0; # 0bbbbbbb
            elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
            elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
            elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
            elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
            elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
            else return false; # Does not match any model

            for ($j=0; $j<$n; $j++)
            {
                if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
                    return false;
            }
        }
        return true;
    }

    private function intelligent_utf8_encode($str)
    {
        if ($this->seems_utf8($str))
            return $str;
        else
            return utf8_encode($str);
    }

    public function formatText($text)
    {
        $text = $this->intelligent_utf8_encode($text);
        $text = escape($text);

        // Allow simple, correctly closed, html tags (<strong>, <em>, <code>...)
        $text = preg_replace('!&lt;([a-z]+)&gt;(.*)&lt;/\\1&gt;!isU', '<\\1>\\2</\\1>', $text);

        $text = preg_replace('#(^|\s)([a-z]+://([^\s\w/]?[\w/])*)(\s|$)#im', '\\1<a href="\\2">\\2</a>\\4', $text);
        $text = str_replace('\http:', 'http:', $text);

        foreach ($this->html_tags as $tag=>$url)
        {
            $tag_class = preg_replace('![^a-zA-Z0-9_]!', '_', $tag);
            $text = preg_replace('#(^|\s)'.preg_quote($tag, '#').':([^\s,.]+)#iem',
                "'\\1<a href=\"'.str_replace('KEYWORD', '\\2', \$url).'\" class=\"'.\$tag_class.'\">\\2</a>\\3'", $text);
        }

        $text = nl2br($text);

        return $text;
    }

    public function formatDetails($details)
    {
        if (empty($details))
            return '';

        if (!is_array($details))
            $details = json_decode($details, true);

        $out = array();

        if (isset($details['maker']))
            $out[__('Camera maker:')] = $details['maker'];

        if (isset($details['model']))
            $out[__('Camera model:')] = $details['model'];

        if (isset($details['exposure']))
            $out[__('Exposure:')] = __('%EXPOSURE seconds', 'REPLACE', array('%EXPOSURE' => $details['exposure']));

        if (isset($details['fnumber']))
            $out[__('Aperture:')] = 'f' . $details['fnumber'];

        if (isset($details['iso']))
            $out[__('ISO speed:')] = $details['iso'];

        if (isset($details['flash']))
            $out[__('Flash:')] = $details['flash'] ? __('On') : __('Off');

        if (isset($details['focal']))
            $out[__('Focal length:')] = $details['focal'] . ' mm';

        if (isset($details['resolution']))
            $out[__('Original resolution:')] = $details['resolution'];

        return $out;
    }

    /*
     * Resize an image using imlib, imagick or GD, if one fails it tries the next
     */
    private function resizeImage($source, $dest, $width, $height, $max)
    {
        list($new_width, $new_height) = $this->getNewSize($width, $height, $max);

        if ($new_width == $width && $new_height == $height)
            return true;

        // IMLib (fast!)
        if (extension_loaded('imlib'))
        {
            $src = @imlib_load_image($source);

            if ($src)
            {
                $dst = imlib_create_scaled_image($src, $new_width, $new_height);
                imlib_free_image($src);

                if ($dst)
                {
                    imlib_image_set_format($dst, "jpeg");
                    if (file_exists($dest)) @unlink($dest);
                    imlib_save_image($dst, $dest);
                    imlib_free_image($dst);
                    return true;
                }
            }
        }

        // Imagick >= 2.0 API (quite fast)
        if (extension_loaded('imagick') && class_exists('Imagick'))
        {
            $im = new Imagick;
            if ($im->readImage($source) && $im->resizeImage($new_width, $new_height, Imagick::FILTER_UNDEFINED, 1))
            {
                if (file_exists($dest)) @unlink($dest);
                $im->stripImage();
                $im->writeImage($dest);
                $im->destroy();
                return true;
            }
        }

        // Imagick < 2.0 API (quite fast)
        if (extension_loaded('imagick') && function_exists('imagick_readimage'))
        {
            $handle = imagick_readimage($source);
            imagick_resize($handle, $new_width, $new_height, IMAGICK_FILTER_UNKNOWN, 1);
            imagick_convert($handle,'JPEG');
            if (file_exists($dest)) @unlink($dest);
            imagick_writeimage($handle, $dest);
            imagick_free($handle);

            if (file_exists($dest))
                return true;
        }

        // GD >= 2.0 (slow)
        if (function_exists('imagecopyresampled') && extension_loaded('gd'))
        {
            $sourceImage = @imagecreatefromjpeg($source);

            if($sourceImage)
            {
                $newImage = imagecreatetruecolor($new_width, $new_height);
                imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                if (file_exists($dest)) @unlink($dest);
                if(imagejpeg($newImage, $dest))
                    return true;
            }
        }

        return false;
    }

    public function getNewSize($width, $height, $max)
    {
        if($width > $max OR $height > $max)
        {
            if($height <= $width)
                $ratio = $max / $width;
            else
                $ratio = $max / $height;

            $width = round($width * $ratio);
            $height = round($height * $ratio);
        }

        return array($width, $height);
    }
}




function escape($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function zero_pad($str, $length = 2)
{
    return str_pad($str, $length, '0', STR_PAD_LEFT);
}

function get_url($type, $data = null, $args = null)
{
    if ($type == 'cache_thumb')
    {
        return BASE_URL . 'cache/' . $data['hash'][0] . '/' . $data['hash'] . '_thumb.jpg';
    }
    elseif ($type == 'cache_small')
    {
        return BASE_URL . 'cache/' . $data['hash'][0] . '/' . $data['hash'] . '_small.jpg';
    }
    elseif ($type == 'real_image')
    {
        return BASE_URL . (empty($data['path']) ? '' : $data['path'].'/') . $data['filename'];
    }

    if (is_array($data) && isset($data['filename']) && isset($data['path']))
    {
        if (isset($data['filename']))
            $data = ($data['path'] ? $data['path'].'/' : '') . $data['filename'];
        elseif (isset($data['path']))
            $data = $data['path'] ? $data['path'].'/' : '';
    }
    elseif ($type == 'embed')
    {
        $data = (empty($data['path']) ? '' : $data['path'].'/') . $data['filename'];
    }

    if (function_exists('get_custom_url'))
    {
        $url = get_custom_url($type, $data);
    }
    elseif ($type == 'image' || $type == 'album')
    {
        $url = SELF_URL . '?' . rawurlencode($data);
    }
    elseif ($type == 'embed' || $type == 'slideshow')
    {
        $url = SELF_URL . '?'.$type.'=' . rawurlencode($data);
    }
    elseif ($type == 'embed_tag')
    {
        $url = SELF_URL . '?embed&tag=' . rawurlencode($data);
    }
    elseif ($type == 'slideshow_tag')
    {
        $url = SELF_URL . '?slideshow&tag=' . rawurlencode($data);
    }
    elseif ($type == 'embed_img')
    {
        $url = SELF_URL . '?i=' . rawurlencode($data);
    }
    elseif ($type == 'tag')
    {
        $url = SELF_URL . '?tag=' . rawurlencode($data);
    }
    elseif ($type == 'date')
    {
        $url = SELF_URL . '?date=' . rawurlencode($data);
    }
    elseif ($type == 'tags' || $type == 'timeline' || $type == 'feed')
    {
        $url = SELF_URL . '?' . $type;
    }
    else
    {
        throw new Exception('Unknown type '.$type);
    }

    if (!is_null($args))
    {
        if (strpos($url, '?') === false)
            $url .= '?' . $args;
        else
            $url .= '&' . $args;
    }

    return $url;
}

function embed_html($data)
{
    if (is_array($data))
        $url = get_url('embed', $data);
    else
        $url = get_url('embed_tag', $data);

    $html = '<object type="text/html" width="600" height="450" data="'.escape($url).'">'
        .   '<iframe src="'.escape($url).'" width="600" height="450" frameborder="0" scrolling="no"></iframe>'
        .   '</object>';
    return $html;
}



// Against bad configurations
if (get_magic_quotes_gpc())
{
    foreach ($_GET as $k=>$v)   { $_GET[$k]  = stripslashes($v); }
}

if (file_exists(dirname(__FILE__) . '/user_config.php'))
{
    require dirname(__FILE__) . '/user_config.php';
}

if (!function_exists('__'))
{
    function __($str, $mode=false, $datas=false) {
        if ($mode == 'TIME')
            return strftime($str, $datas);
        elseif ($mode == 'REPLACE')
            return strtr($str, $datas);
        else
            return $str;
    }
}

if (!defined('BASE_DIR'))   define('BASE_DIR', dirname(__FILE__));
if (!defined('CACHE_DIR'))  define('CACHE_DIR', BASE_DIR . '/cache');
if (!defined('BASE_URL'))   define('BASE_URL', 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/');
if (!defined('SELF_URL'))   define('SELF_URL', BASE_URL . (basename($_SERVER['SCRIPT_NAME']) == 'index.php' ? '' : basename($_SERVER['SCRIPT_NAME'])));
if (!defined('GEN_SMALL'))  define('GEN_SMALL', 0);
if (!defined('MAX_IMAGE_SIZE')) define('MAX_IMAGE_SIZE', 2048);
if (!defined('SMALL_IMAGE_SIZE')) define('SMALL_IMAGE_SIZE', 600);
if (!defined('GALLERY_TITLE'))  define('GALLERY_TITLE', __('My pictures'));
if (!defined('ALLOW_EMBED'))    define('ALLOW_EMBED', true);

if (!isset($f) || !($f instanceOf fotooManager))
    $f = new fotooManager;

$mode = false;

if (isset($_GET['update_js']))
{
    header('Content-Type: text/javascript');

    ?>if (typeof need_update != 'undefined')
{
    var update_done = 0;
    var loading = document.createElement('div');
    loading.style.padding = '5px';
    loading.style.margin = '0px';
    loading.style.fontFamily = 'Sans-serif';
    loading.style.fontSize = '12px';
    loading.style.position = 'absolute';
    loading.style.top = '5px';
    loading.style.left = '5px';
    loading.style.border = '2px solid #ccc';
    loading.style.background = '#fff';
    loading.style.color = '#000';
    loading.style.width = (Math.round(need_update.length * 5) + 100) + 'px';
    loading.id = 'updatingTemp';

    if (typeof update_msg != 'undefined')
        loading.innerHTML = update_msg;
    else
        loading.innerHTML = 'Updating';

    var body = document.getElementsByTagName('body')[0];
    body.appendChild(loading);

    function updateNext()
    {
        var loading = document.getElementById('updatingTemp');

        if (update_done >= need_update.length)
        {
            window.setTimeout('window.location = window.location', 100);
            return;
        }

        var file = need_update[update_done];
        var img = document.createElement('img');
        img.src = update_url + '?updateDir=' + encodeURI(update_dir) + '&updateFile=' + encodeURI(file);
        img.alt = update_done + '/' + need_update.length;
        img.width = Math.round(update_done * 5);
        img.height = 1;
        img.style.borderBottom = "2px solid #000099";
        img.style.verticalAlign = "middle";
        img.style.margin = "0 10px";

        img.onload = function ()
        {
            update_done++;
            var elm = document.getElementById('updatingTemp');

            if (update_done < need_update.length)
                elm.removeChild(this);

            updateNext();
        }

        loading.appendChild(img);
    }

    updateNext();
}<?php
    exit;
}

if (isset($_GET['feed']))
{
    $last = $f->getNewPhotos(20);
    $last_update = $last[0]['time'];

    header('Content-Type: text/xml');

    echo  '<?xml version="1.0" encoding="utf-8" ?>
    <rdf:RDF
      xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
      xmlns:dc="http://purl.org/dc/elements/1.1/"
      xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
      xmlns:content="http://purl.org/rss/1.0/modules/content/"
      xmlns:media="http://search.yahoo.com/mrss/"
      xmlns="http://purl.org/rss/1.0/">

    <channel rdf:about="'.SELF_URL.'">
      <title>'.GALLERY_TITLE.'</title>
      <description>Last pictures added in &quot;'.GALLERY_TITLE.'&quot;</description>
      <link>'.SELF_URL.'</link>
      <dc:language></dc:language>
      <dc:creator></dc:creator>
      <dc:rights></dc:rights>
      <dc:date>'.date(DATE_W3C, $last_update).'</dc:date>

      <sy:updatePeriod>daily</sy:updatePeriod>
      <sy:updateFrequency>1</sy:updateFrequency>
      <sy:updateBase>'.date(DATE_W3C, $last_update).'</sy:updateBase>

      <items>
      <rdf:Seq>
        ';

    foreach ($last as &$photo)
    {
        echo '<rdf:li rdf:resource="'.get_url('image', $photo).'" />
        ';
    }

    echo '
      </rdf:Seq>
      </items>
    </channel>';

    foreach ($last as &$photo)
    {
        if (file_exists($f->getSmallPath($photo['hash'])))
            $small_url = get_url('cache_small', $photo);
        else
            $small_url = get_url('cache_thumb', $photo);

        $content = '<p><a href="'.escape(get_url('image', $photo)).'">'
            . '<img src="'.$small_url.'" alt="'.escape($photo['filename']).'" /></a></p>'
            . '<p>'.$f->formatText($photo['comment']).'</p>';

        $title = $photo['path'] ? strtr($photo['path'] . '/' . $photo['filename'], array('/' => ' / ', '_' => ' ')) : $photo['filename'];
        $title = preg_replace('!\.[a-z]+$!i', '', $title);

        echo '
            <item rdf:about="'.get_url('image', $photo).'">
                <title>'.escape($title).'</title>
                <link>'.get_url('image', $photo).'</link>
                <dc:date>'.date(DATE_W3C, $photo['time']).'</dc:date>
                <dc:language></dc:language>
                <dc:creator></dc:creator>
                <dc:subject></dc:subject>
                <description>'.escape($content).'</description>
                <content:encoded>
                    <![CDATA['.$content.']]>
                </content:encoded>
                <media:content medium="image" url="'.get_url('real_image', $photo).'" type="image/jpeg" height="'.$photo['height'].'" width="'.$photo['width'].'" />
                <media:title>'.escape($photo['filename']).'</media:title>
                <media:thumbnail url="'.get_url('cache_thumb', $photo).'" />
            </item>';
    }

    echo '
    </rdf:RDF>';
    exit;
}

if (isset($_GET['style_css']))
{
    header('Content-Type: text/css');

    if (isset($_GET['style_css']))
    {
        echo <<<EOF_STYLE_CSS
h1, h2, h3, h4, h5, h6, ul, ol, body, div, hr, li, dl, dt, dd, p { margin: 0; padding: 0; }
ul, ol { list-style-type: none; }
a img { border: none; }

body { font-family: "Trebuchet MS", Sans-serif; background: #fff; color: #000; padding: 1em; }

h1 { font-size: 2em; text-align: center; margin-bottom: .2em; }

#header { border-radius: .5em; border: .1em solid #ccc; background: #eee; padding: .5em; margin-bottom: .5em; height: 1.3em; }
#header .breadcrumbs li:before { content: " > "; }
#header .menu { float: right; }
#header ul li { display: inline; }
#header .menu li:before { content: "| "; }
#header .menu li:first-child:before { content: ""; }

ul.actions { text-align: right; }
ul.actions li { display: inline-block; }
ul.actions li a { border: .1em solid #ccf; border-radius: .5em; padding: .2em .5em; background: #eef; }

ul.pics, ul.dirs { text-align: left; display: inline-block; }
ul.pics li, ul.dirs li { text-align: center; display: inline-block; margin: 0.5em; vertical-align: middle; }
ul.pics li a { display: inline-block; max-width: 170px; max-height: 170px; overflow: hidden; }
ul.pics li a img { padding: .1em; border: .1em solid transparent; overflow: hidden; }
ul.pics li a:hover img { border-color: #999; border-radius: 1em; background: #eee; }
ul.dirs li a { display: inline-block; width: 12em; border: .3em double #ccc; border-radius: 1em; padding: .5em; }
ul.dirs li a:hover { background: #eee; border-color: #999; }

dl.pic { width: 65%; float: left; text-align: center; }
dl.pic dd.orig { margin: .5em 0; }
dl.metas, dl.details { float: right; width: 30%; border: .1em solid #ccc; background: #eee; padding: .5em; margin-bottom: 1em; border-radius: .5em; }
dl.metas dt, dl.details dt { font-weight: bold; }
input { font-size: .7em; padding: 2pt; width: 97%; border: .1em solid #ccc; border-radius: .5em; background: #eee; color: #666; }
dl.metas dd { margin: 0.2em 0 1em; }
dl.details { font-size: 0.9em; }
dl.details dt, dl.details dd { float: left; }
dl.details dt { clear: left; margin-right: .5em; }
ul.goPrevNext { float: right; width: 32%; clear: right; text-align: right; }
ul.goPrevNext li { display: table-cell; width: 50%; text-align: center; }
ul.goPrevNext img { display: none; }
ul.goPrevNext li a { width: 160px; height: 160px; display: inline-block; position: relative; overflow: hidden; text-align: center; background-position: center center; background-repeat: no-repeat; margin: .5em; }
ul.goPrevNext li a span { position: absolute; width: 100%; height: 100%; font-size: 70pt; color: #fff; text-shadow: 0px 0px 5px #000; line-height: 160px; }
ul.goPrevNext li a:hover span { opacity: 0.7; font-size: 140pt; color: #000; text-shadow: 0px 0px 1px #fff; }

p.tags, p.related_tags { margin: 1em; }
p.tags small, p.related_tags small { margin-right: 1em; color: #999; }

div.desc, p.info { border-bottom: 2px solid #99f; border-top: 2px solid #99f; padding: 0.5em; margin: 1em 0;  }
p.info { border: 2px solid #cc6; background-color: #ffc; clear: both; }

ul.dates li.year { clear: left; }
ul.dates li.day, ul.dates li.month { clear: left; padding-top: 1em; }
ul.dates ul { margin-left: 2em; }
ul.dates h3, ul.dates h2 { display: inline; }
ul.dates p.more { display: inline; margin-left: 2em; }

a:link { color: #009; }
a:visited { color: #006; }
a:hover { color: #900; }
EOF_STYLE_CSS;
    }
    exit;
}

// Update or add a file to database
if (!empty($_GET['updateFile']) && isset($_GET['updateDir']))
{
    $f->addInfos($_GET['updateFile'], $_GET['updateDir']);

    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

    header('Content-Type: image/gif');
    echo base64_decode("R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==");
    exit;
}

if (isset($_GET['index_all']))
{
    if (!isset($_SESSION))
    {
        session_start();

        if (!isset($_SESSION['processed']))
            $_SESSION['processed'] = array();

        if ($_GET['index_all'] == 'done')
        {
            $_SESSION = array();
            session_destroy();
            session_write_close();
            setcookie(session_name(), '', time() - 3600);
        }
    }

    if ($_GET['index_all'] != 'done')
    {
        define('TIMER_START', time());

        function update_dir($dir)
        {
            global $f;

            // Let's start again if timer seems too late, because processing a lot of
            // directories could be very slow
            if ((time() - TIMER_START) >= (ini_get('max_execution_time') - 5))
            {
                header('Location: '.SELF_URL.'?index_all='.time());
                exit;
            }

            if (in_array($dir, $_SESSION['processed']))
                $dont_check = true;
            else
                $dont_check = false;

            $pics = $dirs = $update = $desc = false;
            $list = $f->getDirectory($dir, $dont_check);

            $_SESSION['processed'][] = $dir;

            if (empty($list))
                return false;
            else
                list($dirs, $pics, $update, $desc) = $list;

            if (!empty($update))
            {
                return array($dir, $update);
            }

            foreach ($dirs as $subdir)
            {
                $subdir = (!empty($dir) ? $dir . '/' : '') . $subdir;
                $res = update_dir($subdir);

                if ($res)
                    return $res;
            }

            return false;
        }

        $res = update_dir('');

        if (!$res)
        {
            header('Location: '.SELF_URL.'?index_all=done');
            exit;
        }
    }
    else
    {
        $res = false;
    }

    echo '<html><body>';

    if ($res)
    {
        list($dir, $update_list) = $res;

        echo "<script type=\"text/javascript\">\n"
            .'var update_msg = "'.__('Updating')."\";\n"
            .'var update_dir = "'.escape($dir)."\";\n"
            .'var update_url = "'.SELF_URL."\";\n"
            ."var need_update = new Array();\n";

        foreach ($update_list as $file)
        {
            echo 'need_update.push("'.escape($file)."\");\n";
        }

        echo "</script>\n"
            .'<script type="text/javascript" src="'.SELF_URL.'?update.js"></script>';
    }

    echo '<p style="margin: 20% auto; width: 50%; border: 2px solid #ccc; padding: 2%; font-family: Sans-serif;">';

    if ($res)
    {
        echo '
        <strong>'.__('Updating').'</strong><br />
        ... '.$dir;
    }
    else
    {
        echo '<a href="'.SELF_URL.'?r='.time().'">'.__('Update done.').'</a>';
    }

    echo '
        </p>
    </body>
    </html>';

    exit;
}

// For cache force url
if (!empty($_GET['r']))
{
    unset($_GET['r']);
    $_SERVER['QUERY_STRING'] = '';
}

// Small image redirect
if (!empty($_GET['i']) && preg_match('!^(.*)(?:/?([^/]+)[_.](jpe?g))?$!Ui', $_GET['i'], $match))
{
    $selected_dir = fotooManager::getValidDirectory($match[1]);
    $selected_file = $match[2] . '.' . $match[3];

    $pic = $f->getInfos($selected_file, $selected_dir);

    header('Content-Type: ', true);

    if (!is_array($pic))
    {
        header('HTTP/1.1 404 Not Found', true, 404);
        exit;
    }

    $orig_url = get_url('real_image', $pic);

    if ($pic['width'] <= SMALL_IMAGE_SIZE && $pic['height'] <= SMALL_IMAGE_SIZE)
    {
        $small_url = $orig_url;
    }
    elseif (file_exists($f->getSmallPath($pic['hash'])))
    {
        $small_url = get_url('cache_small', $pic);
    }
    else
    {
        $small_url = false;
    }

    header('HTTP/1.1 302 Found', true, 302);
    header('Location: '.($small_url ? $small_url : $orig_url));
    exit;
}
// Get which display mode is asked
elseif (isset($_GET['date']) || isset($_GET['timeline']))
{
    $day = $year = $month = $date = false;
    if (!empty($_GET['date']) && preg_match('!^[0-9]{4}(/[0-9]{2}){0,2}$!', $_GET['date']))
        $date = explode('/', $_GET['date']);

    if (!empty($date[0]))
        $year = $date[0];
    if (!empty($date[1]))
        $month = $date[1];
    if (!empty($date[2]))
        $day = $date[2];

    if ($day)
        $title = __('Pictures for %A %d %B %Y', 'TIME', strtotime($_GET['date']));
    elseif ($month)
        $title = __('Pictures for %B %Y', 'TIME', strtotime($_GET['date'].'/01'));
    elseif ($year)
        $title = __('Pictures for %Y', 'REPLACE', array('%Y' => $year));
    else
        $title = __('Pictures by date');

    $mode = 'date';
}
elseif (isset($_GET['tags']))
{
    $title = __('Pictures by tag');
    $mode = 'tags';
}
elseif (isset($_GET['slideshow']) || isset($_GET['embed']))
{
    $mode = isset($_GET['embed']) ? 'embed' : 'slideshow';
    $title = __('Slideshow');

    if (!empty($_GET['tag']))
    {
        $selected_tag = explode('/', $_GET['tag']);
        $current_index = isset($selected_tag[1]) ? (int) $selected_tag[1] : 1;
        $selected_tag = $f->getTagId($selected_tag[0]);
    }
    else
    {
        $src = isset($_GET['embed']) ? $_GET['embed'] : $_GET['slideshow'];
        $selected_file = basename($src);

        if (preg_match('!\.jpe?g$!i', $selected_file))
        {
            $selected_dir = fotooManager::getValidDirectory(dirname($src));
        }
        else
        {
            $selected_dir = fotooManager::getValidDirectory($src);
            $selected_file = false;
        }
    }
}
elseif (!empty($_GET['tag']))
{
    $mode = 'tag';
    $tag = $f->getTagId($_GET['tag']);
    $tag_name = $f->getTagNameFromId($tag);
    $title = __('Pictures in tag %TAG', 'REPLACE', array('%TAG' => $tag_name));
}
else
{
    $mode = 'dir';
    $title = false;

    if (isset($_GET['cleanUpdate']))
    {
        $cleanUpdate = true;
        unset($_GET['cleanUpdate']);
        $_SERVER['QUERY_STRING'] = '';
    }

    if (!empty($_SERVER['QUERY_STRING']) && preg_match('!^(.*)(?:/?([^/]+)[_.](jpe?g))?$!Ui', urldecode($_SERVER['QUERY_STRING']), $match))
    {
        $selected_dir = fotooManager::getValidDirectory($match[1]);
        if ($selected_dir !== false)
        {
            $title = strtr(escape($match[1]), array('/' => ' / ', '_' => ' '));

            if (!empty($match[2]))
            {
                $selected_file = $match[2] . '.' . $match[3];
                $mode = 'pic';
                $title = strtr(escape($match[2]), array('_' => ' ', '-' => ' - '));
            }
        }
    }
    else
    {
        $selected_dir = '';
    }
}

if (file_exists(BASE_DIR . '/user_style.css'))
    $css = BASE_URL . 'user_style.css';
else
    $css = SELF_URL . '?style.css';

$f->html_tags['tag'] = get_url('tag', 'KEYWORD');
$f->html_tags['date'] = get_url('date', 'KEYWORD');
$menu = '<ul class="menu">
    <li><a class="home" href="'.SELF_URL.'">'.__('My Pictures').'</a></li>
    <li><a class="tags" href="'.get_url('tags').'">'.__('By tags').'</a></li>
    <li><a class="date" href="'.get_url('timeline').'">'.__('By date').'</a></li>
</ul>';

header('Content-Type: text/html; charset=UTF-8');

if ($mode != 'slideshow' && $mode != 'embed')
{
    if (file_exists(BASE_DIR . '/user_header.php'))
        require BASE_DIR . '/user_header.php';
    else
    {
        if (!$title) $title = GALLERY_TITLE;
        else $title .= ' - '.GALLERY_TITLE;
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>'.escape($title).'</title>
        <link rel="stylesheet" href="'.escape($css).'" type="text/css" />
        <link rel="alternate" type="application/rss+xml" title="RSS" href="'.escape(get_url('feed')).'" />
    </head>

    <body>';
    }
}

if ($mode == 'date')
{
    echo '<h1>'.escape($title).'</h1>';
    echo '<div id="header">
        '.$menu.'
        <ul class="breadcrumbs">
            <li><strong><a href="'.get_url('timeline').'">'.__('By date').'</a></strong></li>';

    if ($year)
        echo '<li><a href="'.escape(get_url('date', $year)).'">'.$year.'</a></li>';
    if ($month)
        echo '<li><a href="'.escape(get_url('date', $year.'/'.zero_pad($month))).'">'.__('%B', 'TIME', strtotime($year.'-'.$month.'-01')).'</a></li>';
    if ($day)
        echo '<li><a href="'.escape(get_url('date', $year.'/'.zero_pad($month).'/'.zero_pad($day))).'">'.__('%A %d', 'TIME', strtotime($year.'-'.$month.'-'.$day)).'</a></li>';

    echo '
        </ul>
    </div>';

    $pics = $f->getByDate($year, $month, $day);

    if (empty($pics))
        echo '<p class="info">'.__('No picture found.').'</p>';

    if ($day)
    {
        echo '<ul class="pics">'."\n";

        foreach ($pics as &$pic)
        {
            echo '  <li>'
                .'<a class="thumb" href="'.escape(get_url('image', $pic)).'"><img src="'
                .escape(get_url('cache_thumb', $pic)).'" alt="'.escape($pic['filename']).'" /></a>'
                ."</li>\n";
        }

        echo "</ul>\n";
    }
    else
    {
        echo '<ul class="dates">'."\n";

        $current = 0;
        $current_y = 0;
        $more = false;

        foreach ($pics as &$pic)
        {
            if ($pic['year'] != $current_y)
            {
                if ($current_y)
                    echo '</ul></li>';

                echo '<li class="year">';

                if (!$year)
                {
                    echo '<h2><a href="'.escape(get_url('date', $pic['year'])).'">'.$pic['year'].'</a></h2>';

                    if (isset($pic['more']))
                        echo '<p class="more"><a href="'.escape(get_url('date', $pic['year'])).'">'.__("(%NB more pictures)", 'REPLACE', array('%NB' => $pic['more'])).'</a></p>';

                    echo '<ul class="pics">';
                }
                else
                    echo '<ul>';

                $current_y = $pic['year'];
            }

            if (($month && $pic['day'] != $current) || ($year && !$month && $pic['month'] != $current))
            {
                if ($current)
                    echo '</ul></li>';

                $url = get_url('date', $pic['year'].'/'.zero_pad($pic['month']).($month ? '/'.zero_pad($pic['day']) : ''));

                echo '
                <li class="'.($month ? 'day' : 'month').'">
                    <h3><a href="'.escape($url).'">'.($month ? __('%A %d', 'TIME', $pic['time']) : __('%B', 'TIME', $pic['time'])).'</a></h3>';

                if (isset($pic['more']))
                    echo '<p class="more"><a href="'.escape($url).'">'.__("(%NB more pictures)", 'REPLACE', array('%NB' => $pic['more'])).'</a></p>';

                echo '
                    <ul class="pics">';

                $current = $month ? $pic['day'] : $pic['month'];
            }

            echo '  <li><a class="thumb" href="'.escape(get_url('image', $pic)).'"><img src="'
                .escape(get_url('cache_thumb', $pic)).'" alt="'.escape($pic['filename']).'" /></a>'
                ."</li>\n";
        }

        echo '</ul></li></ul>';
    }
}
elseif ($mode == 'tags')
{
    echo '<h1>'.$title.'</h1>';
    echo '<div id="header">
        '.$menu.'
    </div>';

    $tags = $f->getTagList();

    if (empty($tags))
        echo '<p class="info">'.__('No tag found.').'</p>';
    else
    {
        $max = max(array_values($tags));
        $min = min(array_values($tags));
        $spread = $max - $min;
        if ($spread == 0) $spread = 1;
        $step = 200 / $spread;

        echo '<p class="tags">';

        foreach ($tags as $tag=>$nb)
        {
            $size = 100 + round(($nb - $min) * $step);
            echo '<a href="'.escape(get_url('tag', $f->getTagId($tag))).'" style="font-size: '.$size.'%;">'
                .escape($tag).'</a> <small>('.$nb.')</small> ';
        }

        echo '</p>';
    }
}
elseif ($mode == 'tag')
{
    $pics = $f->getByTag($tag);

    echo '<h1>'.$title.'</h1>';
    echo '<div id="header">
        '.$menu.'
        <ul class="breadcrumbs">
            <li><strong><a href="'.escape(get_url('tags')).'">'.__('Tags').'</a></strong></li>
            <li><a href="'.escape(get_url('tag', $tag)).'">'.escape($tag_name).'</a></li>
        </ul>
    </div>';

    if (!empty($pics))
        echo '<ul class="actions"><li class="slideshow"><a class="slideshow" href="'.escape(get_url('slideshow_tag', $tag)).'">'.__('Slideshow').'</a></li></ul>';

    $tags = $f->getNearTags($tag);

    if (!empty($tags))
    {
        $max = max(array_values($tags));
        $min = min(array_values($tags));
        $spread = $max - $min;
        if ($spread == 0) $spread = 1;
        $step = 100 / $spread;

        echo '<p class="related_tags">'.__("Other tags related to '%TAG':", 'REPLACE', array('%TAG' => $tag)).' ';
        foreach ($tags as $name=>$nb)
        {
            $size = 100 + round(($nb - $min) * $step);
            echo '<a href="'.escape(get_url('tag', $f->getTagId($name))).'" style="font-size: '.$size.'%;">'.escape($name).'</a> ';
            echo '<small>('.$nb.')</small> ';
        }
        echo '</p>';
    }

    if (empty($pics))
    {
        echo '<p class="info">'.__('No picture found.').'</p>';
    }
    else
    {
        echo '<ul class="pics">'."\n";

        foreach ($pics as &$pic)
        {
            echo '  <li>'
                .'<a class="thumb" href="'.escape(get_url('image', $pic))
                .'"><img src="'.escape(get_url('cache_thumb', $pic)).'" alt="'.escape($pic['filename']).'" /></a>'
                ."</li>\n";
        }

        echo "</ul>\n";

        if (ALLOW_EMBED)
        {
            echo '
            <dl class="metas">
                <dt class="embed">'.__('Embed:').'</dt>
                <dd class="embed"><input type="text" onclick="this.select();" value="'.escape(embed_html($tag)).'" /></dd>
            </dl>';
        }
    }
}
elseif ($mode == 'pic')
{
    $pic = $f->getInfos($selected_file, $selected_dir);

    if (!is_array($pic))
    {
        echo '<h1>'.__('Picture not found').'</h1>
        <h3><a href="'.SELF_URL.'">'.__('Back to homepage').'</a></h3>
        </body></html>';
        exit;
    }

    echo '
    <h1>'.$title.'</h1>
    <div id="header">'.$menu.'
        <ul class="breadcrumbs">
            <li><strong><a href="'.SELF_URL.'">'.__('My Pictures')."</a></strong></li>\n";

    if (!empty($selected_dir))
    {
        $current = '';
        $dir = explode('/', $selected_dir);

        foreach ($dir as $d)
        {
            if ($current) $current .= '/';
            $current .= $d;

            echo '<li><a href="'.escape(get_url('album', $current)).'">'.escape(strtr($d, '_-', '  '))."</a></li>\n";
        }
    }

    echo "</ul>\n</div>\n";

    $orig_url = get_url('real_image', $pic);
    $wh = '';

    if (file_exists($f->getSmallPath($pic['hash'])))
    {
        $small_url = get_url('cache_small', $pic);
    }
    elseif ($pic['width'] <= MAX_IMAGE_SIZE && $pic['height'] <= MAX_IMAGE_SIZE)
    {
        $small_url = $orig_url;
        list($nw, $nh) = $f->getNewSize($pic['width'], $pic['height'], SMALL_IMAGE_SIZE);
        $wh = 'width="'.$nw.'" height="'.$nh.'"';
    }
    else
    {
        $small_url = false;
    }

    echo '
    <dl class="pic">
        <dt class="small">';

        if ($small_url)
            echo '<a href="'.escape($orig_url).'"><img src="'.escape($small_url).'" alt="'.escape($pic['filename']).'" '.$wh.' /></a>';
        else
            echo __("This picture is too big (%W x %H) to be displayed in this page.", 'REPLACE', array('%W' => $pic['width'], '%H' => $pic['height']));

        echo '
        </dt>
        <dd class="orig">
            <a href="'.escape($orig_url).'">'.__('Download image at full size (%W x %H) - %SIZE KB', 'REPLACE',
            array('%W' => $pic['width'], '%H' => $pic['height'], '%SIZE' => round($pic['size'] / 1000))).'</a>
        </dd>
    </dl>
    ';

    echo '
    <dl class="metas">';

    if (!empty($pic['comment']))
    {
        echo '
        <dt class="comment">'.__('Comment:').'</dt>
        <dd class="comment">'.$f->formatText($pic['comment']).'</dd>';
    }

    if (!empty($pic['tags']))
    {
        echo '
        <dt class="tags">'.__('Tags:').'</dt>
        <dd class="tags">';

        foreach ($pic['tags'] as $tag_id=>$tag)
            echo '<a href="'.escape(get_url('tag', $tag_id)).'">'.escape($tag).'</a> ';

        echo '</dd>';
    }

    $date = __('%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> at %H:%M');
    $date = strtr($date, array(
        '%1' => escape(get_url('date', $pic['year'].'/'.zero_pad($pic['month']).'/'.zero_pad($pic['day']))),
        '%2' => escape(get_url('date', $pic['year'].'/'.zero_pad($pic['month']))),
        '%3' => escape(get_url('date', $pic['year'])),
    ));
    $date = __($date, 'TIME', $pic['time']);
    echo '
        <dt class="date">'.__('Date:').'</dt>
        <dd class="date">'.$date.'</dd>';

    if (ALLOW_EMBED)
    {
        echo '
        <dt class="embed">'.__('Embed:').'</dt>
        <dd class="embed"><input type="text" onclick="this.select();" value="'.escape(embed_html($pic)).'" /></dd>
        <dt class="embed">'.__('Embed as image:').'</dt>
        <dd class="embed"><input type="text" onclick="this.select();" value="'.escape(get_url('embed_img', $pic)).'" /></dd>';
    }

    echo '
    </dl>';

    if (!empty($pic['details']))
    {
        $details = $f->formatDetails($pic['details']);

        echo '
        <dl class="details">';

        foreach ($details as $name=>$value)
        {
            echo '
            <dt>'.$name.'</dt>
            <dd>'.$value.'</dd>';
        }

        echo '
        </dl>';
    }

    list($prev, $next) = $f->getPrevAndNext($selected_dir, $selected_file);

    echo '
    <ul class="goPrevNext">
        <li class="goPrev">' .
        ($prev ?
            '<a href="' . escape(get_url('image', $prev)) . '" title="' . __('Previous') . '" style="background-image: url(' . escape(get_url('cache_thumb', $prev)) . ')"><span>&larr;</span><img src="' .
            escape(get_url('cache_thumb', $prev)) . '" alt="' . __('Previous') . '" /></a>' : '') .
        '</li>
        <li class="goNext">' .
        ($next ?
            '<a href="' . escape(get_url('image', $next)) . '" title="' . __('Next') . '" style="background-image: url(' . escape(get_url('cache_thumb', $next)) . ')"><span>&rarr;</span><img src="' .
            escape(get_url('cache_thumb', $next)) . '" alt="' . __('Next') . '" /></a>' : '') .
        '</li>
    </ul>';
}
elseif ($mode == 'slideshow' || $mode == 'embed')
{
    

if (!empty($selected_tag))
    $type = $mode . '_tag';
else
    $type = $mode;

$hd = $zoom = $current = false;

if (isset($_COOKIE['slideshow']) && strpos($_COOKIE['slideshow'], 'hd') !== false)
{
    $hd = true;
}

if (isset($_COOKIE['slideshow']) && strpos($_COOKIE['slideshow'], 'z') !== false)
{
    $zoom = true;
}

if (isset($selected_dir))
{
    $list = $f->getDirectory($selected_dir);

    if (!empty($list[1]))
        $list = array_values($list[1]);
    else
        $list = false;

    $back_url = get_url('album', $selected_dir);

    if (!empty($list))
    {
        if ($selected_file)
        {
            $current = $f->getInfos($selected_file, $selected_dir);
        }
        else
        {
            $current = current($list);
        }
    }
}
elseif (!empty($selected_tag))
{
    $list = array_values($f->getByTag($selected_tag));
    $back_url = get_url('tag', $selected_tag);

    if (!empty($list))
    {
        if (!empty($current_index) && !empty($list[$current_index - 1]))
        {
            $current = $list[$current_index - 1];
        }
        else
        {
            $current = current($list);
        }
    }
}

if (isset($_GET['hd']) || isset($_GET['zoom']))
{
    if (isset($_GET['hd']))
        $hd = ! $hd;
    else
        $zoom = ! $zoom;

    setcookie('slideshow', ($zoom ? 'z' : '') . ($hd ? 'hd' : ''), time() + (3600 * 24 * 365), '/');

    if (!empty($selected_tag))
    {
        $current = $selected_tag . '/' . ($current_index);
    }

    header('Location: '.get_url($type, $current));
    exit;
}

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>'.escape($title).'</title>
    <style type="text/css">
    * { margin: 0; padding: 0; }
    body { background: #000; color: #fff; font-family: Sans-serif; position: absolute; top: 0; left: 0; bottom: 0; right: 0; font-size: 12px; overflow: hidden; }
    a { color: #fff; }
    ul { list-style-type: none; }
    body.loading { cursor: wait; }

    p.info { padding: 1em; }

    #pic_comment {
        position: absolute; top: 0px; width: 75%; z-index: 100; margin: 0 12%;
        text-align: center; text-shadow: 2px 2px 5px #000; font-size: 1.5em;
        background: rgba(70, 70, 70, 0.75); width: 75%; margin: 0 12%; text-align: center; padding: 0.5em;
        border: 1px solid #666; border-radius: 0 0 2em 2em; }

    .nozoom { display: table; width: 100%; height: 100%; position: absolute; top: 0px; left: 0px; right: 0px; bottom: 0px; text-align: center; vertical-align: middle; }
    .nozoom #picture { position: relative; vertical-align: middle; display: table-cell; width: 100%; height: 100%;}
    .zoom #picture, .hd #picture { background: no-repeat center center; width: 100%; height: 100%; position: absolute; top: 0px; left: 0px; right: 0px; bottom: 0px; }
    .zoom #picture { background-size: contain; }
    .zoom #picture img, .hd #picture img { display: none; }

    #embed #picture img { max-width: 100%; max-height: 100%;}
    #embed #pic_comment { width: 90%; margin: 0 5%; font-size: 1em; border-radius: 0 0 1em 1em; }

    ul { position: absolute; bottom: 0px; z-index: 100; width: 75%; margin: 0 12%; text-align: center;
        padding: 0.5em; border-radius: 2em 2em 0 0; font-size: 1.5em;
        background: rgb(30, 30, 30); background: rgba(30, 30, 30, 0.5);
        border: 1px solid #333; border-color: rgba(70, 70, 70, 0.5); }
    ul:hover { background-color: rgba(30, 30, 30, 0.90); }
    li { display: inline-block; margin: 0 0.5em; color: #666; }
    li a { display: inline-block; color: #999; text-decoration: none; }
    li a b { display: inline-block; padding: 0.2em 0.5em; border: 1px solid #999; border-radius: 5px; text-align: center; background: rgba(255, 255, 255, 0.1); font-weight: normal; }
    li a:hover, li.current { text-shadow: 0px 0px 5px #000; color: #fff; }
    li a:hover b { background: #666; border-color: #fff; }
    li.zoom a, li.hd a { font-size: 0.8em; }

    ul.embed { width: 90%; margin: 0 5%; font-size: 1em; border-radius: 1em 1em 0 0; }
    </style>
</head>

<body>
';


if (empty($list))
{
    echo '<p class="info">'.__('No picture found.').'</p>';
}
else
{
    echo '
    <div id="'.$mode.'" class="'.($hd ? 'hd' : '').' '.($zoom ? 'zoom' : 'nozoom').'">';

    if (!empty($current['comment']))
    {
        echo '<p id="pic_comment">'.$f->formatText($current['comment']).'</p>';
    }

    $url = get_url('real_image', $current);

    if (($mode == 'embed' || !$hd) && file_exists($f->getSmallPath($current['hash'])))
    {
        $url = get_url('cache_small', $current);
    }

    if ($current['width'] > MAX_IMAGE_SIZE || $current['height'] > MAX_IMAGE_SIZE)
    {
        echo '<p class="info">';
        echo __("This picture is too big (%W x %H) to be displayed in this page.",
            'REPLACE', array('%W' => $current['width'], '%H' => $current['height']));
        echo '</p>';
    }
    else
    {
        if ($zoom || $hd)
        {
            echo '<p id="picture" style="background-image: url(\''.escape($url).'\');">';
        }
        else
        {
           echo '<p id="picture">';
        }

        echo '<img src="'.escape($url).'" alt="'.escape($current['filename']).'" />';
        echo '</p>';
    }

    echo '</div>';

    $index = array_search($current, $list);

    $prev = $index == 0 ? count($list) - 1 : $index - 1;
    $next = $index == (count($list) - 1) ? 0 : $index + 1;

    if (!empty($selected_tag))
    {
        $prev = $selected_tag . '/' . ($prev + 1);
        $next = $selected_tag . '/' . ($next + 1);
        $current = $selected_tag . '/' . ($index + 1);
    }
    else
    {
        $prev = $list[$prev];
        $next = $list[$next];
    }

    echo '
    <ul id="controlBar" class="'.$mode.'">
        <li class="prev"><a href="'.escape(get_url($type, $prev)).'"><b>&larr;</b> '.__('Previous').'</a></li>
        <li class="current"><b id="current_nb">'.($index + 1).'</b> / '.count($list).'</li>
        <li class="next"><a href="'.escape(get_url($type, $next)).'">'.__('Next').' <b>&rarr;</b></a></li>';

    if ($mode != 'embed')
    {
        echo '
        <li class="hd"><a href="'.escape(get_url($type, $current, 'hd')).'">'.($hd ? '<b>'.__('HD').'</b>' : __('HD')).'</a></li>
        <li class="zoom"><a href="'.escape(get_url($type, $current, 'zoom')).'">'.($zoom ? '<b>'.__('Zoom').'</b>' : __('Zoom')).'</a></li>';
    }

    echo '
        <li class="back"><a href="'.escape($back_url).'"'.($mode == 'embed' ? ' onclick="return !window.open(this.href);"' : '').'>'.__('Back').'</a></li>
    </ul>

    <script type="text/javascript">
    (function () {
        function clickToolbar(e, c)
        {
            e.preventDefault();

            var bar = document.getElementById("controlBar");

            if (bar.className == "embed" && c != "prev" && c != "next")
            {
                return false;
            }

            for (i in bar.children)
            {
                if (bar.children[i].className == c)
                {
                    window.location.href = bar.children[i].firstChild.href;
                }
            }

            return true;
        }

        window.onkeypress = function(e) {
            e = e || window.event;

            switch (e.keyCode)
            {
                case 37:
                    return clickToolbar(e, "prev");
                case 39:
                    return clickToolbar(e, "next");
                case 8:
                    return clickToolbar(e, "back");
                case 72:
                case 104:
                    return clickToolbar(e, "hd");
                case 90:
                case 122:
                    return clickToolbar(e, "zoom");
                default:
                    return true;
            }
        };

        window.onmouseover = function (e) {
            window.focus();
        };
    } ());
    </script>
    ';
}

echo '
</body>
</html>';


}
else
{
    $pics = $dirs = $update = $desc = false;
    $list = $f->getDirectory($selected_dir);

    echo '
        <h1>'.escape($title).'</h1>
        <div id="header">
            '.$menu.'
            <ul class="breadcrumbs"><li><strong><a href="'.SELF_URL.'">'.__('My Pictures').'</a></strong></li>';

    if (!empty($selected_dir))
    {
        $dir = explode('/', $selected_dir);
        $current = '';

        foreach ($dir as $d)
        {
            if ($current) $current .= '/';
            $current .= $d;

            echo '<li><a href="'.escape(get_url('album', $current)).'">'.escape(strtr($d, '_-', '  '))."</a></li>\n";
        }
    }

    echo '</ul>
    </div>';

    if (!empty($list[1]))
        echo '<ul class="actions"><li class="slideshow"><a href="'.escape(get_url('slideshow', $selected_dir)).'">'.__('Slideshow').'</a></li></ul>';

    if ($list === false)
        echo '<p class="info">'.__('No picture found.').'</p>';
    else
        list($dirs, $pics, $update, $desc) = $list;

    if (!empty($update))
        echo '<p class="info">'.__('Updating database, please wait, more pictures will appear in a while...').'</p>';

    if ($desc)
    {
        echo '<div class="desc">'.$f->formatText($desc).'</div>';
    }

    if (!empty($dirs))
    {
        echo '<ul class="dirs">'."\n";
        foreach ($dirs as $dir)
        {
            echo '  <li><a href="'.escape(get_url('album', (!empty($selected_dir) ? $selected_dir.'/'.$dir : $dir)))
                .'">'.escape(strtr($dir, '_', ' '))."</a></li>\n";
        }
        echo "</ul>\n";
    }

    if (!empty($pics))
    {
        echo '<ul class="pics">'."\n";

        foreach ($pics as &$pic)
        {
            echo '  <li>'
                .'<a class="thumb" href="'.escape(get_url('image', $pic))
                .'"><img src="'.escape(get_url('cache_thumb', $pic)).'" alt="'.escape($pic['filename']).'" /></a>'
                ."</li>\n";
        }

        echo "</ul>\n";
    }

    if (!empty($update))
    {
        echo "<script type=\"text/javascript\">\n"
            .'var update_msg = "'.__('Updating')."\";\n"
            .'var update_dir = "'.escape($selected_dir)."\";\n"
            .'var update_url = "'.SELF_URL."\";\n"
            ."var need_update = new Array();\n";

        foreach ($update as $file)
        {
            echo 'need_update.push("'.escape($file)."\");\n";
        }

        echo "</script>\n"
            .'<script type="text/javascript" src="'.SELF_URL.'?update.js"></script>';
    }
}

if ($mode != 'embed' && $mode != 'slideshow')
{
    if (file_exists(BASE_DIR . '/user_footer.php'))
        require_once BASE_DIR . '/user_footer.php';
    else
    {
        echo '
    </body>
    </html>';
    }
}

if ((time() % 100 == 0) || isset($cleanUpdate))
{
    flush();
    $f->cleanDB();
}

?>