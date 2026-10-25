<?php
// Self-check for the Frame Guesser listing parser: php -d zend.assertions=1 _dev/scripts/frame-guesser-check.php
require __DIR__ . '/../../games/frame-guesser/lib.php';

$html = '<ul><li><a href="/videoframegame/"> Parent Directory</a></li>
<li><a href="?C=N;O=D">Name</a></li>
<li><a href="Season%2010/"> Season 10/</a></li>
<li><a href="Season%202/"> Season 2/</a></li>
<li><a href="Tom%20&amp;%20Jerry/"> Tom &amp; Jerry/</a></li>
<li><a href="0010.jpg">0010.jpg</a></li>
<li><a href="0009.JPG">0009.JPG</a></li>
<li><a href="notes.txt">notes.txt</a></li>
<li><a href="https://evil.example/x/">x</a></li></ul>';
$got = fg_parse_listing($html);
assert($got['dirs'] === ['Season 2', 'Season 10', 'Tom & Jerry'], 'natural order, decoded names, parent and sort links skipped');
assert($got['files'] === ['0009.JPG', '0010.jpg'], 'only jpgs, natural order');
assert(fg_url(['Tom & Jerry', 'Season 1'], true) === FG_BASE . 'Tom%20%26%20Jerry/Season%201/');
assert(fg_frame_parts(['title' => 'A', 'season' => null, 'episode' => 'B', 'frame' => '1.jpg']) === ['A', 'B', '1.jpg']);
echo "ok\n";
