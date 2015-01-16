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
        =>  'Photos par mot-clé',
    'Pictures in tag %TAG'
        =>  'Photos pour le mot-clé %TAG',
    'My Pictures'
        =>  'Mes photos',
    'By date'
        =>  'Par date',
    'By tags'
        =>  'Par mot-clé',
    'Search:'
        =>  'Rechercher :',
    '%B'
        =>  '%B',
    '%A %d'
        =>  '%A %d',
    '(%NB more pictures)'
        =>  '(%NB photos de plus)',
    'Tags'
        =>  'Mots-clés',
    "Other tags related to '%TAG':"
        =>  "Autres mots-clés en rapport avec '%TAG' :",
    'Download image at full size (%W x %H) - %SIZE KB'
        =>  "Télécharger l'image en grand format (%W x %H) - %SIZE Ko",
    'Comment:'
        =>  'Commentaire :',
    'Tags:'
        =>  'Mots-clés :',
    'Date:'
        =>  'Date :',
    'Share or embed:'
        =>  'Partager ou intégrer sur un site :',
    'Image only'
        =>  'Image seule',
    '%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> at %H:%M'
        =>  '%A <a href="%1">%d</a> <a href="%2">%B</a> <a href="%3">%Y</a> à %H:%M',
    'Updating database, please wait, more pictures will appear in a while...'
        =>  "Mise à jour de la base de données. "
        .   "Patientez, dans quelques instants d'autres images apparaîtront.",
    'Updating'
        =>  'Mise à jour',
    'Update done.'
        =>  'Mise à jour terminée.',
    'Picture not found'
        =>  'Photo non trouvée',
    'Back to homepage'
        =>  "Retour à la page d'accueil",
    'No picture found.'
        =>  "Aucune image n'a été trouvée.",
    'No tag found.'
        =>  "Aucun mot-clé n'a été trouvé.",
    'Previous'
        =>  "Photo précédente",
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
    'Camera:'
        =>  'Appareil photo :',
    'Exposure time:'
        =>  'Temps de pose :',
    'Aperture:'
        =>  'Ouverture :',
    'ISO speed:'
        =>  'Sensibilité ISO :',
    'Flash:'
        =>  'Flash :',
    'On'
        =>  'Activé',
    'Off'
        =>  'Désactivé',
    'Focal length:'
        =>  'Longueur focale :',
    'Original resolution:'
        =>  'Résolution originale :',
    'Statistics:'
        =>  'Statistiques',
    '%s pictures in this gallery'
        =>  '%s photos dans cette galerie',
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
    'February'  =>  'février',
    'March'     =>  'mars',
    'April'     =>  'avril',
    'May'       =>  'mai',
    'June'      =>  'juin',
    'July'      =>  'juillet',
    'August'    =>  'août',
    'September' =>  'septembre',
    'October'   =>  'octobre',
    'November'  =>  'novembre',
    'December'  =>  'décembre',
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