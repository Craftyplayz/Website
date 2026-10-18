<?php
// XML sitemap for search engines, built from data/site.json (served as /sitemap.xml via a rewrite in .htaccess; not named sitemap.xml.php, MultiViews would grab it)
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$base = 'https://www.craftyplayz.com';
$urls = ['/'];
foreach (site_data()['sections'] as $section) {
    $urls[] = $section['url'];
    foreach ($section['groups'] as $group) {
        foreach ($group['pages'] as $page) {
            if (!isset($page['search']) || $page['search'] !== false) $urls[] = $page['url'];
        }
    }
}
foreach (site_data()['links'] as $link) $urls[] = $link['url'];
$urls[] = '/sitemap/';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_unique($urls) as $u) echo '  <url><loc>' . h($base . $u) . '</loc></url>' . "\n";
echo '</urlset>' . "\n";
