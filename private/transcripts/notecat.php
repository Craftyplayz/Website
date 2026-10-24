<?php
// Pulls recordings from NoteCat (notecat.fyi, the Discord recording bot). NoteCat has no public API, so this
// calls the same tRPC endpoints its own dashboard uses, logged in with the owner's notecat.fyi cookie.
// They're undocumented and can change without warning; every failure becomes a plain message for the page.
const NC_BASE = 'https://notecat.fyi';
const NC_COOKIE = __DIR__ . '/notecat-cookie.txt'; // gitignored, and denied by .htaccess

function nc_cookie()
{
    return is_file(NC_COOKIE) ? trim((string)file_get_contents(NC_COOKIE)) : '';
}

// GET a tRPC query (superjson: input wrapped in {"json": ...}, answer in result.data.json)
function nc_call($proc, $input = null)
{
    $cookie = nc_cookie();
    if ($cookie === '') throw new RuntimeException('Paste your NoteCat login cookie first (under "NoteCat login").');
    $url = NC_BASE . '/api/trpc/' . $proc . ($input === null ? '' : '?input=' . rawurlencode(json_encode(['json' => $input])));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CraftyPlayz transcript import; +https://craftyplayz.com/games/dnd/transcripts/)',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $status === 0) throw new RuntimeException('Couldn\'t reach NoteCat. Try again in a minute.');
    if ($status === 401 || $status === 403) throw new RuntimeException('NoteCat says you\'re logged out. Paste a fresh cookie under "NoteCat login".');
    $j = json_decode($body, true);
    if (!isset($j['result']['data'])) throw new RuntimeException('NoteCat answered something unexpected (' . $status . '). Its site may have changed.');
    return $j['result']['data']['json'] ?? $j['result']['data'];
}

// "<@123>" Discord mentions -> "@name", as NoteCat's export does
function nc_mentions($text, $users)
{
    return preg_replace_callback('/<@(\d+)>/', fn($m) => isset($users[$m[1]]['username']) ? '@' . $users[$m[1]]['username'] : $m[0], (string)$text);
}

// finished recordings from the last $days days, in every server the bot is in
function nc_recent($days = 30)
{
    $out = [];
    foreach (nc_call('user.getGuilds') as $g) {
        if (empty($g['botPresent']) || empty($g['lastRecordingAt']) || strtotime($g['lastRecordingAt']) < time() - $days * 86400) continue;
        $list = nc_call('recording.getSessions', ['guildId' => $g['id']]);
        foreach ($list['sessions'] ?? [] as $s) {
            if (empty($s['finishedAt']) || strtotime($s['createdAt']) < time() - $days * 86400) continue;
            $out[] = [
                'id' => (string)$s['id'],
                'title' => nc_mentions($s['generatedTitle'] ?? '', $list['mentionedUsers'] ?? []),
                'server' => (string)($g['name'] ?? ''),
                'channel' => (string)($s['voiceChannelName'] ?? ''),
                'ts' => strtotime($s['createdAt']),
                'minutes' => (int)round((strtotime($s['finishedAt']) - strtotime($s['createdAt'])) / 60),
            ];
        }
    }
    usort($out, fn($a, $b) => $b['ts'] <=> $a['ts']);
    return $out;
}

// One recording as markdown in the same layout as NoteCat's own .md export, so the viewer parses both alike.
// Mirrors ct() in NoteCat's dashboard code; times are written in UK time like the owner's downloads.
function nc_markdown($d)
{
    $s = $d['session'];
    $users = $d['mentionedUsers'] ?? [];
    $mention = fn($t) => nc_mentions($t, $users);
    $tz = new DateTimeZone('Europe/London');
    $at = fn($iso, $f) => (new DateTime($iso))->setTimezone($tz)->format($f);
    $name = fn($p) => ($p['user']['guildDisplayName'] ?? '') ?: (($p['user']['username'] ?? '') ?: 'User ' . substr((string)($p['userId'] ?? ''), -4));
    $msgs = array_values(array_filter($d['transcript'] ?? [], fn($p) => empty($p['isVoiceEvent'])));

    $md = '# ' . ($s['generatedTitle'] ? $mention($s['generatedTitle']) : 'Recording Session') . "\n\n## Recording Information\n\n";
    $md .= '- **Date**: ' . $at($s['createdAt'], 'F j, Y \a\t h:i:s A T') . "\n";
    $md .= '- **Channel**: ' . (($s['voiceChannelName'] ?? '') ?: 'Voice Channel') . "\n";
    if (!empty($s['finishedAt'])) {
        $secs = strtotime($s['finishedAt']) - strtotime($s['createdAt']);
        $md .= '- **Duration**: ' . intdiv($secs, 60) . 'm ' . ($secs % 60) . "s\n";
    }
    $md .= '- **Speakers**: ' . count(array_unique(array_column($msgs, 'userId'))) . "\n";
    $md .= '- **Total Messages**: ' . count($msgs) . "\n\n";
    if (!empty($s['generatedSummary'])) $md .= "## Summary\n\n" . $mention(preg_replace('/[ \t]?\[\[cite:\d+\]\]/', '', $s['generatedSummary'])) . "\n\n";
    $md .= "## Transcript\n\n";
    foreach ($msgs as $p) $md .= '**' . $name($p) . '** - ' . $at($p['createdAt'], 'h:i:s A') . "  \n" . $p['text'] . "\n\n";
    return $md;
}

// Save a recording's audio to $path. The cookie only goes to notecat.fyi, never to a storage host it redirects to.
function nc_audio($id, $path)
{
    $url = NC_BASE . '/api/audio/' . rawurlencode($id);
    for ($hop = 0; $hop < 4; $hop++) {
        $fh = fopen($path, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CraftyPlayz transcript import)',
        ] + (parse_url($url, PHP_URL_HOST) === parse_url(NC_BASE, PHP_URL_HOST) ? [CURLOPT_COOKIE => nc_cookie()] : []));
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $next = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        fclose($fh);
        if ($status >= 300 && $status < 400 && $next) {
            $url = $next;
            continue;
        }
        if ($status !== 200) throw new RuntimeException('Couldn\'t download the recording from NoteCat (' . $status . ').');
        return;
    }
    throw new RuntimeException('NoteCat redirected the recording download too many times.');
}
