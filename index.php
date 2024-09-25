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

if (!version_compare(phpversion(), '7.4', '>='))
{
    die("You need at least PHP 7.4 to use this application.");
}

if (!class_exists('SQLite3'))
{
    die("You need PHP SQLite3 extension to use this application.");
}

define('UPLOAD_ERR_INVALID_IMAGE', 42);

class FotooException extends Exception {}

require_once __DIR__ . '/ErrorManager.php';

if (class_exists('ErrorManager')) {
    ErrorManager::enable(ErrorManager::DEVELOPMENT);
}

require_once __DIR__ . '/ZipWriter.php';

class Fotoo_Hosting_Config
{
    private string $db_file = __DIR__ . '/datas.db';
    private string $storage_path = __DIR__ . '/i/';

    private string $base_url;
    private string $storage_url;
    private string $image_page_url;
    private string $album_page_url;

    private int $max_width = 1920;
    private int $thumb_width = 320;
    private int $quality = 75;

    private string $title = 'Fotoo Image Hosting service';

    private int $max_file_size;
    private array $allowed_formats = ['png', 'jpeg', 'gif', 'svg', 'webp'];
    private bool $allow_upload = true;
    private bool $allow_album_zip = false;
    private int $nb_pictures_by_page = 20;

    private string $admin_password = 'fotoo';
    private array $banned_ips = [];
    private int $ip_storage_expiration = 366;

    public function __set($key, $value)
    {
        switch ($key)
        {
            case 'max_width':
            case 'thumb_width':
            case 'quality':
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
        if (property_exists($this, $key)) {
            return $this->$key;
        }
        else {
            throw new FotooException("Unknown configuration property $key");
        }
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
        $proto = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $this->base_url = $proto . '://'. $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

        if ($this->base_url[strlen($this->base_url) - 1] != '/') {
            $this->base_url .= '/';
        }

        $this->storage_url = $this->base_url . str_replace(dirname(__FILE__) . '/', '', $this->storage_path);
        $this->image_page_url = $this->base_url . '?';
        $this->album_page_url = $this->base_url . '?a=';

        if (substr(basename($_SERVER['PHP_SELF']), 0, 5) != 'index') {
            $this->base_url .= basename($_SERVER['PHP_SELF']);
        }

        $size = self::return_bytes(ini_get('upload_max_filesize'));
        $post = self::return_bytes(ini_get('post_max_size'));

        if ($post < $size) {
            $size = $post;
        }

        $memory = self::return_bytes(ini_get('memory_limit'));

        if ($memory > 0 && $memory < $size) {
            $size = $memory;
        }

        $this->max_file_size = $size;
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

function page(string $html, string $title = '', int $section = 0)
{
    global $fh, $config;
    $css_url = file_exists(__DIR__ . '/style.css')
        ? $config->base_url . 'style.css?2024'
        : $config->base_url . '?css&2024';

    $title = escape($title);

    if ($title) {
        $title .= ' - ';
    }

    $title .= $config->title;
    $subtitle = $fh->logged() ? '<h2>(admin mode)</h2>' : '';
    $login = sprintf(
        $fh->logged() ? '<a href="%s?logout">Logout</a>' : '<a href="%s?login">Login</a>',
        $config->base_url
    );

    $a = $section == 1 ? ' class="current"' : '';
    $b = $section == 2 ? ' class="current"' : '';

    echo <<<EOF
    <!DOCTYPE html>
    <html>
    <head>
        <meta name="charset" content="utf-8" />
        <title>{$title}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, target-densitydpi=device-dpi" />
        <link rel="stylesheet" type="text/css" href="{$css_url}" />
    </head>

    <body>
    <header>
        <h1><a href="{$config->base_url}">{$config->title}</a></h1>
        {$subtitle}
        <nav>
            <ul>
                <li{$a}><a href="{$config->base_url}">Upload</a></li>
                <li{$b}><a href="{$config->base_url}?list">Browse images</a></li>
            </ul>
        </nav>
    </header>
    <div id="page">
        {$html}
    </div>
    <footer>
        Powered by Fotoo Hosting application from <a href="https://kd2.org/">KD2.org</a>
        | {$login}
    </footer>
    </body>
    </html>
EOF;
}

require_once __DIR__ . '/class.fotoo_hosting.php';
require_once __DIR__ . '/class.image.php';

$config = new Fotoo_Hosting_Config;

$config_file = __DIR__ . '/config.php';

if (file_exists($config_file))
{
    require $config_file;
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

if (!empty($_POST['delete']) && !empty($_POST['key'])) {
    $img = $fh->get($_POST['delete']);
    $r = null;

    if ($img) {
        $r = $fh->userDeletePicture($img, $_POST['key']);
    }

    if ($r === null) {
        page('<h1 class="error">Cannot delete this image</h1>');
        exit;
    }

    if (!$r || !$img['album']) {
        $url = $config->base_url.'?list';
    }
    else {
        $url = $fh->getAlbumUrl($img['album'], true);
    }

    header('Location: ' . $url);
    exit;
}
elseif (!empty($_POST['deleteAlbum']) && !empty($_POST['key'])) {
    if ($fh->userDeleteAlbum($_POST['deleteAlbum'], $_POST['key'])) {
        header('Location: ' . $config->base_url . '?list');
    }
    else {
        page('<h1 class="error">Cannot delete this album</h1>');
    }

    exit;
}
elseif (!empty($_POST['delete']) && $fh->logged()) {
    foreach ($_POST['pictures'] ?? [] as $pic) {
        $fh->deletePicture($pic);
    }

    foreach ($_POST['albums'] ?? [] as $album) {
        $fh->deleteAlbum($album);
    }

    header('Location: ' . $config->base_url . '?list');
    exit;
}

if (isset($_GET['upload'], $_POST['album']) && $_POST['album'] === 'new' && $config->allow_upload) {
    if (empty($_POST['title'])) {
        http_response_code(400);
        die("Bad Request");
    }

    try {
        $hash = $fh->createAlbum($_POST['title'], !empty($_POST['private']), $_POST['expiry'] ?? null);
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
elseif (isset($_GET['upload'], $_POST['album']) && $config->allow_upload) {
    if (!$fh->checkRemoveId($_POST['album'], $_POST['key'])) {
        http_response_code(401);
        die("Invalid key");
    }

    if (!isset($_POST['name'], $_POST['filename'])) {
        http_response_code(400);
        die("Wrong Request");
    }

    try {
        if (!isset($_FILES['file']) && !isset($_POST['content'])) {
            throw new FotooException('No file', UPLOAD_ERR_NO_FILE);
        }

        $file = $_FILES['file'] ?? ['content' => $_POST['content'], 'name' => $_POST['filename']];
        $file['thumb'] = $_POST['thumb'] ?? null;

        $url = $fh->appendToAlbum($_POST['album'], $_POST['name'], $file);
        http_response_code(201);
    }
    catch (FotooException $e) {
        http_response_code(400);
        echo $e->getMessage();
    }

    exit;
}
// Single image upload, no album
elseif (isset($_GET['upload'], $_POST['filename'], $_POST['name'], $_POST['private']) && $config->allow_upload) {
    try {
        if (!isset($_FILES['file']) && !isset($_POST['content'])) {
            throw new FotooException('No file', UPLOAD_ERR_NO_FILE);
        }

        $file = $_FILES['file'] ?? ['content' => $_POST['content'], 'name' => $_POST['filename']];
        $file['thumb'] = $_POST['thumb'] ?? null;

        $url = $fh->upload($file, $_POST['name'], (bool) $_POST['private'], $_POST['expiry'] ?? null);

        http_response_code(200);
        echo $url;
    }
    catch (FotooException $e) {
        http_response_code(400);
        echo $e->getMessage();
    }

    exit;
}
// Images upload, no JS
elseif (isset($_GET['upload']) && $config->allow_upload) {
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
                isset($_POST['private']) ? (bool) $_POST['private'] : false,
                $_POST['expiry'] ?? null
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

$copy_script = '
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
</script>';

$current = 0;

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
    $current = 2;
    $fh->pruneExpired();
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
            $url = $config->album_page_url . rawurlencode($img['album']);
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
        http_response_code(404);
        page('<h1 class="error">404 Not Found</h1>');
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

    $html .= $copy_script;

    $is_uploader = !empty($_GET['c']) && $fh->checkRemoveId($album['hash'], $_GET['c']);

    $html .= sprintf(
        '<article class="browse">
            <h2>%s</h2>
            <p class="info">
                Uploaded on <time datetime="%s">%s</time>
                | <strong>%d picture%s</strong>
                | Expires: %s
            </p>
            <aside class="examples">
            <dl>
                <dt>Share this album using this URL: <input type="button" onclick="copy(\'#url\', this);" value="Copy" /></dt>
                <dd><input readonly="readonly" type="text" id="url" onclick="this.select();" value="%s" /></dd>
                <dt>All pictures for a forum (BBCode): <input type="button" onclick="copy(\'#all\', this);" value="Copy" /></dt>
                <dd><textarea readonly="readonly" id="all" cols="70" rows="3" onclick="this.select(); this.setSelectionRange(0, this.value.length); navigator.clipboard.writeText(this.value);">%s</textarea></dd>
                <dd></dd>
            </dl>',
        escape($title),
        date(DATE_W3C, $album['date']),
        date('d/m/Y H:i', $album['date']),
        $max,
        $max > 1 ? 's' : '',
        $album['expiry'] ? date('d/m/Y H:i', strtotime($album['expiry'])) : 'never',
        escape($config->album_page_url . $album['hash']),
        escape($bbcode)
    );


    if (!$fh->logged() && !empty($_GET['c'])) {
        $hash = $album['hash'];
        $key = $fh->makeRemoveId($album['hash']);
        $url = $config->album_page_url . $hash
            . (strpos($config->album_page_url, '?') !== false ? '&c=' : '?c=')
            . $key;

        $html .= sprintf('
            <dl class="admin">
                <dt>
                    Bookmark this URL to be able to delete this album later:
                    <input type="button" onclick="copy(\'#admin\', this);" value="Copy" />
                </dt>
                <dd><input type="text" id="admin" onclick="this.select();" value="%s" />
                <dd><form method="post"><button class="icon delete" type="submit" name="deleteAlbum" value="%s" onclick="return confirm(\'Really?\');">Delete this album now</button><input type="hidden" name="key" value="%s" /></form></dd>
            </dl>
            <form method="post" action="%s?append=%2$s">
            <p>
                <input type="hidden" name="key" value="%3$s" />
                <input type="submit" value="Add images to this album" class="icon select" />
            </p>
            </form>
            ',
            $url,
            $hash,
            $key,
            $config->base_url
        );
    }

    $html .= '</aside>';

    if ($config->allow_album_zip) {
        $html .= '
        <form method="post" action="">
        <p>
            <input type="submit" name="download" value="Download all images in a ZIP file" class="icon zip" />
        </p>
        </form>';
    }

    if ($fh->logged())
    {
        $html .= sprintf(
            '<form class="admin" method="post"><button class="icon delete" type="submit" name="delete" value="1" onclick="return confirm(\'Really?\');">Delete this album now</button><input type="hidden" name="albums[]" value="%s" /></form>',
            $album['hash']
        );
    }

    foreach ($list as &$img)
    {
        $thumb_url = $fh->getImageThumbUrl($img);
        $url = $fh->getUrl($img, $is_uploader);

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
elseif (!isset($_GET['album']) && !isset($_GET['error']) && !isset($_GET['append']) && !empty($_SERVER['QUERY_STRING']))
{
    $query = explode('.', $_SERVER['QUERY_STRING']);
    $hash = ($query[0] == 'r') ? $query[1] : $query[0];
    $img = $fh->get($hash);

    if (empty($img))
    {
        http_response_code(404);
        page('<h1 class="error">404 Not Found</h1>');
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
    $is_uploader = !empty($_GET['c']) && $fh->checkRemoveId($img['hash'], $_GET['c']);

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

    $album = null;

    if (!empty($img['album']))
    {
        $album = $fh->getAlbum($img['album']);
        $album = sprintf('<h3>(Album: <a href="%s">%s</a>)</h3>',
            $fh->getAlbumUrl($album['hash'], $is_uploader),
            escape($album['title'])
        );
    }

    $html .= $copy_script;
    $html .= sprintf('<article class="picture">
        <header>
            %s
            %s
            <p class="info">
                Uploaded on <time datetime="%s">%s</time>
                | Size: %d × %d
                | Expires: %s
            </p>
        </header>',
        trim($img['filename']) ? '<h2>' . escape(strtr($img['filename'], '-_.', '   ')) . '</h2>' : '',
        $album,
        date(DATE_W3C, $img['date']),
        date('d/m/Y H:i', $img['date']),
        $img['width'],
        $img['height'],
        $img['expiry'] ? date('d/m/Y H:i', strtotime($img['expiry'])) : 'never'
    );

    $examples = '
        <aside class="examples">
            <dl>
                <dt>Short URL for full size <input type="button" onclick="copy(\'#url\', this);" value="Copy" /></dt>
                <dd><input type="text" readonly="readonly" onclick="this.select();" value="'.escape($short_url).'" id="url" /></dd>
                <dt>BBCode <input type="button" onclick="copy(\'#bbcode\', this);" value="Copy" /></dt>
                <dd><textarea readonly="readonly" cols="70" rows="3" onclick="this.select();" id="bbcode">'.escape($bbcode).'</textarea></dd>
                <dt>HTML code <input type="button" onclick="copy(\'#html\', this);" value="Copy" /></dt>
                <dd><textarea readonly="readonly" cols="70" rows="3" onclick="this.select();" id="html">'.escape($html_code).'</textarea></dd>
            </dl>';

    if (!empty($_GET['c']))
    {
        $examples .= sprintf('
            <dl class="admin">
                <dt>
                    Bookmark this URL to be able to delete this picture later:
                    <input type="button" onclick="copy(\'#admin\', this);" value="Copy" />
                </dt>
                <dd><input type="text" readonly="readonly" id="admin" onclick="this.select();" value="%s" />
                <dd><form method="post"><button class="icon delete" type="submit" name="delete" value="%s" onclick="return confirm(\'Really?\');">Delete this picture now</button><input type="hidden" name="key" value="%s" /></form></dd>
            </dl>',
            $fh->getUrl($img, true),
            $img['hash'],
            escape($_GET['c'])
        );
    }

    $examples .= '</aside>';
    $prev = $next = null;

    if (!empty($img['album']))
    {
        $prev = $fh->getAlbumPrevNext($img['album'], $img['hash'], -1);
        $next = $fh->getAlbumPrevNext($img['album'], $img['hash'], 1);

        if ($prev) {
            $prev['url'] = $fh->getUrl($prev, $is_uploader);
        }

        if ($next) {
            $next['url'] = $fh->getUrl($next, $is_uploader);
        }
    }

    $html .= sprintf('
        <div class="pic">
            <div class="prev">%s</div>
            <figure>
                <a href="%s" target="_blank">%s<img src="%s" alt="%s" /></a>
            </figure>
            <div class="next">%s</div>
        </div>
        <footer>
            <p>
                <a href="%2$s" target="_blank">View full size (%s, %s)</a>
            </p>
        </footer>',
        $prev ? sprintf('<a href="%s"><span>Previous</span></a>', $prev['url']) : '<p></p>',
        $img_url,
        $img['private'] ? '<span class="private">Private</span>' : '',
        $img_url,
        escape($title),
        $next ? sprintf('<a href="%s"><span>Next</span></a>', $next['url']) : '<p></p>',
        strtoupper($img['format']),
        $size
    );

    $html .= $examples;

    if ($fh->logged())
    {
        $ip = !$img['ip'] ? 'Not available' : ($img['ip'] == 'R' ? 'Automatically removed from database' : $img['ip']);
        $html .= sprintf('
            <form class="admin" method="post" action="">
                <dl class="admin"><dt>IP address:</dt><dd>%s</dd></dl>
                <p><button class="icon delete" type="submit" name="delete" value="1" onclick="return confirm(\'Really?\');">Delete this picture</button><input type="hidden" name="pictures[]" value="%s" /></p>
            </form>',
            escape($ip),
            $img['hash']
        );
    }

    $html .= '</article>';
}
elseif (!$config->allow_upload)
{
    $html = '<p class="error">Uploading is not allowed.</p>';
}
else
{
    $append = $_GET['append'] ?? null;
    $append_key = $_POST['key'] ?? null;
    $current = 1;
    $js_url = file_exists(__DIR__ . '/upload.js')
        ? $config->base_url . 'upload.js?2024'
        : $config->base_url . '?js&2024';

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

    $expiry_list = ['+1 hour' => '1 hour', '+1 day' => '24 hours', '+1 week' => '1 week', '+2 weeks' => '2 weeks', '+1 month' => '1 month', '+3 month' => '3 months', '+6 months' => '6 months', '+1 year' => '1 year', null => 'Never expires'];
    $expiry_options = '';
    $default_expiry = null;

    foreach ($expiry_list as $a => $b) {
        $expiry_options .= sprintf('<option value="%s"%s>%s</option>', $a, $a == $default_expiry ? ' selected="selected"' : '', $b);
    }

    $html .= sprintf('<form method="post" enctype="multipart/form-data" action="%s?upload" id="f_upload" class="%s">
        <input type="hidden" name="MAX_FILE_SIZE" value="%d" />
        <article class="upload"><header><h2>%s</h2>
        <p class="info">
            Maximum file size: %s MB
            | Image types accepted: %s
        </p>
        </header><fieldset><dl>',
        $config->base_url,
        $append ? 'append' : 'new',
        $max_file_size,
        $append ? 'Add images to album' : 'Upload images',
        $max_file_size_human,
        $formats
    );

    if (!$append) {
        $html .= '<dt><label for="f_title">Title:</label></dt>
                <dd><input type="text" name="title" id="f_title" maxlength="100" required="required" /></dd>
                <dd><label><input type="checkbox" name="private" id="f_private" value="1" />
                    <strong>Private</strong><br />
                    <small>(If checked, the pictures won\'t be listed in &quot;browse images&quot;)</small></label></dd>
                <dd><label for="f_expiry"><strong>Expiry:</strong></label> <select name="expiry" id="f_expiry">' . $expiry_options . '</select><br /><small>(The images will be deleted after this time)</small></dd>';
    }
    else {
        $html .= sprintf('<input type="hidden" name="append" value="%s" /><input type="hidden" name="append_key" value="%s" />',
            htmlspecialchars($append),
            htmlspecialchars($append_key)
        );
    }

    $html .= '<dd id="f_file_container"><input type="file" name="upload" id="f_files" multiple="multiple" accept="image/jpeg,image/webp,image/png,image/gif,image/svg+xml" /></dd>
            </dl>
            <p class="submit">
                <input type="submit" value="Upload images" class="icon upload" />
            </p>
        </fieldset>
        <div id="albumParent"></div>
        <p class="submit">
            <input type="submit" value="Upload images" class="icon upload" />
        </p>
    </article>
    </form>
    <script type="text/javascript" src="' . $js_url . '"></script>';
}

page($html, $title, $current);

?>