<?php
// title and description live here until the page is added to data/site.json (requested in _dev/handoffs/requests.md)
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css'], 'title' => 'Steam Profile', 'description' => 'Level, badges, playtime and their history over time'];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam.php';

$profile = steam_profile();
$games = steam_games();
$ach = steam_achievements($games);
$progress = steam_progress($ach);
$items = steam_items(array_keys($games));
$badges = steam_api('IPlayerService/GetBadges/v1', ['steamid' => $steamid], 86400);
$badges = isset($badges['response']['badges']) ? $badges['response'] : null;
$names = $badges ? steam_badge_info() : [];

// the smaller profile endpoints, fetched together and cached a day
$extra = steam_fetch([
    'equipped' => steam_url('IPlayerService/GetProfileItemsEquipped/v1', ['steamid' => $steamid]),
    'custom' => steam_url('IPlayerService/GetProfileCustomization/v1', ['steamid' => $steamid]),
    'favorite' => steam_url('IPlayerService/GetFavoriteBadge/v1', ['steamid' => $steamid]),
    'quests' => steam_url('IPlayerService/GetCommunityBadgeProgress/v1', ['steamid' => $steamid]),
    'groups' => steam_url('ISteamUser/GetUserGroupList/v1', ['steamid' => $steamid]),
] + ($badges ? ['percentile' => steam_url('IPlayerService/GetSteamLevelDistribution/v1', ['player_level' => (int)$badges['player_level']])] : []), 86400);
$resp = function ($k) use ($extra) {
    return isset($extra[$k]['response']) ? $extra[$k]['response'] : [];
};
$equipped = array_filter($resp('equipped'));
$custom = $resp('custom');
$favorite = $resp('favorite');
$quests = isset($resp('quests')['quests']) ? $resp('quests')['quests'] : [];
$groups = isset($resp('groups')['groups']) ? $resp('groups')['groups'] : [];
$percentile = isset($resp('percentile')['player_level_percentile']) ? (float)$resp('percentile')['player_level_percentile'] : null;

$created = $profile && !empty($profile['timecreated']) ? (int)$profile['timecreated'] : 0;
$minutes = array_sum(array_column($games, 'playtime_forever'));
$played = array_filter($games, function ($g) {
    return $g['playtime_forever'] > 0;
});
$fortnight = array_filter($games, function ($g) {
    return !empty($g['playtime_2weeks']);
});
uasort($fortnight, function ($a, $b) {
    return $b['playtime_2weeks'] <=> $a['playtime_2weeks'];
});
$unlocked = array_sum(array_column($progress, 'done'));
$perfect = count(array_filter($progress, function ($p) {
    return $p['done'] == $p['total'];
}));
$started = array_filter($progress, function ($p) {
    return $p['done'] > 0;
});

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
usort($list, function ($a, $b) {
    return $b['xp'] <=> $a['xp'] ?: $b['completion_time'] <=> $a['completion_time'];
});
$foil = count(array_filter($list, function ($b) {
    return !empty($b['border_color']);
}));
$vanity = $profile && preg_match('~/id/([^/]+)~', $profile['profileurl'], $m) ? $m[1] : '';
$state = $profile ? (int)$profile['personastate'] : 0;

// ---------- history ----------
// one row a day, kept on disk (cron.php also records it hourly)
$history = steam_history($badges, $games, $progress) ?: steam_history();
ksort($history);
$series = steam_backfill($history, $ach, $badges, $created);
$metrics = ['level' => 'Level', 'xp' => 'XP', 'hours' => 'Hours played', 'games' => 'Games owned', 'achievements' => 'Achievements', 'badges' => 'Badges'];
$days = array_keys($history);
$first = $days ? $days[0] : null;
$latest = $days ? $history[end($days)] : null;
$span = $first ? max(1, (int)round((strtotime(end($days)) - strtotime($first)) / 86400)) : 0;
$missing = $first ? $span + 1 - count($days) : 0;

// change in $k over the last $back days, or null when recording doesn't reach back that far
function steam_change($history, $k, $back)
{
    $cut = date('Y-m-d', strtotime("-$back days"));
    $then = null;
    foreach ($history as $day => $row) {
        if ($day > $cut) break;
        $then = $row;
    }
    return $then && isset($then[$k]) ? end($history)[$k] - $then[$k] : null;
}

function steam_delta($n, $zero = '–')
{
    if ($n === null) return '<span class="muted">–</span>';
    if (!$n) return '<span class="muted">' . $zero . '</span>';
    return '<span class="' . ($n > 0 ? 'steam-up' : 'muted') . '">' . ($n > 0 ? '+' : '−') . number_format(abs($n)) . '</span>';
}

$stats = [];
foreach ($metrics as $k => $label) {
    if (!$latest || !isset($latest[$k])) continue;
    $best = [0, null];
    $prev = null;
    foreach ($history as $day => $row) {
        if ($prev !== null && isset($row[$k]) && $row[$k] - $prev > $best[0]) $best = [$row[$k] - $prev, $day];
        $prev = isset($row[$k]) ? $row[$k] : $prev;
    }
    $total = $latest[$k] - $history[$first][$k];
    $stats[$k] = ['now' => $latest[$k], 'd7' => steam_change($history, $k, 7), 'd30' => steam_change($history, $k, 30), 'd365' => steam_change($history, $k, 365), 'all' => $total, 'best' => $best, 'avg' => $total / $span];
}

// minutes per game per day (cron.php records it too)
$playlog = steam_playtime($games) ?: steam_playtime();
$pdays = $playlog['days'];
ksort($pdays);
$playedText = function ($row, $n = 3) use (&$gameName) {
    $out = [];
    foreach (array_slice($row, 0, $n, true) as $id => $min) $out[] = h($gameName($id) ?: 'App ' . $id) . ' ' . steam_hours($min);
    return implode(', ', $out) . (count($row) > $n ? ', +' . (count($row) - $n) . ' more' : '');
};

// next level at the XP rate of the past 30 days (or since recording began)
$forecast = null;
if ($badges && isset($stats['xp'])) {
    $gain = $stats['xp']['d30'] !== null ? [$stats['xp']['d30'], 30] : [$stats['xp']['all'], $span];
    if ($gain[0] > 0) $forecast = time() + $badges['player_xp_needed_to_level_up'] / ($gain[0] / $gain[1]) * 86400;
}

// ---------- milestones ----------
// every achievement unlock in time order, for the exact milestones and the per-year table
$unlocks = [];
foreach ($ach as $id => $l) foreach ($l as $a) if ($a['achieved']) $unlocks[] = [(int)$a['unlocktime'], $id, isset($a['name']) ? $a['name'] : $a['apiname'], $a['apiname']];
usort($unlocks, function ($a, $b) {
    return $a[0] <=> $b[0];
});
$gameName = function ($id) use ($games, $items) {
    return isset($games[$id]) ? $games[$id]['name'] : (isset($items[$id]['name']) ? $items[$id]['name'] : '');
};
$milestones = [];
if ($created) $milestones[] = [$created, 'Joined Steam', $profile ? $profile['personaname'] : '', '', $profile ? $profile['avatarmedium'] : '', 'join'];
if ($unlocks) {
    $n = count($unlocks);
    $step = $n < 1000 ? 100 : ($n < 5000 ? 500 : 1000);
    $marks = array_filter(array_merge([1], range($step, $n, $step)), function ($i) use ($n) {
        return $i <= $n;
    });
    // achievement icons for the milestone unlocks only, from the (week-cached) schemas of their games
    $mschema = steam_schema(array_unique(array_map(function ($i) use ($unlocks) {
        return $unlocks[$i - 1][1];
    }, $marks)));
    foreach ($marks as $i) {
        $u = $unlocks[$i - 1];
        $icon = isset($mschema[$u[1]][$u[3]]['icon']) ? $mschema[$u[1]][$u[3]]['icon'] : '';
        $milestones[] = [$u[0], number_format($i) . ($i == 1 ? 'st' : 'th') . ' achievement', $u[2] . ' · ' . $gameName($u[1]), '', $icon, 'ach'];
    }
}
// level-ups: exact on recorded days, approximate (rebuilt from badge dates) before that
if (isset($series['level'])) {
    $prev = null;
    foreach ($series['level'] as $s) {
        if ($prev !== null && $s[1] > $prev) $milestones[] = [$s[0], 'Reached level ' . $s[1], '', $s[2] ? 'approx.' : '', '', 'level', $s[1]];
        $prev = $s[1];
    }
}
foreach ($progress as $id => $p) {
    if ($p['done'] == $p['total'] && $p['last']) $milestones[] = [$p['last'], '100% ' . $gameName($id), $p['total'] . ' achievements', '', steam_art($id, $items, 'small_capsule'), 'perfect'];
}
usort($milestones, function ($a, $b) {
    return $b[0] <=> $a[0];
});

// Tiny line of the last 30 points of a steam_backfill() series, for the metric buttons
function steam_mini($series)
{
    $pts = array_slice($series, -30);
    if (count($pts) < 2) return '';
    $vals = array_column($pts, 1);
    $lo = min($vals);
    $span = max($vals) - $lo ?: 1;
    $xy = [];
    foreach ($vals as $i => $v) $xy[] = round($i / (count($vals) - 1) * 120, 1) . ',' . round(30 - ($v - $lo) / $span * 28, 1);
    return '<svg class="steam-mini" viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="' . implode(' ', $xy) . '" /></svg>';
}

// ---------- by year ----------
$years = [];
if ($created) for ($y = (int)date('Y'); $y >= (int)date('Y', $created); $y--) $years[$y] = ['ach' => 0, 'badges' => 0, 'xp' => 0, 'last' => 0, 'perfect' => 0];
foreach ($unlocks as $u) if (isset($years[(int)date('Y', $u[0])])) $years[(int)date('Y', $u[0])]['ach']++;
foreach ($list as $b) if (isset($years[(int)date('Y', $b['completion_time'])])) {
    $years[(int)date('Y', $b['completion_time'])]['badges']++;
    $years[(int)date('Y', $b['completion_time'])]['xp'] += $b['xp'];
}
foreach ($games as $g) if (!empty($g['rtime_last_played']) && isset($years[(int)date('Y', $g['rtime_last_played'])])) $years[(int)date('Y', $g['rtime_last_played'])]['last']++;
foreach ($progress as $p) if ($p['done'] == $p['total'] && $p['last'] && isset($years[(int)date('Y', $p['last'])])) $years[(int)date('Y', $p['last'])]['perfect']++;
$yearMax = $years ? max(1, max(array_column($years, 'ach'))) : 1;

// ---------- playtime ----------
$platforms = [];
foreach (['windows' => 'Windows', 'linux' => 'Linux (incl. Steam Deck)', 'deck' => 'Steam Deck', 'mac' => 'macOS'] as $k => $label) {
    $platforms[$label] = array_sum(array_column($games, 'playtime_' . $k . '_forever'));
}
$platforms['Offline'] = array_sum(array_column($games, 'playtime_disconnected'));
$buckets = ['Never played' => 0, 'Under 1 h' => 0, '1–10 h' => 0, '10–100 h' => 0, '100–1,000 h' => 0, '1,000 h +' => 0];
foreach ($games as $g) {
    $h = $g['playtime_forever'] / 60;
    $buckets[array_keys($buckets)[$g['playtime_forever'] == 0 ? 0 : ($h < 1 ? 1 : ($h < 10 ? 2 : ($h < 100 ? 3 : ($h < 1000 ? 4 : 5))))]]++;
}
$top = array_slice($played, 0, 15, true);
$topMax = $top ? reset($top)['playtime_forever'] : 1;
$lastPlayed = $games ? max(array_map(function ($g) {
    return isset($g['rtime_last_played']) ? (int)$g['rtime_last_played'] : 0;
}, $games)) : 0;
$accountDays = $created ? max(1, (time() - $created) / 86400) : 0;

// ---------- profile items ----------
const STEAM_SHOWCASES = [1 => 'Rarest achievements', 2 => 'Game collector', 3 => 'Items', 4 => 'Trade offer', 5 => 'Badges', 6 => 'Favourite game', 7 => 'Screenshots', 8 => 'Custom info box', 9 => 'Favourite group', 10 => 'Review', 11 => 'Workshop item', 12 => 'My workshop', 13 => 'Artwork', 14 => 'Videos', 15 => 'Guides', 16 => 'My guides', 17 => 'Achievements', 18 => 'Greenlight', 19 => 'My Greenlight', 20 => 'Salien', 21 => 'Loyalty reactions', 22 => 'Featured artwork', 23 => 'Completionist', 24 => 'Steam Replay'];
$itemLabels = ['profile_background' => 'Profile background', 'mini_profile_background' => 'Mini profile background', 'avatar_frame' => 'Avatar frame', 'animated_avatar' => 'Animated avatar', 'profile_modifier' => 'Profile modifier', 'steam_deck_keyboard_skin' => 'Steam Deck keyboard'];
$theme = isset($custom['profile_theme']['theme_id']) ? $custom['profile_theme']['theme_id'] : '';

// raw data for the last section: everything above as Steam sent it, minus anything that could locate me
$raw = $profile ? array_diff_key($profile, array_flip(['gameserverip', 'gameserversteamid', 'lobbysteamid'])) : null;

// Played each day: estimated days from backfill.json up to the first per-game reading, then the recorded ones
$bf = steam_backfill_file();
$estPlayed = [];
if ($bf && !empty($bf['played'])) foreach ($bf['played'] as $d => $row) if (!$pdays || $d < array_key_first($pdays)) $estPlayed[$d] = $row;
$playDays = [];
$playNames = [];
foreach ([$estPlayed, $pdays] as $set) foreach ($set as $d => $row) {
    $playDays[] = [$d, array_map(null, array_keys($row), array_values($row))];
    foreach ($row as $id => $min) if (!isset($playNames[$id])) $playNames[$id] = [$gameName($id) ?: 'App ' . $id, steam_art($id, $items, 'small_capsule')];
}
$estTo = $estPlayed ? ($pdays ? array_key_first($pdays) : date('Y-m-d')) : null;

// long estimated runs go to the browser as one start time + values instead of thousands of [time, value, kind] points
$chartSeries = [];
foreach ($series as $k => $pts) {
    $lead = 0;
    while ($lead < count($pts) && $pts[$lead][2] !== null && $pts[$lead][2] === $pts[0][2] && ($lead == 0 || abs($pts[$lead][0] - $pts[$lead - 1][0] - 86400) <= 3600)) $lead++;
    $chartSeries[$k] = $lead > 60 ? ['t0' => $pts[0][0], 'kind' => $pts[0][2], 'vals' => array_column(array_slice($pts, 0, $lead), 1), 'pts' => array_slice($pts, $lead)] : $pts;
}

$chartData = ['series' => $chartSeries, 'played' => ['days' => $playDays, 'names' => $playNames, 'estTo' => $estTo], 'hourly' => array_intersect_key(steam_hourly(), ['rows' => 1, 'play' => 1]), 'labels' => $metrics, 'history' => $history, 'recorded' => $first ? strtotime($first) : null, 'since' => $first ? date('j M Y', strtotime($first)) : ''];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://shared.akamai.steamstatic.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page steam" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <?php steam_hero(['title' => 'Steam Profile', 'lede' => 'My Steam profile and how it has grown, day by day.', 'current' => '/games/steam/profile/']); ?>
        <?php if (!$profile): ?><p class="err">Couldn't load the profile from Steam. Try again in a minute.</p><?php endif; ?>

        <?php if ($badges || $games): ?>
            <!-- each number is also a button that shows its history in the chart below -->
            <div class="steam-stats-row steam-metrics">
                <?php
                $tiles = [
                    'level' => [$badges ? $badges['player_level'] : null, 'Level'],
                    'xp' => [$badges ? $badges['player_xp'] : null, 'XP'],
                    'badges' => [$badges ? count($badges['badges']) : null, 'Badges'],
                    'games' => [$games ? count($games) : null, 'Games'],
                    'hours' => [$games ? round($minutes / 60) : null, 'Hours played'],
                    'achievements' => [$progress ? $unlocked : null, 'Achievements'],
                ];
                foreach ($tiles as $k => $t): if ($t[0] === null) continue; $d = isset($stats[$k]) ? $stats[$k]['d30'] : null; $chart = isset($series[$k]) && count($series[$k]) > 1; ?>
                    <<?= $chart ? 'button type="button" data-k="' . $k . '" title="Show ' . strtolower($t[1]) . ' in the History chart"' : 'div' ?> class="steam-stat">
                        <b><?= number_format($t[0]) ?></b><span><?= $t[1] ?></span>
                        <small><?= $d ? steam_delta($d) . ' in 30 days' : '&nbsp;' ?></small>
                        <?= $chart ? steam_mini($series[$k]) : '' ?>
                    </<?= $chart ? 'button' : 'div' ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <nav class="jump steam-jump" aria-label="Sections">
            <a href="#history">History</a>
            <?php if ($playDays): ?><a href="#played">Played each day</a><?php endif; ?>
            <?php if ($stats): ?><a href="#log">Daily log</a><?php endif; ?>
            <?php if ($badges || $profile): ?><a href="#profile">Level &amp; profile</a><?php endif; ?>
            <?php if ($milestones): ?><a href="#milestones">Milestones</a><?php endif; ?>
            <?php if ($years): ?><a href="#years">By year</a><?php endif; ?>
            <?php if ($games): ?><a href="#playtime">Playtime</a><?php endif; ?>
            <?php if ($list): ?><a href="#badges">Badges</a><?php endif; ?>
            <?php if ($equipped || $custom): ?><a href="#items">Items</a><?php endif; ?>
            <a href="#raw">Raw data</a>
        </nav>

        <!-- ---------- history ---------- -->
        <section class="card section" id="history">
            <h2>History<?php if ($first): ?> <span class="n">recorded daily since <?= date('j M Y', strtotime($first)) ?> · <?= count($days) ?> day<?= count($days) == 1 ? '' : 's' ?></span><?php endif; ?></h2>
            <?php if (!isset($series['level']) || count($series['level']) < 2): ?>
                <p class="muted">Recording started <?= $first ? date('j M Y', strtotime($first)) : 'today' ?>. The chart appears once there are two days of data.</p>
            <?php else: ?>
                <div class="steam-tools steam-picks">
                    <label><span>Show</span>
                        <select class="field" id="metric">
                            <?php $pick = isset($series['hours']) && count($series['hours']) > 1 ? 'hours' : null; foreach ($metrics as $k => $label): if (!isset($series[$k]) || count($series[$k]) < 2) continue; $pick = $pick ?: $k; ?>
                                <option value="<?= $k ?>"<?= $k === $pick ? ' selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Time range</span>
                        <select class="field" id="range">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="365">1 year</option>
                            <option value="0">All time</option>
                        </select>
                    </label>
                </div>
                <div class="steam-chart-box" id="chart"><p class="note">Drawing the chart…</p></div>
                <p class="steam-chart-note" id="chart-note"></p>
                <p class="steam-legend steam-kinds muted"><span data-kind="rec"><i></i>Recorded</span><span data-kind="rebuilt"><i class="rebuilt"></i>Rebuilt from Steam's own dates</span><span data-kind="est"><i class="est"></i>Estimated</span><span data-kind="guess"><i class="guess"></i>Guess</span></p>
            <?php endif; ?>

            <?php if ($stats): ?>
                <div class="steam-rec">
                    <span><b><?= count($days) ?></b> of <?= $span + 1 ?> days recorded<?= $missing ? ' · ' . $missing . ' missed' : '' ?></span>
                    <span>First reading <b><?= date('j M Y', strtotime($first)) ?></b></span>
                    <span>Latest <b><?= date('j M Y', strtotime(end($days))) ?></b></span>
                    <?php if ($forecast): ?><span title="At the XP rate of the past <?= $stats['xp']['d30'] !== null ? '30 days' : $span . ' days' ?>">Next level about <b><?= date('j M Y', (int)$forecast) ?></b></span><?php endif; ?>
                </div>
                <details class="steam-more-box">
                    <summary>All changes: 7 days, 30 days, 1 year, per day, best day</summary>
                    <div class="steam-scroll">
                        <table class="steam-tbl">
                            <thead><tr><th></th><th>Now</th><th>7 days</th><th>30 days</th><th>1 year</th><th>Since <?= date('j M Y', strtotime($first)) ?></th><th>Per day</th><th>Best day</th></tr></thead>
                            <tbody>
                                <?php foreach ($stats as $k => $s): ?>
                                    <tr>
                                        <th><?= $metrics[$k] ?></th>
                                        <td><?= number_format($s['now']) ?></td>
                                        <td><?= steam_delta($s['d7'], '0') ?></td>
                                        <td><?= steam_delta($s['d30'], '0') ?></td>
                                        <td><?= steam_delta($s['d365'], '0') ?></td>
                                        <td><?= steam_delta($s['all'], '0') ?></td>
                                        <td><?= $s['avg'] ? number_format($s['avg'], $s['avg'] < 10 ? 2 : 0) : '<span class="muted">0</span>' ?></td>
                                        <td><?= $s['best'][1] ? '+' . number_format($s['best'][0]) . ' <small class="muted">' . date('j M Y', strtotime($s['best'][1])) . '</small>' : '<span class="muted">–</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </section>

        <!-- ---------- played each day ---------- -->
        <?php if ($playDays): ?>
            <section class="card section" id="played">
                <h2>Played each day <span class="n" id="played-total"></span></h2>
                <div class="steam-tools steam-picks">
                    <label><span>Time range</span>
                        <select class="field" id="played-range">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="365">1 year</option>
                            <option value="0">All time</option>
                        </select>
                    </label>
                </div>
                <div class="steam-days" id="played-bars" role="list" aria-label="Hours played per day"><p class="note">Drawing the chart…</p></div>
                <div class="steam-days-axis muted" id="played-axis"></div>
                <h3 id="played-top-h">Most played</h3>
                <div class="steam-ranks" id="played-top"></div>
                <p class="muted steam-foot">The 7-day view shows each hour, from the hourly readings. Recorded days come from the change in each game's total playtime between daily readings; a day after a missed reading also holds the missed days.<?= $estTo ? ' Days before ' . date('j M Y', strtotime($estTo)) . ' (paler) are estimated from purchase dates, achievement unlocks and last-played dates; the day-to-day detail is simulated.' : '' ?></p>
            </section>
        <?php endif; ?>

        <!-- ---------- daily log ---------- -->
        <?php if ($stats): ?>
            <section class="card section" id="log">
                <h2>Daily log <span class="n"><?= count($days) ?> readings, newest first</span></h2>
                <div class="steam-scroll">
                    <table class="steam-tbl steam-log">
                        <thead><tr><th>Day</th><?php foreach ($stats as $k => $s): ?><th><?= $metrics[$k] ?></th><?php endforeach; ?><?php if ($pdays): ?><th>Played</th><?php endif; ?></tr></thead>
                        <tbody>
                            <?php
                            $rows = [];
                            $prev = null;
                            foreach ($history as $day => $row) {
                                $rows[] = [$day, $row, $prev];
                                $prev = $row;
                            }
                            foreach (array_reverse($rows) as $i => $r): ?>
                                <tr<?= $i >= 14 ? ' hidden' : '' ?>>
                                    <th><time datetime="<?= $r[0] ?>"><?= date('D j M Y', strtotime($r[0])) ?></time></th>
                                    <?php foreach ($stats as $k => $s): $v = isset($r[1][$k]) ? $r[1][$k] : null; $d = $r[2] && $v !== null && isset($r[2][$k]) ? $v - $r[2][$k] : null; ?>
                                        <td><?= $v === null ? '–' : number_format($v) ?><?= $d ? ' <small class="' . ($d > 0 ? 'steam-up' : 'muted') . '">' . ($d > 0 ? '+' : '−') . number_format(abs($d)) . '</small>' : '' ?></td>
                                    <?php endforeach; ?>
                                    <?php if ($pdays): ?><td class="steam-played"><?= !empty($pdays[$r[0]]) ? $playedText($pdays[$r[0]]) : '<span class="muted">–</span>' ?></td><?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="steam-tools steam-log-tools">
                    <?php if (count($rows) > 14): ?><button type="button" class="btn small" id="log-all">Show all <?= count($rows) ?> days</button><?php endif; ?>
                    <button type="button" class="btn small" id="csv">Download CSV</button>
                </div>
            </section>
        <?php endif; ?>

        <!-- ---------- level, profile, games ---------- -->
        <?php if ($badges || $profile || $games): ?>
            <div class="steam-panels section" id="profile">
                <?php if ($badges): $into = $badges['player_xp'] - $badges['player_xp_needed_current_level']; $cost = $into + $badges['player_xp_needed_to_level_up']; ?>
                    <section class="card steam-levelcard">
                        <h2>Level</h2>
                        <div class="steam-levelbig">
                            <?= str_replace('<b>' . round($into / max(1, $cost) * 100) . '<small>%</small></b>', '<b>' . (int)$badges['player_level'] . '</b>', steam_ring($into / max(1, $cost) * 100, 'Level ' . (int)$badges['player_level'] . ', ' . round($into / max(1, $cost) * 100) . '% of the way to the next')) ?>
                            <div>
                                <b><?= number_format($into) ?> / <?= number_format($cost) ?> XP</b>
                                <small class="muted"><?= number_format($badges['player_xp_needed_to_level_up']) ?> XP to level <?= (int)$badges['player_level'] + 1 ?><?= $forecast ? ', about ' . date('j M Y', (int)$forecast) : '' ?></small>
                            </div>
                        </div>
                        <?php if ($percentile !== null): ?>
                            <div class="steam-gauge" title="Higher level than <?= round($percentile, 2) ?>% of Steam accounts">
                                <span class="steam-gauge-bar"><i style="left: <?= $percentile ?>%"></i></span>
                                <small><span class="muted">Higher than</span> <b><?= round($percentile, 1) ?>%</b> <span class="muted">of Steam · top <?= round(100 - $percentile, 1) ?>%</span></small>
                            </div>
                        <?php endif; ?>
                        <div class="kv">
                            <span>Total XP</span><span><?= number_format($badges['player_xp']) ?></span>
                            <span>Badges</span><span><?= count($badges['badges']) ?> (<?= $foil ?> foil)</span>
                            <span>Badge XP</span><span><?= number_format(array_sum(array_column($badges['badges'], 'xp'))) ?></span>
                            <?php if ($quests): ?><span>Community tasks</span><span><?= count(array_filter(array_column($quests, 'completed'))) ?> / <?= count($quests) ?> done</span><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($profile): ?>
                    <section class="card steam-idcard">
                        <?php if (!empty($equipped['mini_profile_background']['image_large'])): ?><img class="steam-idcard-bg" src="<?= h(STEAM_IMG_COMMUNITY . $equipped['mini_profile_background']['image_large']) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                        <div class="steam-idcard-head">
                            <img src="<?= h($profile['avatarfull']) ?>" alt="" width="72" height="72" loading="lazy">
                            <div>
                                <h2><?= h($profile['personaname']) ?></h2>
                                <small class="steam-dot<?= !empty($profile['gameextrainfo']) ? ' ingame' : ($state ? ' online' : '') ?>"><?= !empty($profile['gameextrainfo']) ? 'Playing ' . h($profile['gameextrainfo']) : (isset(STEAM_STATES[$state]) ? STEAM_STATES[$state] : 'Online') ?></small>
                            </div>
                        </div>
                        <div class="kv">
                            <?php if (!empty($profile['realname'])): ?><span>Real name</span><span><?= h($profile['realname']) ?></span><?php endif; ?>
                            <?php if ($vanity): ?><span>Custom URL</span><span class="mono"><?= h($vanity) ?></span><?php endif; ?>
                            <span>SteamID64</span><span class="mono"><?= h($profile['steamid']) ?></span>
                            <span>Account ID</span><span class="mono">[U:1:<?= (int)$profile['steamid'] - 76561197960265728 ?>]</span>
                            <?php if ($created): ?>
                                <span>Joined</span><span><?= date('j M Y', $created) ?> <small class="muted">(<?= floor($accountDays / 365.25) ?> y <?= floor(fmod($accountDays, 365.25) / 30.44) ?> m)</small></span>
                            <?php endif; ?>
                            <?php if (!empty($profile['lastlogoff'])): ?><span>Last logged off</span><span><?= steam_time($profile['lastlogoff']) ?></span><?php endif; ?>
                            <span>Profile</span><span><?= $profile['communityvisibilitystate'] == 3 ? 'Public' : 'Private' ?><?= empty($profile['profilestate']) ? ', not set up' : '' ?></span>
                            <?php if (isset($profile['commentpermission'])): ?><span>Comments</span><span><?= $profile['commentpermission'] == 1 ? 'Open' : 'Friends only' ?></span><?php endif; ?>
                            <?php if (!empty($profile['loccountrycode'])): ?><span>Country</span><span><?= h($profile['loccountrycode']) ?><?= !empty($profile['locstatecode']) ? ', ' . h($profile['locstatecode']) : '' ?></span><?php endif; ?>
                            <?php if ($groups): ?><span>Groups</span><span><?= count($groups) ?></span><?php endif; ?>
                            <?php if ($theme): ?><span>Profile theme</span><span><?= h($theme) ?></span><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($games): $avgDone = $started ? round(array_sum(array_column($started, 'pct')) / count($started)) : 0; ?>
                    <section class="card">
                        <h2>Games</h2>
                        <div class="steam-levelbig">
                            <?= steam_ring(count($played) / count($games) * 100, round(count($played) / count($games) * 100) . '% of games played') ?>
                            <div>
                                <b><?= count($played) ?> of <?= count($games) ?> played</b>
                                <small class="muted"><?= count($games) - count($played) ?> never started</small>
                            </div>
                        </div>
                        <div class="kv">
                            <span>Library value</span><span title="At today's full UK store price">£<?= number_format($value / 100, 2) ?><?= $unpriced ? ' <small class="muted">+ ' . $unpriced . ' unpriced</small>' : '' ?></span>
                            <?php if ($progress): ?>
                                <span>Achievements</span><span><?= number_format($unlocked) ?> of <?= number_format(array_sum(array_column($progress, 'total'))) ?></span>
                                <span>100% games</span><span><?= $perfect ?></span>
                                <span>Avg. completion</span><span title="Over the <?= count($started) ?> games with at least one achievement"><?= $avgDone ?>%</span>
                            <?php endif; ?>
                            <span>With Workshop</span><span><?= count(array_filter(array_column($games, 'has_workshop'))) ?></span>
                            <span>With DLC</span><span><?= count(array_filter(array_column($games, 'has_dlc'))) ?></span>
                            <span>With Market items</span><span><?= count(array_filter(array_column($games, 'has_market'))) ?></span>
                            <span>With leaderboards</span><span><?= count(array_filter(array_column($games, 'has_leaderboards'))) ?></span>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ---------- milestones ---------- -->
        <?php if ($milestones): ?>
            <section class="card section" id="milestones">
                <h2>Milestones <span class="n"><?= count($milestones) ?></span></h2>
                <ol class="steam-timeline">
                    <?php foreach ($milestones as $i => $m): ?>
                        <li class="k-<?= $m[5] ?>"<?= $i >= 12 ? ' hidden' : '' ?>>
                            <span class="steam-tl-icon">
                                <?php if ($m[5] === 'level'): ?><span class="steam-level"><?= (int)$m[6] ?></span>
                                <?php elseif ($m[4]): ?><img src="<?= h($m[4]) ?>" alt="" width="<?= $m[5] === 'perfect' ? 92 : 40 ?>" height="<?= $m[5] === 'perfect' ? 35 : 40 ?>" loading="lazy" decoding="async"><?php endif; ?>
                            </span>
                            <span class="steam-tl-text">
                                <b><?= h($m[1]) ?></b>
                                <?php if ($m[2]): ?><small><?= h($m[2]) ?></small><?php endif; ?>
                            </span>
                            <time datetime="<?= date('c', $m[0]) ?>" title="<?= date('j M Y, H:i', $m[0]) ?>"><?= $m[3] ? '~' . date('M Y', $m[0]) : date('j M Y', $m[0]) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($milestones) > 12): ?><button type="button" class="btn small" data-more="#milestones li">Show all <?= count($milestones) ?></button><?php endif; ?>
                <p class="muted steam-foot">Level-ups marked ~ are worked out from badge dates, not recorded.</p>
            </section>
        <?php endif; ?>

        <!-- ---------- by year ---------- -->
        <?php if ($years): $chrono = array_reverse($years, true); ?>
            <section class="card section" id="years">
                <h2>By year <span class="n">achievements unlocked</span></h2>
                <div class="steam-cols-chart" role="list">
                    <?php foreach ($chrono as $y => $r): ?>
                        <div role="listitem" tabindex="0" aria-label="<?= $y ?>: <?= number_format($r['ach']) ?> achievements, <?= $r['perfect'] ?> 100% games, <?= $r['badges'] ?> badges">
                            <span class="steam-col-val"><?= $r['ach'] ? number_format($r['ach']) : '' ?></span>
                            <span class="steam-col"><i style="height: <?= $r['ach'] / $yearMax * 100 ?>%"></i></span>
                            <span class="steam-col-y"><?= $y ?></span>
                            <span class="steam-col-tip"><b><?= $y ?></b><?= number_format($r['ach']) ?> achievements<br><?= $r['perfect'] ?> games at 100%<br><?= $r['badges'] ?> badges · <?= number_format($r['xp']) ?> XP<br><?= $r['last'] ?> games last played</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <details class="steam-more-box">
                    <summary>Show as a table</summary>
                    <div class="steam-scroll">
                        <table class="steam-tbl">
                            <thead><tr><th>Year</th><th>Achievements</th><th>100% games</th><th>Badges</th><th>Badge XP</th><th title="Games whose last play was that year">Last played</th></tr></thead>
                            <tbody>
                                <?php foreach ($years as $y => $r): ?>
                                    <tr><th><?= $y ?></th><td><?= number_format($r['ach']) ?></td><td><?= $r['perfect'] ?></td><td><?= $r['badges'] ?></td><td><?= number_format($r['xp']) ?></td><td><?= $r['last'] ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <p class="muted steam-foot">Badges count in the year they reached their current level.</p>
            </section>
        <?php endif; ?>

        <!-- ---------- playtime ---------- -->
        <?php if ($games): ?>
            <section class="card section" id="playtime">
                <h2>Playtime <span class="n"><?= number_format(round($minutes / 60)) ?> h<?= $accountDays ? ' · ' . steam_hours(round($minutes / $accountDays)) . ' a day since joining' : '' ?></span></h2>
                <?php $split = array_filter(['Windows' => $platforms['Windows'], 'Linux / SteamOS' => $platforms['Linux (incl. Steam Deck)'], 'macOS' => $platforms['macOS']]); $splitTotal = array_sum($split); ?>
                <?php if ($splitTotal): ?>
                    <div class="steam-split" role="img" aria-label="<?= h(implode(', ', array_map(function ($k, $v) use ($splitTotal) {
                        return $k . ' ' . round($v / $splitTotal * 100) . '%';
                    }, array_keys($split), $split))) ?>">
                        <?php $i = 0; foreach ($split as $name => $min): ?><i class="s<?= $i++ ?>" style="width: <?= $min / $splitTotal * 100 ?>%" title="<?= $name ?>: <?= steam_hours($min) ?>"></i><?php endforeach; ?>
                    </div>
                    <div class="steam-legend">
                        <?php $i = 0; foreach ($split as $name => $min): ?><span><i class="s<?= $i++ ?>"></i><?= $name ?> <b><?= steam_hours($min) ?></b></span><?php endforeach; ?>
                        <?php if ($platforms['Steam Deck']): ?><span class="muted">Steam Deck <?= steam_hours($platforms['Steam Deck']) ?></span><?php endif; ?>
                        <?php if ($platforms['Offline']): ?><span class="muted">Offline <?= steam_hours($platforms['Offline']) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="steam-two">
                    <div>
                        <h3>Games by hours played</h3>
                        <?php $bmax = max(1, max($buckets)); ?>
                        <div class="steam-hist">
                            <?php foreach ($buckets as $label => $n): ?>
                                <div><span><?= $label ?></span><span class="steam-rank-bar"><i style="width: <?= $n / $bmax * 100 ?>%"></i></span><b><?= $n ?></b></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <h3>Averages</h3>
                        <div class="kv">
                            <span>Per played game</span><span><?= steam_hours(round($minutes / max(1, count($played)))) ?></span>
                            <span>Past 2 weeks</span><span><?= steam_hours(array_sum(array_column($games, 'playtime_2weeks'))) ?></span>
                            <?php if ($lastPlayed): ?><span>Last played</span><span><?= steam_time($lastPlayed) ?></span><?php endif; ?>
                            <?php foreach ($platforms as $label => $min): if (!$min) continue; ?>
                                <span><?= $label ?></span><span><?= steam_hours($min) ?> <small class="muted"><?= round($min / max(1, $minutes) * 100, 1) ?>%</small></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($fortnight): ?>
                    <h3>Past 2 weeks <span class="muted"><?= steam_hours(array_sum(array_column($fortnight, 'playtime_2weeks'))) ?></span></h3>
                    <div class="steam-banners steam-banners-sm">
                        <?php foreach ($fortnight as $id => $g): ?>
                            <a class="steam-banner" href="/games/steam/game/?game=<?= $id ?>">
                                <img src="<?= h(steam_art($id, $items, 'library_hero')) ?>" data-alt="<?= h(steam_art($id, $items)) ?>" alt="" width="1920" height="620" loading="lazy" decoding="async">
                                <span class="steam-banner-text"><b><?= h($g['name']) ?></b><small><?= steam_hours($g['playtime_2weeks']) ?> in 2 weeks</small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($top): ?>
                    <h3>Most played <span class="muted">share of <?= number_format(round($minutes / 60)) ?> h</span></h3>
                    <div class="steam-ranks">
                        <?php $r = 0; foreach ($top as $id => $g): ?>
                            <a class="steam-rank" href="/games/steam/game/?game=<?= $id ?>">
                                <span class="steam-rank-n"><?= ++$r ?></span>
                                <img src="<?= h(steam_art($id, $items, 'small_capsule')) ?>" alt="" width="231" height="87" loading="lazy" decoding="async">
                                <span class="grow"><b><?= h($g['name']) ?></b><span class="steam-rank-bar"><i style="width: <?= $g['playtime_forever'] / $topMax * 100 ?>%"></i></span></span>
                                <span class="steam-rank-val"><?= steam_hours($g['playtime_forever']) ?><small><?= round($g['playtime_forever'] / $minutes * 100, 1) ?>%</small></span>
                            </a>
                        <?php endforeach; ?>
                        <?php $rest = $minutes - array_sum(array_column($top, 'playtime_forever')); if ($rest > 0): ?>
                            <div class="steam-rank muted">
                                <span class="steam-rank-n"></span>
                                <span></span>
                                <span class="grow"><b>Other <?= count($played) - count($top) ?> games</b><span class="steam-rank-bar"><i class="other" style="width: <?= min(100, $rest / $topMax * 100) ?>%"></i></span></span>
                                <span class="steam-rank-val"><?= steam_hours($rest) ?><small><?= round($rest / $minutes * 100, 1) ?>%</small></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- ---------- badges ---------- -->
        <?php if ($list): ?>
            <h2 class="steam-h" id="badges">Badges <span class="n"><?= count($list) ?> · <?= number_format(array_sum(array_column($list, 'xp'))) ?> XP</span></h2>
            <div class="steam-badgegrid section">
                <?php foreach ($list as $b):
                    $key = isset($b['appid']) ? 'g' . $b['appid'] . (!empty($b['border_color']) ? 'f' : '') : 'b' . $b['badgeid'];
                    $info = isset($names[$key]) ? $names[$key] : null;
                    $game = isset($b['appid']) ? $gameName($b['appid']) : '';
                    $fav = !empty($favorite['has_favorite_badge']) && $favorite['badgeid'] == $b['badgeid'] && (isset($favorite['appid']) ? $favorite['appid'] : 0) == (isset($b['appid']) ? $b['appid'] : 0);
                ?>
                    <div class="steam-badge<?= !empty($b['border_color']) ? ' foil' : '' ?><?= $fav ? ' fav' : '' ?>">
                        <?php if ($fav): ?><span class="steam-badge-fav">Favourite</span><?php endif; ?>
                        <?php if ($info && $info[1]): ?><img src="<?= h($info[1]) ?>" alt="" width="80" height="80" loading="lazy" decoding="async"><?php else: ?><span class="steam-ach-noicon"></span><?php endif; ?>
                        <b><?= h($info ? $info[0] : ($game ?: 'Badge ' . $b['badgeid'])) ?></b>
                        <?php if ($game): ?><small class="muted"><?= h($game) ?><?= !empty($b['border_color']) ? ' · foil' : '' ?></small><?php endif; ?>
                        <span class="steam-badge-stats"><span>Lv <?= number_format($b['level']) ?></span><span><?= number_format($b['xp']) ?> XP</span></span>
                        <small class="muted"><?= !empty($b['scarcity']) ? '<span title="People who have this badge">' . number_format($b['scarcity']) . ' owners</span> · ' : '' ?><span title="Earned <?= date('j M Y, H:i', $b['completion_time']) ?>"><?= date('M Y', $b['completion_time']) ?></span></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ---------- items & showcases ---------- -->
        <?php if ($equipped): ?>
            <h2 class="steam-h" id="items">Equipped items <span class="n"><?= count($equipped) ?></span></h2>
            <div class="steam-shots section">
                <?php foreach ($equipped as $k => $it): $img = !empty($it['image_large']) ? $it['image_large'] : (isset($it['image_small']) ? $it['image_small'] : ''); $small = in_array($k, ['avatar_frame', 'steam_deck_keyboard_skin', 'animated_avatar']); ?>
                    <a class="steam-shot<?= $small ? ' contain' : '' ?>" href="<?= h($img ? STEAM_IMG_COMMUNITY . $img : '#') ?>" target="_blank" rel="noopener">
                        <?php if ($img): ?><img src="<?= h(STEAM_IMG_COMMUNITY . $img) ?>" alt="" width="320" height="200" loading="lazy" decoding="async"><?php endif; ?>
                        <span class="steam-banner-text">
                            <b><?= h(isset($it['item_title']) ? $it['item_title'] : (isset($it['name']) ? $it['name'] : '')) ?></b>
                            <small><?= isset($itemLabels[$k]) ? $itemLabels[$k] : h($k) ?><?= !empty($it['appid']) && $gameName($it['appid']) ? ' · ' . h($gameName($it['appid'])) : '' ?><?= !empty($it['movie_mp4']) ? ' · animated' : '' ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($custom['customizations'])): ?>
            <section class="card section"<?= $equipped ? '' : ' id="items"' ?>>
                <h2>Showcases <span class="n"><?= count($custom['customizations']) ?><?= isset($custom['slots_available']) ? ' of ' . (int)$custom['slots_available'] . ' slots' : '' ?><?= $theme ? ' · ' . h($theme) . ' theme' : '' ?></span></h2>
                <div class="steam-showcases">
                    <?php foreach ($custom['customizations'] as $c):
                        $ids = [];
                        foreach (isset($c['slots']) ? $c['slots'] : [] as $s) if (!empty($s['appid']) && !in_array($s['appid'], STEAM_HIDE)) $ids[$s['appid']] = isset($ids[$s['appid']]) ? $ids[$s['appid']] + 1 : 1;
                    ?>
                        <div class="steam-showcase">
                            <b><?= isset(STEAM_SHOWCASES[$c['customization_type']]) ? STEAM_SHOWCASES[$c['customization_type']] : 'Showcase ' . (int)$c['customization_type'] ?><?= empty($c['active']) ? ' <small class="muted">(off)</small>' : '' ?></b>
                            <?php if ($ids): ?>
                                <div class="steam-showcase-games">
                                    <?php foreach ($ids as $id => $n): ?>
                                        <a href="/games/steam/game/?game=<?= (int)$id ?>" title="<?= h($gameName($id) ?: 'App ' . $id) ?><?= $n > 1 ? ' (' . $n . ' achievements)' : '' ?>"><img src="<?= h(steam_art($id, $items, 'small_capsule')) ?>" alt="<?= h($gameName($id) ?: 'App ' . $id) ?>" width="184" height="69" loading="lazy" decoding="async"><?php if ($n > 1): ?><span>×<?= $n ?></span><?php endif; ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <small class="muted">Filled in by Steam</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- ---------- raw ---------- -->
        <section class="card section" id="raw">
            <h2>Raw data</h2>
            <p class="muted steam-foot">What the Steam Web API returned, and the recorded history, exactly as stored.</p>
            <?php foreach (['GetPlayerSummaries' => $raw, 'GetBadges' => $badges, 'GetSteamLevelDistribution' => $resp('percentile'), 'GetFavoriteBadge' => $favorite, 'GetCommunityBadgeProgress' => $resp('quests'), 'GetUserGroupList' => $resp('groups'), 'GetProfileItemsEquipped' => $equipped, 'GetProfileCustomization' => $custom, 'history.json' => $history, 'playtime.json' => $pdays ? $playlog : null] as $label => $d): if (!$d) continue; ?>
                <details class="steam-raw">
                    <summary><?= $label ?></summary>
                    <pre class="tree"><?= h(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
            <?php endforeach; ?>
        </section>
    </main>

    <?php site_footer(null, false); ?>
    <script type="application/json" id="steam-data"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="<?= h(asset('/games/steam/steam.js')) ?>" defer></script>
    <script src="<?= h(asset('/games/steam/profile/profile.js')) ?>" defer></script>
</body>

</html>
