<?php

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
    } ());
    </script>
    ';
}

echo '
</body>
</html>';

?>