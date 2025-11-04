<?php
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'ja_JP';

// cookie, redirect
if (isset($_GET['lang'])) {
    setcookie('lang', $lang, time() + 30 * 24 * 3600, '/'); // cookie
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

//locale
putenv("LANG=$lang.UTF-8");
putenv("LANGUAGE=$lang");
putenv("LC_ALL=$lang.UTF-8");
setlocale(LC_ALL, "$lang.UTF-8");

$langRoot = realpath(__DIR__ . '/../lang');

$moFiles = glob("$langRoot/$lang/LC_MESSAGES/*.mo");
foreach ($moFiles as $moFile) {
    $domain = basename($moFile, '.mo');
    bindtextdomain($domain, $langRoot);
    bind_textdomain_codeset($domain, 'UTF-8');
}

// translate
if (!function_exists('__')) {
    function __($text, $domain = 'global') {
        return dgettext($domain, $text);
    }
}
