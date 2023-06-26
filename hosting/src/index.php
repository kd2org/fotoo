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
    return;
    // For @ ignored errors
    if (error_reporting() & $errno) return;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler("exception_error_handler");

require_once __DIR__ . '/ZipWriter.php';

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
    private $allow_album_zip = null;
    private $nb_pictures_by_page = null;

    private $admin_password = null;
    private $banned_ips = null;
    private $ip_storage_expiration = null;

    public function __set($key, $value)
    {
        switch ($key)
        {
            case 'max_width':
            case 'thumb_width':
            case 'max_file_size':
            case 'nb_pictures_by_page':
            case 'ip_storage_expiration':
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
            case 'banned_ips':
                $this->$key = (array) $value;
                break;
            case 'allow_upload':
            case 'allow_album_zip':
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
                    static $base_support = ['png', 'jpeg', 'gif', 'webp'];

                    if (!in_array($format, $base_support) && !class_exists('Imagick'))
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

        if ($memory > 0 && $memory < $size)
            $size = $memory;

        $this->max_file_size = $size;
        $this->allow_upload = true;
        $this->allow_album_zip = false;
        $this->admin_password = 'fotoo';
        $this->banned_ips = [];
        $this->ip_storage_expiration = 366;
        $this->nb_pictures_by_page = 20;

        $this->allowed_formats = ['png', 'jpeg', 'gif', 'svg', 'webp'];
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
require_once __DIR__ . '/class.image.php';

$config = new Fotoo_Hosting_Config;

$config_file = __DIR__ . '/config.php';

if (file_exists($config_file))
{
    require_once $config_file;
}

// Check upload access
if (!is_bool($config->allow_upload) && is_callable($config->allow_upload))
{
    $config->allow_upload = (bool) call_user_func($config->allow_upload);
}

$fh = new Fotoo_Hosting($config);

if ($fh->isClientBanned())
{
    $fh->setBanCookie();
}

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
        header('Location: ' . $config->base_url . '?list');
    }
    else
    {
        echo "Can't delete album";
    }

    exit;
}
elseif (!empty($_POST['delete']) && $fh->logged())
{
    foreach ($_POST['pictures'] as $pic)
    {
        $fh->remove($pic, null);
    }

    foreach ($_POST['albums'] as $album)
    {
        $fh->removeAlbum($album, null);
    }

    header('Location: ' . $config->base_url . '?list');
    exit;
}

if (isset($_GET['upload'], $_POST['album']) && $_POST['album'] === 'new') {
    if (empty($_POST['title'])) {
        http_response_code(400);
        die("Bad Request");
    }

    try {
        $hash = $fh->createAlbum($_POST['title'], empty($_POST['private']) ? false : true);
        $key = $fh->makeRemoveId($hash);
        http_response_code(200);
        echo json_encode(compact('hash', 'key'));
        exit;
    }
    catch (FotooException $e) {
        http_response_code(400);
        die("Upload not permitted.");
    }
}
elseif (isset($_GET['upload'], $_POST['album'])) {
    if (!$fh->checkRemoveId($_POST['album'], $_POST['key'])) {
        http_response_code(401);
        die("Invalid key");
    }

    if (empty($_POST['content']) || !isset($_POST['name'], $_POST['filename'])) {
        http_response_code(400);
        die("Wrong Request");
    }

    try {
        $url = $fh->appendToAlbum($_POST['album'], $_POST['name'], ['content' => $_POST['content'], 'name' => $_POST['filename']]);
        http_response_code(201);
    }
    catch (FotooException $e) {
        http_response_code(400);
        echo $e->getMessage();
    }

    exit;
}
// Single image upload, no album
elseif (isset($_GET['upload'], $_POST['content'], $_POST['filename'], $_POST['name'], $_POST['private'])) {
    try {
        $url = $fh->upload([
            'content' => $_POST['content'],
            'name' => $_POST['filename']
        ], $_POST['name'], $_POST['private']);

        http_response_code(200);
        echo $url;
    }
    catch (FotooException $e) {
        http_response_code(400);
        echo $e->getMessage();
    }

    exit;
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
            $url = $fh->upload(!empty($_FILES['upload']) ? $_FILES['upload'] : $_POST['upload'],
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
    $title = 'Browse images';

    if (!empty($_GET['list']) && is_numeric($_GET['list']))
        $page = (int) $_GET['list'];
    else
        $page = 1;

    $list = $fh->getList($page);
    $max = $fh->countList();

    $html = '';

    if ($fh->logged())
    {
        $html .= '<form method="post" action="" onsubmit="return confirm(\'Delete all the checked pictures and albums?\');">
        <p class="admin">
            <input type="button" value="Check / uncheck all" onclick="var l = this.form.querySelectorAll(\'input[type=checkbox]\'), s = l[0].checked; for (var i = 0; i < l.length; i++) { l[i].checked = s ? false : true; }" />
        </p>';
    }

    $html .= '
        <article class="browse">
            <h2>'.$title.'</h2>';

    foreach ($list as $img)
    {
        $thumb_url = $fh->getImageThumbUrl($img);

        if ($img['album']) {
            $url = '?a=' . rawurlencode($img['album']);
            $html .= sprintf(
                '<figure>
                    <a href="%s">%s<span class="count"><b>%d</b> images</span><img src="%s" alt="%s" /></a>
                    <figcaption><a href="%1$s">%5$s</b></a></figcaption>
                    %s
                </figure>',
                escape($url),
                $img['private'] ? '<span class="private">Private</span>' : '',
                $img['count'],
                $thumb_url,
                escape($img['title']),
                !$fh->logged() ? '' : '<label><input type="checkbox" name="albums[]" value="' . escape($img['album']) . '" /> Delete</label>'
            );
        }
        else {
            $url = $fh->getUrl($img);
            $html .= sprintf(
                '<figure>
                    <a href="%s">%s<img src="%s" alt="%s" /></a>
                    <figcaption><a href="%1$s">%4$s</a></figcaption>
                    %s
                </figure>',
                escape($url),
                $img['private'] ? '<span class="private">Private</span>' : '',
                $thumb_url,
                escape(preg_replace('![_-]!', ' ', $img['filename'])),
                !$fh->logged() ? '' : '<label><input type="checkbox" name="pictures[]" value="' . escape($img['hash']) . '" /> Delete</label>'
            );
        }
    }

    $html .= '
        </article>';


    if ($fh->logged())
    {
        $html .= '
        <p class="admin submit">
            <input type="submit" name="delete" value="Delete checked pictures and albums" />
        </p>
        </form>';
    }

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

    if (!empty($_POST['download']) && $config->allow_album_zip) {
        $fh->downloadAlbum($album['hash']);
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

    foreach ($fh->getAllAlbumPictures($album['hash']) as $img)
    {
        $label = $img['filename'] ? escape(preg_replace('![_-]!', ' ', $img['filename'])) : 'View image';
        $bbcode .= '[url='.$fh->getImageUrl($img).'][img]'.$fh->getImageThumbUrl($img)."[/img][/url] ";
    }

    $html = '
        <script type="text/javascript">
        var copy = (e, c) => {
            if (typeof e === \'string\') {
                e = document.querySelector(e);
            }

            e.select();
            e.setSelectionRange(0, e.value.length);
            navigator.clipboard.writeText(e.value);

            if (!c) {
                return;
            }

            c.value = \'Copied!\';
            window.setTimeout(() => c.value = \'Copy\', 5000);
        };
        </script>
        <article class="browse">
            <h2>'.escape($title).'</h2>
            <p class="info">
                Uploaded on <time datetime="'.date(DATE_W3C, $album['date']).'">'.@strftime('%c', $album['date']).'</time>
                | '.(int)$max.' picture'.((int)$max > 1 ? 's' : '').'
            </p>
            <aside class="examples">
                <dt>Share this album using this URL: <input type="button" onclick="copy(\'#url\', this);" value="Copy" /></dt>
                <dd><input type="text" id="url" onclick="this.select();" value="'.escape($config->album_page_url . $album['hash']).'" /></dd>
                <dt>All pictures for a forum (BBCode): <input type="button" onclick="copy(\'#all\', this);" value="Copy" /></dt>
                <dd><textarea id="all" cols="70" rows="3" onclick="this.select(); this.setSelectionRange(0, this.value.length); navigator.clipboard.writeText(this.value);">'.escape($bbcode).'</textarea></dd>
                <dd></dd>
            </aside>';

    if ($config->allow_album_zip) {
        $html .= '
        <form method="post" action="">
        <p>
            <input type="submit" name="download" value="Download full album as a ZIP file" />
        </p>
        </form>';
    }

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

        $url = $config->album_page_url . $album['hash'] . ((strpos($config->album_page_url, '?') === false) ? '?p=' : '&amp;p=');

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
                Uploaded on <time datetime="'.date(DATE_W3C, $img['date']).'">'.@strftime('%c', $img['date']).'</time>
                | Size: '.$img['width'].' × '.$img['height'].'
            </p>
        </header>
        <figure>
            <a href="'.$img_url.'">'.($img['private'] ? '<span class="private">Private</span>' : '').'<img src="'.$thumb_url.'" alt="'.escape($title).'" /></a>
        </figure>
        <footer>
            <p>
                <a href="'.$img_url.'">View full size ('.strtoupper($img['format']).', '.$size.')</a>
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
            IP address: ' . escape(is_null($img['ip']) ? 'Not available' : ($img['ip'] == 'R' ? 'Automatically removed from database' : $img['ip'])) . '
        </p>
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
            <dt>Short URL for full size <input type="button" onclick="copy(\'#url\', this);" value="Copy" /></dt>
            <dd><input type="text" onclick="this.select();" value="'.escape($short_url).'" id="url" /></dd>
            <dt>BBCode <input type="button" onclick="copy(\'#bbcode\', this);" value="Copy" /></dt>
            <dd><textarea cols="70" rows="3" onclick="this.select();" id="bbcode">'.escape($bbcode).'</textarea></dd>
            <dt>HTML code <input type="button" onclick="copy(\'#html\', this);" value="Copy" /></dt>
            <dd><textarea cols="70" rows="3" onclick="this.select();" id="html">'.escape($html_code).'</textarea></dd>
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
        ? $config->base_url . 'upload.js?2023'
        : $config->base_url . '?js&2023';

    $html = '
        <script type="text/javascript">
        var config = '.$config->exportJSON().';
        </script>';

    if (!empty($_GET['error']))
    {
        $html .= '<p class="error">'.escape(Fotoo_Hosting::getErrorMessage($_GET['error'])).'</p>';
    }

    $max_file_size = $config->max_file_size - 1024;
    $max_file_size_human = round($config->max_file_size / 1024 / 1024, 2);
    $formats = implode(', ', array_map('strtoupper', $config->allowed_formats));

    $html .= <<<EOF
    <form method="post" enctype="multipart/form-data" action="{$config->base_url}?upload" id="f_upload">
    <input type="hidden" name="MAX_FILE_SIZE" value="{$max_file_size}" />
    <article class="upload">
        <header>
            <h2>Upload images</h2>
            <p class="info">
                Maximum file size: {$max_file_size_human} MB
                | Image types accepted: {$formats}
            </p>
        </header>
        <fieldset>
            <dl>
                <dt><label for="f_title">Title:</label></dt>
                <dd><input type="text" name="title" id="f_title" maxlength="100" required="required" /></dd>
                <dt><label for="f_private">Private</label></dt>
                <dd class="private"><label><input type="checkbox" name="private" id="f_private" value="1" />
                    (If checked, the pictures won't be listed in &quot;browse images&quot;)</label></dd>
                <dd id="f_file_container"><input type="file" name="upload" id="f_files" multiple="multiple" accept="image/jpeg,image/webp,image/png,image/gif,image/svg+xml" required="required" /></dd>
            </dl>
            <p class="submit">
                <input type="submit" value="Upload" />
            </p>
        </fieldset>
        <div id="albumParent"></div>
        <p class="submit">
            <input type="submit" value="Upload" />
        </p>
    </article>
    </form>
    <script type="text/javascript" src="{$js_url}"></script>
EOF;

}

$css_url = file_exists(__DIR__ . '/style.css')
    ? $config->base_url . 'style.css?2023'
    : $config->base_url . '?css&2023';

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
            <li><a href="'.$config->base_url.'">Upload</a></li>
            <li><a href="'.$config->base_url.'?list">Browse images</a></li>
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