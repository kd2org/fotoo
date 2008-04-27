<?php

if (phpversion() < 5)
{
    die("Argh you don't have PHP 5 ! Please install it right now !");
}

if (!class_exists('SQLiteDatabase'))
{
    die("You don't have SQLite native extension, please install it.");
}

if (extension_loaded('imlib'))
    $img = 'IMLib (fast)';
elseif (extension_loaded('imagick') && class_exists('Imagick'))
    $img = 'Imagick >= 2.0 (quite fast)';
elseif (extension_loaded('imagick') && function_exists('imagick_readimage'))
    $img = 'Imagick < 2.0 (fast?)';
elseif (function_exists('imagecopyresampled') && extension_loaded('gd'))
    $img = 'GD >= 2.0 (slow)';
else
    $img = 'None';

echo "<p>Image resize engine : {$img}</p>";

?>
