<?php
/*
    Fotoo Gallery v2.2.1
    Copyright 2004-2009 BohwaZ - http://dev.kd2.org/
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
*/

// This check is useless, if you have PHP < 5 you will get a parse error
if (!version_compare(phpversion(), '5.2', '>='))
{
    die("You need at least PHP 5.2 to use this application.");
}

if (!class_exists('SQLiteDatabase'))
{
    die("You need PHP SQLite extension to use this application.");
}

if (strpos($_SERVER['HTTP_HOST'], '.free.fr'))
{
    die("Free.fr n'est pas compatible avec Fotoo Gallery (bug dans SQLite).");
}

class fotooManager
{
    private $db = false;
    public $html_tags = array('wp' => 'http://en.wikipedia.org/wiki/{KEYWORD}');

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
        $init = false;

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

        if (!file_exists(CACHE_DIR . '/photos.db'))
            $init = true;

        $this->db = new SQLiteDatabase(CACHE_DIR . '/photos.db', 0600);

        if ($init)
        {
            header('Location: '.SELF_URL.'?index_all');
            $this->initDB();
            exit;
        }
    }

    private function initDB()
    {
        $this->db->queryExec('
        CREATE TABLE photos (
            id INTEGER UNSIGNED PRIMARY KEY NOT NULL,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(255) NOT NULL,
            width SMALLINT UNSIGNED NOT NULL,
            height SMALLINT UNSIGNED NOT NULL,
            size INT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            month TINYINT UNSIGNED NOT NULL,
            day TINYINT UNSIGNED NOT NULL,
            time INTEGER UNSIGNED NOT NULL,
            comment VARCHAR(255) NOT NULL,
            details TEXT NOT NULL,
            hash VARCHAR(50) NOT NULL
        );

        CREATE UNIQUE INDEX hash ON photos (hash);
        CREATE INDEX file ON photos (filename, path);
        CREATE INDEX date ON photos (year, month, day);

        CREATE TABLE tags (
            name VARCHAR(255) NOT NULL,
            name_id VARCHAR(255) NOT NULL,
            photo INTEGER UNSIGNED,
            PRIMARY KEY (name_id, photo)
        );
        ');
    }

    // Returns informations on a photo
    public function getInfos($filename, $path, $from_list=false)
    {
        $res = $this->db->arrayQuery("SELECT * FROM photos
            WHERE filename='".sqlite_escape_string($filename)."'
            AND path='".sqlite_escape_string($path)."'", SQLITE_ASSOC);

        if (empty($res[0]))
            return false;

        $pic =& $res[0];
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

        $tags = @$this->db->arrayQuery('SELECT name, name_id FROM tags
            WHERE photo="'.(int)$pic['id'].'";', SQLITE_ASSOC);

        $pic['tags'] = array();
        foreach ($tags as $row)
        {
            $pic['tags'][$row['name_id']] = $row['name'];
        }

        $small_path = $this->getSmallPath($hash);
        if (GEN_SMALL == 2 && !$from_list && !file_exists($small_path) && $pic['width'] <= MAX_IMAGE_SIZE && $pic['height'] <= MAX_IMAGE_SIZE)
        {
            $this->resizeImage($file, $small_path, $pic['width'], $pic['height'], 600);
        }

        return $res[0];
    }

    public function getPrevAndNext($dir, $file)
    {
        $prev = $this->db->arrayQuery('SELECT id, hash, path, filename FROM photos
            WHERE path="'.sqlite_escape_string($dir).'"
            AND filename < "'.sqlite_escape_string($file).'"
            ORDER BY filename DESC LIMIT 0,1;', SQLITE_ASSOC);

        if (!empty($prev[0]))
            $prev = $prev[0];
        else
            $prev = false;

        $next = $this->db->arrayQuery('SELECT id, hash, path, filename FROM photos
            WHERE path="'.sqlite_escape_string($dir).'"
            AND filename > "'.sqlite_escape_string($file).'"
            ORDER BY filename ASC LIMIT 0,1;', SQLITE_ASSOC);

        if (!empty($next[0]))
            $next = $next[0];
        else
            $next = false;

        return array($prev, $next);
    }

    // Delete photo informations and thumb
    private function cleanInfos($id, $hash)
    {
        $this->db->queryExec('DELETE FROM photos WHERE id="'.(int)$id.'";');
        $this->db->queryExec('DELETE FROM tags WHERE photo="'.(int)$id.'";');

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
        $res = $this->db->arrayQuery('SELECT id, hash, path, filename FROM photos ORDER BY id;');
        foreach ($res as &$row)
        {
            $file = BASE_DIR . '/' . ($row['path'] ? $row['path'] . '/' : '') . $row['filename'];

            if (!file_exists($file))
            {
                $this->cleanInfos($row['id'], $row['hash']);
            }
        }
        unset($res);
    }

    public function getNewPhotos($nb=10)
    {
        return $this->db->arrayQuery('SELECT * FROM photos ORDER BY time DESC LIMIT 0,'.(int)$nb.';', SQLITE_ASSOC);
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
            $this->resizeImage($file, $this->getSmallPath($hash), $width, $height, 600);

        if (!empty($details))
            $details = json_encode($details);
        else
            $details = '';

        @$this->db->unbufferedQuery("INSERT INTO photos
            (id, filename, path, width, height, size, year, month, day, time, comment, details, hash)
            VALUES (NULL, '".sqlite_escape_string($filename)."', '".sqlite_escape_string($path)."',
            '".(int)$width."', '".(int)$height."', '".(int)$file_size."', '".date('Y', $date)."',
            '".date('m', $date)."', '".date('d', $date)."', '".(int)$date."',
            '".sqlite_escape_string($comment)."', '".sqlite_escape_string($details)."',
            '".sqlite_escape_string($hash)."');");

        $id = $this->db->lastInsertRowid();

        if (!$id)
            return false;

        foreach ($tags as $tag)
        {
            $this->db->unbufferedQuery("INSERT INTO tags (name, name_id, photo)
                VALUES ('".sqlite_escape_string($tag)."',
                '".sqlite_escape_string($this->getTagId($tag))."', '".(int)$id."');");
        }

        return array(
            'id'    =>  $id,
            'filename' => $filename,
            'path'  =>  $path,
            'width' =>  $width,
            'height'=>  $height,
            'time'  =>  $date,
            'comment'=> $comment,
            'details'=> $details,
            'hash'  =>  $hash,
            'year'  =>  date('Y', $date),
            'month' =>  date('m', $date),
            'day'   =>  date('d', $date),
            'tags'  =>  $tags,
        );
    }

    static public function getValidDirectory($path)
    {
        $path = preg_replace('!(^/+|/+$)!', '', $path);

        if (preg_match('![.]{2,}!', $path))
            return false;

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
            return $this->db->arrayQuery('SELECT * FROM photos
                    WHERE year="'.(int)$y.'" AND month="'.(int)$m.'" AND day="'.(int)$d.'"
                    ORDER BY time;');
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

            $res = $this->db->arrayQuery($req, SQLITE_ASSOC);

            $list = array();
            foreach ($res as &$row)
            {
                $start = 0;
                if ($row['nb'] > 5)
                    $start = mt_rand(0, $row['nb'] - 5);

                // Get 5 random pictures for each line
                $res_sub = $this->db->arrayQuery('SELECT * FROM photos
                    WHERE year="'.(int)$row['year'].'" '.
                    ($y ? 'AND month="'.(int)$row['month'].'"' : '').
                    ($m ? 'AND day="'.(int)$row['day'].'"' : '').'
                    ORDER BY time LIMIT '.$start.', 5;',
                    SQLITE_ASSOC);

                if ($row['nb'] > 5)
                {
                    $more = $row['nb'] - 5;
                    foreach ($res_sub as &$row_sub)
                    {
                        $row_sub['nb'] = $row['nb'];
                        $row_sub['more'] = $more;
                    }
                }

                $list = array_merge($list, $res_sub);
            }

            return $list;
        }
    }

    public function getTagList()
    {
        $res = $this->db->arrayQuery('SELECT COUNT(photo) AS nb, name, name_id FROM tags
            GROUP BY name ORDER BY name;', SQLITE_ASSOC);

        $tags = array();
        foreach ($res as &$row)
        {
            $tags[$row['name']] = $row['nb'];
        }

        return $tags;
    }

    public function getByTag($tag)
    {
        // Can't use SQL JOIN here because SQLite is too slow with JOIN queries

        $tag = $this->getTagId($tag);
        $res = $this->db->arrayQuery('SELECT photo FROM tags WHERE name_id = \''.sqlite_escape_string($tag).'\';', SQLITE_ASSOC);
        $photos = array();

        foreach ($res as $row)
        {
            $photos[] = (int) $row['photo'];
        }

        unset($res);

        $pics = $this->db->arrayQuery('SELECT * FROM photos
            WHERE id IN (\''.implode("','", $photos).'\')
            ORDER BY photos.time, photos.filename;', SQLITE_ASSOC);

        unset($photos);

        return $pics;
    }

    public function getNearTags($tag)
    {
        // Can't use SQL JOIN here because SQLite is too slow with JOIN queries

        $tag = $this->getTagId($tag);
        $res = $this->db->arrayQuery('SELECT photo FROM tags WHERE name_id = \''.sqlite_escape_string($tag).'\';', SQLITE_ASSOC);

        $orig = array();

        foreach ($res as $row)
        {
            $orig[] = (int) $row['photo'];
        }

        $res = $this->db->arrayQuery('SELECT name, COUNT(photo) AS nb FROM tags
            WHERE photo IN (\''.implode("','", $orig).'\')
                AND name_id != \''.sqlite_escape_string($tag).'\'
            GROUP BY name_id ORDER BY nb DESC;', SQLITE_ASSOC);

        unset($orig);

        $tags = array();
        foreach ($res as &$row)
        {
            $tags[$row['name']] = $row['nb'];
        }

        return $tags;
    }

    public function getTagNameFromId($tag)
    {
        $res = $this->db->arrayQuery('SELECT name FROM tags
            WHERE name_id = \''.sqlite_escape_string($tag).'\' LIMIT 1;', SQLITE_ASSOC);

        if (!empty($res[0]))
            return $res[0]['name'];
        else
            return false;
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
                "'\\1<a href=\"'.str_replace('{KEYWORD}', urlencode('\\2'), \$url).'\" class=\"'.\$tag_class.'\">\\2</a>\\3'", $text);
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

function thumb_url($pic)
{
    return BASE_URL . 'cache/' . $pic['hash'][0] . '/' . $pic['hash'] . '_thumb.jpg';
}

function small_url($pic)
{
    return BASE_URL.'cache/'.$pic['hash'][0].'/'.$pic['hash'].'_small.jpg';
}

function img_page_url($pic)
{
    return SELF_URL . '?' . (empty($pic['path']) ? '' : $pic['path'].'/') . $pic['filename'];
}

function image_url($pic)
{
    return BASE_URL . (empty($pic['path']) ? '' : $pic['path'].'/') . $pic['filename'];
}

function embed_html($data)
{
    if (is_array($data))
        $url = SELF_URL . '?embed=' . (empty($data['path']) ? '' : $data['path'].'/') . '#' . $data['filename'];
    else
        $url = SELF_URL . '?embed&tag=' . urlencode($data);

    $html = '<object type="text/html" width="600" height="450" data="'.$url.'">'
        .   '<iframe src="'.$url.'" width="600" height="450" frameborder="0" scrolling="no"></iframe>'
        .   '</object>';
    return $html;
}

function embed_img($data)
{
    return SELF_URL . '?img=' . (empty($data['path']) ? '' : $data['path'].'/') . $data['filename'];
}

error_reporting(E_ALL);

// Against bad configurations
if (get_magic_quotes_gpc())
{
    foreach ($_GET as $k=>$v)   { $_GET[$k]  = stripslashes($v); }
}

if (file_exists(dirname(__FILE__) . '/user_config.php'))
    require dirname(__FILE__) . '/user_config.php';

if (!function_exists('__'))
{
    function __($str, $mode=false, $datas=false) {
        if ($mode == 'TIME')
            return strftime($str, $time);
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
if (!defined('GALLERY_TITLE'))  define('GALLERY_TITLE', __('My pictures'));
if (!defined('ALLOW_EMBED'))    define('ALLOW_EMBED', true);

if (!isset($f) || !($f instanceOf fotooManager))
    $f = new fotooManager;

$mode = false;

if (isset($_GET['update_js']))
{
    header('Content-Type: text/javascript');

echo <<<EOF_UPDATE_JS
if (typeof need_update != 'undefined')
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
}
EOF_UPDATE_JS;
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
        echo '<rdf:li rdf:resource="'.img_page_url($photo).'" />
        ';
    }

    echo '
      </rdf:Seq>
      </items>
    </channel>';

    foreach ($last as &$photo)
    {
        if (file_exists($f->getSmallPath($photo['hash'])))
            $small_url = small_url($photo);
        else
            $small_url = thumb_url($photo);

        $content = '<p><a href="'.img_page_url($photo).'">'
            . '<img src="'.$small_url.'" alt="'.escape($photo['filename']).'" /></a></p>'
            . '<p>'.$f->formatText($photo['comment']).'</p>';

        $title = $photo['path'] ? strtr($photo['path'] . '/' . $photo['filename'], array('/' => ' / ', '_' => ' ')) : $photo['filename'];
        $title = preg_replace('!\.[a-z]+$!i', '', $title);

        echo '
            <item rdf:about="'.img_page_url($photo).'">
                <title>'.escape($title).'</title>
                <link>'.img_page_url($photo).'</link>
                <dc:date>'.date(DATE_W3C, $photo['time']).'</dc:date>
                <dc:language></dc:language>
                <dc:creator></dc:creator>
                <dc:subject></dc:subject>
                <description>'.escape($content).'</description>
                <content:encoded>
                    <![CDATA['.$content.']]>
                </content:encoded>
                <media:content medium="image" url="'.image_url($photo).'" type="image/jpeg" height="'.$photo['height'].'" width="'.$photo['width'].'" />
                <media:title>'.escape($photo['filename']).'</media:title>
                <media:thumbnail url="'.thumb_url($photo).'" />
            </item>';
    }

    echo '
    </rdf:RDF>';
    exit;
}

if (isset($_GET['style_css']) || isset($_GET['slideshow_css']))
{
    header('Content-Type: text/css');

    $img_home = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAAgVBMVEX%2F%2F%2F%2F%2B%2Ffr%2F%2F%2F%2Fx5svn06W%2FrYL06tPw48T69%2B769er17drv4sH%2B%2Fv39%2Bvb48uT2793s27SzoXn38ODq163r163q2Kzp2Kzz6dLz6M%2Fv4sP38eHu4L748eLz6dHt3Lfs3Ljw4sPu4L3s3Lfy6M%2Ft3bhmXEXMuIv%2FgICdjmvMAAD8%2BfNB06UXAAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfUDB4RJBdfkpQSAAAAfElEQVR42o3MyRKCQAxF0WaeZwFRAQdId%2Fj%2FDzQNVEpc8Xb3VCVCbJNSHCYR5V8fhNq2f4S6qS41C3X%2BHqch34X6HnWe94xemyCCdW1dt%2F9YgLjeQJiVj1uZhbA%2FhTRQtCBl8Bc1z2rxGRJDg5EwxKYGM2ZwCv2jcECc2ReExg8II8b%2F0AAAAABJRU5ErkJggg%3D%3D';
    $img_tag = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8%2F9hAAAABmJLR0QA%2FwD%2FAP%2BgvaeTAAAAE3RFWHRBdXRob3IATWF1cmljZSBTdmF52MWWvwAAAaNJREFUeNrEU7FOAkEQnb2QGBIRKS38E402BgtCIXaCDedhDJY0WmlLSYgNiSIhWPER%2FIM%2FoAbt5E4SE%2FZ2x5nZQ6%2BncJLh3ezNvHszOyhEhFXMgxUt83HRBCAVlpUYg%2FJsDdAPWGMACZHRWHdOiITWxJKT4Ra27rowDc4xF%2FiSQG9BxTETiitGipnIYyTns9f7PmQUILw3qPisDmYWUkEsyRAziQallzG7Bksxn3uFAoklQiTt6%2FU6xGEkkph5s1QSVNbJhaWblBMR1dIQuWeWRMnf47H0zJavHJHUVEEiW9mkNb2gUsp98wMMJxOcBg3keSSIs9EIZ4NHXFrU7WLa5p0OPu%2FtIykgmSQnWy7LLLLFA3c%2F9PV8tQZRr%2Bdi45R9tdsuXjgFPAM3IOoxe1ikARnXQvVEcMP3BXOXTYetVooA%2BRqtioZDzB9Xkp4tRIOBuyqt3XWyyzPvg4bc1bXErN7jyW%2F3H9Tn6EkOFE%2BbmHmojH8O8sW1nV2Y395I26xevdROfzeON9Gmtk420LoYZPuYlGOU%2FrlG%2FfufaWWCHwEGAEtagFSXeJBuAAAAAElFTkSuQmCC';
    $img_date = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAAflBMVEX%2F%2F%2F%2Bbm5vuExPuJSXuUlPuWlnuLi7uSkruQULuNzjuHBzDwsPJysnuDAyZmpqampq3t7eamZqqq6u%2Fvr%2B6urrGxsa%2Bv76fn5%2Bnp6ecnJy9vL2%2Bvr6cm5uam5q5urmzs7Ovr6%2FHxsebnJyjo6PKycruX1%2FMzMyZmZn%2F%2F%2F%2FuAAALeyOvAAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfUDB4RJToDVvkmAAAAZElEQVR42oWNRw6AMBADQ%2B%2B99x6S%2F38QFhBEAQn7Nh7JCL2zLIqs6YYqmSKlHHDopxGTJyuAiOC969EAgMUYHoDkWqECgJkxagCYN2zGGAEM945Pg31pAKRV2fpdH%2BZTVrjoPxuqaxRtezAMLwAAAABJRU5ErkJggg%3D%3D';
    $img_dir = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAABa1BMVEX%2F%2F%2F%2F%2F92v%2F8GPp1CPklwrjjgX%2F9Wn%2F5FX%2F7F7%2F%2FXH%2F%2Bm7%2F%2FnL%2F6lz%2F6Fr%2F82bj1oXlpRDjkAfmpxDkmQrnuRj110jnuxniigP%2F82f1zz71zT3%2F51nozCD%2F%2B2%2Fkkgf16l7jiQPmsxbmshXp1STlqBD14lXmsRTlqxLjigTp0yP%2F72LihgL120zp0iP%2F30%2FpzyLnxR3%2F%2B3D1zDv%2F8mX%2F%2FXLo0SL%2F2UnoxR717mL11Ub%2F92z%2F7mHp0CLntxf%2F%2BW3loA7%2F4FHnvhr%2F%2BG3%2F51jjkQb%2F5FbmrhP%2F4VHihQH%2F41PozSH%2F4FDknw3knAzlow7mrBP182f%2F%2BW7nwRviiAP%2F617jlAj%2F7WD%2F%2F3P10EHlnAvoyh%2F%2F8WTkmwvkoQ7131HoyB7%2F3Ev%2F9Gf%2F7V%2F%2F4lPo0CHbxj%2FklQj10kP151r%2F5Vf%2F%2B3HnvRr%2F%2FnP%2F3U7owBvowxzmthf18WXpzSH%2F41X%2F6Fn%2F3E319Wr%2F%2F3Tp1CTmqPETAAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfUDB4RJC2Znk2gAAAAtElEQVR42mNgIAJUVGjaFCHxmSvKysqizPPECtTUBexBAkB%2BQKGFfIZqnI5oJAODMrNuikx8vqOIopuKn6Uw0ITy8nBOLkY%2BJo4Sdp%2F0NLAAt6ETW3QYT2ZyTIQLA4NpeQ5nICOfVoh0sTeLJCsDg365iaxVIlMSr6t7bqiUBgODF3eWHZuxNY%2Bzp16CoFEwAwO%2FARejBBMHL7tDqVmqggfQHUHZtkr%2BQrG%2BLHKs4tr8DGQAAGf6I1yfqMWaAAAAAElFTkSuQmCC';
    $img_forward = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAAulBMVEX%2F%2F%2F9ylekzTY42VZt3muyIp%2FSIp%2FV0mOuRr%2Fp3me10l%2Bo0TpA0UJR9n%2B%2BNrPczTpA4W6Q0UJOVsvw0UJUvQX4vQn04W6V5nO41UpcwQn6KqfZxlumPrvlxlemFpvM1U5g2VJkvQXw5XacySYkySog1UpYxSIY4XqiCovIyTI02V54xRoSHp%2FUwRYE2VZowRYIuQXwxSIcySIgvQn6AoPCTsfs4XKc2VJo3WKA3WqMzTY81VJmCo%2FKFpfPmpKZIAAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfUDB4RITX3hSGzAAAAgklEQVR42mNgIAKom4FIZnm4gJKQJZC0ZhWECYiZcmgxMKja2mhCBQQ4ZPjMGXhsTHgNoSIWfFKswgxMvOIs%2BlARPR05GyYGIxYuRgMwn5nNFshX4WSXVYTzrRiUObmkRSEaFGw1uBkY1NgZRaAmSNgA%2BQzajMYwd%2FDwg0hdSQZyAQCimAm2dQJutQAAAABJRU5ErkJggg%3D%3D';
    $img_info = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAABGlBMVEX%2F%2F%2F90tf93t%2F%2FL4%2F%2FI4v81lf9Aqvw8p%2FxAmv9csv8%2Bmf9gtv%2BOw%2F9Drfw7mf9uwf9qvf5Io%2F%2BBz%2F5gtf9PqP82o%2Fwzofx0xf5TrP9Gr%2FxBnv9asf5Mpv89mf80lP84pPyD0f5Ervw1lP89ov1Kpf5luf5Ko%2F99zP5Tqv9vwv5qvf9Qsf1BrPxQqf9Yr%2F6E0f5zxP5KpP9TrP5JpP5Bq%2Fx3yP5Urf5mu%2F5Wrv57y%2F43lv9UrP9buv1Psv06pvyB0P5Wtf00ofyAzv4%2BqfxXr%2F5Krf13x%2F9SrP50xP5uwf6Az%2F57yv49m%2F9YuP1tv%2F40k%2F9Yr%2F9Pqf9htf9zxf55yP5nu%2F9fvf13x%2F5luv5Bpf2Nw%2F8zk%2F%2F%2F%2F%2F9%2Bzf4NgFg2AAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfUDB4RJAzV913%2BAAAAx0lEQVR42o2P1RaCUBBFAQEFCwU7ELu7u7tbGP7%2FN7zWu%2Fvt7DWz1jkYhtAQOkXRERrsi4HyOIbqtkoZPlleVwJXl7TJ10zy%2B54656wugLSZ44OvL8ITsKoSAJ2M6EsEEqyjp7aNZTp11zMzFgllqa6MAKA9Mnun8hKxxq1PA3SZcTGzQ8KXmJ7MIwAx2xKiPiTw%2BnzR0QLYHoLXjSNBUhcOZQC71%2BIn38VM%2FES0DewhS1P%2BVg2G485Dwe2Xf2NIHI1jcRL7iycAMB5ogC93MwAAAABJRU5ErkJggg%3D%3D';
    $img_back = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAyCAYAAACd+7GKAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAADVJREFUCNeFjUEOACAMwiqT/3/Zm3OJUw5NyBjAKQmYG672hoxEtf5/tNfowu3au8oChgASC4cWARUDoKzWAAAAAElFTkSuQmCC';
    $img_bg = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAAXNSR0IArs4c6QAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAtJREFUCNdjYDgAAADDAME6JGfnAAAAAElFTkSuQmCC';

    if (isset($_GET['style_css']))
    {
        echo <<<EOF_STYLE_CSS
* { margin: 0; padding: 0; }
ul { list-style-type: none; }
body { font-family: Sans-serif; background: #fff; color: #000; padding: 1em; }
h1 { font-size: 2em; }
hr { visibility: hidden; clear: both; }
a img { border: none; }

#header { border: 1px solid #ccc; background: #eef; padding: 0.2em; height: 1.4em; margin-bottom: 0.5em; }
#header h4, #header h5 { font-weight: normal; }
#header h5 { float: right; font-size: 1em; }
#header h5 a { padding-left: 2em; background: no-repeat 0.2em 0; }
#header h5 a.home { background-image: url({$img_home}); }
#header h5 a.date { background-image: url({$img_date}); }
#header h5 a.tags { background-image: url({$img_tag}); }
#header h4 a { padding-left: 2em; background: no-repeat 0.2em 0 url({$img_forward}); }
#header h4 small a { background-image: url({$img_dir}); color: #999; margin-left: 2em; }

ul.pics { clear: left; }
ul.pics li { float: left; margin: 0.5%; width: 19%; height: 160px; text-align: center; }
ul.pics li a img { border: 1px solid white; }
ul.pics li a:hover img { outline: 3px solid black; }
ul.dirs li { float: left; width: 18%; margin: 0.5em; }
ul.dirs li a { display: block; border: 2px solid #cc6; padding: 0.5em 0.5em 0.5em 2em; background: no-repeat 0.5em 0.5em url({$img_dir}); }
ul.dirs li a:hover { background-color: #ffc; }

dl.pic { width: 60%; float: left; text-align: center; }
dl.pic dd.orig { margin: 0.5em 0; }
dl.metas, dl.details { float: right; width: 35%; border: 1px solid #ccc; background: #efe; padding: 0.5em; margin-bottom: 1em; }
dl.metas dt { font-weight: bold; padding-left: 1.7em; background: no-repeat 0.1em 0.1em; }
dl.metas dt.tags { background-image: url({$img_tag}); }
dl.metas dt.date { background-image: url({$img_date}); }
dl.metas dt.comment { background-image: url({$img_info}); }
dl.metas dt.embed { background-image: url({$img_forward}); }
dl.metas dd.embed input { padding: 0.2em; width: 97%; }
dl.metas dd { margin: 0.2em 0 1em; }
dl.details { font-size: 0.9em; }
dl.details dt { width: 40%; float: left; text-align: right; margin-right: 0.5em; clear: left;}
dl.details dd { width: 55%; float: left; }
ul.goPrevNext { float: right; width: 35%; clear: right; }
ul.goPrevNext li { float: left; position: relative; width: 50%; text-align: center; min-height: 1px; }
ul.goPrevNext li a { display: block; width: 100%; color: #000; text-decoration: none; opacity: 0.50; overflow: hidden; }
ul.goPrevNext li span { display: block; width: 100%; font-size: 50px; line-height: 40px; }
ul.goPrevNext li a:hover { opacity: 1.0; }

p.tags, p.related_tags { margin: 1em; }
p.tags small, p.related_tags small { margin-right: 1em; }

div.desc, p.info { border-bottom: 2px solid #99f; border-top: 2px solid #99f; padding: 0.5em; padding-left: 2em; margin: 1em 0; background: no-repeat 0.2em 0.5em url({$img_info}); }
p.info { border: 2px solid #cc6; background-color: #ffc; clear: both; }

ul.dates li.year { clear: left; }
ul.dates li.day, ul.dates li.month { clear: left; padding-top: 1em; }
ul.dates ul { margin-left: 2em; }
ul.dates h3, ul.dates h2 { display: inline; }
ul.dates p.more { display: inline; margin-left: 2em; }

a:link { color: darkblue; }
a:visited { color: black; }
a:hover { color: darkred; }
EOF_STYLE_CSS;
    }
    else
    {
        echo <<<EOF_SLIDESHOW_CSS
* { margin: 0; padding: 0; }
body { background: #000; font-family: Sans-serif; position: absolute; top: 0; left: 0; bottom: 0; right: 0; font-size: 12px; overflow: hidden; }
ul { list-style-type: none; }
body.loading { cursor: wait; }

p.info { color: #fff; padding: 1em; }

#pic_comment { display: block; color: #fff; position: absolute; top: 0px; left: 0px; right: 0px; width: 100%; z-index: 100;
    text-align: center; text-shadow: 0px 0px 5px #000; font-size: 1.2em; }
body.loading #pic_comment { display: none; }

#slideshow img { position: absolute; top: 0; left: 0; }

ul { position: absolute; bottom: 0px; left: 0px; right: 0px; z-index: 100;
    background: repeat-x bottom left url({$img_back}); height: 40px; width: 100%; padding-top: 10px;}
li { float: left; padding: 0 10%; }
li a { display: block; opacity: 0.50; color: #fff; text-decoration: none; font-size: 2em;
    line-height: 20px; width: 50px; text-align: center; }
li a:hover { opacity: 1.0; text-shadow: 0px 0px 5px #000; }
li.back a { font-size: 2em; font-weight: bold; }
li.next a, #controlBar li.prev a { font-size: 4em; }
li.play a { font-size: 3em; }

.playing li.play { display: none; }
.pause li.pause { display: none; }

ul.embed { height: 30px; padding-top: 20px; }
ul.embed li { padding: 0 3%; }
ul.embed li.current { color: #fff; font-size: 1.5em; line-height: 20px; opacity: 0.75; width: 70px; }
ul.embed li.back a { font-size: 1.2em; width: 280px; }
b { font-weight: normal; }

EOF_SLIDESHOW_CSS;
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
if (!empty($_GET['img']) && preg_match('!^(.*)(?:/?([^/]+)[_.](jpe?g))?$!Ui', $_GET['img'], $match))
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

    $orig_url = image_url($pic);

    if (file_exists($f->getSmallPath($pic['hash'])))
    {
        $small_url = small_url($pic);
    }
    elseif ($pic['width'] <= MAX_IMAGE_SIZE && $pic['height'] <= MAX_IMAGE_SIZE)
    {
        $small_url = $orig_url;
        list($nw, $nh) = $f->getNewSize($pic['width'], $pic['height'], 600);
        $wh = 'width="'.$nw.'" height="'.$nh.'"';
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
elseif (isset($_GET['date']))
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
elseif (isset($_GET['slideshow']))
{
    $mode = 'slideshow';
    $title = __('Slideshow');

    if (!empty($_GET['tag']))
        $selected_tag = $f->getTagId($_GET['tag']);
    else
        $selected_dir = fotooManager::getValidDirectory($_GET['slideshow']);
}
elseif (isset($_GET['embed']))
{
    $mode = 'embed';
    $title = __('Slideshow');

    if (!empty($_GET['tag']))
        $selected_tag = $f->getTagId($_GET['tag']);
    else
        $selected_dir = fotooManager::getValidDirectory($_GET['embed']);
}
elseif (!empty($_GET['tag']))
{
    $mode = 'tag';
    $tag = $f->getTagNameFromId($_GET['tag']);
    $title = __('Pictures in tag %TAG', 'REPLACE', array('%TAG' => escape($tag)));
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

if ($mode == 'slideshow' || $mode == 'embed')
    $css = SELF_URL . '?slideshow.css';
elseif (file_exists(BASE_DIR . '/user_style.css'))
    $css = BASE_URL . 'user_style.css';
else
    $css = SELF_URL . '?style.css';

$f->html_tags['tag'] = SELF_URL . '?tag={KEYWORD}';
$f->html_tags['date'] = SELF_URL . '?date={KEYWORD}';
$menu = '<h5><a class="home" href="'.SELF_URL.'">'.__('My Pictures').'</a>
    <a class="tags" href="'.SELF_URL.'?tags">'.__('By tags').'</a>
    <a class="date" href="'.SELF_URL.'?date">'.__('By date').'</a></h5>';

header('Content-Type: text/html; charset=UTF-8');

if ($mode != 'slideshow' && $mode != 'embed' && file_exists(BASE_DIR . '/user_header.php'))
    require BASE_DIR . '/user_header.php';
else
{
    if (!$title) $title = GALLERY_TITLE;
    else $title .= ' - '.GALLERY_TITLE;
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>'.$title.'</title>
    <link rel="stylesheet" href="'.$css.'" type="text/css" />
    <link rel="alternate" type="application/rss+xml" title="RSS" href="'.SELF_URL.'?feed" />
</head>

<body>';
}

if ($mode == 'date')
{
    echo '<h1>'.$title.'</h1>';
    echo '<div id="header">
        '.$menu.'
        <h4><strong><a href="'.SELF_URL.'?date">'.__('By date').'</a></strong> ';

    if ($year)
        echo '<a href="'.SELF_URL.'?date='.$year.'">'.$year.'</a> ';
    if ($month)
        echo '<a href="'.SELF_URL.'?date='.$year.'/'.$month.'">'.__('%B', 'TIME', strtotime($year.'-'.$month.'-01')).'</a> ';
    if ($day)
        echo '<a href="'.SELF_URL.'?date='.$year.'/'.$month.'/'.$day.'">'.__('%A %d', 'TIME', strtotime($year.'-'.$month.'-'.$day)).'</a> ';

    echo '
        </h4>
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
                .'<a class="thumb" href="'.img_page_url($pic).'"><img src="'
                .thumb_url($pic).'" alt="'.escape($pic['filename']).'" /></a>'
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
                    echo '<h2><a href="'.SELF_URL.'?date='.$pic['year'].'">'.$pic['year'].'</a></h2>';

                    if (isset($pic['more']))
                        echo '<p class="more"><a href="'.SELF_URL.'?date='.$pic['year'].'">'.__("(%NB more pictures)", 'REPLACE', array('%NB' => $pic['more'])).'</a></p>';

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

                $url = SELF_URL.'?date='.$pic['year'].'/'.$pic['month'].($month ? '/'.$pic['day'] : '');

                echo '
                <li class="'.($month ? 'day' : 'month').'">
                    <h3><a href="'.$url.'">'.($month ? __('%A %d', 'TIME', $pic['time']) : __('%B', 'TIME', $pic['time'])).'</a></h3>';

                if (isset($pic['more']))
                    echo '<p class="more"><a href="'.$url.'">'.__("(%NB more pictures)", 'REPLACE', array('%NB' => $pic['more'])).'</a></p>';

                echo '
                    <ul class="pics">';

                $current = $month ? $pic['day'] : $pic['month'];
            }

            echo '  <li><a class="thumb" href="'.img_page_url($pic).'"><img src="'
                .thumb_url($pic).'" alt="'.escape($pic['filename']).'" /></a>'
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
            echo '<a href="'.SELF_URL.'?tag='.urlencode($f->getTagId($tag)).'" style="font-size: '.$size.'%;">'
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
        <h4><strong><a href="'.SELF_URL.'?tags">'.__('Tags').'</a></strong>
            <a href="'.SELF_URL.'?tag='.urlencode($tag).'">'.escape($f->getTagNameFromId($tag)).'</a>';

    if (!empty($pics))
        echo '<script type="text/javascript"> document.write("<small><a class=\\"slideshow\\" href=\\"'.SELF_URL.'?slideshow&amp;tag='.urlencode($f->getTagId($tag)).'\\">'.__('Slideshow').'</a></small>"); </script>';

    echo '
        </h4>
    </div>';

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
            echo '<a href="'.SELF_URL.'?tag='.urlencode($f->getTagId($name)).'" style="font-size: '.$size.'%;">'.escape($name).'</a> ';
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
                .'<a class="thumb" href="'.img_page_url($pic)
                .'"><img src="'.thumb_url($pic).'" alt="'.escape($pic['filename']).'" /></a>'
                ."</li>\n";
        }

        echo "</ul>\n";

        if (ALLOW_EMBED)
        {
            echo '
            <dl class="details">
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
        <h4><strong><a href="'.SELF_URL.'">'.__('My Pictures')."</a></strong>\n";

    if (!empty($selected_dir))
    {
        $current = '';
        $dir = explode('/', $selected_dir);

        foreach ($dir as $d)
        {
            if ($current) $current .= '/';
            $current .= $d;

            echo '  <a href="'.SELF_URL.'?'.escape($current).'">'.escape(strtr($d, '_-', '  '))."</a>\n";
        }
    }

    echo "</h4>\n</div>\n";

    $orig_url = image_url($pic);
    $wh = '';

    if (file_exists($f->getSmallPath($pic['hash'])))
        $small_url = small_url($pic);
    elseif ($pic['width'] <= MAX_IMAGE_SIZE && $pic['height'] <= MAX_IMAGE_SIZE)
    {
        $small_url = $orig_url;
        list($nw, $nh) = $f->getNewSize($pic['width'], $pic['height'], 600);
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
            echo '<a href="'.$orig_url.'"><img src="'.$small_url.'" alt="'.escape($pic['filename']).'" '.$wh.' /></a>';
        else
            echo __("This picture is too big (%W x %H) to be displayed in this page.", 'REPLACE', array('%W' => $pic['width'], '%H' => $pic['height']));

        echo '
        </dt>
        <dd class="orig">
            <a href="'.$orig_url.'">'.__('Download image at original size (%W x %H) - %SIZE KB', 'REPLACE',
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
            echo '<a href="'.SELF_URL.'?tag='.urlencode($tag_id).'">'.escape($tag).'</a> ';

        echo '</dd>';
    }

    $date = __('%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> at %H:%M');
    $date = strtr($date, array(
        '%1' => SELF_URL . '?date='.$pic['year'].'/'.$pic['month'].'/'.$pic['day'],
        '%2' => SELF_URL . '?date='.$pic['year'].'/'.$pic['month'],
        '%3' => SELF_URL . '?date='.$pic['year'],
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
        <dd class="embed"><input type="text" onclick="this.select();" value="'.escape(embed_img($pic)).'" /></dd>';
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
            '<a href="' . img_page_url($prev) . '" title="' . __('Previous') . '"><span>&larr;</span><img src="' .
            thumb_url($prev) . '" alt="' . __('Previous') . '" /></a>' : '') .
        '</li>
        <li class="goNext">' .
        ($next ?
            '<a href="' . img_page_url($next) . '" title="' . __('Next') . '"><span>&rarr;</span><img src="' .
            thumb_url($next) . '" alt="' . __('Next') . '" /></a>' : '') .
        '</li>
    </ul>';
}
elseif ($mode == 'slideshow' || $mode == 'embed')
{
    if (!empty($selected_dir))
    {
        $list = $f->getDirectory($selected_dir);

        if (!empty($list[1]))
            $list = $list[1];
        else
            $list = false;

        $back_url = SELF_URL . '?' . $selected_dir;
    }
    elseif (!empty($selected_tag))
    {
        $list = $f->getByTag($selected_tag);
        $back_url = SELF_URL . '?tag=' . $selected_tag;
    }

    if (empty($list))
        echo '<p class="info">'.__('No picture found.').'</p>';
    else
    {
        echo '<script type="text/javascript">
        var slideEvent = false;
        var time_slide = 5;
        var playing = false;
        var current = 0;

        var max_width = 0;
        var max_height = 0;

        function hidePrevious(previous) {
            if (!document.getElementById("picture_"+previous))
                return;

            document.getElementById("picture_"+previous).style.display = "none";
        }

        function loadPicture(id, previous) {
            var pic = pictures[id];
            var pic_id = "picture_"+id;
            document.body.className = "loading";

            if (slideEvent)
                window.clearTimeout(slideEvent);

            if (!document.getElementById(pic_id)) {
                var width = pic.width;
                var height = pic.height;
                var ratio = false;

                if(width > max_width) {
                    if(height <= width)
                        ratio = max_width / width;
                    else
                        ratio = max_height / height;

                    width = Math.round(width * ratio);
                    height = Math.round(height * ratio);
                }

                if (height > max_height) {
                    ratio = max_height / height;

                    width = Math.round(width * ratio);
                    height = Math.round(height * ratio);
                }

                var img = document.createElement("img");
                img.id = pic_id;
                img.style.display = "block";
                img.style.margin = Math.round((max_height - height) / 2) + "px 0px 0px " + Math.round((max_width - width) / 2) + "px";
                img.src = pic.src;
                img.onclick = goNext;

                if (typeof(previous) != "undefined") {
                    img.width = "1";
                    img.height = "1";
                }
                else {
                    img.width = width;
                    img.height = height;
                }

                document.getElementById("slideshow").appendChild(img);

                document.getElementById(pic_id).onload = function() {
                    document.body.className = "";

                    if (playing)
                        slideEvent = window.setTimeout(goNext, time_slide * 1000);

                    img.width = width;
                    img.height = height;

                    if (typeof(previous) != "undefined")
                        hidePrevious(previous);

                    if (document.getElementById("current_nb"))
                        document.getElementById("current_nb").innerHTML = parseInt(current) + 1;
                }
            }
            else {
                document.body.className = "";

                document.getElementById(pic_id).style.display = "block";

                if (playing)
                    slideEvent = window.setTimeout(goNext, time_slide * 1000);

                if (typeof(previous) != "undefined")
                    hidePrevious(previous);

                if (document.getElementById("current_nb"))
                    document.getElementById("current_nb").innerHTML = parseInt(current) + 1;
            }

            document.getElementById("pic_comment").innerHTML = pic.comment;

            window.location.href = "#" + pic.filename;
        }

        function playPause() {
            max_width = document.body.offsetWidth;
            max_height = document.body.offsetHeight;

            if (playing) {
                playing = false;
                document.getElementById("controlBar").className = "pause";
            }
            else {
                playing = true;
                document.getElementById("controlBar").className = "playing";
            }

            loadPicture(current);
        }

        function goNext() {
            var previous = current;
            current++;

            if (current >= pictures.length)
                current = 0;

            loadPicture(current, previous);
        }

        function goPrev() {
            var previous = current;
            current--;

            if (current < 0)
                current = pictures.length - 1;

            loadPicture(current, previous);
        }

        var pictures = new Array(';

        $pics = '';

        foreach ($list as &$pic)
        {
            $path = BASE_URL . (empty($pic['path']) ? '' : $pic['path'].'/') . $pic['filename'];

            if ($mode == 'embed' && file_exists($f->getSmallPath($pic['hash'])))
            {
                $path = small_url($pic);
            }
            elseif ($pic['width'] > MAX_IMAGE_SIZE || $pic['height'] > MAX_IMAGE_SIZE)
            {
                continue;
            }

            $comment = escape($pic['comment']);
            $comment = nl2br($comment);
            $comment = strtr($comment, array("\r" => "", "\n" => ""));

            $pics.= '{
                    filename: "' . escape($pic['filename']) . '",
                    src: "' . escape($path) . '",
                    comment: "' . $comment . '",
                    width: ' . (int)$pic['width'] . ',
                    height: ' . (int)$pic['height'] . "},";
        }

        echo substr($pics, 0, -1);
        unset($pics, $i, $path);

        echo ');

        if (window.location.hash)
        {
            var load_pic = window.location.hash.substr(1);
            for (i in pictures)
            {
                if (pictures[i].filename == load_pic)
                {
                    current = i;
                    break;
                }
            }
        };

        window.onload = function() {
            playPause();
        };
        window.onresize = function() {
            window.clearTimeout(slideEvent);
            window.setTimeout("window.location.reload();", 10);
        };';

        if ($mode == 'embed')
        {
            echo '
            max_width = 600;
            max_height = 450;

            window.onload = function()
            {
                document.getElementById("current_nb").innerHTML = parseInt(current) + 1;
                loadPicture(current);
            };
            ';
        }

        echo '
        </script>
        <div id="slideshow">
            <p id="pic_comment"></p>
        </div>';

        if ($mode == 'slideshow')
        {
            echo '
            <ul id="controlBar" class="playing">
                <li class="prev"><a href="#" onclick="goPrev(); return false;" title="'.__('Previous').'">&larr;</a></li>
                <li class="pause"><a href="#" onclick="playPause(); return false;" title="'.__('Pause').'">&#9612;&#9612;</a></li>
                <li class="play"><a href="#" onclick="playPause(); return false;" title="'.__('Restart').'">&#9654;</a></li>
                <li class="next"><a href="#" onclick="goNext(); return false;" title="'.__('Next').'">&rarr;</a></li>
                <li class="back"><a href="'.escape($back_url).'">'.__('Back').'</a></li>
            </ul>';
        }
        else
        {
            echo '
            <ul id="controlBar" class="embed">
                <li class="prev"><a href="#" onclick="goPrev(); return false;" title="'.__('Previous').'">&larr;</a></li>
                <li class="back"><a href="'.escape($back_url).'" onclick="window.open(this.href); return false;">'.escape(GALLERY_TITLE).'</a></li>
                <li class="current"><b id="current_nb">0</b> / '.count($list).'</li>
                <li class="next"><a href="#" onclick="goNext(); return false;" title="'.__('Next').'">&rarr;</a></li>
            </ul>';
        }
    }
}
else
{
    $pics = $dirs = $update = $desc = false;
    $list = $f->getDirectory($selected_dir);

    echo "<h1>".$title."</h1>\n";

        echo '<div id="header">'.$menu."<h4>\n";

    echo '  <strong><a href="'.SELF_URL.'">'.__('My Pictures')."</a></strong>\n";

    if (!empty($selected_dir))
    {
        $dir = explode('/', $selected_dir);
        $current = '';

        foreach ($dir as $d)
        {
            if ($current) $current .= '/';
            $current .= $d;

            echo '  <a href="'.SELF_URL.'?'.escape($current).'">'.escape(strtr($d, '_-', '  '))."</a>\n";
        }
    }

    if (!empty($list[1]))
        echo '  <script type="text/javascript"> document.write("<small><a class=\\"slideshow\\" href=\\"'.SELF_URL.'?slideshow='.escape($selected_dir).'\\">'.__('Slideshow').'</a></small>"); </script>';

    echo "</h4>
    </div>\n";

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
            echo '  <li><a href="'.SELF_URL.'?'
                .(!empty($selected_dir) ? escape($selected_dir.'/'.$dir) : escape($dir))
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
                .'<a class="thumb" href="'.img_page_url($pic)
                .'"><img src="'.thumb_url($pic).'" alt="'.escape($pic['filename']).'" /></a>'
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

if (file_exists(BASE_DIR . '/user_footer.php'))
    require_once BASE_DIR . '/user_footer.php';
else
{
    echo '
</body>
</html>';
}

if ((time() % 100 == 0) || isset($cleanUpdate))
{
    flush();
    $f->cleanDB();
}

?>