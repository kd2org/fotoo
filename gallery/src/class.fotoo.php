<?php

class fotoo
{
    private $db = false;
    private $search = false;
    static public $html_tags = array('wp' => 'http://en.wikipedia.org/wiki/KEYWORD');

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
            $this->initSearch();
            exit;
        }

        $this->db->sqliteCreateFunction('normalize_ascii', array($this, 'getTagId'));
        
        // Do we need to init search?
        $query = $this->db->prepare('SELECT 1 FROM sqlite_master WHERE tbl_name = ?;');
        $query->execute(array('search'));
        
        if ($query->fetch(PDO::FETCH_NUM))
        {
            $this->search = true;
        }
        else
        {
            $query = $this->db->prepare('SELECT 1 FROM sqlite_master WHERE tbl_name = ?;');
            $query->execute(array('no_search'));

            if (!$query->fetch(PDO::FETCH_NUM))
            {
                $this->initSearch();
            }
        }

        if ($this->search)
        {
            $this->db->sqliteCreateFunction('rank', array($this, 'sql_rank'));
        }

        $query = $this->db->prepare('SELECT 1 FROM sqlite_master 
            WHERE tbl_name = \'photos\' AND type = \'table\' AND sql LIKE \'%camera TEXT%\';');
        $query->execute();
        
        if (!$query->fetch(PDO::FETCH_NUM))
        {
            $this->upgradeDbDetails();
        }
    }

    public function sql_rank($aMatchInfo)
    {
        $iSize = 4; // byte size
        $iPhrase = (int) 0;                 // Current phrase //
        $score = (double)0.0;               // Value to return //

        /* Check that the number of arguments passed to this function is correct.
        ** If not, jump to wrong_number_args. Set aMatchinfo to point to the array
        ** of unsigned integer values returned by FTS function matchinfo. Set
        ** nPhrase to contain the number of reportable phrases in the users full-text
        ** query, and nCol to the number of columns in the table.
        */
        $aMatchInfo = (string) func_get_arg(0);
        $nPhrase = ord(substr($aMatchInfo, 0, $iSize));
        $nCol = ord(substr($aMatchInfo, $iSize, $iSize));

        if (func_num_args() > (1 + $nCol))
        {
            throw new \Exception("Invalid number of arguments : ".$nCol);
        }

        // Iterate through each phrase in the users query. //
        for ($iPhrase = 0; $iPhrase < $nPhrase; $iPhrase++)
        {
            $iCol = (int) 0; // Current column //

            /* Now iterate through each column in the users query. For each column,
            ** increment the relevancy score by:
            **
            **   (<hit count> / <global hit count>) * <column weight>
            **
            ** aPhraseinfo[] points to the start of the data for phrase iPhrase. So
            ** the hit count and global hit counts for each column are found in
            ** aPhraseinfo[iCol*3] and aPhraseinfo[iCol*3+1], respectively.
            */
            $aPhraseinfo = substr($aMatchInfo, (2 + $iPhrase * $nCol * 3) * $iSize);

            for ($iCol = 0; $iCol < $nCol; $iCol++)
            {
                $nHitCount = ord(substr($aPhraseinfo, 3 * $iCol * $iSize, $iSize));
                $nGlobalHitCount = ord(substr($aPhraseinfo, (3 * $iCol + 1) * $iSize, $iSize));
                $weight = ($iCol < func_num_args() - 1) ? (double) func_get_arg($iCol + 1) : 0;

                if ($nHitCount > 0 && $nGlobalHitCount != 0)
                {
                    $score += ((double)$nHitCount / (double)$nGlobalHitCount) * $weight;
                }
            }
        }

        return $score;
    }

    protected function upgradeDBv3()
    {
        $this->initDB();
        $this->initSearch();

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

    protected function upgradeDbDetails()
    {
        $this->db->exec('BEGIN;');
        $this->db->exec('ALTER TABLE photos RENAME TO photos_old;');
        $this->initDB();
        $this->db->exec('INSERT INTO photos
            (id, filename, path, width, height, size, year, month, day, time, comment, hash)
            SELECT id, filename, path, width, height, size, year, month, day, time, comment, hash
            FROM photos_old;');

        $res = $this->db->query('SELECT id, details FROM photos_old;');

        while ($row = $res->fetch(PDO::FETCH_ASSOC))
        {
            $details = json_decode($row['details'], true);

            if (empty($details))
                continue;

            $data = array(
                'camera' => array()
            );

            if (!empty($details['maker']))
                $data['camera'][] = ucfirst(strtolower($details['maker']));
            
            if (!empty($details['model']))
                $data['camera'][] = $details['model'];

            if (isset($details['flash']))
                $data['flash'] = (int)$details['flash'];

            if (!empty($details['resolution']))
            {
                $details['resolution'] = explode(' x ', $details['resolution']);
                $data['orig_width'] = $details['resolution'][0];
                $data['orig_height'] = $details['resolution'][1];
            }

            if (!empty($details['exposure']) && is_numeric($details['exposure']))
            {
                $data['exposure'] = $details['exposure'];
            }

            foreach (array('iso', 'fnumber', 'focal') as $key)
            {
                if (isset($details[$key]))
                    $data[$key] = $details[$key];
            }

            if (empty($data['camera']))
                unset($data['camera']);
            else
                $data['camera'] = implode(' ', $data['camera']);

            if (empty($data))
                continue;

            $query = 'UPDATE photos SET ';

            foreach ($data as $key=>$value)
            {
                $query .= $key . ' = :' . $key . ', ';
            }

            $query = substr($query, 0, -2) . ' WHERE id = :id';
            $data['id'] = (int)$row['id'];

            $query = $this->db->prepare($query);
            $query->execute($data);
        }

        $this->db->exec('DROP TABLE IF EXISTS photos_old;
            CREATE UNIQUE INDEX IF NOT EXISTS hash ON photos (hash);
            CREATE INDEX IF NOT EXISTS file ON photos (filename, path);
            CREATE INDEX IF NOT EXISTS date ON photos (year, month, day);');
        $this->db->exec('END;');

        return true;
    }

    public function normalizeExposureTime($time)
    {
        if ($time >= 1)
            return round($time, 2);

        if (!is_numeric($time))
            return $time;

        $fractions = array(1.3, 1.6, 2, 2.5, 3.2, 4, 5, 6, 8,
            10, 13, 15, 20, 25, 30, 40, 50, 60, 80, 100, 125, 160, 200,
            250, 320, 400, 500, 640, 800, 1000, 1300, 1600, 2000, 2500,
            3200, 4000, 8000);

        reset($fractions);
        $f = 1;

        while ($f)
        {
            $n = next($fractions);
            
            if ($time >= (1/$n) && $time <= (1/$f))
                return '1/' . $f;

            $f = $n;
        }

        return round($f, 2);
    }

    // Inspired by jhead code
    public function normalizeFocalLength($exif)
    {
        if (!empty($exif['FocalLengthIn35mmFilm']))
        {
            if (preg_match('!^(\d+)/(\d+)$!', $exif['FocalLengthIn35mmFilm'], $match)
                && (int)$match[1] && (int)$match[2])
            {
                return round((int)$match[1] / (int)$match[2], 1);
            }
            elseif (is_numeric($exif['FocalLengthIn35mmFilm']))
            {
                return round($exif['FocalLengthIn35mmFilm'], 1);
            }
        }

        if (empty($exif['FocalLength']))
        {
            return null;
        }

        $width = $height = $res = $unit = null;

        if (!empty($exif['ExifImageWidth']))
            $width = (int)$exif['ExifImageWidth'];
        
        if (!empty($exif['ExifImageLength']))
            $height = (int)$exif['ExifImageLength'];

        if (!empty($exif['FocalPlaneXResolution']))
        {
            if (preg_match('!^(\d+)/(\d+)$!', $exif['FocalPlaneXResolution'], $match)
                && (int)$match[1] && (int)$match[2])
            {
                $res = (int)$match[1] / (int)$match[2];
            }
            elseif (is_numeric($exif['FocalPlaneXResolution']))
            {
                $res = (float) $exif['FocalPlaneXResolution'];
            }
        }

        if (!empty($exif['FocalPlaneResolutionUnit']))
        {
            switch ((int)$exif['FocalPlaneResolutionUnit'])
            {
                case 1: $unit = 25.4; break; // inch
                case 2: $unit = 25.4; break; // supposed to be meters but actually inches
                case 3: $unit = 10;   break;  // centimeter
                case 4: $unit = 1;    break;  // millimeter
                case 5: $unit = .001; break;  // micrometer
            }
        }

        if ($width && $height && $res && $unit)
        {
            $size = max($width, $height);
            $ccd_width = (float)($size * $unit / $res);

            return round($exif['FocalLength'] / $ccd_width * 36 + 0.5, 1);
        }

        if (preg_match('!^([0-9.]+)/([0-9.]+)$!', $exif['FocalLength'], $match)
            && (int)$match[1] && (int)$match[2])
        {
            return round($match[1] / $match[2], 1);
        }

        if (is_numeric($exif['FocalLength']))
            return round($exif['FocalLength'], 1);

        return null;
    }

    protected function initDB()
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS photos (
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
                hash TEXT,
                camera TEXT,
                iso INTEGER,
                fnumber REAL,
                focal REAL,
                flash INTEGER,
                exposure REAL,
                orig_width INTEGER,
                orig_height INTEGER
            );

            CREATE UNIQUE INDEX IF NOT EXISTS hash ON photos (hash);
            CREATE INDEX IF NOT EXISTS file ON photos (filename, path);
            CREATE INDEX IF NOT EXISTS date ON photos (year, month, day);

            CREATE TABLE IF NOT EXISTS tags (
                name TEXT,
                name_id TEXT,
                photo INTEGER REFERENCES photos(id),
                PRIMARY KEY (name_id, photo)
            );
        ');
    }

    protected function initSearch()
    {
        try {
            $this->db->exec('
                CREATE VIRTUAL TABLE IF NOT EXISTS search USING fts4 (
                    photo INTEGER PRIMARY KEY REFERENCES photos (id),
                    text TEXT,
                    tags TEXT
                );');
            $this->search = true;
        }
        catch (\Exception $e)
        {
            try {
                $this->db->exec('
                    CREATE VIRTUAL TABLE IF NOT EXISTS search USING fts3 (
                        photo INTEGER PRIMARY KEY REFERENCES photos (id),
                        text TEXT,
                        tags TEXT
                    );');
                $this->search = true;
            }
            catch (\Exception $e)
            {
                // OK no search capability
                $this->db->exec('CREATE TABLE IF NOT EXISTS no_search (no_search);');
                return false;
            }
        }

        $this->db->exec('INSERT INTO search 
            SELECT id, normalize_ascii(path || filename || \' \' || comment || \' \' || camera),
                (SELECT group_concat(name_id, \' \') FROM tags WHERE photo = id)
            FROM photos;');
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

        if ($this->search)
        {
            $this->db->exec('DELETE FROM search WHERE photo="'.(int)$id.'";');
        }

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
        $this->db->exec('BEGIN;');
        $query = $this->db->query('SELECT id, hash, path, filename FROM photos ORDER BY id;');

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $file = BASE_DIR . '/' . ($row['path'] ? $row['path'] . '/' : '') . $row['filename'];

            if (!file_exists($file))
            {
                $this->cleanInfos($row['id'], $row['hash']);
            }
        }

        $this->db->exec('END;');

        unset($query);
    }

    public function search($search)
    {
        $search = trim($search);

        if ($search == '')
        {
            return array();
        }

        $query = $this->db->prepare('SELECT photos.*, rank(matchinfo(search), 0, 1.0, 1.0) AS rank 
            FROM photos INNER JOIN search ON search.photo = photos.id
            WHERE search MATCH ?
            ORDER BY rank DESC LIMIT 0,100;');
        $query->execute(array($this->getTagId($search)));
        return $query->fetchAll(PDO::FETCH_ASSOC);
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
        $tags = array();
        $date = false;
        $pic = array(
            'filename'  =>  $filename,
            'path'      =>  $path,
            'width'     =>  (int)$width,
            'height'    =>  (int)$height,
            'size'      =>  (int)$file_size,
            'year'      =>  null,
            'month'     =>  null,
            'day'       =>  null,
            'time'      =>  null,
            'hash'      =>  $hash,
            'comment'   =>  null,
            'camera'    =>  array(),
            'iso'       =>  null,
            'fnumber'   =>  null,
            'focal'     =>  null,
            'flash'     =>  null,
            'exposure'  =>  null,
            'orig_width'=>  null,
            'orig_height'=> null,
        );

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
                $pic['comment'] = implode("\n", $exif['COMMENT']);
                $pic['comment'] = trim($pic['comment']);
            }

            if (!empty($exif['THUMBNAIL']['THUMBNAIL']))
            {
                $thumb = $exif['THUMBNAIL']['THUMBNAIL'];
            }

            if (!empty($exif['IFD0']['Make']))
            {
                $pic['camera'][] = ucfirst(strtolower(trim($exif['IFD0']['Make'])));
            }

            if (!empty($exif['IFD0']['Model']))
            {
                if (!empty($exif['IFD0']['Make']))
                {
                    $exif['IFD0']['Model'] = str_ireplace($exif['IFD0']['Make'], '', $exif['IFD0']['Model']);
                }

                if (trim($exif['IFD0']['Model']) !== '')
                {
                    $pic['camera'][] = trim($exif['IFD0']['Model']);
                }
            }

            if (!empty($exif['EXIF']['ExposureTime']))
            {
                $pic['exposure'] = $exif['EXIF']['ExposureTime'];

                if (preg_match('!^([0-9.]+)/([0-9.]+)$!', $pic['exposure'], $match)
                    && (float)$match[1] > 0 && (float)$match[2] > 0)
                {
                    $pic['exposure'] = (float)$match[1] / (float)$match[2];
                }

                if (!is_numeric($pic['exposure']))
                {
                    $pic['exposure'] = null;
                }
            }

            if (!empty($exif['EXIF']['FNumber']))
            {
                $pic['fnumber'] = $exif['EXIF']['FNumber'];

                if (preg_match('!^([0-9.]+)/([0-9.]+)$!', $pic['fnumber'], $match))
                {
                    $pic['fnumber'] = round($match[1] / $match[2], 1);
                }
            }

            if (!empty($exif['EXIF']['ISOSpeedRatings']))
            {
                $pic['iso'] = $exif['EXIF']['ISOSpeedRatings'];
            }

            if (!empty($exif['EXIF']['Flash']))
            {
                $pic['flash'] = ($exif['EXIF']['Flash'] & 0x01) ? true : false;
            }

            if (!empty($exif['EXIF']['ExifImageWidth']) && !empty($exif['EXIF']['ExifImageLength']))
            {
                $pic['orig_width'] = (int)$exif['EXIF']['ExifImageWidth'];
                $pic['orig_height'] = (int)$exif['EXIF']['ExifImageLength'];
            }

            if (!empty($exif['EXIF']['FocalLength']))
            {
                $pic['focal'] = $this->normalizeFocalLength($exif['EXIF']);
            }
        }

        unset($exif);

        if (!$date)
            $date = $file_time;

        $can_resize = ($width <= MAX_IMAGE_SIZE && $height <= MAX_IMAGE_SIZE);

        if (!file_exists($this->getThumbPath($hash)))
        {
            if (isset($thumb))
            {
                file_put_contents($this->getThumbPath($hash), $thumb);
            }
            elseif ($can_resize)
            {
                $this->resizeImage($file, $this->getThumbPath($hash), $width, $height, 160);
            }
        }

        unset($thumb);

        if (GEN_SMALL == 1 && $can_resize && !file_exists($this->getSmallPath($hash)))
        {
            $this->resizeImage($file, $this->getSmallPath($hash), $width, $height, SMALL_IMAGE_SIZE);
        }

        $pic['year'] = date('Y', $date);
        $pic['month'] = date('m', $date);
        $pic['day'] = date('d', $date);
        $pic['time'] = (int) $date;
        $pic['camera'] = !empty($camera) ? implode(' ', $camera) : null;

        $this->db->beginTransaction();

        $keys = array_keys($pic);
        $query = $this->db->prepare('INSERT INTO photos ('.implode(', ', $keys).')
            VALUES (:'.implode(', :', $keys).');');
        $query->execute($pic);

        $pic['id'] = $this->db->lastInsertId();

        if (!$pic['id'])
        {
            return false;
        }

        if (!empty($tags))
        {
            $query = $this->db->prepare('INSERT OR IGNORE INTO tags (name, name_id, photo) VALUES (?, ?, ?);');

            foreach ($tags as $tag)
            {
                $query->execute(array($tag, $this->getTagId($tag), (int)$pic['id']));
            }
        }

        if ($this->search)
        {
            $this->db->exec('INSERT INTO search SELECT id, 
                path || \' \' || filename || \' \' || comment || \' \' || camera,
                (SELECT group_concat(name || \' \' || name_id, \' \') FROM tags WHERE photo = id)
                FROM photos WHERE id = ' . (int)$pic['id']);
        }

        $this->db->commit();

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
    public function getDirectory($path = '', $dont_check = false, $page = 1, &$total = null)
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
            {
                continue;
            }

            if (is_dir($file_path))
            {
                $dirs[] = $file;
                continue;
            }
            elseif (!preg_match('!\.jpe?g$!i', $file))
            {
                continue;
            }
            // Don't detect updates when directory has already been updated
            // (used in 'index_all' p'rocess only, to avoid server load)
            elseif ($dont_check)
            {
                continue;
            }
            elseif ($pic = $this->getInfos($file, $path, true))
            {
                $pictures[$file] = $pic;
            }
            else
            {
                $to_update[] = $file;
            }
        }

        $dir->close();

        sort($dirs);
        ksort($pictures);

        $total = count($pictures);

        // Pagination
        $begin = ($page - 1) * NB_PICTURES_PER_PAGE;
        $pictures = array_slice($pictures, $begin, NB_PICTURES_PER_PAGE, true);

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

    public function countByDate($y, $m = false, $d = false)
    {
        $req = 'SELECT COUNT(*) FROM photos WHERE year="'.(int)$y.'"';

        if ($m)
            $req .= ' AND month="'.(int)$m.'"';
        if ($d)
            $req .= 'AND day="'.(int)$d.'"';

        $query = $this->db->query($req . ';');
        return $query->fetchColumn();
    }

    public function getByDate($y = false, $m = false, $d = false, $page = 1)
    {
        if ($d)
        {
            $begin = ($page - 1) * NB_PICTURES_PER_PAGE;
            $query = $this->db->prepare('SELECT * FROM photos WHERE year = ? AND month = ? AND day = ? 
                ORDER BY time LIMIT ' . (int) $begin . ', ' . (int) NB_PICTURES_PER_PAGE . ';');
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

                $subquery .= 'ORDER BY random() LIMIT '.(int)$start.', 5;';
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

    public function getByTag($tag, $page = 1)
    {
        $begin = ($page - 1) * NB_PICTURES_PER_PAGE;
        $query = $this->db->prepare('SELECT photos.* FROM photos
            INNER JOIN tags ON tags.photo = photos.id
            WHERE tags.name_id = ? ORDER BY photos.time, photos.filename COLLATE NOCASE
            LIMIT ' . (int) $begin . ', ' . (int) NB_PICTURES_PER_PAGE . ';');
        $query->execute(array($this->getTagId($tag)));
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByTag($tag)
    {
        $query = $this->db->prepare('SELECT COUNT(*) FROM photos
            INNER JOIN tags ON tags.photo = photos.id
            WHERE tags.name_id = ?;');
        $query->execute(array($this->getTagId($tag)));
        return $query->fetchColumn();
    }

    public function getNearTags($tag)
    {
        $query = $this->db->prepare('SELECT t2.name, COUNT(t2.photo) AS nb FROM tags
            INNER JOIN tags AS t2 ON tags.photo = t2.photo
            WHERE tags.name_id = ? AND t2.name_id != tags.name_id
            GROUP BY t2.name_id ORDER BY nb DESC LIMIT 0,50;');
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

        foreach (self::$html_tags as $tag=>$url)
        {
            $tag_class = preg_replace('![^a-zA-Z0-9_]!', '_', $tag);
            $text = preg_replace_callback('#(^|\s)'.preg_quote($tag, '#').':([^\s,.]+)#im',
                function ($match) use ($tag_class, $url) {
                	return sprintf('%s<a href="%s" class="%s">%s</a>%s', $match[1], str_replace('KEYWORD', $match[2], $url), $tag_class, $match[3]);
                }, $text);
        }

        $text = nl2br($text);

        return $text;
    }

    public function formatTitle($text)
    {
        $text = $this->formatText($text);
        
        if ($pos = stripos('<br', $text))
        {
            $text = substr($text, 0, $pos);
        }

        $text = strip_tags($text);
        $text = trim($text);

        if (strlen($text) > 100)
        {
            $text = substr($text, 0, 100);

            if ($pos = strripos(' ', $text))
            {
                $text = substr($text, 0, $pos);
            }
        }

        return $text;
    }

    public function formatDetails($pic)
    {
        $out = array();

        if ($pic['camera'])
            $out[__('Camera')] = $pic['camera'];

        if (!is_null($pic['exposure']))
            $out[__('Exposure time')] = $this->normalizeExposureTime($pic['exposure']);

        if (!is_null($pic['fnumber']))
            $out[__('Aperture')] = '<i>f</i>/' . $pic['fnumber'];

        if (!is_null($pic['iso']))
            $out[__('ISO speed')] = $pic['iso'];

        if (!is_null($pic['flash']))
            $out[__('Flash')] = $pic['flash'] ? __('On') : __('Off');

        if (!is_null($pic['focal']))
            $out[__('Focal length')] = (int)$pic['focal'] . ' mm';

        if (!is_null($pic['orig_width']) && !is_null($pic['orig_height']))
            $out[__('Original resolution')] = $pic['orig_width'] . ' x ' . $pic['orig_height'];

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
                $im->setInterlaceScheme(Imagick::INTERLACE_PLANE); // To get progressive jpeg
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
                imageinterlace($newImage, true);

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

    public function getNb()
    {
        $query = $this->db->prepare('SELECT COUNT(*) FROM photos;');
        $query->execute();
        return $query->fetchColumn();
    }

    public function getStats()
    {
        $out = array();
        $query = $this->db->query('SELECT 
            year, month, COUNT(*) AS nb, ROUND(AVG(size)) AS size,
            ROUND(AVG(focal)) AS focal, ROUND(AVG(fnumber)) AS fnumber,
            (CASE WHEN orig_width IS NOT NULL THEN ROUND(AVG(orig_width)) ELSE ROUND(AVG(width)) END) AS width,
            (CASE WHEN orig_height IS NOT NULL THEN ROUND(AVG(orig_height)) ELSE ROUND(AVG(height)) END) AS height
            FROM photos GROUP BY year, month ORDER BY year, month;');

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $out[$row['year'] . '-' . (int)$row['month']] = $row;
        }

        return $out;
    }

    public function getCameraStats()
    {
        $out = array();
        $query = $this->db->query('SELECT 
            year, month, camera, COUNT(*) AS nb
            FROM photos GROUP BY year, month, camera ORDER BY year, month, nb DESC;');

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            if (!isset($out[$row['year'] . '-' . (int)$row['month']]))
                $out[$row['year'] . '-' . (int)$row['month']] = array();
            
            $out[$row['year'] . '-' . (int)$row['month']][$row['camera']] = $row;
        }

        return $out;
    }

}

?>