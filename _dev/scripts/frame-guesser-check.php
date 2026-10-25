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
// The host's generated listings: links have a class first, files are linked twice, a stylesheet <link> is not a folder
$built = '<link rel="stylesheet" href="/assets/css/global.css"><p class="crumbs"><a href="/">Home</a></p>
<a class="rowi link" href="Ep%2010%20(Fire%20and%20Blood)/"><span class="name">Ep 10 (Fire and Blood)</span></a>
<a class="rowi link" href="Ep%2004%20(Cripples%2C%20Bastards%2C%20and%20Broken%20Things)/">x</a>
<a class="name" href="0002.jpg">0002.jpg</a><a class="btn small" href="0002.jpg" download>Download</a>
<a class="name" href="0001.jpg">0001.jpg</a>';
$got = fg_parse_listing($built);
assert($got['dirs'] === ['Ep 04 (Cripples, Bastards, and Broken Things)', 'Ep 10 (Fire and Blood)'], 'generated listing folders');
assert($got['files'] === ['0001.jpg', '0002.jpg'], 'generated listing files, duplicates dropped');
assert(fg_url(['Game of Thrones', 'Season 1', 'Ep 01 (Winter Is Coming)', '0001.jpg']) === FG_BASE . 'Game%20of%20Thrones/Season%201/Ep%2001%20%28Winter%20Is%20Coming%29/0001.jpg');
assert(fg_url(['Tom & Jerry', 'Season 1'], true) === FG_BASE . 'Tom%20%26%20Jerry/Season%201/');
assert(fg_frame_parts(['title' => 'A', 'season' => null, 'episode' => 'B', 'frame' => '1.jpg']) === ['A', 'B', '1.jpg']);
echo "ok\n";
