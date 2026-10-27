<?php
// Saves one tick from the private Minecraft checklists into the public page's done.json.
//   POST {"list": "farms"|"collection", "key": "<item key>", "done": true|false}
// done.json is gitignored, so deploys never overwrite it.
header('Content-Type: text/plain');

function fail(string $msg): void
{
    http_response_code(400);
    exit($msg);
}

// the custom header can't be sent cross-site without CORS, so other sites can't post here with the saved login
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_MC_TICK'] ?? '') !== '1') fail('Bad request');
$lists = ['farms' => 'farms', 'collection' => 'collection'];
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in) || !isset($lists[$in['list'] ?? ''])) fail('Unknown list');
$key = (string)($in['key'] ?? '');
if (!preg_match('~^[a-z0-9_-]{1,120}$~', $key)) fail('Bad key');

$file = $_SERVER['DOCUMENT_ROOT'] . '/minecraft/projects/' . $lists[$in['list']] . '/done.json';
$fh = fopen($file, 'c+');
if (!$fh || !flock($fh, LOCK_EX)) fail('Could not open the progress file');
$done = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];
if (!empty($in['done'])) $done[$key] = 1;
else unset($done[$key]);
ksort($done);
ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode($done ?: new stdClass()));
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);
exit('ok');
