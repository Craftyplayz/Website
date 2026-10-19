<?php
// Guess Who. Every folder next to this file that holds images is a game:
//   games/guesswho/<folder>/<Name>.webp  ->  a card called "Name" in the game "<Folder>".
// Add a folder of images and it shows up here; nothing else to edit.
// Folders starting with _ or . are ignored, so are folders without images (og/ and atla/ are old-URL redirects).
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-guesswho.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

function gw_images($dir)
{
    $files = array_filter(scandir($dir), function ($f) {
        return preg_match('/\.(webp|jpe?g|png|gif|avif)$/i', $f);
    });
    natcasesort($files);
    return array_values($files);
}

function gw_title($name)
{
    return ucwords(str_replace(['-', '_'], ' ', $name));
}

$games = [];
foreach (scandir(__DIR__) as $d) {
    if ($d[0] === '.' || $d[0] === '_' || !is_dir(__DIR__ . '/' . $d)) continue;
    $imgs = gw_images(__DIR__ . '/' . $d);
    if ($imgs) $games[$d] = $imgs;
}

// ?game= must be one of the folders found above, so it can't point anywhere else
$slug = isset($_GET['game']) ? (string)$_GET['game'] : '';
$game = isset($games[$slug]) ? $games[$slug] : null;
if ($game) $site_page['title'] = 'Guess Who: ' . gw_title($slug);

function gw_src($slug, $file)
{
    return '/games/guesswho/' . rawurlencode($slug) . '/' . rawurlencode($file);
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

        <?php if (!$game): ?>
            <h1 class="page-title">Guess Who</h1>
            <p class="page-lede">Pick a board. Each player opens the same one on their own screen and gets a secret character.</p>
            <?php if ($slug !== ''): ?><p class="err section">There's no Guess Who game called "<?= h($slug) ?>". Pick one below.</p><?php endif; ?>

            <div class="gw-games">
                <?php foreach ($games as $s => $imgs): ?>
                    <a class="card gw-game" href="?game=<?= rawurlencode($s) ?>">
                        <span class="gw-cover">
                            <?php foreach (array_slice($imgs, 0, 4) as $img): ?><img src="<?= h(gw_src($s, $img)) ?>" alt="" width="100" height="100" loading="lazy" decoding="async"><?php endforeach; ?>
                        </span>
                        <b><?= h(gw_title($s)) ?></b>
                        <small><?= count($imgs) ?> characters</small>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <h1 class="page-title">Guess Who: <?= h(gw_title($slug)) ?></h1>
            <p class="page-lede">Ask yes/no questions to find the other player's character. Click a face to flip it down, click again to flip it back. <a href="/games/guesswho/">All boards</a></p>

            <div class="gw-play">
                <aside class="card gw-mine">
                    <h2>Your character</h2>
                    <?php $pick = $game[array_rand($game)]; ?>
                    <figure id="mine"><img src="<?= h(gw_src($slug, $pick)) ?>" alt="" width="200" height="200"><figcaption><?= h(ucfirst(pathinfo($pick, PATHINFO_FILENAME))) ?></figcaption></figure>
                    <div class="gw-actions">
                        <button class="btn small" type="button" id="hide" aria-pressed="false">Hide</button>
                        <button class="btn small" type="button" id="newcard">New character</button>
                        <button class="btn small" type="button" id="reset">Flip all up</button>
                    </div>
                </aside>

                <section class="card gw-board-card">
                    <h2>Board <span class="n"><span id="left"><?= count($game) ?></span> of <?= count($game) ?> left</span></h2>
                    <div class="gw-board" id="board">
                        <?php foreach ($game as $i => $img): $name = ucfirst(pathinfo($img, PATHINFO_FILENAME)); ?>
                            <button class="gw-card" type="button" aria-pressed="false" data-src="<?= h(gw_src($slug, $img)) ?>" data-name="<?= h($name) ?>">
                                <img src="<?= h(gw_src($slug, $img)) ?>" alt="" width="120" height="120" <?= $i > 11 ? 'loading="lazy" ' : '' ?>decoding="async">
                                <span><?= h($name) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </main>

    <?php site_footer(); ?>
    <?php if ($game): ?>
        <script>
            (function () {
                var board = document.getElementById('board');
                var cards = Array.prototype.slice.call(board.querySelectorAll('.gw-card'));
                var mine = document.getElementById('mine');
                var left = document.getElementById('left');

                function count() {
                    left.textContent = board.querySelectorAll('.gw-card[aria-pressed="false"]').length;
                }
                function pick() {
                    var c = cards[Math.floor(Math.random() * cards.length)];
                    mine.querySelector('img').src = c.dataset.src;
                    mine.querySelector('figcaption').textContent = c.dataset.name;
                }

                board.addEventListener('click', function (e) {
                    var c = e.target.closest('.gw-card');
                    if (!c) return;
                    c.setAttribute('aria-pressed', c.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                    count();
                });
                document.getElementById('reset').addEventListener('click', function () {
                    cards.forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
                    count();
                });
                document.getElementById('newcard').addEventListener('click', pick);
                document.getElementById('hide').addEventListener('click', function () {
                    var on = this.getAttribute('aria-pressed') !== 'true';
                    this.setAttribute('aria-pressed', on);
                    this.textContent = on ? 'Show' : 'Hide';
                    mine.classList.toggle('hidden', on);
                });
            })();
        </script>
    <?php endif; ?>
</body>

</html>
