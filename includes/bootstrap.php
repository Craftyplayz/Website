<?php
// Load this first on every page. It never prints anything.
//
// New-style pages set $site_page before requiring it (see _templates/page.php):
//     $site_page = ['title' => '...', 'description' => '...', 'components' => true, 'config' => true];
// 'config' => true loads the API keys from php/config.php (its markup is swallowed).

if (!defined('SITE_ROOT')) {
    define('SITE_ROOT', dirname(__DIR__));
    define('INC', __DIR__);
    require INC . '/functions.php';

    if (isset($site_page) && is_array($site_page) && !empty($site_page['config']) && is_file(SITE_ROOT . '/php/config.php')) {
        // config.php also starts the session and prints <div class="content"> for old pages
        ob_start();
        include_once SITE_ROOT . '/php/config.php';
        ob_end_clean();
    }
}
