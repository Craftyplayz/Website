<?php
// Saves a meme GIF template from the editor (index.php) into gifs/templates/<group>/<name>/.
//   POST ?t=group/name&frame=N  body = PNG of frame N
//   POST ?t=group/name          body = template JSON, sent last; frames past the end are deleted
header('Content-Type: text/plain');

function fail(string $msg): void
{
    http_response_code(400);
    exit($msg);
}

// the custom header can't be sent cross-site without CORS, so other sites can't post here with the saved login
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_GIFS'] ?? '') !== '1') fail('Bad request');
$t = (string)($_GET['t'] ?? '');
if (!preg_match('~^[a-z0-9-]{1,40}/[a-z0-9-]{1,40}$~', $t)) fail('Group and name may only use a-z, 0-9 and -');
$dir = $_SERVER['DOCUMENT_ROOT'] . '/gifs/templates/' . $t;
$body = (string)file_get_contents('php://input');

if (isset($_GET['frame'])) {
    $i = (int)$_GET['frame'];
    if ($i < 0 || $i > 999 || strncmp($body, "\x89PNG\r\n\x1a\n", 8) !== 0 || !@getimagesizefromstring($body)) fail('Bad frame');
    if (!is_dir("$dir/frames") && !mkdir("$dir/frames", 0775, true)) fail('Could not create the folder');
    if (file_put_contents(sprintf('%s/frames/%03d.png', $dir, $i), $body) === false) fail('Could not write the frame');
    exit('ok');
}

// rebuild the template from known fields only
$in = json_decode($body, true);
if (!is_array($in)) fail('Bad JSON');
$w = (int)($in['w'] ?? 0);
$h = (int)($in['h'] ?? 0);
$delays = array_map('intval', (array)($in['delays'] ?? []));
if ($w < 1 || $h < 1 || $w > 2000 || $h > 2000) fail('Bad size');
if (!$delays || count($delays) > 1000) fail('Bad frame count');
foreach ($delays as $i => $d) {
    if (!is_file(sprintf('%s/frames/%03d.png', $dir, $i))) fail("Frame $i is missing");
}
$fonts = array_map('basename', glob($_SERVER['DOCUMENT_ROOT'] . '/gifs/fonts/*.ttf') ?: []);
$hex = fn($c) => preg_match('/^#[0-9a-f]{6}$/i', (string)$c) ? strtolower($c) : '#ffffff';
$boxes = [];
foreach (array_slice((array)($in['boxes'] ?? []), 0, 10) as $b) {
    $keys = [];
    foreach (array_slice((array)($b['keys'] ?? []), 0, 1000) as $k) {
        $f = max(0, min(count($delays) - 1, (int)($k['f'] ?? 0)));
        $keys[$f] = ['f' => $f, 'x' => round((float)($k['x'] ?? 0), 1), 'y' => round((float)($k['y'] ?? 0), 1)] + (!empty($k['hide']) ? ['hide' => true] : []);
    }
    if (!$keys) fail('Every text needs at least one position');
    ksort($keys);
    $boxes[] = [
        'text' => mb_substr((string)($b['text'] ?? ''), 0, 200),
        'font' => in_array($b['font'] ?? '', $fonts, true) ? $b['font'] : ($fonts[0] ?? ''),
        'size' => max(4, min(300, (int)($b['size'] ?? 32))),
        'color' => $hex($b['color'] ?? ''),
        'stroke' => $hex($b['stroke'] ?? '#000000'),
        'strokeW' => max(0, min(20, (int)($b['strokeW'] ?? 0))),
        'maxW' => max(0, min($w, (int)($b['maxW'] ?? 0))),
        'motion' => ($b['motion'] ?? '') === 'smooth' ? 'smooth' : 'step',
        'keys' => array_values($keys),
    ];
}

$tpl = ['w' => $w, 'h' => $h, 'delays' => $delays, 'boxes' => $boxes];
if (file_put_contents("$dir/template.json", json_encode($tpl, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) fail('Could not write the template');
foreach (glob("$dir/frames/*.png") ?: [] as $png) {
    if ((int)basename($png, '.png') >= count($delays)) @unlink($png);
}
exit('ok');
