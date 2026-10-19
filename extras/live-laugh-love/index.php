<?php
$site_page = ['components' => true, 'description' => 'Live Laugh Love alternatives', 'css' => ['/assets/css/pages/extras-random.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$letters = [
    'A' => ['Alienate', 'Aggravate', 'Assert'],
    'B' => ['Bed', 'Bath', 'Breakfast'],
    'B-2' => ['Bed', 'Bath', 'Beyond'],
    'C' => ['Catcall', 'Catcreep', 'Catalog'],
    'D' => ['Defend', 'Demand', 'Depend'],
    'D-2' => ['Destroy', 'Dick', 'December'],
    'E' => ['Expunge', 'Extract', 'Explain'],
    'F' => ['Fight', 'Flight', 'Fear'],
    'F-2' => ['Flake', 'Fake', 'Fabricate'],
    'G' => ['Gaslight', 'Gatekeep', 'Girlboss'],
    'H' => ['Hero', 'Himbo', 'Whore'],
    'I' => ['Isolate', 'Irritate', 'Ignore'],
    'J' => ['Juke', 'Jebait', 'Justify'],
    'K' => ['Kick', 'Knock', 'Kidnap'],
    'L' => ['Live', 'Laugh', 'Love'],
    'M' => ['Manipulate', 'Mansplain', 'Malewife'],
    'N' => ['No', 'Nut', 'November'],
    'O' => ['Outwit', 'Outplay', 'Outlast'],
    'P' => ['Plaigerize', 'Popularize', 'Profit'],
    'Q' => ['Queer', 'Quote', 'Queen'],
    'R' => ['Reduce', 'Reuse', 'Recycle'],
    'S' => ['Slay', 'Serve', 'Survive'],
    'T' => ['Transpose', 'Transcend', 'Transgender'],
    'U' => ['Unify', 'Update', 'Upgrade'],
    'U-2' => ['Ungovernable', 'Unmarkable', 'Unapologetic'],
    'V' => ['Veni', 'Vidi', 'Vici'],
    'W' => ['Weep', 'Whine', 'Weaponize'],
    'X' => ['Xenophobe', 'Xenosexual', 'Xenophile'],
    'Y' => ['Yikes', 'Yoink', 'Yeet'],
    'Z' => ['Zen', 'Zine', 'Zuck'],
];
$mentions = [
    '1' => ['Stop', 'Drop', 'Roll'],
    '2' => ['Fuck', 'Marry', 'Kill'],
    '3' => ['Improvise', 'Adapt', 'Overcome'],
    // '4' => ['Help', 'Help', 'Help'],
];

function lll_rows($rows)
{
    foreach ($rows as $key => $words) {
        echo '<div class="rowi"><b class="lll-k">' . h($key) . '</b><span class="grow">' . h(implode(' · ', $words)) . '</span></div>';
    }
}
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
        <h1 class="page-title">Live Laugh Love alternatives</h1>
        <p class="page-lede">Three words for every letter. Suggestions: <a href="mailto:mail@craftyplayz.com">mail@craftyplayz.com</a></p>

        <div class="card section">
            <h2>A to Z <span class="n"><?= count($letters) ?></span></h2>
            <div class="lll"><?php lll_rows($letters); ?></div>
        </div>

        <div class="card">
            <h2>Honorable mentions <span class="n"><?= count($mentions) ?></span></h2>
            <div class="lll"><?php lll_rows($mentions); ?></div>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
