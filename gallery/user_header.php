<?php

// Simple example of user_header.php

if (!class_exists('fotoo'))
    die('just header');

$website_url = 'http://bohwaz.net/';
$website_name = 'bohwaz.net';

echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>'.($title ? $title.' - '.$website_name : $website_name).'</title>
    <link rel="stylesheet" href="'.$css.'" type="text/css" />
    <link rel="alternate" type="application/rss+xml" title="RSS" href="'.SELF_URL.'?feed" />
</head>

<body>
<div id="mywebsite">
    <a href="'.$website_url.'">'.$website_name.'</a>
</div>';

?>