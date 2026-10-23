<?php
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <style>
        /* 6 7: the two numbers weigh up and down like the hand gesture; transform only, off for reduced motion (global.css) */
        .sx { display: flex; justify-content: center; gap: clamp(24px, 12vw, 160px); padding: clamp(24px, 8vw, 72px) 16px; }
        .sx-n { display: flex; flex-direction: column; align-items: center; font-size: clamp(96px, 28vw, 240px); font-weight: 700; line-height: 1; animation: sx-bob .6s ease-in-out infinite alternate; }
        .sx-n + .sx-n { animation-delay: -.6s; }
        .sx-n span { font-size: .4em; }
        .sx-n + .sx-n span { transform: scaleX(-1); }
        @keyframes sx-bob { from { transform: translateY(-.12em); } to { transform: translateY(.12em); } }
    </style>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">6 7</h1>
        <p class="page-lede">Six seven. That's it. That's the page.</p>

        <section class="card sx" aria-label="6 7">
            <div class="sx-n">6<span aria-hidden="true">🫴</span></div>
            <div class="sx-n">7<span aria-hidden="true">🫴</span></div>
        </section>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
