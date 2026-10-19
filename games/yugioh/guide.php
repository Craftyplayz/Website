<?php
// Shared by the 4 Yu-Gi-Oh guide pages: page top (title, lede, guide links) and the winner/loser cards.
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-yugioh.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

function ygo_top($lede)
{
    $here = site_norm(site_current_path());
    echo site_breadcrumbs(site_current_path());
    echo '<h1 class="page-title">' . h(site_page_title(site_current_path())) . '</h1>';
    echo '<p class="page-lede">' . h($lede) . '</p>';
    echo '<nav class="jump" aria-label="Guide pages">';
    foreach (['/games/yugioh/' => '1. Setup', '/games/yugioh/next-packs/' => '2. Next packs', '/games/yugioh/sets/' => 'Sets', '/games/yugioh/extra-rules/' => 'Extra rules'] as $url => $label) {
        echo '<a href="' . $url . '"' . (site_norm($url) === $here ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    }
    echo '</nav>';
}

function ygo_trade()
{
?>
    <p class="ygo-note">The winner of the duel picks a card from the loser's list of cards and adds it to their deck, even if the loser wasn't playing that card.</p>
    <div class="ygo-pair section">
        <section class="card">
            <h2>Winner</h2>
            <ol>
                <li>In the binders section under "All Cards", search for the name of the card you picked.</li>
                <li>Click <b>+</b> to add the card to your deck.</li>
            </ol>
        </section>
        <section class="card">
            <h2>Loser</h2>
            <ol>
                <li>In the binders section under "Your Binders", search for the card the winner chose.</li>
                <li>Click <b>−</b> to remove that card from your deck.</li>
            </ol>
        </section>
    </div>
<?php
}
