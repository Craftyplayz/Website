<?php
// Meme GIFs: /gifs/<group>/<name>/<text1>/<text2>.gif (rewrite rule in .htaccess).
// Templates are made in /private/gifs/ and live in gifs/templates/<group>/<name>/ (template.json + frames/NNN.png).
// Box text: plain words always show; {thing} is filled by the next URL part (blank if missing);
// {place = Ba Sing Se} falls back to "Ba Sing Se". In the URL "_" is a space and "-" skips a part (use its fallback).

// {name} or {name = fallback}. Mirrors SLOT in private/gifs/editor.js.
const GIF_SLOT = '/\{\s*([^}=]*?)\s*(?:=\s*([^}]*?)\s*)?\}/';

function gif_404(): void
{
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('No such GIF');
}

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!preg_match('~^/gifs/([a-z0-9-]+)/([a-z0-9-]+)((?:/[^/]*)*)\.gif$~', $path, $m)) gif_404();
$dir = __DIR__ . "/templates/$m[1]/$m[2]";
$tpl = json_decode((string)@file_get_contents("$dir/template.json"), true);
if (!is_array($tpl)) gif_404();

$texts = [];
$parts = $m[3] === '' ? [] : array_slice(explode('/', substr($m[3], 1)), 0, 20);
foreach (gif_slots($tpl['boxes']) as $i => $name) {
    if (isset($parts[$i]) && $parts[$i] !== '-') $texts[$name] = mb_substr(str_replace('_', ' ', rawurldecode($parts[$i])), 0, 100);
}

$cache = sys_get_temp_dir() . '/craftyplayz-gifs';
$file = $cache . '/' . md5("$m[1]/$m[2]|" . filemtime("$dir/template.json") . '|' . json_encode($texts)) . '.gif';
$gif = is_file($file) ? file_get_contents($file) : false;
if ($gif === false) {
    $gif = gif_render($dir, $tpl, $texts);
    // shortcut: old renders are only pruned now and then by age; add a size cap if the cache ever fills the disk
    if (mt_rand(1, 100) === 1) foreach (glob("$cache/*.gif") ?: [] as $old) if (filemtime($old) < time() - 7 * 86400) @unlink($old);
    if ((is_dir($cache) || @mkdir($cache, 0777, true)) && @file_put_contents("$file.tmp", $gif) !== false) @rename("$file.tmp", $file);
}

header('Content-Type: image/gif');
header('Content-Length: ' . strlen($gif));
header('Cache-Control: public, max-age=86400');
echo $gif;

function gif_render(string $dir, array $tpl, array $texts): string
{
    $out = 'GIF89a' . pack('vv', $tpl['w'], $tpl['h']) . "\x00\x00\x00"   // no global colour table
        . "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";                  // loop forever
    foreach ($tpl['delays'] as $i => $delay) {
        $im = imagecreatefrompng(sprintf('%s/frames/%03d.png', $dir, $i));
        foreach ($tpl['boxes'] as $box) gif_draw($im, $box, $texts, $i);
        // imagetruecolortopalette() crashes IONOS's GD 2.3.3, so copy onto a palette image instead: each colour gets
        // its own slot until all 256 are used, then the nearest. The source was a GIF, so frames rarely need more.
        // shortcut: transparency is flattened; fine for video-clip memes, needs a transparent index for cut-out GIFs
        $pal = imagecreate($tpl['w'], $tpl['h']);
        foreach ($tpl['boxes'] as $box) {   // text colours first so they always get exact slots
            gif_color($pal, $box['color']);
            gif_color($pal, $box['stroke']);
        }
        imagecopy($pal, $im, 0, 0, 0, 0, $tpl['w'], $tpl['h']);
        ob_start();
        imagegif($pal);
        $g = ob_get_clean();
        imagedestroy($pal);
        $out .= gif_frame($g, $delay);
        imagedestroy($im);
    }
    return $out . ';';
}

// Turns one still GIF from imagegif() into an animation frame: its global colour table becomes a local one.
function gif_frame(string $g, int $delay): string
{
    $packed = ord($g[10]);
    $tableLen = $packed & 0x80 ? 3 << (($packed & 7) + 1) : 0;
    $table = substr($g, 13, $tableLen);
    $p = 13 + $tableLen;
    while ($g[$p] === "\x21") {                 // skip extensions GD may add
        $p += 2;
        while (($n = ord($g[$p])) > 0) $p += $n + 1;
        $p++;
    }
    $desc = substr($g, $p, 9) . chr((ord($g[$p + 9]) & 0x40) | ($tableLen ? 0x80 | ($packed & 7) : 0));
    return "\x21\xF9\x04\x04" . pack('v', $delay) . "\x00\x00"   // graphic control: keep frame, delay, no transparency
        . $desc . $table . substr($g, $p + 10, -1);              // image data without the trailer
}

// Where a text box sits on frame $f, or null when hidden. Mirrors pos() in private/gifs/editor.js.
function gif_pos(array $box, int $f): ?array
{
    $a = $box['keys'][0];
    $b = null;
    foreach ($box['keys'] as $k) {
        if ($k['f'] > $f) { $b = $k; break; }
        $a = $k;
    }
    if (!empty($a['hide'])) return null;
    if ($box['motion'] !== 'smooth' || !$b || !empty($b['hide']) || $a['f'] > $f) return [$a['x'], $a['y']];
    $t = ($f - $a['f']) / ($b['f'] - $a['f']);
    return [$a['x'] + ($b['x'] - $a['x']) * $t, $a['y'] + ($b['y'] - $a['y']) * $t];
}

// Slot names in URL order; a name used twice shares one URL part.
function gif_slots(array $boxes): array
{
    $names = [];
    foreach ($boxes as $box) {
        preg_match_all(GIF_SLOT, $box['text'], $mm);
        foreach ($mm[1] as $n) if (!in_array($n, $names, true)) $names[] = $n;
    }
    return $names;
}

function gif_text(string $tpl, array $texts): string
{
    return preg_replace_callback(GIF_SLOT, fn($mm) => $texts[$mm[1]] ?? ($mm[2] ?? ''), $tpl);
}

function gif_draw($im, array $box, array $texts, int $f): void
{
    $pos = gif_pos($box, $f);
    $str = trim(preg_replace('/ {2,}/', ' ', gif_text($box['text'], $texts)));   // a blank slot leaves no double space
    if (!$pos || $str === '') return;
    $font = __DIR__ . '/fonts/' . basename($box['font']);
    $pt = $box['size'] * 0.75;                  // GD sizes are points at 96 dpi; the editor uses pixels
    $bb = imagettfbbox($pt, 0, $font, $str);
    if ($bb === false) throw new RuntimeException("can't read font $box[font]");
    $w = $bb[2] - $bb[0];
    // widest it may be: the max width, and never past the picture's edges (text is centred on x). Mirrors fitWidth() in editor.js
    $fit = min($box['maxW'] > 0 ? $box['maxW'] : INF, 2 * min($pos[0], imagesx($im) - $pos[0]) - 8);
    if ($w > $fit) {
        $pt *= max(1, $fit) / $w;
        $bb = imagettfbbox($pt, 0, $font, $str);
        $w = $bb[2] - $bb[0];
    }
    $x = (int)round($pos[0] - $w / 2 - $bb[0]);
    $y = (int)round($pos[1]);
    $s = (int)$box['strokeW'];
    if ($s > 0) {
        $sc = gif_color($im, $box['stroke']);
        for ($dx = -$s; $dx <= $s; $dx++) for ($dy = -$s; $dy <= $s; $dy++)
            if ($dx * $dx + $dy * $dy <= $s * $s + 1) imagettftext($im, $pt, 0, $x + $dx, $y + $dy, $sc, $font, $str);
    }
    imagettftext($im, $pt, 0, $x, $y, gif_color($im, $box['color']), $font, $str);
}

function gif_color($im, string $hex): int
{
    return imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
}
