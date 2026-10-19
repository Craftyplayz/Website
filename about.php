<?php
$site_page = ['components' => true];
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
        <h1 class="page-title">About Me</h1>
        <p class="page-lede">Who runs this site.</p>

        <div class="grid">
            <section class="card">
                <h2>Hobbies</h2>
                <p>Programming, mostly creating websites like this one.</p>
            </section>
            <section class="card">
                <h2>Background</h2>
                <div class="kv">
                    <span>Age</span><span>22</span>
                    <span>Born</span><span>NSW, Australia (lived there for 16 years)</span>
                    <span>Now</span><span>Living in the UK since 2019</span>
                </div>
            </section>
            <section class="card">
                <h2>Interests</h2>
                <p><b>Gaming.</b> My main games are Minecraft, Rocket League and Factorio. See my <a href="/games/steam/">Steam Stats</a>.</p>
                <p><b>Books.</b> I'm currently reading the Heroes of Olympus series, and I've read Harry Potter over 135 times. See <a href="/books/">Books I've Read</a>.</p>
            </section>
            <section class="card">
                <h2>Contact me</h2>
                <p>Please don't. I don't like people.</p>
            </section>
        </div>
    </main>

    <?php site_footer(); ?>
</body>

</html>
