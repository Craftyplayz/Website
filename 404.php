<?php
// Not-found page (ErrorDocument in .htaccess, and /go/ for unknown short links).
// Suggests the pages in data/site.json whose URL or title is closest to what was asked for.
$site_page = ['title' => 'Page not found'];
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
http_response_code(404);

// Each word typed in the URL is matched against every word of a page's URL, title and keywords;
// a page scores the sum of its best match per typed word (so "nethr" finds "nether", "steem" finds "steam").
$words = array_values(array_filter(preg_split('/[^a-z0-9]+/', site_norm(site_current_path())), function ($w) { return strlen($w) > 2; }));
$suggest = [];
foreach (site_data()['sections'] as $section) {
    foreach ($section['groups'] as $group) {
        foreach ($group['pages'] as $page) {
            if (isset($page['search']) && $page['search'] === false) continue;
            $pageWords = preg_split('/[^a-z0-9]+/', strtolower($page['url'] . ' ' . $page['title'] . ' ' . implode(' ', $page['keywords'] ?? [])));
            $score = 0;
            foreach ($words as $w) {
                $best = 0;
                foreach ($pageWords as $pw) {
                    if (strlen($pw) < 3) continue;
                    similar_text($w, $pw, $pct);
                    $best = max($best, $pct);
                }
                $score += $best >= 70 ? $best : 0;
            }
            if ($score > 0) $suggest[] = [$score, $page, $section['title']];
        }
    }
}
usort($suggest, function ($a, $b) { return $b[0] <=> $a[0]; });
$suggest = array_slice($suggest, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <h1 class="page-title">Page not found</h1>
        <p class="page-lede">Nothing lives at <code><?= h(site_current_path()) ?></code>. It may have moved in the site reorganisation.</p>
        <?php if ($suggest): ?>
            <h2 style="font-size: 17px; margin: 0 0 10px;">Did you mean</h2>
            <div class="hub-grid">
                <?php foreach ($suggest as [$score, $page, $sectionTitle]): ?>
                    <a class="hub-card" href="<?= h($page['url']) ?>"><b><?= h($page['title']) ?></b><span><?= h($sectionTitle) ?><?= !empty($page['description']) ? ' · ' . h($page['description']) : '' ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p style="margin-top: 20px;">Try the search box at the top (press <kbd>/</kbd>), or browse the <a href="/sitemap/">sitemap</a>.</p>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
