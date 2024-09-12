<?php

// Do not edit the line below
if (!isset($config) || !($config instanceof Fotoo_Hosting_Config)) die("Invalid call.");

// To edit one of the following configuration options, comment it out and change it

// Path to the SQLite DB file.
#$config->db_file = '/home/bohwaz/svn/misc/apps/fotoo/hosting/datas.db';

// Path to where the pictures are stored.
#$config->storage_path = '/home/bohwaz/svn/misc/apps/fotoo/hosting/i/';

// URL of the webservice index.
#$config->base_url = 'http://misc.svn/apps/fotoo/hosting/';

// URL to where the pictures are stored. Filename is added at the end.
#$config->storage_url = 'http://misc.svn/apps/fotoo/hosting/i/';

// URL to the picture information page, hash is added at the end.
#$config->image_page_url = 'http://misc.svn/apps/fotoo/hosting/?';

// URL to the picture information page, hash is added at the end.
#$config->album_page_url = 'http://misc.svn/apps/fotoo/hosting/?a=';

// Max image size, bigger images will be resized.
#$config->max_width = 1920;

// Thumb size, used for creating thumbnails.
#$config->thumb_width = 320;

// Allow visitors to download ZIP archives of albums?
// (may put some pressure on your server, although the archives are uncompressed)
// Default: false
#$config->allow_album_zip = true;

// Title of the service.
#$config->title = 'Fotoo Image Hosting service';

// Maximum uploaded file size (in bytes). By default it's the maximum
// size allowed by the PHP configuration. See the FAQ for more
// informations.
#$config->max_file_size = 8388608;

// Allow upload of files? You can use this to restrict upload access. See
// the FAQ for more informations.

$config->allow_upload = true;

function check_upload_access()
{
    $username = 'bohwaz';
    $password = 'abcd';

    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])
        && $_SERVER['PHP_AUTH_USER'] == $username && $_SERVER['PHP_AUTH_PW'] == $password)
    {
        return true;
    }
    else
    {
        header('WWW-Authenticate: Basic realm="FotooHosting"');
        header('HTTP/1.0 401 Unauthorized');
        die("Unauthorized access forbidden!");
    }
}

// Number of images to display on each page in the pictures list.
#$config->nb_pictures_by_page = 20;
