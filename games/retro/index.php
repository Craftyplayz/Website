<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam/steam.php'; // steam_json() caches any JSON API

$ra = 'https://retroachievements.org';
$profile = steam_json($ra . '/API/API_GetUserProfile.php?u=CraftyPlayz&y=' . $retrokey);
$progress = steam_json($ra . '/API/API_GetUserCompletionProgress.php?u=CraftyPlayz&y=' . $retrokey);
$last = !empty($profile['LastGameID']) ? steam_json($ra . '/API/API_GetGame.php?g=' . (int)$profile['LastGameID'] . '&y=' . $retrokey, 86400) : null;

$awards = ['beaten-softcore' => 'Beaten', 'beaten-hardcore' => 'Beaten (hardcore)', 'completed' => 'Completed', 'mastered' => 'Mastered'];
$beaten = $playing = [];
foreach (isset($progress['Results']) ? $progress['Results'] : [] as $g) {
    if (isset($awards[$g['HighestAwardKind']])) $beaten[] = $g;
    else $playing[] = $g;
}

function retro_game($g, $ra, $awards)
{
    $won = isset($awards[$g['HighestAwardKind']]);
    $ts = strtotime($won ? $g['HighestAwardDate'] : $g['MostRecentAwardedDate']);
    $pct = $g['MaxPossible'] > 0 ? round($g['NumAwarded'] / $g['MaxPossible'] * 100) : 0;
?>
    <article class="card retro-game">
        <img src="<?= h($ra . $g['ImageIcon']) ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
        <div class="grow">
            <h3 title="<?= h($g['Title']) ?>"><?= h($g['Title']) ?></h3>
            <div class="badges">
                <span class="badge"><?= h($g['ConsoleName']) ?></span>
                <?php if ($won): ?><span class="badge g"><?= $awards[$g['HighestAwardKind']] ?></span><?php endif; ?>
            </div>
            <div class="kv">
                <?php if ($ts): ?>
                    <span><?= $won ? 'Beaten' : 'Last unlock' ?></span><span title="<?= date('j M Y, H:i', $ts) ?>"><?= steam_ago($ts) ?></span>
                <?php endif; ?>
                <span>Achievements</span><span><?= (int)$g['NumAwarded'] ?> / <?= (int)$g['MaxPossible'] ?> (<?= $pct ?>%)</span>
            </div>
            <div class="progress"><i style="width: <?= $pct ?>%"></i></div>
        </div>
    </article>
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

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Retro Achievements</h1>
        <p class="page-lede">Retro game achievements from <a href="<?= $ra ?>/user/CraftyPlayz">RetroAchievements</a>.</p>

        <?php if (!$progress): ?>
            <p class="err">Couldn't load games from RetroAchievements. Try again in a minute.</p>
        <?php endif; ?>

        <?php if (!empty($last['Title'])): ?>
            <section class="card retro-game section">
                <img src="<?= h($ra . $last['ImageIcon']) ?>" alt="" width="64" height="64" decoding="async">
                <div class="grow">
                    <h2>Last played <span class="n"><?= h($last['ConsoleName']) ?></span></h2>
                    <h3><?= h($last['Title']) ?></h3>
                    <?php if (!empty($profile['RichPresenceMsg'])): ?><p class="muted"><?= h($profile['RichPresenceMsg']) ?></p><?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php foreach ([['Beaten', $beaten], ['In progress', $playing]] as [$title, $list]): if (!$list) continue; ?>
            <h2 class="retro-h"><?= $title ?> <span class="muted"><?= count($list) ?></span></h2>
            <div class="retro-grid section">
                <?php foreach ($list as $g) retro_game($g, $ra, $awards); ?>
            </div>
        <?php endforeach; ?>
    </main>

    <?php site_footer(); ?>
</body>

</html>
