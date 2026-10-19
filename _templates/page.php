<?php
// Template for a new page. Copy it next to where the page should live, then:
//   1. fill in $site_page below
//   2. add the page to data/site.json (title, url, description) so it shows in the menu and search
//   3. run through the checklist in _dev/handoffs/building-pages.md (the full build guide)
//
// $site_page options (all optional):
//   'title'       => shown in the tab; leave it out so it comes from data/site.json (set it only for pages not in site.json)
//   'description' => meta description; same rule, comes from data/site.json
//   'components'  => true loads /assets/css/components.css (.btn, .card, .drawer, ...)
//   'ui'          => true loads /assets/js/ui.js (window.UI helpers; use inside DOMContentLoaded)
//   'css'         => ['/assets/css/pages/<section>-<name>.css'] page stylesheet
//   'config'      => true loads the API keys from php/config.php ($steamkey, $hypixelkey, ...); never print a key
$site_page = [
    'components' => true,
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Page Title</h1>
        <p class="page-lede">One muted line saying what this page does.</p>

        <div class="grid">
            <section class="card">
                <h2>Section</h2>
                <p>Content goes here.</p>
            </section>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
