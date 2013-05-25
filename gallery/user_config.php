<?php
/*
    Fotoo Gallery
    Copyright 2004-2013 BohwaZ - http://dev.kd2.org/
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

/**
 * This is an example user_config.php file
 * It shows you how to translate the application in your own language
 * (here it's french)
 */

if (!class_exists('fotooManager'))
    die('Just config');

/**
* User configuration
* You can set those constants to what you need
* Let them commented to get the defaults
*/

//define('BASE_DIR', dirname(__FILE__));
//define('CACHE_DIR', BASE_DIR . '/cache');
//define('BASE_URL', 'http://myserver.tld/pics/');
//define('SELF_URL', BASE_URL . 'gallery.php');

// Gallery title, change it to what you prefer
define('GALLERY_TITLE', 'My own photo gallery');

// Allow embedding of your pictures ?
define('ALLOW_EMBED', true);

// Number of pictures per page in albums, tag list and date list
define('NB_PICTURES_PER_PAGE', 50);

// Generate a resized copy of images for small view (600x600), disabled by default
// WARNING GENERATING IMAGES IS REALLY SLOW AND IT MAY KILL YOU WEBSERVER!

// Generate all small images in directory update process (slow)
//define('GEN_SMALL', 1);

// Generate small image when an image is viewed (less slow)
define('GEN_SMALL', 2);

// If an image width or height is superior to this number, the gallery
// will not try to resize it
define('MAX_IMAGE_SIZE', 2048);

// Shortcut tags in album and pictures comments
// eg. if you type wp:Belgium in your comment tag, it will make a link to wikipedia
// You can add anything you want
$f->html_tags = array(
    'wp:fr' =>  'http://fr.wikipedia.org/wiki/KEYWORD',
    'wp'    =>  'http://en.wikipedia.org/wiki/KEYWORD',
);

// Activation of custom-URLs (if you use RewriteRules for example)
/*
function get_custom_url($type, $data = null)
{
    if ($type == 'image')
    {
        return BASE_URL . 'image/' . $data;
    }
    elseif ($type == 'album')
    {
        return BASE_URL . 'album/' . $data;
    }
    elseif ($type == 'embed' || $type == 'slideshow')
    {
        return BASE_URL . $type . '/' . $data;
    }
    elseif ($type == 'embed_tag')
    {
        return BASE_URL . 'tag/embed/' . $data;
    }
    elseif ($type == 'slideshow_tag')
    {
        return BASE_URL . 'tag/slideshow/' . $data;
    }
    elseif ($type == 'embed_img')
    {
        return BASE_URL . 'r/' . $data;
    }
    elseif ($type == 'tag')
    {
        return BASE_URL . 'tag/' . $data;
    }
    elseif ($type == 'date')
    {
        return BASE_URL . 'date/' . $data;
    }
    elseif ($type == 'tags' || $type == 'timeline' || $type == 'feed')
    {
        return BASE_URL . $type;
    }
    elseif ($type == 'page')
    {
        return '?p=';
    }
}
*/

?>