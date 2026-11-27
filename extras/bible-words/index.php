<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-bible-words.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preload" href="<?= asset('/extras/bible-words/words.txt') ?>" as="fetch" crossorigin>
</head>

<body>
    <?php site_header(); ?>

    <main class="page bw" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">None of These Words Are in the Bible</h1>
        <p class="page-lede">Type or paste some text to see how many of its words appear in the King James Bible.</p>

        <section class="card bw-card">
            <label class="bw-label">Your text
                <textarea class="field bw-input" id="bw-text" rows="5" placeholder="We hope you enjoy our new burger"></textarea>
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

        <p class="muted bw-src">Words from the King James Version (1611, Cambridge text). Matching ignores capitals and punctuation, so "Lord" and "lord" count the same.</p>
    </main>

    <?php site_footer(null, false); ?>

    <script>
    (() => {
        const $ = (id) => document.getElementById(id);
        const input = $('bw-text'), status = $('bw-status'), result = $('bw-result'), words = $('bw-words');
        const esc = (s) => s.replace(/[&<>"']/g, (c) => '&#' + c.charCodeAt(0) + ';');
        let bible = null, timer;

        // Same tokeniser as _dev/bible-words/build.js, so both sides split words the same way.
        const tokens = (t) => t.toLowerCase().replace(/[‘’]/g, "'").match(/[a-z]+(?:'[a-z]+)*/g) || [];
        const known = (w) => bible.has(w) || (w.endsWith("'s") && bible.has(w.slice(0, -2)));

        function check() {
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

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(check, 80); });

        fetch(<?= json_encode(asset('/extras/bible-words/words.txt')) ?>)
            .then((r) => { if (!r.ok) throw 0; return r.text(); })
            .then((t) => {
                bible = new Set(t.split('\n'));
                status.remove();
                check();
            })
            .catch(() => {
                status.className = 'err';
                status.textContent = "Couldn't load the Bible word list. Reload the page to try again.";
            });
    })();
    </script>
</body>

</html>
