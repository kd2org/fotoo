<?php
/*
    Fotoo Gallery v2
    Copyright 2004-2008 BohwaZ - http://dev.kd2.org/
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

// Generate a resized copy of images for small view (600x600)
// WARNING GENERATING IMAGES IS REALLY SLOW AND IT MAY KILL YOU WEBSERVER!
//define('GEN_SMALL', true);

// Shortcut tags in album and pictures comments
// eg. if you type wp:Belgium in your comment tag, it will make a link to wikipedia
// You can add anything you want
$f->html_tags = array(
    'wp:fr' =>  'http://fr.wikipedia.org/wiki/{KEYWORD}',
    'wp'    =>  'http://en.wikipedia.org/wiki/{KEYWORD}',
);

// Strings translation
$french_strings = array(
    'Pictures for %A %d %B %Y'
        =>  'Photos prises le %A %d %B %Y',
    'Pictures for %B %Y'
        =>  'Photos prises en %B %Y',
    'Pictures for %Y'
        =>  'Photos prises en %Y',
    'Pictures by date'
        =>  'Photos par date',
    'Pictures by tag'
        =>  'Photos par mot-cl&eacute;',
    'Pictures in tag %s'
        =>  'Photos pour le mot-cl&eacute; %s',
    'My Pictures'
        =>  'Mes photos',
    'By date'
        =>  'Par date',
    'By tags'
        =>  'Par mot-cl&eacute;',
    '%B'
        =>  '%B',
    '%A %d'
        =>  '%A %d',
    '(%s more pictures)'
        =>  '(%s photos de plus)',
    'Tags'
        =>  'Mots-cl&eacute;s',
    "Other tags related to '%s':"
        =>  "Autres mots-cl&eacute;s en rapport avec '%s' :",
    'Download image at original size (%s x %s)'
        =>  "T&eacute;l&eacute;charger l'image au format original (%s x %s)",
    'Comment:'
        =>  'Commentaire :',
    'Tags:'
        =>  'Mots-cl&eacute;s :',
    'Date:'
        =>  'Date :',
    '%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> at %H:%M'
        =>  '%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> &agrave; %H:%M',
    'Updating database, please wait, more pictures will appear in a while...'
        =>  "Mise &agrave; jour de la base de donn&eacute;es. "
        .   "Patientez, dans quelques instants d'autres images appara&icirc;tront.",
    'Updating'
        =>  'Mise &agrave; jour',
    'Picture not found'
        =>  'Photo non trouv&eacute;e',
    'Back to homepage'
        =>  "Retour &agrave; la page d'accueil",
    'No picture found.'
        =>  "Aucune image n'a &eacute;t&eacute; trouv&eacute;e.",
    'No tag found.'
        =>  "Aucun mot-cl&eacute; n'a &eacute;t&eacute; trouv&eacute;.",
    'Previous'
        =>  "Photo pr&eacute;c&eacute;dente",
    'Next'
        =>  "Photo suivante",
    'Slideshow'
        =>  'Diaporama',
);

// Days of the week translations
$french_days = array(
    'Monday'    =>  'lundi',
    'Tuesday'   =>  'mardi',
    'Wednesday' =>  'mercredi',
    'Thursday'  =>  'jeudi',
    'Friday'    =>  'vendredi',
    'Saturday'  =>  'samedi',
    'Sunday'    =>  'dimanche',
);

// Months of the year translations
$french_months = array(
    'January'   =>  'janvier',
    'February'  =>  'f&eacute;vrier',
    'March'     =>  'mars',
    'April'     =>  'avril',
    'May'       =>  'mai',
    'June'      =>  'juin',
    'July'      =>  'juillet',
    'August'    =>  'ao&ucirc;t',
    'September' =>  'septembre',
    'October'   =>  'octobre',
    'November'  =>  'novembre',
    'December'  =>  'd&eacute;cembre',
);

function __($str, $time=false)
{
    global $french_strings, $french_days, $french_months;

    if (isset($french_strings[$str]))
        $tr = $french_strings[$str];
    else
        $tr = $str;

    if ($time)
    {
        $tr = strftime($tr, $time);
        $tr = strtr($tr, $french_days);
        $tr = strtr($tr, $french_months);
    }

    return $tr;
}

?>