<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-random.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// A random tweet from @realDonaldTrump, read from a local copy of the Trump Twitter Archive (see data/build.js).
// tweets.idx holds each line's byte offset, so one tweet is read without loading the 8 MB file.
// ?t=<number> shows a specific tweet, which is what "Link to this tweet" uses.
function trump_tweet($n = null)
{
    $dir = __DIR__ . '/data';
    $idx = @fopen("$dir/tweets.idx", 'rb');
    $txt = @fopen("$dir/tweets.txt", 'rb');
    if (!$idx || !$txt) return null;
    $count = intdiv(filesize("$dir/tweets.idx"), 4);
    if ($n === null || $n < 1 || $n > $count) $n = random_int(1, $count);
    fseek($idx, ($n - 1) * 4);
    $offset = unpack('N', fread($idx, 4))[1];
    fseek($txt, $offset);
    $parts = explode("\t", rtrim((string)fgets($txt), "\r\n"), 5);
    fclose($idx);
    fclose($txt);
    if (count($parts) < 5) return null;
    [$date, $likes, $retweets, $device, $text] = $parts;
    return ['n' => $n, 'count' => $count, 'date' => $date, 'likes' => (int)$likes, 'retweets' => (int)$retweets, 'device' => $device, 'text' => str_replace('\n', "\n", $text)];
}

$tweet = trump_tweet(isset($_GET['t']) && ctype_digit((string)$_GET['t']) ? (int)$_GET['t'] : null);

// escape, keep line breaks, make links clickable
function tweet_html($text)
{
    $html = nl2br(h($text), false);
    return preg_replace('~https?://[^\s<]+?(?=[.,;:!?)]*(?:\s|<|&quot;|&#039;|$))~','<a href="$0" target="_blank" rel="noopener noreferrer nofollow">$0</a>', $html);
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
        <h1 class="page-title">Random Donald Trump Tweet</h1>
        <p class="page-lede">A random tweet from @realDonaldTrump, 2009 to 2021.</p>

        <div class="card xr-card">
            <img class="xr-mug" src="/media/memes/trump-mugshot.webp" width="160" height="160" alt="Donald Trump's mugshot">
            <?php if ($tweet): ?>
                <blockquote class="xr-line"><?= tweet_html($tweet['text']) ?></blockquote>
                <p class="xr-meta muted">
                    <time datetime="<?= h(str_replace(' ', 'T', $tweet['date'])) ?>Z"><?= h(date('jS F Y', strtotime($tweet['date']))) ?></time>
                    · <?= number_format($tweet['likes']) ?> likes · <?= number_format($tweet['retweets']) ?> retweets<?= $tweet['device'] ? ' · ' . h($tweet['device']) : '' ?>
                </p>
            <?php else: ?>
                <p class="err">Couldn't load a tweet right now. Try again in a moment.</p>
            <?php endif; ?>
        </div>

        <p class="xr-actions">
            <a class="btn primary" href="/extras/trump-quotes/">Another tweet</a>
            <?php if ($tweet): ?><a class="btn" href="/extras/trump-quotes/?t=<?= $tweet['n'] ?>">Link to this tweet</a><?php endif; ?>
        </p>
        <?php if ($tweet): ?><p class="xr-meta muted">Tweet <?= number_format($tweet['n']) ?> of <?= number_format($tweet['count']) ?>, from the <a href="https://www.thetrumparchive.com/" target="_blank" rel="noopener noreferrer">Trump Twitter Archive</a>.</p><?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
