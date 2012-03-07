<?php

function escape($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function zero_pad($str, $length = 2)
{
    return str_pad($str, $length, '0', STR_PAD_LEFT);
}

function get_url($type, $data = null)
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

    if ($type == 'image' || $type == 'embed' || $type == 'embed_img')
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
        return get_custom_url($type, $data);
    }

    if ($type == 'image' || $type == 'album')
    {
        return SELF_URL . '?' . rawurlencode($data);
    }
    elseif ($type == 'embed' || $type == 'slideshow')
    {
        return SELF_URL . '?'.$type.'=' . rawurlencode($data);
    }
    elseif ($type == 'embed_tag')
    {
        return SELF_URL . '?embed&tag=' . rawurlencode($data);
    }
    elseif ($type == 'slideshow_tag')
    {
        return SELF_URL . '?slideshow&tag=' . rawurlencode($data);
    }
    elseif ($type == 'embed_img')
    {
        return SELF_URL . '?i=' . rawurlencode($data);
    }
    elseif ($type == 'tag')
    {
        return SELF_URL . '?tag=' . rawurlencode($data);
    }
    elseif ($type == 'date')
    {
        return SELF_URL . '?date=' . rawurlencode($data);
    }
    elseif ($type == 'tags' || $type == 'timeline' || $type == 'feed')
    {
        return SELF_URL . '?' . $type;
    }

    throw new Exception('Unknown type '.$type);
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

?>