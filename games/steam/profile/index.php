<?php
// title and description live here until the page is added to data/site.json (requested in _dev/handoffs/requests.md)
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css'], 'title' => 'Steam Profile', 'description' => 'Level, badges, playtime and library value'];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam.php';

$profile = steam_profile();
$games = steam_games();
$progress = steam_progress(steam_achievements($games));
$items = steam_items(array_keys($games));
$badges = steam_api('IPlayerService/GetBadges/v1', ['steamid' => $steamid], 86400);
$badges = isset($badges['response']['badges']) ? $badges['response'] : null;
$names = $badges ? steam_badge_info() : [];

$minutes = array_sum(array_column($games, 'playtime_forever'));
$played = array_filter($games, function ($g) {
    return $g['playtime_forever'] > 0;
});
$fortnight = array_sum(array_column($games, 'playtime_2weeks'));
$unlocked = array_sum(array_column($progress, 'done'));

// library value at today's full UK store price; free and delisted games count as nothing
$value = 0;
$unpriced = 0;
foreach ($games as $id => $g) {
    $p = isset($items[$id]['best_purchase_option']) ? $items[$id]['best_purchase_option'] : null;
    if (!$p) {
        if (empty($items[$id]['is_free'])) $unpriced++;
        continue;
    }
    $value += (int)(isset($p['original_price_in_cents']) ? $p['original_price_in_cents'] : (isset($p['final_price_in_cents']) ? $p['final_price_in_cents'] : 0));
}

$list = $badges ? $badges['badges'] : [];
$foil = count(array_filter($list, function ($b) {
    return !empty($b['border_color']);
}));
usort($list, function ($a, $b) {
    return $b['xp'] <=> $a['xp'] ?: $b['completion_time'] <=> $a['completion_time'];
});
$years = $profile && !empty($profile['timecreated']) ? (int)floor((time() - $profile['timecreated']) / (365.25 * 86400)) : 0;
$vanity = $profile && preg_match('~/id/([^/]+)~', $profile['profileurl'], $m) ? $m[1] : '';
$state = $profile ? (int)$profile['personastate'] : 0;

// one snapshot a day, kept on disk so the history charts grow over time
$history = [];
$histFile = __DIR__ . '/cache/history.json';
if (is_file($histFile)) $history = json_decode((string)file_get_contents($histFile), true) ?: [];
if ($badges && $games && $progress && !isset($history[date('Y-m-d')])) {
    $history[date('Y-m-d')] = ['level' => $badges['player_level'], 'xp' => $badges['player_xp'], 'games' => count($games), 'hours' => round($minutes / 60), 'achievements' => $unlocked, 'badges' => count($badges['badges'])];
    @file_put_contents($histFile, json_encode($history), LOCK_EX);
}

$top = array_slice($played, 0, 10, true);
$topMax = $top ? reset($top)['playtime_forever'] : 1;

// Small line chart for one history series: [date => value]
function steam_spark($series, $label)
{
    $W = 300;
    $H = 80;
    $vals = array_values($series);
    $dates = array_keys($series);
    $lo = min($vals);
    $hi = max($vals);
    $span = $hi - $lo ?: 1;
    $n = count($vals);
    $pts = [];
    foreach ($vals as $i => $v) $pts[] = [round(4 + $i / ($n - 1) * ($W - 8), 1), round($H - 6 - ($v - $lo) / $span * ($H - 20), 1)];
    ?>
    <figure class="steam-spark">
        <figcaption><span><?= $label ?></span><b><?= number_format(end($vals)) ?></b></figcaption>
        <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= $label ?>: <?= number_format($vals[0]) ?> on <?= $dates[0] ?> to <?= number_format(end($vals)) ?> on <?= end($dates) ?>">
            <polyline points="<?= implode(' ', array_map(function ($p) {
                return $p[0] . ',' . $p[1];
            }, $pts)) ?>" />
            <?php foreach ($pts as $i => $p): ?>
                <g><title><?= date('j M Y', strtotime($dates[$i])) ?>: <?= number_format($vals[$i]) ?></title><circle class="hit" cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="10" /><circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3" /></g>
            <?php endforeach; ?>
        </svg>
        <small class="muted"><?= date('j M Y', strtotime($dates[0])) ?> – <?= date('j M Y', strtotime(end($dates))) ?></small>
    </figure>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page steam" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <?php if ($profile): ?>
            <div class="steam-profile-head">
                <img class="steam-avatar" src="<?= h($profile['avatarfull']) ?>" alt="" width="96" height="96">
                <div class="grow">
                    <h1 class="page-title"><?= h($profile['personaname']) ?></h1>
                    <p class="page-lede">Level, badges, playtime and library value.</p>
                    <p class="steam-state<?= !empty($profile['gameextrainfo']) ? ' ingame' : ($state ? ' online' : '') ?>">
                        <?= !empty($profile['gameextrainfo']) ? 'Playing ' . h($profile['gameextrainfo']) : (isset(STEAM_STATES[$state]) ? STEAM_STATES[$state] : 'Online') ?>
                    </p>
                </div>
                <a class="btn" target="_blank" rel="noopener" href="<?= h($profile['profileurl']) ?>">Open on Steam</a>
            </div>
        <?php else: ?>
            <h1 class="page-title">Steam Profile</h1>
            <p class="err">Couldn't load the profile from Steam. Try again in a minute.</p>
        <?php endif; ?>
        <?php steam_nav(''); ?>

        <?php if ($profile): ?>
            <div class="steam-stats section">
                <section class="card">
                    <h2>Profile info</h2>
                    <div class="kv">
                        <?php if ($vanity): ?><span>Steam ID</span><span><?= h($vanity) ?></span><?php endif; ?>
                        <span>SteamID64</span><span class="mono"><?= h($profile['steamid']) ?></span>
                        <span>Public profile</span><span><?= $profile['communityvisibilitystate'] == 3 ? 'Yes' : 'No' ?></span>
                        <?php if (!empty($profile['loccountrycode'])): ?><span>Country</span><span><?= h($profile['loccountrycode']) ?></span><?php endif; ?>
                        <?php if ($years): ?><span>Steam years</span><span title="Joined <?= date('j M Y', $profile['timecreated']) ?>"><?= $years ?> (since <?= date('M Y', $profile['timecreated']) ?>)</span><?php endif; ?>
                        <span>Status</span><span><?= !empty($profile['gameextrainfo']) ? 'In game' : (isset(STEAM_STATES[$state]) ? STEAM_STATES[$state] : 'Online') ?></span>
                    </div>
                </section>

                <?php if ($badges): ?>
                    <section class="card">
                        <h2>Level stats</h2>
                        <p class="steam-big"><span class="steam-level"><?= (int)$badges['player_level'] ?></span></p>
                        <div class="kv">
                            <span>Badges</span><span><?= count($badges['badges']) ?> (<?= $foil ?> foil)</span>
                            <span>Total XP</span><span><?= number_format($badges['player_xp']) ?></span>
                            <span>Next level</span><span><?= number_format($badges['player_xp_needed_to_level_up']) ?> XP to go</span>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($games): ?>
                    <section class="card">
                        <h2>Playtime stats</h2>
                        <p class="steam-big"><?= number_format(round($minutes / 60)) ?> <small>hours</small></p>
                        <div class="kv">
                            <span>Per played game</span><span><?= steam_hours(round($minutes / max(1, count($played)))) ?> average</span>
                            <span>Past 2 weeks</span><span><?= steam_hours($fortnight) ?></span>
                            <span>Most played</span><span><?= h(reset($games)['name']) ?></span>
                        </div>
                    </section>

                    <section class="card">
                        <h2>Games stats</h2>
                        <p class="steam-big"><?= count($games) ?> <small>games</small></p>
                        <div class="kv">
                            <span>Played</span><span><?= count($played) ?> (<?= round(count($played) / count($games) * 100) ?>%)</span>
                            <span>Library value</span><span title="At today's full UK store price">£<?= number_format($value / 100, 2) ?><?= $unpriced ? ' <small class="muted">+ ' . $unpriced . ' without a price</small>' : '' ?></span>
                            <?php if ($progress): ?><span>Achievements</span><span><?= number_format($unlocked) ?></span><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($top): ?>
            <section class="card section">
                <h2>Most played games <span class="n">share of <?= number_format(round($minutes / 60)) ?> h</span></h2>
                <div class="steam-bars">
                    <?php foreach ($top as $id => $g): ?>
                        <a href="/games/steam/game/?game=<?= $id ?>" title="<?= h($g['name']) ?>: <?= steam_hours($g['playtime_forever']) ?>">
                            <span class="name"><?= h($g['name']) ?></span>
                            <span class="track"><i style="width: <?= $g['playtime_forever'] / $topMax * 100 ?>%"></i></span>
                            <span class="val"><?= steam_hours($g['playtime_forever']) ?> <small><?= round($g['playtime_forever'] / $minutes * 100) ?>%</small></span>
                        </a>
                    <?php endforeach; ?>
                    <?php $rest = $minutes - array_sum(array_column($top, 'playtime_forever')); if ($rest > 0): ?>
                        <div class="muted">
                            <span class="name">Other <?= count($played) - count($top) ?> games</span>
                            <span class="track"><i class="other" style="width: <?= min(100, $rest / $topMax * 100) ?>%"></i></span>
                            <span class="val"><?= steam_hours($rest) ?> <small><?= round($rest / $minutes * 100) ?>%</small></span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($list): ?>
            <section class="card section">
                <h2>Badges <span class="n"><?= count($list) ?></span></h2>
                <div class="rows steam-badges">
                    <?php foreach ($list as $b):
                        $key = isset($b['appid']) ? 'g' . $b['appid'] . (!empty($b['border_color']) ? 'f' : '') : 'b' . $b['badgeid'];
                        $info = isset($names[$key]) ? $names[$key] : null;
                        $game = isset($b['appid']) ? (isset($games[$b['appid']]) ? $games[$b['appid']]['name'] : (isset($items[$b['appid']]) ? $items[$b['appid']]['name'] : '')) : '';
                    ?>
                        <div class="rowi">
                            <?php if ($info && $info[1]): ?><img src="<?= h($info[1]) ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><?php endif; ?>
                            <div class="grow">
                                <b><?= h($info ? $info[0] : ($game ?: 'Badge ' . $b['badgeid'])) ?><?= !empty($b['border_color']) ? ' <span class="badge gold">Foil</span>' : '' ?></b>
                                <small><?= $game ? h($game) . ' · ' : '' ?>Level <?= (int)$b['level'] ?> · <?= number_format($b['xp']) ?> XP</small>
                            </div>
                            <small title="Earned <?= date('j M Y', $b['completion_time']) ?>"><?= date('M Y', $b['completion_time']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="card section">
            <h2>History</h2>
            <?php if (count($history) < 2): ?>
                <p class="muted">Recording started <?= $history ? date('j M Y', strtotime(array_keys($history)[0])) : 'today' ?>. The charts appear once there are two days of data.</p>
            <?php else: ?>
                <div class="steam-sparks">
                    <?php foreach (['level' => 'Level', 'hours' => 'Hours played', 'games' => 'Games owned', 'achievements' => 'Achievements', 'badges' => 'Badges', 'xp' => 'XP'] as $k => $label) steam_spark(array_column(array_map(function ($d, $row) use ($k) {
                        return ['d' => $d, 'v' => $row[$k]];
                    }, array_keys($history), $history), 'v', 'd'), $label); ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php site_footer(null, false); ?>
    <script>
        document.addEventListener('error', function (e) {
            if (e.target.closest && e.target.closest('.steam-badges')) e.target.style.visibility = 'hidden';
        }, true);
    </script>
</body>

</html>
