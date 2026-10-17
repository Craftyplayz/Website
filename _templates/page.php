<?php
// Template for a new page. Copy it next to where the page should live, then:
//   1. fill in $site_page below
//   2. add the page to data/site.json (title, url, description) so it shows in the menu and search
//   3. run through the checklist in plan.html > Design guide
//
// $site_page options (all optional):
//   'title'       => shown in the tab; defaults to the title in data/site.json
//   'description' => meta description; defaults to the one in data/site.json
//   'components'  => true loads /assets/css/components.css (.btn, .card, .drawer, ...)
//   'ui'          => true loads /assets/js/ui.js (window.UI helpers)
//   'css'         => ['/path/to/page.css'] extra stylesheets
//   'config'      => true loads the API keys from php/config.php ($steamkey, $hypixelkey, ...)
$site_page = [
    'title' => 'Page title',
    'description' => 'One line about what this page does.',
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
        <h1 class="page-title"><?= h($site_page['title']) ?></h1>
        <p class="page-lede"><?= h($site_page['description']) ?></p>

        <div class="card">
            <h2>Section</h2>
            <p>Content goes here.</p>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
