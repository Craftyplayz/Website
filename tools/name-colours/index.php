<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/tools-name-colours.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// Minecraft's 16 chat colours: code, hex, name shown here, name in MiniMessage/JSON
$legacy = [
    ['0', '000000', 'Black', 'black'], ['1', '0000aa', 'Dark Blue', 'dark_blue'],
    ['2', '00aa00', 'Dark Green', 'dark_green'], ['3', '00aaaa', 'Dark Aqua', 'dark_aqua'],
    ['4', 'aa0000', 'Dark Red', 'dark_red'], ['5', 'aa00aa', 'Purple', 'dark_purple'],
    ['6', 'ffaa00', 'Gold', 'gold'], ['7', 'aaaaaa', 'Grey', 'gray'],
    ['8', '555555', 'Dark Grey', 'dark_gray'], ['9', '5555ff', 'Blue', 'blue'],
    ['a', '55ff55', 'Green', 'green'], ['b', '55ffff', 'Aqua', 'aqua'],
    ['c', 'ff5555', 'Red', 'red'], ['d', 'ff55ff', 'Pink', 'light_purple'],
    ['e', 'ffff55', 'Yellow', 'yellow'], ['f', 'ffffff', 'White', 'white'],
];
$presets = [
    'Crafty' => ['66c0f4', 'b388ff'],
    'Rainbow' => ['ff0000', 'ff8000', 'ffff00', '00ff00', '0080ff', '8000ff'],
    'Sunset' => ['ff5f6d', 'ffc371'],
    'Ocean' => ['00c6ff', '0072ff'],
    'Fire' => ['ff1a00', 'ff8000', 'ffd000'],
    'Toxic' => ['a8ff00', '00ff87'],
    'Ice' => ['e0f7ff', '7ad7ff', '2f80ed'],
    'Candy' => ['ff6ec4', '7873f5'],
    'Gold' => ['fff1a8', 'ffb300', 'b36b00'],
    'Lava' => ['8b0000', 'ff4500', 'ffae00'],
    'Galaxy' => ['5b2a9e', 'd76d77', 'ffaf7b'],
    'Pastel' => ['ffb3ba', 'ffdfba', 'ffffba', 'baffc9', 'bae1ff'],
    'Mint' => ['00f5a0', '00d9f5'],
    'Pride' => ['e40303', 'ff8c00', 'ffed00', '008026', '004dff', '750787'],
    'Trans' => ['5bcefa', 'f5a9b8', 'ffffff', 'f5a9b8', '5bcefa'],
    'Silver' => ['ffffff', '6a6a6a'],
];
$fmts = [['l', 'Bold'], ['o', 'Italic'], ['n', 'Underline'], ['m', 'Strikethrough'], ['k', 'Obfuscated']];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page nc" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Minecraft Name Colours</h1>
        <p class="page-lede">Colour a Minecraft name with gradients, colour codes and formatting, then copy the code for your server.</p>

        <div class="nc-wrap">
            <div class="nc-controls">
                <section class="card">
                    <h2>Name</h2>
                    <label class="nc-label">Name or text <input class="field" id="ncName" maxlength="48" value="CraftyPlayz_" spellcheck="false" autocomplete="off"></label>
                    <div class="nc-letters" id="ncLetters" aria-label="Letters"></div>
                    <div class="nc-row">
                        <p class="muted nc-hint">Click a letter to give it its own colour.</p>
                        <button class="btn small" type="button" id="ncResetLetters" hidden>Reset letters</button>
                    </div>
                </section>

                <section class="card">
                    <h2>Colours</h2>
                    <div class="nc-presets" id="ncPresets">
                        <?php foreach ($presets as $name => $stops): ?>
                            <button type="button" data-preset="<?= h($name) ?>" style="--g: linear-gradient(90deg, #<?= implode(', #', $stops) ?>)"><i></i><?= h($name) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="nc-bar" id="ncBar" role="img" aria-label="Gradient preview"></div>
                    <div class="nc-stops" id="ncStops"></div>
                    <div class="nc-row">
                        <button class="btn small" type="button" data-act="add">Add colour</button>
                        <button class="btn small" type="button" data-act="reverse">Reverse</button>
                        <button class="btn small" type="button" data-act="random">Random</button>
                    </div>
                    <div class="nc-opts">
                        <label class="nc-label">Blend
                            <select class="field" id="ncBlend">
                                <option value="oklab">Smooth</option>
                                <option value="rgb">Straight (RGB)</option>
                                <option value="hue">Through the hues</option>
                            </select>
                        </label>
                        <label class="check"><input type="checkbox" id="ncSkip" checked> Skip spaces</label>
                        <label class="check"><input type="checkbox" id="ncLegacy"> Only the 16 colour codes (&amp;0 to &amp;f)</label>
                    </div>

                    <h3 class="nc-h3">Minecraft colours</h3>
                    <p class="muted nc-hint">Click one to add it to your colours.</p>
                    <div class="nc-pal" id="ncPal">
                        <?php foreach ($legacy as [$code, $hex, $name]): ?>
                            <button type="button" data-hex="<?= $hex ?>"><i style="background: #<?= $hex ?>"></i><span><?= h($name) ?></span><code>&amp;<?= $code ?></code></button>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card">
                    <h2>Style</h2>
                    <div class="nc-fmts" id="ncFmts">
                        <?php foreach ($fmts as [$code, $name]): ?>
                            <label class="check"><input type="checkbox" data-fmt="<?= $code ?>"> <?= $name ?> <code class="muted">&amp;<?= $code ?></code></label>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="nc-preview">
                <section class="card">
                    <h2>Preview</h2>
                    <div class="nc-mc" id="ncMc" data-bg="dark">
                        <div class="nc-tag"><span class="nc-name"></span></div>
                        <div class="nc-chat"><span class="nc-name"></span><span class="nc-msg">: hello there</span></div>
                        <div class="nc-chat"><span class="nc-sys">You have entered the hiding area</span></div>
                    </div>
                    <div class="stabs nc-bgs" id="ncBgs">
                        <button type="button" class="on" data-bg="dark" aria-pressed="true">Dark</button>
                        <button type="button" data-bg="sky" aria-pressed="false">Sky</button>
                        <button type="button" data-bg="light" aria-pressed="false">Light</button>
                    </div>

                    <div class="nc-out">
                        <label class="nc-label">Format
                            <select class="field" id="ncFormat">
                                <option value="amp">&amp;#RRGGBB (EssentialsX, CMI, most plugins)</option>
                                <option value="x">&amp;x&amp;R&amp;R&amp;G&amp;G&amp;B&amp;B (Spigot, BungeeCord)</option>
                                <option value="sect">§x§R§R§G§G§B§B (section signs)</option>
                                <option value="brace">{#RRGGBB} (TAB and some chat plugins)</option>
                                <option value="mm">MiniMessage, every letter (Paper, Velocity)</option>
                                <option value="mmg">MiniMessage gradient tag (shortest)</option>
                                <option value="json">JSON text (/tellraw, data packs)</option>
                            </select>
                        </label>
                        <label class="nc-label">Command (optional) <input class="field mono" id="ncCmd" list="ncCmds" placeholder="/nick" spellcheck="false" autocomplete="off"></label>
                        <datalist id="ncCmds"><option value="/nick"><option value="/nickname"><option value="/tellraw @a"><option value="/lp user CraftyPlayz_ meta setprefix"></datalist>
                        <label class="nc-label">Code <textarea class="field mono" id="ncOutput" readonly rows="4"></textarea></label>
                        <p class="muted nc-meta" id="ncMeta"></p>
                        <p class="note nc-warn" id="ncWarn" hidden>This is longer than the 256-character chat limit, so typing it in chat will cut it off. Try a shorter format or fewer colours.</p>
                        <div class="actions nc-actions">
                            <button class="btn primary" type="button" id="ncCopy">Copy code</button>
                            <button class="btn" type="button" id="ncLink">Copy link</button>
                            <button class="btn" type="button" id="ncSave">Save</button>
                        </div>
                    </div>
                </section>

                <section class="card" id="ncSavedCard" hidden>
                    <h2>Saved <span class="n" id="ncSavedN"></span></h2>
                    <div class="rows" id="ncSaved"></div>
                </section>

                <details class="card nc-import">
                    <summary>Import a code</summary>
                    <label class="nc-label">Paste a name in any of the formats above <textarea class="field mono" id="ncImport" rows="3" spellcheck="false" placeholder="&amp;#ff5555C&amp;#ff8855r..."></textarea></label>
                    <p class="err" id="ncImportErr" hidden>No text found in that code. Paste the whole thing, including the colour codes.</p>
                    <div class="actions"><button class="btn" type="button" id="ncImportBtn">Load code</button></div>
                </details>
            </aside>
        </div>
    </main>

    <?php site_footer(null, false); ?>
    <script>window.NC = <?= json_encode(['legacy' => $legacy, 'presets' => $presets]) ?>;</script>
    <script src="<?= h(asset('/tools/name-colours/name-colours.js')) ?>" defer></script>
</body>

</html>
