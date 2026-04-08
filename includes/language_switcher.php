<?php
// Prevent multiple inclusions
if (defined('LANGUAGE_SWITCHER_INCLUDED')) {
    return;
}
define('LANGUAGE_SWITCHER_INCLUDED', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$valid_langs = ['en', 'om'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) && in_array($_SESSION['lang'], $valid_langs) ? $_SESSION['lang'] : 'om';

if (!function_exists('buildLanguageUrl')) {
    function buildLanguageUrl($lang, $current_query, $current_page)
    {
        $query = array_merge($current_query, ['lang' => $lang]);
        return $current_page . '?' . http_build_query($query);
    }
}

$current_query = $_GET;
$current_page = basename($_SERVER['PHP_SELF']);
?>