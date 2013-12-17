<?php

// Fotoo Gallery
// Traduction française

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
    'Pictures in tag %TAG'
        =>  'Photos pour le mot-cl&eacute; %TAG',
    'My Pictures'
        =>  'Mes photos',
    'By date'
        =>  'Par date',
    'By tags'
        =>  'Par mot-cl&eacute;',
    'Search:'
        =>  'Rechercher :',
    '%B'
        =>  '%B',
    '%A %d'
        =>  '%A %d',
    '(%NB more pictures)'
        =>  '(%NB photos de plus)',
    'Tags'
        =>  'Mots-cl&eacute;s',
    "Other tags related to '%TAG':"
        =>  "Autres mots-cl&eacute;s en rapport avec '%TAG' :",
    'Download image at full size (%W x %H) - %SIZE KB'
        =>  "T&eacute;l&eacute;charger l'image en grand format (%W x %H) - %SIZE Ko",
    'Comment:'
        =>  'Commentaire :',
    'Tags:'
        =>  'Mots-cl&eacute;s :',
    'Date:'
        =>  'Date :',
    'Embed:'
        =>  'Int&eacute;grer dans un site :',
    'Embed as image:'
        =>  'Int&eacute;grer en image seule :',
    '%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> at %H:%M'
        =>  '%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> &agrave; %H:%M',
    'Updating database, please wait, more pictures will appear in a while...'
        =>  "Mise &agrave; jour de la base de donn&eacute;es. "
        .   "Patientez, dans quelques instants d'autres images appara&icirc;tront.",
    'Updating'
        =>  'Mise &agrave; jour',
    'Update done.'
        =>  'Mise &agrave; jour termin&eacute;e.',
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
    'Pause'
        =>  'Mettre en pause',
    'Restart'
        =>  'Reprendre',
    'Back'
        =>  'Retour',
    'Photo details'
        =>  'D&eacute;tails de la photo',
    'Camera maker:'
        =>  'Fabricant de l\'appareil :',
    'Camera model:'
        =>  'Mod&egrave;le de l\'appareil :',
    'Exposure:'
        =>  'Exposition :',
    'Aperture:'
        =>  'Ouverture :',
    'ISO speed:'
        =>  'Sensibilit&eacute; ISO :',
    'Flash:'
        =>  'Flash :',
    'On'
        =>  'Activ&eacute;',
    'Off'
        =>  'D&eacute;sactiv&eacute;',
    'Focal length:'
        =>  'Longueur focale :',
    'Original resolution:'
        =>  'R&eacute;solution originale :',
    '%EXPOSURE seconds'
        =>  '%EXPOSURE secondes',
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

function __($str, $mode=false, $datas=false)
{
    global $french_strings, $french_days, $french_months;

    if (isset($french_strings[$str]))
        $tr = $french_strings[$str];
    else
        $tr = $str;

    if ($mode == 'TIME')
    {
        $tr = strftime($tr, $datas);
        $tr = strtr($tr, $french_days);
        $tr = strtr($tr, $french_months);
    }
    elseif ($mode == 'REPLACE')
    {
        foreach ($datas as $key => $value)
        {
            if (is_float($value))
                $value = str_replace('.', ',', (string)$value);

            $tr = str_replace($key, $value, $tr);
        }
    }

    return $tr;
}

?>