<?php
// Imports a D&D session (Discord recorder .md export + .ogg recording) into /games/dnd/transcripts/.
// Lives in /private/ so only the password holder can upload. Files arrive in 4 MB chunks because
// the host's proxy rejects requests near 8 MB (413), well under PHP's own 64 MB limit.
require $_SERVER['DOCUMENT_ROOT'] . '/games/dnd/transcripts/transcripts.php';
require __DIR__ . '/notecat.php';

// Move a checked transcript + recording from .incoming into a new session folder; returns its viewer URL.
function tx_save($tmp, $id, $name, $md, $meta = [])
{
    $head = tx_head($md);
    $base = ($head['ts'] ? date('Y-m-d', $head['ts']) . '-' : '') . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    $base = substr($base, 0, 80) ?: 'session';
    for ($slug = $base, $n = 2; is_dir(TX_DATA . "/$slug"); $n++) $slug = "$base-$n";
    $dir = TX_DATA . "/$slug";
    if (!mkdir($dir, 0755) || !rename("$tmp/$id.md", "$dir/transcript.md") || !rename("$tmp/$id.ogg", "$dir/audio.ogg")) throw new RuntimeException('Could not save the session.');
    file_put_contents("$dir/meta.json", json_encode(['name' => $name, 'imported' => time()] + $meta));
    return '/games/dnd/transcripts/?s=' . $slug;
}

function tx_is_ogg($path)
{
    $f = @fopen($path, 'rb');
    $magic = $f ? fread($f, 4) : '';
    if ($f) fclose($f);
    return $magic === 'OggS';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $fail = function ($msg, $code = 400) {
        http_response_code($code);
        exit(json_encode(['error' => $msg]));
    };
    $act = $_POST['act'] ?? '';
    $name = trim((string)($_POST['name'] ?? ''));
    $tmp = TX_DATA . '/.incoming';
    if (!is_dir($tmp) && !mkdir($tmp, 0755, true)) $fail('Could not create the upload folder.', 500);
    foreach (glob("$tmp/*") ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);

    try {
        if ($act === 'nc-cookie') {
            $c = trim(preg_replace('/^cookie:\s*/i', '', (string)($_POST['cookie'] ?? '')));
            if ($c === '' || preg_match('/[\r\n]/', $c)) $fail('Paste the cookie as one line.');
            file_put_contents(NC_COOKIE, $c);
            exit(json_encode(['ok' => true]));
        }
        if ($act === 'nc-list') {
            $done = [];
            foreach (glob(TX_DATA . '/*/meta.json') ?: [] as $f) $done[json_decode((string)file_get_contents($f), true)['notecat'] ?? ''] = true;
            exit(json_encode(['sessions' => array_values(array_filter(nc_recent(), fn($s) => !isset($done[$s['id']])))]));
        }
        if ($act === 'nc-import') {
            $sid = (string)($_POST['sid'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sid)) $fail('Bad NoteCat recording id.');
            if (!preg_match('/^.{1,100}$/u', $name)) $fail('Give the session a name (up to 100 characters).');
            $d = nc_call('recording.getSessionWithTranscript', ['sessionId' => $sid]);
            if (empty($d['session']['createdAt']) || !empty($d['transcriptExpired'])) $fail('NoteCat no longer has this transcript (free recordings expire after 7 days).');
            $md = nc_markdown($d);
            if (!tx_messages($md)) $fail('This recording has no transcript lines.');
            $id = bin2hex(random_bytes(8));
            file_put_contents("$tmp/$id.md", $md);
            nc_audio($sid, "$tmp/$id.ogg");
            if (!tx_is_ogg("$tmp/$id.ogg")) $fail('NoteCat sent the recording in a format other than .ogg.');
            exit(json_encode(['url' => tx_save($tmp, $id, $name, $md, ['notecat' => $sid])]));
        }
    } catch (RuntimeException $e) {
        $fail($e->getMessage(), 502);
    }

    $id = $_POST['id'] ?? '';
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) $fail('Bad upload id.');

    if ($act === 'chunk') {
        $kind = ($_POST['kind'] ?? '') === 'md' ? 'md' : 'ogg';
        $f = $_FILES['chunk'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $fail('A piece of the file didn\'t arrive. Try again.');
        $ok = file_put_contents("$tmp/$id.$kind", file_get_contents($f['tmp_name']), ($_POST['i'] ?? '') === '0' ? 0 : FILE_APPEND);
        if ($ok === false) $fail('Could not save the upload. The server may be out of space.', 500);
        exit(json_encode(['ok' => true]));
    }

    // finish: check both files, then move them into a new session folder
    if (!preg_match('/^.{1,100}$/u', $name)) $fail('Give the session a name (up to 100 characters).');
    $md = @file_get_contents("$tmp/$id.md");
    if ($md === false || !tx_messages($md)) $fail('That .md file has no transcript messages in it.');
    if (!tx_is_ogg("$tmp/$id.ogg")) $fail('That recording isn\'t an .ogg file.');
    try {
        exit(json_encode(['url' => tx_save($tmp, $id, $name, $md)]));
    } catch (RuntimeException $e) {
        $fail($e->getMessage(), 500);
    }
}

$site_page = ['components' => true, 'title' => 'Import a D&D Session'];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex, nofollow">
    <style>
        .nc-actions { margin-bottom: 12px; }
        #nc-list { display: grid; gap: 12px; max-width: 560px; }
        .nc-item p.muted { margin: 0; font-size: 13px; }
        .nc-login { max-width: 560px; margin-top: 20px; }
        .nc-login summary { cursor: pointer; font-weight: 600; }
    </style>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <h1 class="page-title">Import a D&amp;D Session</h1>
        <p class="page-lede">Upload the transcript and its recording together. They appear on <a href="/games/dnd/transcripts/">Session Transcripts</a>.</p>

        <form class="form form-card" id="tx-form">
            <label>Session name <input class="field" name="name" required maxlength="100" placeholder="Edrin's Disappearance"></label>
            <label>Transcript (.md) <input class="field" type="file" name="md" accept=".md,text/markdown" required></label>
            <label>Recording (.ogg) <input class="field" type="file" name="ogg" accept=".ogg,audio/ogg" required></label>
            <div class="progress" hidden><i style="width:0%"></i></div>
            <p class="err" hidden></p>
            <div class="actions"><button class="btn primary">Import session</button></div>
        </form>

        <h2 class="sec-h">From NoteCat</h2>
        <p class="muted">Finds recordings from the last 30 days that aren't imported yet.</p>
        <div class="actions nc-actions">
            <button class="btn" type="button" id="nc-check">Check NoteCat</button>
            <span class="muted" id="nc-status" role="status"></span>
        </div>
        <div id="nc-list"></div>

        <details class="card nc-login"<?= nc_cookie() === '' ? ' open' : '' ?>>
            <summary>NoteCat login <span class="muted"><?= nc_cookie() === '' ? '(not set)' : '(saved)' ?></span></summary>
            <form class="form" id="nc-cookie-form">
                <label>Cookie from notecat.fyi
                    <input class="field" name="cookie" autocomplete="off" spellcheck="false">
                    <small>On notecat.fyi, logged in: press F12, open Network, reload, click any <span class="mono">trpc</span> request and copy the value of its Cookie request header. Paste a new one when NoteCat logs you out.</small>
                </label>
                <div class="actions"><button class="btn">Save cookie</button></div>
            </form>
        </details>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        (() => {
            const form = document.getElementById('tx-form');
            const btn = form.querySelector('button');
            const bar = form.querySelector('.progress');
            const err = form.querySelector('.err');
            const CHUNK = 4 * 1024 * 1024;

            // suggest the transcript's own title as the name
            form.md.addEventListener('change', async () => {
                const f = form.md.files[0];
                const m = f && (await f.slice(0, 2048).text()).match(/^#\s+(.+)$/m);
                if (m && !form.elements.name.value) form.elements.name.value = m[1].trim();
            });

            async function post(data) {
                const fd = new FormData();
                for (const k in data) fd.append(k, data[k]);
                const r = await fetch('', { method: 'POST', body: fd });
                const j = await r.json().catch(() => ({ error: 'The server sent back something unexpected (' + r.status + ').' }));
                if (j.error) throw new Error(j.error);
                return j;
            }

            form.addEventListener('submit', async e => {
                e.preventDefault();
                const id = [...crypto.getRandomValues(new Uint8Array(8))].map(b => b.toString(16).padStart(2, '0')).join('');
                const md = form.md.files[0], ogg = form.ogg.files[0];
                const total = md.size + ogg.size;
                let sent = 0;
                btn.disabled = true;
                err.hidden = true;
                bar.hidden = false;
                try {
                    for (const [kind, file] of [['md', md], ['ogg', ogg]]) {
                        for (let i = 0, pos = 0; pos < file.size || i === 0; i++, pos += CHUNK) {
                            const chunk = file.slice(pos, pos + CHUNK);
                            btn.textContent = 'Uploading ' + Math.floor(sent / total * 100) + '%...';
                            await post({ act: 'chunk', id, kind, i, chunk });
                            sent += chunk.size;
                            bar.firstElementChild.style.width = (sent / total * 100) + '%';
                        }
                    }
                    btn.textContent = 'Importing...';
                    location.href = (await post({ act: 'finish', id, name: form.elements.name.value })).url;
                } catch (x) {
                    err.textContent = x.message;
                    err.hidden = false;
                    bar.hidden = true;
                    btn.disabled = false;
                    btn.textContent = 'Import session';
                }
            });

            // NoteCat: list recent recordings not imported yet, one small form each to name and import it
            const check = document.getElementById('nc-check');
            const status = document.getElementById('nc-status');
            const list = document.getElementById('nc-list');
            const cookieForm = document.getElementById('nc-cookie-form');
            const el = (tag, cls, text) => Object.assign(document.createElement(tag), cls ? { className: cls } : {}, text ? { textContent: text } : {});

            function card(s) {
                const f = el('form', 'form form-card nc-item');
                const label = el('label', '', 'Session name');
                const input = Object.assign(el('input', 'field'), { name: 'name', required: true, maxLength: 100, value: s.title });
                label.append(input);
                const when = new Date(s.ts * 1000).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                const meta = el('p', 'muted', [s.server, s.channel && '#' + s.channel, when, s.minutes + ' min'].filter(Boolean).join(' · '));
                const msg = Object.assign(el('p', 'err'), { hidden: true });
                const go = el('button', 'btn', 'Import');
                const skip = Object.assign(el('button', 'btn', 'Skip'), { type: 'button' });
                const row = el('div', 'actions');
                row.append(go, skip);
                f.append(label, meta, msg, row);
                skip.addEventListener('click', () => f.remove());
                f.addEventListener('submit', async e => {
                    e.preventDefault();
                    go.disabled = skip.disabled = true;
                    go.textContent = 'Importing (downloading the recording)...';
                    msg.hidden = true;
                    try {
                        const r = await post({ act: 'nc-import', sid: s.id, name: input.value });
                        const done = el('p', 'ok', 'Imported. ');
                        done.append(Object.assign(el('a', '', 'Open ' + input.value), { href: r.url }));
                        f.replaceWith(done);
                    } catch (x) {
                        msg.textContent = x.message;
                        msg.hidden = false;
                        go.disabled = skip.disabled = false;
                        go.textContent = 'Import';
                    }
                });
                return f;
            }

            check.addEventListener('click', async () => {
                check.disabled = true;
                status.textContent = 'Checking...';
                list.replaceChildren();
                try {
                    const { sessions } = await post({ act: 'nc-list' });
                    status.textContent = sessions.length ? sessions.length + ' new recording' + (sessions.length > 1 ? 's' : '') : 'Nothing new.';
                    list.append(...sessions.map(card));
                } catch (x) {
                    status.textContent = '';
                    list.append(el('p', 'err', x.message));
                }
                check.disabled = false;
            });

            cookieForm.addEventListener('submit', async e => {
                e.preventDefault();
                const b = cookieForm.querySelector('button');
                b.disabled = true;
                try {
                    await post({ act: 'nc-cookie', cookie: cookieForm.cookie.value });
                    cookieForm.cookie.value = '';
                    b.textContent = 'Saved';
                } catch (x) {
                    b.textContent = x.message;
                }
                setTimeout(() => { b.disabled = false; b.textContent = 'Save cookie'; }, 2000);
            });
        })();
    </script>
</body>

</html>
