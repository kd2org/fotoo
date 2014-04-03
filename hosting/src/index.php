<?php
/**
    Fotoo Hosting
    Copyright 2010-2012 BohwaZ - http://dev.kd2.org/
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

error_reporting(E_ALL);

if (!version_compare(phpversion(), '5.3', '>='))
{
    die("You need at least PHP 5.2 to use this application.");
}

if (!class_exists('SQLite3'))
{
    die("You need PHP SQLite3 extension to use this application.");
}

define('UPLOAD_ERR_INVALID_IMAGE', 42);

class FotooException extends Exception {}

function exception_error_handler($errno, $errstr, $errfile, $errline )
{
    // For @ ignored errors
    if (error_reporting() === 0) return;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler("exception_error_handler");

require_once __DIR__ . '/lib-image/lib.image.php';

class Fotoo_Hosting_Config
{
    private $db_file = null;
    private $storage_path = null;

    private $base_url = null;
    private $storage_url = null;
    private $image_page_url = null;
    private $album_page_url = null;

    private $max_width = null;

    private $thumb_width = null;

    private $title = null;

    private $max_file_size = null;
    private $allow_upload = null;
    private $nb_pictures_by_page = null;

    private $admin_password = null;

    public function __set($key, $value)
    {
        switch ($key)
        {
            case 'max_width':
            case 'thumb_width':
            case 'max_file_size':
            case 'nb_pictures_by_page':
                $this->$key = (int) $value;
                break;
            case 'db_file':
            case 'storage_path':
            case 'base_url':
            case 'storage_url':
            case 'title':
            case 'image_page_url':
            case 'album_page_url':
            case 'admin_password':
                $this->$key = (string) $value;
                break;
            case 'allow_upload':
                $this->$key = is_bool($value) ? (bool) $value : $value;
                break;
            case 'allowed_formats':
                if (is_string($value))
                {
                    $value = explode(',', strtoupper(str_replace(' ', '', $value)));
                }
                else
                {
                    $value = (array) $value;
                }

                // If Imagick is not present then we can't process images different than JPEG, GIF and PNG
                foreach ($value as $f=>$format)
                {
                    $format = strtoupper($format);

                    if ($format != 'PNG' && $format != 'JPEG' && $format != 'GIF' && !class_exists('Imagick'))
                    {
                        unset($value[$f]);
                    }
                }

                $this->$key = $value;

                break;
            default:
                throw new FotooException("Unknown configuration property $key");
        }
    }

    public function __get($key)
    {
        if (isset($this->$key))
            return $this->$key;
        else
            throw new FotooException("Unknown configuration property $key");
    }

    public function exportJSON()
    {
        $vars = get_object_vars($this);

        unset($vars['db_file']);
        unset($vars['storage_path']);
        unset($vars['admin_password']);

        return json_encode($vars);
    }

    public function exportPHP()
    {
        $vars = get_object_vars($this);

        $out = "<?php\n\n";
        $out.= '// Do not edit the line below';
        $out.= "\n";
        $out.= 'if (!isset($config) || !($config instanceof Fotoo_Hosting_Config)) die("Invalid call.");';
        $out.= "\n\n";
        $out.= '// To edit one of the following configuration options, comment it out and change it';
        $out.= "\n\n";

        foreach ($vars as $key=>$value)
        {
            $out .= "// ".wordwrap($this->getComment($key), 70, "\n// ")."\n";
            $line = '$config->'.$key.' = '.var_export($value, true).";";

            if (strpos($line, "\n") !== false)
                $out .= "/*\n".$line."\n*/\n\n";
            else
                $out .= '#'.$line."\n\n";
        }

        $out.= "\n?>";

        return $out;
    }

    public function getComment($key)
    {
        switch ($key)
        {
            case 'max_width':       return 'Maximum image width or height, bigger images will be resized.';
            case 'thumb_width':     return 'Maximum thumbnail size, used for creating thumbnails.';
            case 'max_file_size':   return 'Maximum uploaded file size (in bytes). By default it\'s the maximum size allowed by the PHP configuration. See the FAQ for more informations.';
            case 'nb_pictures_by_page': return 'Number of images to display on each page in the pictures list.';
            case 'db_file':         return 'Path to the SQLite DB file.';
            case 'storage_path':    return 'Path to where the pictures are stored.';
            case 'base_url':        return 'URL of the webservice index.';
            case 'storage_url':     return 'URL to where the pictures are stored. Filename is added at the end.';
            case 'title':           return 'Title of the service.';
            case 'image_page_url':  return 'URL to the picture information page, hash is added at the end.';
            case 'album_page_url':  return 'URL to the album page, hash is added at the end.';
            case 'allow_upload':    return 'Allow upload of files? You can use this to restrict upload access. Can be a boolean or a PHP callback. See the FAQ for more informations.';
            case 'admin_password':  return 'Password to access admin UI? (edit/delete files, see private pictures)';
            case 'allowed_formats': return 'Allowed formats, separated by a comma';
            default: return '';
        }
    }

    public function __construct()
    {
        // Defaults
        $this->db_file = dirname(__FILE__) . '/datas.db';
        $this->storage_path = dirname(__FILE__) . '/i/';
        $this->base_url = 'http://'. $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

        if ($this->base_url[strlen($this->base_url) - 1] != '/')
            $this->base_url .= '/';

        $this->storage_url = $this->base_url . str_replace(dirname(__FILE__) . '/', '', $this->storage_path);
        $this->image_page_url = $this->base_url . '?';
        $this->album_page_url = $this->base_url . '?a=';

        if (substr(basename($_SERVER['PHP_SELF']), 0, 5) != 'index')
            $this->base_url .= basename($_SERVER['PHP_SELF']);

        $this->max_width = 1920;
        $this->thumb_width = 320;

        $this->title = 'Fotoo Image Hosting service';

        $size = self::return_bytes(ini_get('upload_max_filesize'));
        $post = self::return_bytes(ini_get('post_max_size'));

        if ($post < $size)
            $size = $post;

        $memory = self::return_bytes(ini_get('memory_limit'));

        if ($memory < $size)
            $size = $memory;

        $this->max_file_size = $size;
        $this->allow_upload = true;
        $this->admin_password = 'fotoo';
        $this->nb_pictures_by_page = 20;

        $this->allowed_formats = array('PNG', 'JPEG', 'GIF', 'SVG', 'XCF');
    }

    static public function return_bytes ($size_str)
    {
        switch (substr($size_str, -1))
        {
            case 'G': case 'g': return (int)$size_str * pow(1024, 3);
            case 'M': case 'm': return (int)$size_str * pow(1024, 2);
            case 'K': case 'k': return (int)$size_str * 1024;
            default: return $size_str;
        }
    }
}

function escape($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'utf-8', false);
}

require __DIR__ . '/class.fotoo_hosting.php';

$config = new Fotoo_Hosting_Config;

$config_file = __DIR__ . '/config.php';

if (file_exists($config_file))
{
    require_once $config_file;
}
elseif (isset($_GET['create_config']))
{
    file_put_contents($config_file, $config->exportPHP());
    die("Default configuration created in config.php file, edit it to change default values.");
}

// Check upload access
if (!is_bool($config->allow_upload) && is_callable($config->allow_upload))
{
    $config->allow_upload = (bool) call_user_func($config->allow_upload);
}

$fh = new Fotoo_Hosting($config);

if (!empty($_GET['delete']))
{
    $id = !empty($_GET['c']) ? trim($_GET['c']) : false;

    if ($fh->remove($_GET['delete'], $id))
    {
        header('Location: '.$config->base_url.'?list');
    }
    else
    {
        echo "Can't delete picture";
    }

    exit;
}
elseif (!empty($_GET['deleteAlbum']))
{
    $id = !empty($_GET['c']) ? trim($_GET['c']) : false;

    if ($fh->removeAlbum($_GET['deleteAlbum'], $id))
    {
        header('Location: ' . $config->base_url . '?albums');
    }
    else
    {
        echo "Can't delete album";
    }

    exit;
}

if (isset($_POST['album_create']))
{
    if (!empty($_POST['title']))
    {
        $id = $fh->createAlbum($_POST['title'], empty($_POST['private']) ? false : true);
        echo "$id/" . $fh->makeRemoveId($id);
        exit;
    }

    header('HTTP/1.1 400 Bad Request', true, 400);
    die("Bad Request");
}

if (isset($_POST['album_append']))
{
    if (!empty($_POST['album']) && !empty($_POST['content']) && isset($_POST['name']) && isset($_POST['filename']))
    {
        if ($fh->appendToAlbum($_POST['album'], $_POST['name'], array('content' => $_POST['content'], 'name' => $_POST['filename'])))
        {
            echo "OK";
        }
        else
        {
            echo "FAIL";
        }
        exit;
    }

    header('HTTP/1.1 400 Bad Request', true, 400);
    die("Bad Request");
}

if (isset($_GET['upload']))
{
    $error = false;

    if (empty($_FILES['upload']) && empty($_POST['upload']))
    {
        $error = UPLOAD_ERR_INI_SIZE;
    }
    else
    {
        try {
            $res = $fh->upload(!empty($_FILES['upload']) ? $_FILES['upload'] : $_POST['upload'],
                isset($_POST['name']) ? trim($_POST['name']) : '',
                isset($_POST['private']) ? (bool) $_POST['private'] : false
            );
        }
        catch (FotooException $e)
        {
            if ($e->getCode())
                $error = $e->getCode();
            else
                throw $e;
        }
    }

    if ($error)
    {
        $url = $config->base_url . '?error=' . $error;
        header('Location: ' . $url);
        exit;
    }
    else
    {
        $img = $fh->get($res);
        $url = $fh->getUrl($img, true);

        header('Location: ' . $url);

        exit;
    }
}

$html = $title = '';

if (isset($_GET['logout']))
{
    $fh->logout();
    header('Location: ' . $config->base_url);
    exit;
}
elseif (isset($_GET['login']))
{
    $title = 'Login';
    $error = '';

    if (!empty($_POST['password']))
    {
        if ($fh->login(trim($_POST['password'])))
        {
            header('Location: ' . $config->base_url);
            exit;
        }
        else
        {
            $error = '<p class="error">Wrong password.</p>';
        }
    }

    $html = '
        <article class="browse">
            <h2>'.$title.'</h2>
            '.$error.'
            <form method="post" action="' . $config->base_url . '?login">
            <fieldset>
                <dl>
                    <dt><label for="f_password">Password</label></dt>
                    <dd><input type="password" name="password" id="f_password" /></dd>
                </dl>
            </fieldset>
            <p class="submit">
                <input type="submit" id="f_submit" value="Login" />
            </p>
            </form>
        </article>
    ';
}
elseif (isset($_GET['list']))
{
    $title = 'Browse pictures';

    if (!empty($_GET['list']) && is_numeric($_GET['list']))
        $page = (int) $_GET['list'];
    else
        $page = 1;

    $list = $fh->getList($page);
    $max = $fh->countList();

    $html = '
        <article class="browse">
            <h2>'.$title.'</h2>';

    foreach ($list as &$img)
    {
        $thumb_url = $fh->getImageThumbUrl($img);
        $url = $fh->getUrl($img);

        $label = $img['filename'] ? escape(preg_replace('![_-]!', ' ', $img['filename'])) : 'View image';

        $html .= '
        <figure>
            <a href="'.$url.'">'.($img['private'] ? '<span class="private">Private</span>' : '').'<img src="'.$thumb_url.'" alt="'.$label.'" /></a>
            <figcaption><a href="'.$url.'">'.$label.'</a></figcaption>
        </figure>';
    }

    $html .= '
        </article>';

    if ($max > $config->nb_pictures_by_page)
    {
        $max_page = ceil($max / $config->nb_pictures_by_page);
        $html .= '
        <nav class="pagination">
            <ul>
        ';

        for ($p = 1; $p <= $max_page; $p++)
        {
            $html .= '<li'.($page == $p ? ' class="selected"' : '').'><a href="?list='.$p.'">'.$p.'</a></li>';
        }

        $html .= '
            </ul>
        </nav>';
    }
}
elseif (isset($_GET['albums']))
{
    $title = 'Browse albums';

    if (!empty($_GET['albums']) && is_numeric($_GET['albums']))
        $page = (int) $_GET['albums'];
    else
        $page = 1;

    $list = $fh->getAlbumList($page);
    $max = $fh->countAlbumList();

    $html = '
        <article class="albums">
            <h2>'.$title.'</h2>';

    foreach ($list as $album)
    {
        $url = $config->album_page_url . $album['hash'];
        $nb = $fh->countAlbumPictures($album['hash']);

        $html .= '
        <figure>
            <h2><a href="'.$url.'">'.escape($album['title']).'</a></h2>
            <h6>('.$nb.' pictures)</h6>
            <a href="'.$url.'">'.($album['private'] ? '<span class="private">Private</span>' : '');

        foreach ($album['extract'] as $img)
        {
            $thumb_url = $fh->getImageThumbUrl($img);
            $html .= '<img src="'.$thumb_url.'" alt="" />';
        }

        $html .= '</a>
        </figure>';
    }

    $html .= '
        </article>';

    if ($max > round($config->nb_pictures_by_page / 2))
    {
        $max_page = ceil($max / round($config->nb_pictures_by_page / 2));
        $html .= '
        <nav class="pagination">
            <ul>
        ';

        for ($p = 1; $p <= $max_page; $p++)
        {
            $html .= '<li'.($page == $p ? ' class="selected"' : '').'><a href="'.$config->base_url.'?albums='.$p.'">'.$p.'</a></li>';
        }

        $html .= '
            </ul>
        </nav>';
    }
}
elseif (!empty($_GET['a']))
{
    $album = $fh->getAlbum($_GET['a']);

    if (empty($album))
    {
        header('HTTP/1.1 404 Not Found', true, 404);
        echo '
            <h1>Not Found</h1>
            <p><a href="'.$config->base_url.'">'.$config->title.'</a></p>
        ';
        exit;
    }

    $title = $album['title'];

    if (!empty($_GET['p']) && is_numeric($_GET['p']))
        $page = (int) $_GET['p'];
    else
        $page = 1;

    $list = $fh->getAlbumPictures($album['hash'], $page);
    $max = $fh->countAlbumPictures($album['hash']);

    $bbcode = '[b][url=' . $config->album_page_url . $album['hash'] . ']' . $album['title'] . "[/url][/b]\n";

    foreach ($list as $img)
    {
        $label = $img['filename'] ? escape(preg_replace('![_-]!', ' ', $img['filename'])) : 'View image';
        $bbcode .= '[url='.$fh->getUrl($img).'][img='.$label.']'.$fh->getImageThumbUrl($img)."[/img][/url] ";
    }

    $html = '
        <article class="browse">
            <h2>'.escape($title).'</h2>
            <p class="info">
                Uploaded on <time datetime="'.date(DATE_W3C, $album['date']).'">'.strftime('%c', $album['date']).'</time>
                | '.(int)$max.' picture'.((int)$max > 1 ? 's' : '').'
            </p>
            <aside class="examples">
                <dt>Share this album using this URL:</dt>
                <dd><input type="text" onclick="this.select();" value="'.escape($config->album_page_url . $album['hash']).'" /></dd>
                <dt>All pictures for a forum (BBCode):</dt>
                <dd><textarea cols="70" rows="1" onclick="this.select();">'.escape($bbcode).'</textarea></dd>
            </aside>';

    if ($fh->logged())
    {
        $html .= '
        <p class="admin">
            <a href="?deleteAlbum='.rawurlencode($album['hash']).'" onclick="return confirm(\'Really?\');">Delete album</a>
        </p>';
    }
    elseif (!empty($_GET['c']))
    {
        $url = $config->album_page_url . $album['hash'] 
            . (strpos($config->album_page_url, '?') !== false ? '&c=' : '?c=') 
            . $fh->makeRemoveId($album['hash']);

        $html .= '
        <p class="admin">
            <a href="?deleteAlbum='.rawurlencode($album['hash']).'&amp;c='.rawurldecode($_GET['c']).'" onclick="return confirm(\'Really?\');">Delete album</a>
        </p>
        <p class="admin">
            Keep this URL in your favorites to be able to delete this album later:<br />
            <input type="text" onclick="this.select();" value="'.escape($url).'" />
        </p>';
    }

    foreach ($list as &$img)
    {
        $thumb_url = $fh->getImageThumbUrl($img);
        $url = $fh->getUrl($img);

        $label = $img['filename'] ? escape(preg_replace('![_-]!', ' ', $img['filename'])) : 'View image';

        $html .= '
        <figure>
            <a href="'.$url.'">'.($img['private'] ? '<span class="private">Private</span>' : '').'<img src="'.$thumb_url.'" alt="'.$label.'" /></a>
            <figcaption><a href="'.$url.'">'.$label.'</a></figcaption>
        </figure>';
    }

    $html .= '
        </article>';

    if ($max > $config->nb_pictures_by_page)
    {
        $max_page = ceil($max / $config->nb_pictures_by_page);
        $html .= '
        <nav class="pagination">
            <ul>
        ';

        $url = $config->album_page_url . ((strpos($config->album_page_url, '?') === false) ? '?p=' : '&amp;p=');

        for ($p = 1; $p <= $max_page; $p++)
        {
            $html .= '<li'.($page == $p ? ' class="selected"' : '').'><a href="'.$url.$p.'">'.$p.'</a></li>';
        }

        $html .= '
            </ul>
        </nav>';
    }
}
elseif (!isset($_GET['album']) && !isset($_GET['error']) && !empty($_SERVER['QUERY_STRING']))
{
    $query = explode('.', $_SERVER['QUERY_STRING']);
    $hash = ($query[0] == 'r') ? $query[1] : $query[0];
    $img = $fh->get($hash);

    if (empty($img))
    {
        header('HTTP/1.1 404 Not Found', true, 404);
        echo '
            <h1>Not Found</h1>
            <p><a href="'.$config->base_url.'">'.$config->title.'</a></p>
        ';
        exit;
    }

    $img_url = $fh->getImageUrl($img);

    if ($query[0] == 'r')
    {
        header('Location: '.$img_url);
        exit;
    }

    $url = $fh->getUrl($img);
    $thumb_url = $fh->getImageThumbUrl($img);
    $short_url = $fh->getShortImageUrl($img);
    $title = $img['filename'] ? $img['filename'] : 'Image';

    // Short URL auto discovery
    header('Link: <'.$short_url.'>; rel=shorturl');

    $bbcode = '[url='.$img_url.'][img]'.$thumb_url.'[/img][/url]';
    $html_code = '<a href="'.$img_url.'"><img src="'.$thumb_url.'" alt="'.(trim($img['filename']) ? $img['filename'] : '').'" /></a>';

    $size = $img['size'];
    if ($size > (1024 * 1024))
        $size = round($size / 1024 / 1024, 2) . ' MB';
    elseif ($size > 1024)
        $size = round($size / 1024, 2) . ' KB';
    else
        $size = $size . ' B';

    $html = '
    <article class="picture">
        <header>
            '.(trim($img['filename']) ? '<h2>' . escape(strtr($img['filename'], '-_.', '   ')) . '</h2>' : '').'
            <p class="info">
                Uploaded on <time datetime="'.date(DATE_W3C, $img['date']).'">'.strftime('%c', $img['date']).'</time>
                | Size: '.$img['width'].' × '.$img['height'].'
            </p>
        </header>
        <figure>
            <a href="'.$img_url.'">'.($img['private'] ? '<span class="private">Private</span>' : '').'<img src="'.$thumb_url.'" alt="'.escape($title).'" /></a>
        </figure>
        <footer>
            <p>
                <a href="'.$img_url.'">View full size ('.$img['format'].', '.$size.')</a>
            </p>
        </footer>';

    if (!empty($img['album']))
    {
        $prev = $fh->getAlbumPrevNext($img['album'], $img['hash'], -1);
        $next = $fh->getAlbumPrevNext($img['album'], $img['hash'], 1);
        $album = $fh->getAlbum($img['album']);

        $html .= '
        <footer class="context">';

        if ($prev)
        {
            $thumb_url = $fh->getImageThumbUrl($prev);
            $url = $fh->getUrl($prev);
            $label = $prev['filename'] ? escape(preg_replace('![_-]!', ' ', $prev['filename'])) : 'View image';

            $html .= '
            <figure class="prev">
                <a href="'.$url.'"><b>&larr;</b><img src="'.$thumb_url.'" alt="'.$label.'" /></a>
                <figcaption><a href="'.$url.'">'.$label.'</a></figcaption>
            </figure>';
        }
        else
        {
            $html .= '<figure class="prev"><b>…</b></figure>';
        }

        $html .= '
            <figure>
                <h3>Album:</h3>
                <h2><a href="' . $config->album_page_url . $album['hash'] . '"> ' . escape($album['title']) .'</a></h2></figure
            </figure>';

        if ($next)
        {
            $thumb_url = $fh->getImageThumbUrl($next);
            $url = $fh->getUrl($next);
            $label = $next['filename'] ? escape(preg_replace('![_-]!', ' ', $next['filename'])) : 'View image';

            $html .= '
            <figure class="prev">
                <a href="'.$url.'"><img src="'.$thumb_url.'" alt="'.$label.'" /><b>&rarr;</b></a>
                <figcaption><a href="'.$url.'">'.$label.'</a></figcaption>
            </figure>';
        }
        else
        {
            $html .= '<figure class="next"><b>…</b></figure>';
        }

        $html .= '
            </footer>';
    }

    if ($fh->logged())
    {
        $html .= '
        <p class="admin">
            <a href="?delete='.rawurlencode($img['hash']).'" onclick="return confirm(\'Really?\');">Delete picture</a>
        </p>';
    }
    elseif (!empty($_GET['c']))
    {
        $html .= '
        <p class="admin">
            <a href="?delete='.rawurlencode($img['hash']).'&amp;c='.rawurldecode($_GET['c']).'" onclick="return confirm(\'Really?\');">Delete picture</a>
        </p>
        <p class="admin">
            Keep this URL in your favorites to be able to delete this picture later:<br />
            <input type="text" onclick="this.select();" value="'.$fh->getUrl($img, true).'" />
        </p>';
    }

    $html .= '
        <aside class="examples">
            <dt>Short URL for full size</dt>
            <dd><input type="text" onclick="this.select();" value="'.escape($short_url).'" /></dd>
            <dt>BBCode</dt>
            <dd><input type="text" onclick="this.select();" value="'.escape($bbcode).'" /></dd>
            <dt>HTML code</dt>
            <dd><input type="text" onclick="this.select();" value="'.escape($html_code).'" /></dd>
        </aside>
    </article>
    ';
}
elseif (!$config->allow_upload)
{
    $html = '<p class="error">Uploading is not allowed.</p>';
}
else
{
    $js_url = file_exists(__DIR__ . '/upload.js')
        ? $config->base_url . 'upload.js'
        : $config->base_url . '?js';

    $html = '
        <script type="text/javascript">
        var config = '.$config->exportJSON().';
        </script>';

    if (!empty($_GET['error']))
    {
        $html .= '<p class="error">'.escape(Fotoo_Hosting::getErrorMessage($_GET['error'])).'</p>';
    }

    if (isset($_GET['album']))
    {
        $html .= '
        <form method="post" action="'.$config->base_url.'?upload" id="f_upload">
        <article class="upload">
            <header>
                <h2>Upload an album</h2>
                <p class="info">
                    Maximum file size: '.round($config->max_file_size / 1024 / 1024, 2).'MB
                    | Image types accepted: JPEG only
                </p>
            </header>
            <fieldset>
                <dl>
                    <dt><label for="f_title">Title:</label></dt>
                    <dd><input type="text" name="title" id="f_title" maxlength="100" required="required" /></dd>
                    <dt><label for="f_private">Private</label></dt>
                    <dd class="private"><label><input type="checkbox" name="private" id="f_private" value="1" />
                        (If checked, this album won\'t appear in &quot;browse pictures&quot;)</label></dd>
                    <dt><label for="f_files">Files:</label></dt>
                    <dd id="f_file_container"><input type="file" name="upload" id="f_files" multiple="multiple" accept="image/jpeg" required="required" /></dd>
                </dl>
            </fieldset>
            <div id="albumParent">Please select some files...</div>
            <p class="submit">
                <input type="submit" id="f_submit" value="Upload" />
            </p>
        </article>
        </form>';
    }
    else
    {
        $html .= '
        <form method="post" enctype="multipart/form-data" action="'.$config->base_url.'?upload" id="f_upload">
        <article class="upload">
            <header>
                <h2>Upload a file</h2>
                <p class="info">
                    Maximum file size: '.round($config->max_file_size / 1024 / 1024, 2).'MB
                    | Image types accepted: '.implode(', ', $config->allowed_formats).'
                </p>
            </header>
            <fieldset>
                <input type="hidden" name="MAX_FILE_SIZE" value="'.($config->max_file_size - 1024).'" />
                <dl>
                    <dt><label for="f_file">File:</label></dt>
                    <dd id="f_file_container"><input type="file" name="upload" id="f_file" /></dd>
                    <dt><label for="f_name">Name:</label></dt>
                    <dd><input type="text" name="name" id="f_name" maxlength="30" /></dd>
                    <dt><label for="f_private">Private</label></dt>
                    <dd class="private"><label><input type="checkbox" name="private" id="f_private" value="1" />
                        (If checked, picture won\'t appear in pictures list)</label></dd>
                </dl>
            </fieldset>
            <div id="resizeParent"></div>
            <p class="submit">
                <input type="submit" id="f_submit" value="Upload" />
            </p>
        </article>
        </form>';
    }

    $html .= '<script type="text/javascript" src="'.$js_url.'"></script>';
}

$css_url = file_exists(__DIR__ . '/style.css')
    ? $config->base_url . 'style.css'
    : $config->base_url . '?css';

echo '<!DOCTYPE html>
<html>
<head>
    <meta name="charset" content="utf-8" />
    <title>'.($title ? escape($title) . ' - ' : '').$config->title.'</title>
    <meta name="viewport" content="width=device-width" />
    <link rel="stylesheet" type="text/css" href="'.$css_url.'" />
</head>

<body>
<header>
    <h1><a href="'.$config->base_url.'">'.$config->title.'</a></h1>
    '.($fh->logged() ? '<h2>(admin mode)</h2>' : '').'
    <nav>
        <ul>
            <li><a href="'.$config->base_url.'">Upload a file</a></li>
            <li><a href="'.$config->base_url.'?list">Browse pictures</a></li>
            <li><a href="'.$config->base_url.'?albums">Browse albums</a></li>
        </ul>
    </nav>
</header>
<div id="page">
    '.$html.'
</div>
<footer>
    Powered by Fotoo Hosting application from <a href="http://kd2.org/">KD2.org</a>
    | '.($fh->logged() ? '<a href="'.$config->base_url.'?logout">Logout</a>' : '<a href="'.$config->base_url.'?login">Login</a>').'
</footer>
</body>
</html>';


?>