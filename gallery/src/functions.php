<?php

function escape($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8', false);
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
    elseif ($type == 'page')
    {
        return '&p=';
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

function html_pagination($page, $total, $url)
{
    if ($total <= NB_PICTURES_PER_PAGE)
        return '';

    $nb_pages = ceil($total / NB_PICTURES_PER_PAGE);

    $html = '
    <ul class="pagination">
    ';

    for ($p = 1; $p <= $nb_pages; $p++)
    {
        $html .= '<li'.($page == $p ? ' class="selected"' : '').'><a href="'.escape($url.$p).'">'.$p.'</a></li>';
    }

    $html .= '
    </ul>';

    echo $html;
}

?>