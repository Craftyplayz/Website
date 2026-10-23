<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-forms.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
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
        <h1 class="page-title">Nether Portal Converter</h1>
        <p class="page-lede">Paste a /tp command or F3+C coordinates to get the matching spot in the other dimension.</p>

        <div class="card form-card">
            <div class="form">
                <label>Coordinates
                    <input class="field mono" type="text" id="input" placeholder="/tp -8676 ~ 4405" autocomplete="off" spellcheck="false" autofocus>
                </label>
                <div class="stabs" id="dir" role="group" aria-label="Direction">
                    <button type="button" class="on" data-dim="overworld" aria-pressed="true">Overworld → Nether</button>
                    <button type="button" data-dim="nether" aria-pressed="false">Nether → Overworld</button>
                </div>
            </div>
            <div class="out">
                <div class="cmd"><code id="result" class="muted">Paste coordinates above</code><button class="btn small" type="button" id="copy" disabled>Copy</button></div>
                <p class="err hide" id="err" role="alert">Couldn't find coordinates. Expected something like /tp -8676 ~ 4405</p>
            </div>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, copy } = UI;
            let dim = 'overworld', result = '';

            const NUM = '(-?\\d+(?:\\.\\d+)?)', Y = '(~|-?\\d+(?:\\.\\d+)?)';
            // first three numbers after "tp" (a target like @s is skipped), or any three numbers in a row
            const PATTERNS = [new RegExp(`\\btp\\s+(?:\\S+\\s+)??${NUM}\\s+${Y}\\s+${NUM}`, 'i'), new RegExp(`${NUM}\\s+${Y}\\s+${NUM}`)];

            function setDim(d) {
                dim = d;
                for (const b of $('#dir').children) { b.classList.toggle('on', b.dataset.dim === d); b.setAttribute('aria-pressed', b.dataset.dim === d); }
            }

            function convert() {
                const raw = $('#input').value.trim();
                // F3+C says which dimension it was copied in
                if (/minecraft:the_nether/.test(raw)) setDim('nether');
                else if (/minecraft:overworld/.test(raw)) setDim('overworld');

                const m = raw && PATTERNS.map((p) => raw.match(p)).find(Boolean);
                $('#err').classList.toggle('hide', !raw || !!m);
                if (!m) {
                    result = '';
                    $('#result').textContent = 'Paste coordinates above';
                    $('#result').classList.add('muted');
                    $('#copy').disabled = true;
                    return;
                }
                // the game floors the scaled position when it looks for a portal
                const f = dim === 'overworld' ? 1 / 8 : 8, at = (v) => Math.floor(parseFloat(v) * f);
                const y = m[2] === '~' ? '~' : Math.floor(parseFloat(m[2]));
                result = `/tp ${at(m[1])} ${y} ${at(m[3])}`;
                $('#result').textContent = result;
                $('#result').classList.remove('muted');
                $('#copy').disabled = false;
            }

            $('#input').addEventListener('input', convert);
            $('#dir').addEventListener('click', (e) => {
                const b = e.target.closest('button');
                if (b) { setDim(b.dataset.dim); convert(); }
            });
            $('#copy').addEventListener('click', (e) => result && copy(result, e.currentTarget));
        });
    </script>
</body>

</html>
