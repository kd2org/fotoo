<?php
/*src:COPYING*/

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

require 'class.fotoo.php';
require 'functions.php';

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

    require 'update.js';
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

ul.pics, ul.dirs { text-align: center; display: inline-block; }
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
ul.goPrevNext li a span { position: absolute; left: 0; top: 0; width: 100%; height: 100%; font-size: 70pt; color: #fff; text-shadow: 0px 0px 5px #000; line-height: 160px; }
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
    require 'slideshow.php';
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