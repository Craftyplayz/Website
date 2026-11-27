<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-bible-words.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// id => name; each has words-<id>.txt built by _dev/bible-words/build.js
$bw_versions = [
    'kjv' => 'King James Version (1611)',
    'bsb' => 'Berean Standard Bible (2022)',
    'web' => 'World English Bible (2000)',
    'asv' => 'American Standard Version (1901)',
    'akjv' => 'American King James Version (1999)',
    'erv' => 'English Revised Version (1885)',
    'wbt' => 'Webster Bible (1833)',
    'ylt' => "Young's Literal Translation (1862)",
    'dbt' => 'Darby Bible (1890)',
    'drb' => 'Douay-Rheims Bible (1610)',
];
$bw_v = isset($_GET['v']) && is_string($_GET['v']) && isset($bw_versions[$_GET['v']]) ? $_GET['v'] : 'kjv';
$bw_urls = [];
foreach ($bw_versions as $id => $name) $bw_urls[$id] = asset("/extras/bible-words/words-$id.txt");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preload" href="<?= h($bw_urls[$bw_v]) ?>" as="fetch" crossorigin>
</head>

<body>
    <?php site_header(); ?>

    <main class="page bw" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">None of These Words Are in the Bible</h1>
        <p class="page-lede">Type or paste some text to see how many of its words appear in the Bible.</p>

        <section class="card bw-card">
            <label class="bw-label">Bible
                <select class="field" id="bw-version">
                    <?php foreach ($bw_versions as $id => $name): ?>
                        <option value="<?= $id ?>"<?= $id === $bw_v ? ' selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="bw-label">Your text
                <textarea class="field bw-input" id="bw-text" rows="5" placeholder="McDonald's McRib NFT Project Links to Racial Slur Recorded On Blockchain"></textarea>
            </label>
            <p class="note" id="bw-status">Loading the word list...</p>
            <div class="bw-result" id="bw-result" hidden>
                <span class="ring bw-ring" id="bw-ring" role="img"><b id="bw-pct"></b></span>
                <div class="grow">
                    <p class="bw-line" id="bw-line"></p>
                    <p class="muted bw-key"><mark class="bw-in">in the Bible</mark> <mark class="bw-out">not in the Bible</mark></p>
                </div>
            </div>
            <p class="bw-words" id="bw-words" hidden></p>
        </section>

        <p class="muted bw-src">All versions are public domain. Matching ignores capitals and punctuation, so "Lord" and "lord" count the same.</p>
    </main>

    <?php site_footer(null, false); ?>

    <script>
    (() => {
        const $ = (id) => document.getElementById(id);
        const input = $('bw-text'), status = $('bw-status'), result = $('bw-result'), words = $('bw-words'), picker = $('bw-version');
        const urls = <?= json_encode($bw_urls) ?>, sets = {};
        const esc = (s) => s.replace(/[&<>"']/g, (c) => '&#' + c.charCodeAt(0) + ';');
        let bible = null, timer;

        // Same tokeniser as _dev/bible-words/build.js, so both sides split words the same way.
        const tokens = (t) => t.toLowerCase().replace(/[‘’]/g, "'").match(/[a-z]+(?:'[a-z]+)*/g) || [];
        const known = (w) => bible.has(w) || (w.endsWith("'s") && bible.has(w.slice(0, -2)));

        function check() {
            if (!bible) return;
            const text = input.value;
            const all = tokens(text);
            result.hidden = words.hidden = !all.length;
            if (!all.length) return;
            const hits = all.filter(known).length;
            const pct = Math.round(hits / all.length * 100);
            $('bw-pct').textContent = pct + '%';
            $('bw-ring').style.setProperty('--p', pct);
            $('bw-ring').classList.toggle('full', pct === 100);
            $('bw-ring').setAttribute('aria-label', pct + '% of words are in the Bible');
            $('bw-line').textContent = pct + '% of these words are in the Bible (' + hits.toLocaleString() + ' of ' + all.length.toLocaleString() + ').';
            // Mark each word in the original text, keeping its spacing and capitals.
            words.innerHTML = text.split(/([A-Za-z‘’']+)/).map((m, i) => {
                const w = i % 2 ? tokens(m)[0] : null; // odd parts are the word runs
                return w ? '<mark class="' + (known(w) ? 'bw-in' : 'bw-out') + '">' + esc(m) + '</mark>' : esc(m);
            }).join('');
        }

        function load() {
            const v = picker.value;
            history.replaceState(null, '', v === 'kjv' ? location.pathname : '?v=' + v);
            if (sets[v]) { bible = sets[v]; check(); return; }
            bible = null;
            status.className = 'note';
            status.textContent = 'Loading the word list...';
            status.hidden = false;
            result.hidden = words.hidden = true;
            fetch(urls[v])
                .then((r) => { if (!r.ok) throw 0; return r.text(); })
                .then((t) => {
                    sets[v] = new Set(t.split('\n'));
                    if (picker.value !== v) return; // another version was picked meanwhile
                    bible = sets[v];
                    status.hidden = true;
                    check();
                })
                .catch(() => {
                    if (picker.value !== v) return;
                    status.className = 'err';
                    status.textContent = "Couldn't load that Bible's word list. Reload the page to try again.";
                });
        }

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(check, 80); });
        picker.addEventListener('change', load);
        load();
    })();
    </script>
</body>

</html>
