<?php

$src = file_get_contents(__DIR__ . '/src/index.php');

preg_match_all('!require(?:_once)?\s+[\'"]([^"\']+)[\'"];!', $src, $matches, PREG_SET_ORDER);

foreach ($matches as $match)
{
    $replace = file_get_contents(__DIR__ . '/src/' . $match[1]);

    if (preg_match('!\.php$!', $match[1]))
    {
        $replace = strtr($replace, array('<?php' => '', '?>' => ''));
    }
    else
    {
        $replace = '?>' . $replace . '<?php';
    }

    $src = str_replace($match[0], $replace, $src);
}

preg_match_all('!/\*src:(.*)\*/!', $src, $matches, PREG_SET_ORDER);

foreach ($matches as $match)
{
    $replace = '/' . str_repeat('*', 70) . "\n";
    $replace.= file_get_contents(__DIR__ . '/src/' . $match[1]);
    $replace.= "\n" . str_repeat('*', 70) . "/";
    $src = str_replace($match[0], $replace, $src);
}

echo $src;

?>