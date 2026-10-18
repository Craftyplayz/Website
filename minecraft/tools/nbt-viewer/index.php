<?php
$site_page = ['description' => 'Drop Minecraft player .dat files and explore every bit of their NBT data visually.'];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://crafthead.net">
    <link rel="preconnect" href="https://mc-heads.net">
</head>

<body>
    <?php site_header(); ?>
    <!-- after header.php so these win over /css/global.css -->
    <style>
        :root {
            --bg: #202020;
            --card: #2a2a2a;
            --card2: #323232;
            --line: #3d3d3d;
            --text: #ececec;
            --muted: #9b9b9b;
            --accent: #7ed957;
            --xp: #80ff20;
            --gui: #c6c6c6;
            --s: clamp(30px, 8.6vw, 46px);
        }

        .nbt {
            max-width: 1280px;
            margin: 0 auto;
            padding: 16px;
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .nbt * { box-sizing: border-box; }

        /* undo the site-wide button / input / label styles from global.css inside the viewer */
        :where(.nbt, #drawer, dialog) button { padding: 0; background: none; border: 0; border-radius: 0; font: inherit; color: inherit; z-index: auto; transition: none; }
        :where(.nbt, #drawer, dialog) button:hover { background: none; }
        :where(.nbt, #drawer, dialog) input { font-size: 14px; font-weight: 400; border-radius: 8px; transition: none; }
        :where(.nbt, #drawer, dialog) input:hover { background-color: #161616; }
        :where(.nbt, #drawer, dialog) label { background: none; padding: 0; border-radius: 0; font-size: inherit; font-weight: inherit; }
        .nbt a { color: #8cc8ff; text-decoration: none; }
        .nbt button { font: inherit; color: inherit; cursor: pointer; }
        .muted { color: var(--muted); }
        .mono { font-family: ui-monospace, Consolas, monospace; }
        .hide { display: none !important; }

        /* ---------- drop zone ---------- */
        .drop {
            border: 2px dashed #555;
            border-radius: 14px;
            padding: 56px 20px;
            text-align: center;
            background: repeating-linear-gradient(45deg, #262626 0 14px, #2a2a2a 14px 28px);
            transition: border-color .15s, background .15s;
        }

        .drop h1 { font-size: clamp(26px, 5vw, 42px); margin: 0 0 6px; }
        .drop p { margin: 6px 0; }
        .drop .big-ico { width: 96px; height: 96px; image-rendering: pixelated; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: #3a3a3a; border: 1px solid #555; border-radius: 8px; padding: 9px 16px;
            transition: background .12s, transform .06s;
        }
        .btn:hover { background: #474747; }
        .btn:active { transform: translateY(1px); }
        .btn.primary { background: #3d7a2a; border-color: #5aa83f; }
        .btn.primary:hover { background: #4a9033; }
        .btn.small { padding: 5px 10px; font-size: 13px; }
        .drop .row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 18px; }

        #dragOverlay {
            position: fixed; inset: 0; z-index: 1000; background: rgba(40, 90, 30, .55);
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 700; pointer-events: none; border: 4px dashed #9f9;
        }

        /* ---------- toolbar ---------- */
        .bar {
            position: sticky; top: 0; z-index: 50; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
            background: rgba(32, 32, 32, .94); backdrop-filter: blur(6px);
            padding: 10px 0; border-bottom: 1px solid var(--line); margin-bottom: 16px;
        }
        .tabs { display: flex; gap: 6px; flex-wrap: wrap; flex: 1 1 260px; min-width: 0; }
        /* player picker: one button + dropdown instead of a tab per player */
        .tabs { position: relative; }
        .pick-btn {
            display: flex !important; align-items: center; gap: 8px; padding: 5px 12px 5px 5px !important; border-radius: 8px !important;
            background: #35502b !important; border: 1px solid #5aa83f !important; max-width: 100%;
        }
        .pick-btn:hover { background: #3f5f33 !important; }
        .pick-btn img, .pick-row img { width: 26px; height: 26px; border-radius: 4px; image-rendering: pixelated; flex: none; }
        .pick-btn .nm { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
        .pick-btn .n { font-size: 12px; color: #b9d8ad; white-space: nowrap; }
        .pick-btn .car { font-size: 11px; opacity: .8; transition: transform .12s; }
        .tabs.open .pick-btn .car { transform: rotate(180deg); }
        .pick-menu {
            position: absolute; top: calc(100% + 6px); left: 0; z-index: 60; width: min(400px, calc(100vw - 32px));
            background: #1c1c1c; border: 1px solid #4a4a4a; border-radius: 10px; box-shadow: 0 12px 30px #000a; overflow: hidden;
        }
        .pick-menu input { width: 100%; border: 0 !important; border-bottom: 1px solid #3a3a3a !important; border-radius: 0 !important; background: #161616 !important; color: var(--text); padding: 10px 12px !important; outline: none; }
        .pick-list { max-height: min(60vh, 520px); overflow-y: auto; padding: 4px; }
        .pick-row { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 6px; cursor: pointer; }
        .pick-row:hover, .pick-row.kb { background: #2e2e2e; }
        .pick-row.on { background: #2d4224; }
        .pick-row .grow { flex: 1; min-width: 0; }
        .pick-row .grow b { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pick-row small { color: var(--muted); font-size: 12px; }
        .pick-row .x { opacity: .4; padding: 2px 6px !important; }
        .pick-row .x:hover { opacity: 1; color: #ff8080; }
        .pick-foot { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; border-top: 1px solid #3a3a3a; font-size: 12px; color: var(--muted); }
        .search {
            flex: 0 1 300px; min-width: 160px; background: #161616; border: 1px solid #4a4a4a; color: var(--text);
            border-radius: 8px; padding: 8px 12px; font-size: 14px; outline: none;
        }
        .search:focus { border-color: var(--accent); }

        .jump { display: flex; gap: 6px; flex-wrap: wrap; margin: -6px 0 16px; }
        .jump a {
            font-size: 13px; padding: 3px 10px; border-radius: 999px; background: var(--card);
            border: 1px solid var(--line); color: var(--text) !important;
        }
        .jump a:hover { border-color: var(--accent); }

        /* ---------- layout ---------- */
        /* masonry - cards stack into columns so short cards don't leave gaps */
        .masonry { columns: 3 340px; column-gap: 16px; }
        .masonry > .card { break-inside: avoid; margin-bottom: 16px; width: 100%; display: inline-block; }
        .card {
            background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 16px; min-width: 0;
            scroll-margin-top: 70px;
        }
        .card h2 { font-size: 17px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; }
        .card h2 .n { font-weight: 400; color: var(--muted); font-size: 14px; margin-left: auto; }
        .card h2 img { width: 22px; height: 22px; }
        .card h2 .btn { font-weight: 400; }
        .section { margin-bottom: 16px; }

        /* ---------- hero ---------- */
        .hero { display: grid; grid-template-columns: auto 1fr; gap: 20px; align-items: center; }
        .hero h1 { font-size: clamp(24px, 4vw, 34px); margin: 0; }
        .hero .uuid { font-size: 12px; cursor: pointer; }
        .hero .uuid:hover { color: var(--text); }
        .badges { display: flex; gap: 6px; flex-wrap: wrap; margin: 8px 0; }
        .badge { font-size: 12px; padding: 2px 9px; border-radius: 999px; background: #3a3a3a; border: 1px solid #4d4d4d; }
        .badge.g { background: #2d4a24; border-color: #4f8a3a; }
        .badge.r { background: #4a2424; border-color: #8a3a3a; }
        .badge.p { background: #3d2a4f; border-color: #6c4b8f; }
        .badge.b { background: #24384a; border-color: #3a628a; }
        .tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; margin-top: 10px; }
        .tile { background: var(--card2); border-radius: 8px; padding: 8px 10px; }
        .tile b { display: block; font-size: 20px; line-height: 1.2; }
        .tile span { font-size: 12px; color: var(--muted); }
        @media (max-width: 560px) { .hero { grid-template-columns: 1fr; text-align: center; } .hero .skin3d { margin: 0 auto; } .badges { justify-content: center; } }

        /* ---------- vitals ---------- */
        .vit { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; margin-top: 14px; }
        .icons { display: flex; flex-wrap: wrap; gap: 1px; }
        .icons svg { width: 18px; height: 18px; shape-rendering: crispEdges; }
        .icons.rtl { flex-direction: row-reverse; justify-content: flex-end; }
        .vlabel { font-size: 12px; color: var(--muted); }
        .xp { grid-column: 1 / -1; position: relative; margin-top: 16px; }
        .xpbar { height: 10px; background: #1a1a1a; border: 2px solid #000; border-radius: 2px; overflow: hidden; }
        .xpbar i { display: block; height: 100%; background: linear-gradient(#a8ff60, #5ad21c); }
        .xplvl {
            position: absolute; left: 50%; top: -15px; transform: translateX(-50%); font-weight: 800; font-size: 18px;
            color: var(--xp); text-shadow: 2px 0 #000, -2px 0 #000, 0 2px #000, 0 -2px #000;
        }

        /* ---------- Minecraft GUI (dark) ---------- */
        .gui {
            background: #262626; border-radius: 4px; padding: 10px; color: #d8d8d8;
            box-shadow: inset 2px 2px #3c3c3c, inset -2px -2px #141414, 0 0 0 2px #000; display: inline-block; max-width: 100%;
        }
        .gui .ttl { font-size: 15px; margin: 0 0 6px 2px; color: #cfcfcf; }
        .slots { display: grid; grid-template-columns: repeat(9, var(--s)); }
        .slots + .slots { margin-top: 8px; }
        .slot {
            width: var(--s); height: var(--s); position: relative; background: #1c1c1c;
            box-shadow: inset 2px 2px #111, inset -2px -2px #3a3a3a; cursor: default;
        }
        .slot.has { cursor: pointer; }
        .slot.has:hover { background: #3a3a3a; }
        .slot.sel { box-shadow: inset 0 0 0 2px #e8e8e8, inset 0 0 0 4px #000; }
        .slot.pick { outline: 3px solid #ffd84a; outline-offset: -3px; z-index: 2; }
        /* API renders are 128px - let the browser smooth the downscale; only the 16px local textures stay pixelated */
        .slot img, .ico img {
            position: absolute; inset: 9%; width: 82%; height: 82%; object-fit: contain;
            pointer-events: none;
        }
        img[data-f] { image-rendering: pixelated; }
        .slot .ph { position: absolute; inset: 16%; opacity: .5; filter: brightness(.22); image-rendering: pixelated; background-size: contain; background-repeat: no-repeat; background-position: center; }
        .craft { display: grid; grid-template-columns: repeat(2, var(--s)) auto var(--s); align-items: center; gap: 0; align-self: start; }
        .craft .ttl { grid-column: 1 / -1; }
        .craft .arrow { padding: 0 8px; color: #6a6a6a; font-size: calc(var(--s) * .55); align-self: center; }
        .craft .out { align-self: center; }
        .cnt {
            position: absolute; right: 2px; bottom: 0; font-weight: 700; font-size: calc(var(--s) * .34); color: #fff;
            text-shadow: 2px 2px #000; pointer-events: none; line-height: 1; font-family: ui-monospace, Consolas, monospace;
        }
        .dur { position: absolute; left: 14%; right: 14%; bottom: 10%; height: 3px; background: #000; pointer-events: none; }
        .dur i { display: block; height: 2px; }
        .glint::after {
            content: ""; position: absolute; inset: 9%; pointer-events: none;
            -webkit-mask: var(--m) center / contain no-repeat; mask: var(--m) center / contain no-repeat;
            background: linear-gradient(115deg, transparent 20%, rgba(200, 120, 255, .65) 35%, transparent 50%, rgba(170, 90, 255, .5) 70%, transparent 85%);
            background-size: 300% 300%; animation: glint 3s linear infinite; mix-blend-mode: screen;
        }
        @keyframes glint { from { background-position: 100% 100%; } to { background-position: 0 0; } }
        .searching .slot.has:not(.hit) { opacity: .25; }
        .searching .slot.hit { outline: 3px solid #ffd84a; outline-offset: -3px; }
        .container-dot { position: absolute; left: 3px; top: 3px; width: 6px; height: 6px; border-radius: 50%; background: #ffd84a; box-shadow: 0 0 0 1px #000; pointer-events: none; }

        .inv { display: grid; grid-template-columns: var(--s) calc(var(--s) * 3) var(--s) 1fr; gap: 6px; align-items: end; margin-bottom: 10px; }
        .inv .col { display: grid; gap: 0; }
        .inv .model { background: #000; height: calc(var(--s) * 4); position: relative; box-shadow: inset 2px 2px #111, inset -2px -2px #3a3a3a; overflow: hidden; }
        .inv .craft { justify-self: end; }
        @media (max-width: 520px) { .inv .craft { display: none; } }

        /* 3D skin (skinview3d) - the 2D render shows until the model has loaded */
        .skin3d { position: relative; cursor: grab; touch-action: none; user-select: none; }
        .skin3d:active { cursor: grabbing; }
        .skin3d canvas { position: absolute; inset: 0; width: 100% !important; height: 100% !important; }
        .skin3d > img { position: absolute; inset: 6% 0; height: 88%; width: 100%; object-fit: contain; image-rendering: pixelated; }
        .hero .skin3d { width: 170px; height: 270px; filter: drop-shadow(0 8px 12px #0009); }
        .guis { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; }

        /* ---------- tooltip ---------- */
        #tip {
            position: fixed; z-index: 2000; pointer-events: none; max-width: 380px; padding: 6px 8px;
            background: rgba(16, 0, 16, .94); border: 2px solid #25005a; border-radius: 4px; outline: 2px solid #100010;
            font-family: ui-monospace, Consolas, monospace; font-size: 14px; line-height: 1.35; color: #aaa;
            text-shadow: 2px 2px #2a2a2a;
        }
        #tip .nm { font-size: 15px; margin-bottom: 2px; }
        #tip .id { color: #555; font-size: 12px; margin-top: 3px; }
        .mc-text .l { white-space: pre-wrap; }

        /* ---------- item drawer ---------- */
        #drawer {
            position: fixed; top: 0; right: 0; bottom: 0; width: min(460px, 100vw); box-sizing: border-box; z-index: 1500; overflow-y: auto;
            background: #1b1b1b; border-left: 1px solid #444; padding: 16px; transform: translateX(105%);
            transition: transform .18s ease-out; box-shadow: -12px 0 30px #0009; color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        #drawer.open { transform: none; }
        #drawer .close { position: absolute; right: 12px; top: 10px; background: none; border: 0; font-size: 26px; color: #bbb; }
        #drawer .crumbs { font-size: 12px; color: var(--muted); margin-right: 30px; }
        #drawer .crumbs a { cursor: pointer; }
        #drawer .head { display: flex; gap: 14px; align-items: center; margin: 10px 0; }
        #drawer .head .ico { position: relative; width: 96px; height: 96px; flex: none; background: #2a2a2a; border-radius: 10px; }
        #drawer .tipbox { background: rgba(16, 0, 16, .94); border: 2px solid #25005a; border-radius: 4px; padding: 8px; font-family: ui-monospace, Consolas, monospace; font-size: 14px; color: #aaa; text-shadow: 2px 2px #2a2a2a; margin: 10px 0; }
        #drawer h3 { font-size: 14px; margin: 16px 0 6px; color: #ccc; }
        .kv { display: grid; grid-template-columns: auto 1fr; gap: 3px 12px; font-size: 13px; }
        .kv > :nth-child(odd) { color: var(--muted); }

        /* ---------- lists ---------- */
        .rows { display: grid; gap: 6px; }
        .rowi { display: flex; align-items: center; gap: 10px; background: var(--card2); border-radius: 8px; padding: 7px 10px; font-size: 14px; }
        .rowi .grow { flex: 1; min-width: 0; }
        .rowi small { color: var(--muted); }
        .pill { border-radius: 6px; padding: 1px 7px; font-size: 12px; background: #444; }
        .effdot { width: 30px; height: 30px; border-radius: 6px; flex: none; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; text-shadow: 1px 1px #000; box-shadow: inset 0 0 0 2px #0004; }
        .timebar { height: 4px; background: #444; border-radius: 2px; margin-top: 4px; overflow: hidden; }
        .timebar i { display: block; height: 100%; }
        .attr .bar { height: 5px; background: #444; border-radius: 3px; overflow: hidden; margin-top: 4px; }
        .attr .bar i { display: block; height: 100%; background: #5aa8ff; }
        .chg { color: #ffd84a; }

        .owned { display: grid; grid-template-columns: repeat(auto-fill, minmax(64px, 1fr)); gap: 6px; }
        .own { position: relative; background: var(--card2); border-radius: 8px; padding: 6px 4px 4px; text-align: center; cursor: pointer; border: 1px solid transparent; }
        .own:hover { border-color: #666; }
        .own .ico { position: relative; width: 40px; height: 40px; margin: 0 auto; }
        .own b { display: block; font-size: 13px; }
        .searching .own:not(.hit) { display: none; }

        .map svg { width: 100%; height: auto; background: #1a1a1a; border-radius: 8px; }
        .map text { font-size: 11px; fill: #ddd; paint-order: stroke; stroke: #000; stroke-width: 3px; }

        .chips { display: flex; flex-wrap: wrap; gap: 4px; max-height: 420px; overflow-y: auto; }
        .chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; background: var(--card2); border-radius: 6px; padding: 2px 7px 2px 3px; }
        .chip.new { box-shadow: inset 0 0 0 1px #ffd84a; }
        .chip .ico { position: relative; width: 20px; height: 20px; flex: none; }
        .searching .chip:not(.hit) { display: none; }

        /* ---------- statistics ---------- */
        .stabs { display: flex; gap: 4px; flex-wrap: wrap; margin: 14px 0 10px; }
        .stabs button { padding: 5px 12px !important; border-radius: 999px !important; background: var(--card2) !important; border: 1px solid var(--line) !important; font-size: 13px; }
        .stabs button.on { background: #35502b !important; border-color: #5aa83f !important; }
        .stabs button small { color: var(--muted); margin-left: 4px; }
        .stgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px; }
        .sti { display: flex; align-items: center; gap: 8px; background: var(--card2); border-radius: 8px; padding: 5px 8px; font-size: 13px; min-width: 0; }
        .sti .ico { position: relative; width: 30px; height: 30px; flex: none; }
        .sti div { min-width: 0; }
        .sti span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--muted); font-size: 12px; }
        .sti b { font-size: 14px; }
        .searching .sti:not(.hit) { display: none; }
        .travel { display: flex; height: 16px; border-radius: 4px; overflow: hidden; margin: 6px 0; }
        .travel i { display: block; height: 100%; min-width: 2px; }
        .legend { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12px; color: var(--muted); }
        .legend span::before { content: ""; display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 5px; background: var(--c); }

        /* ---------- advancements ---------- */
        .adv-cat { margin-top: 14px; }
        .adv-cat h3 { font-size: 14px; margin: 0 0 6px; display: flex; gap: 8px; align-items: center; }
        .adv-cat h3 .n { margin-left: auto; color: var(--muted); font-weight: 400; font-size: 13px; }
        .progress { height: 6px; background: #3a3a3a; border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
        .progress i { display: block; height: 100%; background: linear-gradient(90deg, #4f8a3a, #7ed957); }
        .advs { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 6px; }
        .adv { position: relative; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px 4px 6px; background: var(--card2); border-radius: 8px; text-align: center; font-size: 12px; line-height: 1.2; border: 1px solid transparent; }
        .adv .ico { position: relative; display: block; width: 34px; height: 34px; }
        .adv .frame { display: block; padding: 5px; border-radius: 6px; background: #1c1c1c; box-shadow: inset 0 0 0 2px #444; }
        .adv.done .frame { background: #4a3d12; box-shadow: inset 0 0 0 2px #c9a227; }
        .adv.chal.done .frame { background: #3d2a4f; box-shadow: inset 0 0 0 2px #b77cff; }
        .adv:not(.done):not(.part) { opacity: .45; }
        .adv.part { border-color: #5a5a2a; }
        .adv small { color: var(--muted); }
        .searching .adv:not(.hit) { display: none; }
        .extra-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 10px 14px; border: 1px dashed #4a4a4a; border-radius: 12px; font-size: 14px; }
        .extra-bar .muted { flex: 1; min-width: 200px; font-size: 13px; }
        .note { background: #24384a; border: 1px solid #3a628a; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; }
        /* toasts: small stack in the bottom right */
        #errors { position: fixed; right: 16px; bottom: 16px; z-index: 1000; width: min(340px, calc(100vw - 32px)); display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
        #errors > div { margin: 0; padding: 8px 12px; font-size: 13px; line-height: 1.35; overflow-wrap: anywhere; box-shadow: 0 4px 14px rgba(0, 0, 0, .4); pointer-events: auto; cursor: pointer; }

        /* ---------- tree ---------- */
        .tree { font-family: ui-monospace, Consolas, monospace; font-size: 13px; line-height: 1.55; overflow-x: auto; }
        .tree details { padding-left: 16px; }
        .tree > details { padding-left: 0; }
        .tree details { padding-left: 22px; }
        .tree summary { cursor: pointer; list-style: none; margin-left: -22px; padding-left: 22px; position: relative; border-radius: 4px; }
        .tree summary::-webkit-details-marker { display: none; }
        /* checkbox: empty = collapsed, ticked = expanded */
        .tree summary::before {
            content: ""; position: absolute; left: 3px; top: 50%; width: 12px; height: 12px; margin-top: -7px;
            border: 1.5px solid #777; border-radius: 3px; background: #1a1a1a;
        }
        .tree details[open] > summary::before { background: #4f8a3a; border-color: #7ed957; }
        .tree details[open] > summary::after {
            content: ""; position: absolute; left: 7px; top: 50%; width: 4px; height: 7px; margin-top: -6px;
            border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
        }
        .tree summary:hover::before { border-color: #bbb; }
        .tree summary:hover { background: #333; }
        .tree .tl { padding-left: 22px; white-space: nowrap; }
        .tree .tl:hover { background: #2e2e2e; }
        .tree .k { color: #9cdcfe; }
        .tree .v.number { color: #b5cea8; }
        .tree .v.string { color: #ce9178; }
        .tree .v.arr { color: #b5cea8; }
        .tree .ty { color: #777; }
        .tree .hint { color: #6a9955; margin-left: 8px; }
        .tree .more { margin: 2px 0 2px 16px; }
        .hits .tl { padding-left: 0; white-space: normal; word-break: break-all; }
        .hits mark { background: #6b5a00; color: inherit; }

        /* margin:auto because global.css zeroes every margin, which breaks dialog centering */
        dialog { margin: auto; background: #222; color: var(--text); border: 1px solid #555; border-radius: 12px; padding: 20px; width: min(520px, 92vw); font: 15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        dialog h2 { font-size: 20px; margin-bottom: 6px; }
        dialog a { color: #8cc8ff; text-decoration: none; }
        dialog .btn { font-size: 14px; }
        dialog::backdrop { background: #000a; }
        dialog input { width: 100%; background: #161616; border: 1px solid #4a4a4a; color: var(--text); border-radius: 8px; padding: 8px 10px; margin: 4px 0 10px; }
        .err { background: #4a2424; border: 1px solid #8a3a3a; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; }

        /* ---------- world / server files ---------- */
        .sub { font-size: 12px; color: var(--muted); font-weight: 400; margin: 14px 0 6px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; flex: none; }
        .scroll { max-height: 420px; overflow-y: auto; }
        .searching .srow:not(.hit) { display: none; }
        .srow { flex-wrap: wrap; }
        .kv > * { min-width: 0; overflow-wrap: anywhere; }
        .rowi .ico { position: relative; width: 30px; height: 30px; flex: none; }
        .mapimg { display: block; width: 100%; max-width: 256px; image-rendering: pixelated; border-radius: 4px; box-shadow: 0 0 0 2px #000; background: #1a1a1a; }
        img[data-map] { image-rendering: pixelated; }
        .maps { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        .maps figure { margin: 0; font-size: 12px; color: var(--muted); }
        .maps figcaption { margin-top: 4px; }
        .modslots { display: flex; flex-wrap: wrap; gap: 6px 12px; max-width: calc(var(--s) * 9 + 4px); }
        .modslots .ms > span { display: block; font-size: 11px; color: #9a9a9a; margin: 0 0 2px 2px; }
        .modrow img { width: 32px; height: 32px; image-rendering: pixelated; flex: none; border-radius: 4px; }
        .modrow .desc { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        pre.cfg { white-space: pre-wrap; word-break: break-all; font-size: 12px; max-height: 420px; overflow: auto; background: #1c1c1c; padding: 8px; border-radius: 8px; margin: 6px 0 0; }
        .fileblk { margin-top: 6px; }
        .fileblk > summary { cursor: pointer; font-size: 13px; padding: 4px 8px; border-radius: 6px; background: var(--card2); overflow-wrap: anywhere; }
        .fileblk > .tree { margin-top: 6px; }

        /* ---------- schematic folder browser ---------- */
        .fbrowse { display: grid; grid-template-columns: minmax(160px, 240px) 1fr; gap: 12px; align-items: start; }
        .bpbrowse { display: grid; grid-template-columns: minmax(220px, 340px) 1fr; gap: 14px; align-items: start; }
        .bpbrowse button { text-align: left; }
        .bprows { max-height: 75vh; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
        .bpsel { min-width: 0; }
        .bphead { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
        .bphead .grow { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .swatch { display: inline-block; width: 14px; height: 14px; border-radius: 3px; vertical-align: -2px; border: 1px solid #0008; margin-right: 4px; }
        .bploc { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .linkish { background: none; border: 0; padding: 0; color: var(--accent); cursor: pointer; font: inherit; text-decoration: underline; }
        @media (max-width: 700px) { .bpbrowse { grid-template-columns: 1fr; } .bprows { max-height: 300px; } .bpsel { --s: clamp(26px, 7.4vw, 46px); } }
        .ftree, .fpane .rows { max-height: 420px; overflow-y: auto; }
        .ftree { display: flex; flex-direction: column; gap: 2px; background: #1c1c1c; border-radius: 8px; padding: 4px; }
        .fbrowse button { text-align: left; }
        .fnode { display: flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 6px; font-size: 13px; min-width: 0; }
        .fnode .grow { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fnode img { width: 16px; height: 16px; image-rendering: pixelated; flex: none; }
        .fnode small { color: var(--muted); }
        .fnode:hover, .fitem:hover { background: #2a2a2a; }
        .fnode.on { background: var(--card2); box-shadow: inset 2px 0 var(--accent); }
        .fitem { width: 100%; border: 1px solid transparent; }
        .fitem.on { border-color: var(--accent); }
        .fitem .grow { overflow-wrap: anywhere; }
        .fpane { min-width: 0; }
        .fcrumbs { display: flex; flex-wrap: wrap; gap: 4px; font-size: 13px; margin-bottom: 8px; color: var(--muted); }
        .fcrumbs button { color: var(--accent); }
        .fcrumbs button:hover { text-decoration: underline; }
        #player:not(.searching) .fitem[data-off], #player.searching .fitem:not(.srow) { display: none; }
        .fsel { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--line); }
        .fsel > h3 { margin: 0 0 8px; font-size: 15px; }
        .fsel > h3 .n { color: var(--muted); font-weight: 400; font-size: 12px; }
        @media (max-width: 640px) { .fbrowse { grid-template-columns: 1fr; } .ftree { max-height: 200px; } }
        .drop .srv-about { font-size: 13px; margin-top: 12px; }
        .srv { max-width: 440px; margin: 12px auto; font-size: 13px; text-align: center; }
        .srv .progress { height: 4px; margin: 6px 0 0; }
        .srv .progress i { width: 0; transition: width .15s; }
        .srv .progress.ind i { width: 30%; animation: srvind 1.1s linear infinite; }
        @keyframes srvind { from { transform: translateX(-100%); } to { transform: translateX(340%); } }
        @media (prefers-reduced-motion: reduce) { .srv .progress.ind i { width: 100%; opacity: .5; animation: none; } }
        .srv-act { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 8px; }
        .srv-act:empty { display: none; }
        .drop details { max-width: 760px; margin: 14px auto 0; text-align: left; font-size: 13px; }
        .drop details summary { cursor: pointer; text-align: center; color: var(--muted); }
        .drop details ul { margin: 8px 0 0; padding-left: 20px; line-height: 1.6; }
    </style>

    <div class="nbt" id="app">
        <div id="errors"></div>

        <section class="drop" id="drop">
            <img class="big-ico" src="api.php?item=chest" alt="" onerror="this.src='/minecraft/tools/item-list/minecraft_chest.png'">
            <h1>NBT Viewer</h1>
            <p>Drop one or more Minecraft player files here to explore their inventory, ender chest, stats and every last bit of NBT data.</p>
            <p class="muted">Files are read in your browser and only leave it if you press Share. Private details like IP addresses, chat and passwords are never shown or put in share links.<br>
                Player files live in <span class="mono">world/playerdata/&lt;uuid&gt;.dat</span> (<span class="mono">world/players/data/</span> since 26.1). Drop a whole world or server folder, or a <span class="mono">.zip</span> / <span class="mono">.tar.gz</span> backup, to pick up everything below at once.</p>
            <div class="row">
                <button class="btn primary" id="pick">Choose files</button>
                <button class="btn" id="pickDir">Choose folder</button>
                <button class="btn" data-example="Sampledata/playerdata-sample.dat|Sampledata/playerstats-sample.json|Sampledata/playeradvancement-sample.json">Try an example</button>
                <button class="btn" id="mySrv" data-srv="sync">View my server stats</button>
                <button class="btn" id="pickJar" title="Save a mod's item textures and names to this site">Upload mod jars</button>
            </div>
            <p class="muted srv-about">“View my server stats” opens every player on my Minecraft server and saves a copy in your browser, so next time only changed files are downloaded.</p>
            <p class="muted srv-about">“Upload mod jars” saves a mod's item textures and names to this site, so modded items show properly for everyone without the jar. Each mod only ever needs uploading once.</p>
            <div id="srvSync" class="srv hide" role="status" aria-live="polite"><div class="srv-msg"></div><div class="progress"><i></i></div><div class="srv-act"></div></div>
            <details>
                <summary>What can I drop?</summary>
                <ul>
                    <li><b>Players:</b> <span class="mono">playerdata/*.dat</span>, <span class="mono">stats/</span>, <span class="mono">advancements/</span>, SNBT from <span class="mono">/data get</span>, singleplayer <span class="mono">level.dat</span></li>
                    <li><b>Server:</b> <span class="mono">usercache.json</span>, <span class="mono">ops.json</span>, <span class="mono">whitelist.json</span>, <span class="mono">banned-players.json</span>, <span class="mono">banned-ips.json</span>, <span class="mono">server.properties</span>, <span class="mono">logs/</span> (sessions, deaths, chat)</li>
                    <li><b>World:</b> <span class="mono">level.dat</span>, <span class="mono">data/scoreboard.dat</span>, <span class="mono">data/map_*.dat</span> (maps drawn in the inventory), <span class="mono">data/command_storage_*.dat</span>, <span class="mono">uid.dat</span>, <span class="mono">region/</span> and <span class="mono">entities/</span> (placed backpacks, backpacks in chests and on the ground, owned pets, raw chunk NBT - a big world is only scanned when you press Scan)</li>
                    <li><b>Paper / Spigot / Bukkit:</b> <span class="mono">bukkit.yml</span>, <span class="mono">spigot.yml</span>, <span class="mono">config/paper-*.yml</span>, plugin configs, EssentialsX userdata, LuckPerms (YAML/JSON storage or <span class="mono">/lp export</span>), mcMMO, GriefPrevention, WorldGuard, Towny, Multiverse</li>
                    <li><b>Fabric / Forge / NeoForge:</b> <span class="mono">mods/*.jar</span> (names and icons for modded items - saved to the site so nobody has to drop that mod again), Trinkets and Curios slots, <span class="mono">config/</span>, <span class="mono">serverconfig/</span>, FTB Quests / Teams / Chunks, Sophisticated Backpacks</li>
                    <li><b>Client:</b> <span class="mono">hotbar.nbt</span> (saved creative hotbars), <span class="mono">servers.dat</span>, resource pack <span class="mono">.zip</span>s (local textures)</li>
                    <li><b>Builds:</b> <span class="mono">.schem</span>, <span class="mono">.litematic</span>, <span class="mono">.schematic</span> and structure <span class="mono">.nbt</span> files - size and material list</li>
                </ul>
            </details>
        </section>

        <div id="viewer" class="hide">
            <div class="bar">
                <div class="tabs" id="tabs"></div>
                <input class="search" id="q" type="search" placeholder="Search items, enchants, keys…  ( / )" autocomplete="off">
                <button class="btn small" id="add" title="Add more files">+ Add</button>
                <button class="btn small hide" id="srvBtn" title="World, server, plugin and mod files">World &amp; server</button>
                <button class="btn small hide" id="bpBtn" title="Every backpack found in the world, player files and backpack storage">Backpacks</button>
                <button class="btn small" id="rpBtn" title="Render items with custom resource packs">Resource packs</button>
                <button class="btn small primary" id="shareBtn" title="Create a link to this player">Share</button>
            </div>
            <div id="player"></div>
        </div>

        <input type="file" id="file" multiple hidden>
        <input type="file" id="dirFile" webkitdirectory multiple hidden>
        <input type="file" id="extraFile" multiple accept=".json" hidden>
        <input type="file" id="jarFile" multiple accept=".jar" hidden>
    </div>

    <div id="tip" class="hide"></div>
    <aside id="drawer" aria-hidden="true"></aside>
    <div id="dragOverlay" class="hide">Drop NBT files to load them</div>

    <dialog id="rpDlg">
        <h2 style="margin-top:0">Resource packs</h2>
        <p class="muted" style="font-size:14px">Item icons are rendered by <a href="https://www.minecraftitems.xyz/" target="_blank" rel="noopener">minecraftitems.xyz</a>. Tick any number of packs - like in game, packs higher in the list win when two change the same item. Custom model data is supported.</p>
        <div id="rpList" class="rows" style="margin-bottom:14px"></div>
        <label>Pack name<input id="rpName" maxlength="60" placeholder="My pack"></label>
        <label>Direct download link to the .zip<input id="rpUrl" placeholder="https://example.com/pack.zip"></label>
        <div id="rpMsg" class="muted" style="font-size:13px;min-height:1.4em"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
            <button class="btn" id="rpClose" autofocus>Close</button>
            <button class="btn primary" id="rpAdd">Upload pack</button>
        </div>
    </dialog>

    <dialog id="shareDlg">
        <h2 style="margin-top:0" id="shareTitle">Share player</h2>
        <div id="shareScope" class="hide" style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px;font-size:14px">
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="radio" name="shareScope" value="one" checked style="width:auto;margin:0"><span id="shareOneLbl">Just this player</span></label>
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="radio" name="shareScope" value="all" style="width:auto;margin:0"><span id="shareAllLbl">All players</span></label>
        </div>
        <p id="shareMsg" class="muted" style="font-size:14px"></p>
        <input id="shareUrl" readonly>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
            <button class="btn" id="shareClose">Close</button>
            <button class="btn primary" id="shareCopy" autofocus>Copy link</button>
        </div>
    </dialog>

    <script>
    (() => {
        'use strict';

        /* =====================================================================
           Small helpers
           ===================================================================== */
        const $ = (s, r = document) => r.querySelector(s);
        const $$ = (s, r = document) => [...r.querySelectorAll(s)];
        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const isObj = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);
        const strip = (id) => String(id || '').replace(/^minecraft:/, '');
        const pretty = (id) => strip(id).replace(/^[a-z0-9_.-]+:/, (m) => m).split(/[_:/.]/).filter(Boolean).map((w) => w[0].toUpperCase() + w.slice(1)).join(' ');
        const num = (n, d = 0) => (typeof n === 'number' ? n.toLocaleString(undefined, { maximumFractionDigits: d }) : n);
        const roman = (n) => {
            if (n < 1 || n > 3999 || n % 1) return String(n);
            const m = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']];
            let r = '';
            for (const [v, s] of m) while (n >= v) { r += s; n -= v; }
            return r;
        };
        const ticks = (t) => {
            if (t < 0) return '∞';
            const s = Math.floor(t / 20), h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), ss = s % 60;
            return h ? `${h}:${String(m).padStart(2, '0')}:${String(ss).padStart(2, '0')}` : `${m}:${String(ss).padStart(2, '0')}`;
        };
        const uuidOf = (a) => {
            if (!Array.isArray(a) || a.length !== 4) return null;
            const h = a.map((n) => (n >>> 0).toString(16).padStart(8, '0')).join('');
            return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
        };
        const store = {
            get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
            set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { } },
        };

        /* =====================================================================
           Parsing: binary NBT (gzip / zlib / raw), JSON, SNBT
           ===================================================================== */
        async function inflate(u) {
            let fmt = null;
            if (u[0] === 0x1f && u[1] === 0x8b) fmt = 'gzip';
            else if (u[0] === 0x78 && [0x01, 0x5e, 0x9c, 0xda].includes(u[1])) fmt = 'deflate';
            if (!fmt) return u;
            const out = await new Response(new Blob([u]).stream().pipeThrough(new DecompressionStream(fmt))).arrayBuffer();
            return new Uint8Array(out);
        }

        function readNBT(u) {
            const dv = new DataView(u.buffer, u.byteOffset, u.byteLength);
            const td = new TextDecoder();
            let p = 0;
            const str = () => { const n = dv.getUint16(p); p += 2; const s = td.decode(u.subarray(p, p + n)); p += n; return s; };
            const long = () => { const v = dv.getBigInt64(p); p += 8; return v <= 9007199254740991n && v >= -9007199254740991n ? Number(v) : v.toString(); };
            function pay(t) {
                switch (t) {
                    case 1: return dv.getInt8(p++);
                    case 2: { const v = dv.getInt16(p); p += 2; return v; }
                    case 3: { const v = dv.getInt32(p); p += 4; return v; }
                    case 4: return long();
                    case 5: { const v = dv.getFloat32(p); p += 4; return v; }
                    case 6: { const v = dv.getFloat64(p); p += 8; return v; }
                    case 7: { const n = dv.getInt32(p); p += 4; const a = Array.from(new Int8Array(u.buffer, u.byteOffset + p, n)); p += n; return a; }
                    case 8: return str();
                    case 9: { const et = dv.getUint8(p++); const n = dv.getInt32(p); p += 4; const a = []; for (let k = 0; k < n; k++) a.push(pay(et)); return a; }
                    case 10: { const o = {}; for (; ;) { const tt = dv.getUint8(p++); if (tt === 0) return o; const k = str(); o[k] = pay(tt); } }
                    case 11: { const n = dv.getInt32(p); p += 4; const a = []; for (let k = 0; k < n; k++) { a.push(dv.getInt32(p)); p += 4; } return a; }
                    case 12: { const n = dv.getInt32(p); p += 4; const a = []; for (let k = 0; k < n; k++) a.push(long()); return a; }
                    default: throw new Error('Unknown NBT tag type ' + t + ' at byte ' + (p - 1));
                }
            }
            const t = dv.getUint8(p++);
            if (t !== 10) throw new Error('This does not look like Java Edition NBT (Bedrock files are not supported).');
            str();
            return pay(10);
        }

        function parseSNBT(src) {
            const start = src.indexOf('{');
            if (start < 0) throw new Error('No { found');
            const s = src.slice(start);
            let i = 0;
            const ws = () => { while (i < s.length && /\s/.test(s[i])) i++; };
            const fail = () => { throw new Error('SNBT syntax error near "' + s.slice(Math.max(0, i - 15), i + 15) + '"'); };
            const quoted = () => {
                const q = s[i++]; let r = '';
                while (i < s.length && s[i] !== q) {
                    if (s[i] === '\\') { i++; const e = s[i]; r += e === 'n' ? '\n' : e === 't' ? '\t' : e; i++; continue; }
                    r += s[i++];
                }
                i++; return r;
            };
            const bare = (key) => { const st = i; while (i < s.length && (key ? /[0-9A-Za-z_\-.+]/ : /[0-9A-Za-z_\-.+:]/).test(s[i])) i++; return s.slice(st, i); };
            function val() {
                ws();
                const c = s[i];
                // FTB Quests style SNBT separates entries with newlines instead of commas - accept both
                if (c === '{') {
                    i++; const o = {};
                    for (; ;) {
                        ws(); if (s[i] === '}') { i++; return o; }
                        if (i >= s.length) fail();
                        const k = s[i] === '"' || s[i] === "'" ? quoted() : bare(true);
                        ws(); if (s[i++] !== ':') fail();
                        o[k] = val(); ws();
                        if (s[i] === ',') i++;
                    }
                }
                if (c === '[') {
                    i++; ws();
                    if (/[BIL]/.test(s[i]) && s[i + 1] === ';') i += 2;
                    const a = [];
                    for (; ;) {
                        ws(); if (s[i] === ']') { i++; return a; }
                        if (i >= s.length) fail();
                        a.push(val()); ws();
                        if (s[i] === ',') i++;
                    }
                }
                if (c === '"' || c === "'") return quoted();
                const t = bare();
                if (!t) fail();
                if (t === 'true') return 1;
                if (t === 'false') return 0;
                const m = /^([-+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?)[bslfd]?$/i.exec(t);
                return m ? Number(m[1]) : t;
            }
            return val();
        }

        async function parseFile(name, buf) {
            const u = await inflate(new Uint8Array(buf));
            // binary NBT starts with TAG_Compound (0x0a) followed by a 2-byte name length
            if (u[0] === 0x0a && u[1] === 0x00) return readNBT(u);
            const text = new TextDecoder().decode(u).replace(/^﻿/, '');
            try { return JSON.parse(text); } catch (e) { }
            return parseSNBT(text);
        }
        const inflateAs = async (u, fmt) => new Uint8Array(await new Response(new Blob([u]).stream().pipeThrough(new DecompressionStream(fmt))).arrayBuffer());
        const textOf = (u) => new TextDecoder().decode(u).replace(/^﻿/, '');

        // JSON with comments, trailing commas and bare keys (json5 configs, some mod metadata)
        function parseLoose(text) {
            try { return JSON.parse(text); } catch (e) { }
            let out = '', i = 0;
            while (i < text.length) {
                const c = text[i];
                if (c === '"' || c === "'") {
                    let j = i + 1, s = '';
                    while (j < text.length && text[j] !== c) { if (text[j] === '\\') { s += text[j] + text[j + 1]; j += 2; continue; } s += text[j++]; }
                    out += c === '"' ? '"' + s + '"' : '"' + s.replace(/\\'/g, "'").replace(/"/g, '\\"') + '"';
                    i = j + 1;
                    continue;
                }
                if (c === '/' && text[i + 1] === '/') { while (i < text.length && text[i] !== '\n') i++; continue; }
                if (c === '/' && text[i + 1] === '*') { const e = text.indexOf('*/', i + 2); i = e < 0 ? text.length : e + 2; continue; }
                out += c; i++;
            }
            return JSON.parse(out.replace(/,(\s*[}\]])/g, '$1').replace(/([{,]\s*)([A-Za-z_$][\w$-]*)(\s*:)/g, '$1"$2"$3'));
        }

        // Java .properties (server.properties, Towny data, old .lang files)
        function parseProps(text) {
            const unesc = (s) => s.replace(/\\(u[0-9a-fA-F]{4}|.)/g, (m, e) => (e.length === 5 ? String.fromCharCode(parseInt(e.slice(1), 16)) : { n: '\n', t: '\t', r: '\r' }[e] ?? e));
            const o = {};
            for (const raw of text.replace(/\\\r?\n[ \t]*/g, '').split(/\r?\n/)) {
                const l = raw.replace(/^\s+/, '');
                if (!l || l[0] === '#' || l[0] === '!') continue;
                const m = /^((?:\\.|[^=:\s\\])+)\s*[=:\s]?\s*(.*)$/.exec(l);
                if (m) o[unesc(m[1])] = unesc(m[2]);
            }
            return o;
        }

        // TOML (Forge/NeoForge configs and mods.toml) - dates are kept as text
        function parseTOML(s) {
            const root = {};
            let cur = root, i = 0;
            const fail = (m) => { throw new Error(`TOML ${m} on line ${s.slice(0, i).split('\n').length}`); };
            const ws = () => { while (s[i] === ' ' || s[i] === '\t') i++; };
            const wsnl = () => { for (; ;) { while (i < s.length && /\s/.test(s[i])) i++; if (s[i] !== '#') return; while (i < s.length && s[i] !== '\n') i++; } };
            const unesc = (r) => r.replace(/\\(u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8}|.)/g, (m, e) => (e.length > 1 ? String.fromCodePoint(parseInt(e.slice(1), 16)) : { n: '\n', t: '\t', r: '\r', b: '\b', f: '\f' }[e] ?? e));
            function str() {
                const q = s[i];
                if (s.startsWith(q + q + q, i)) {
                    i += 3;
                    if (s[i] === '\r') i++;
                    if (s[i] === '\n') i++;
                    const end = s.indexOf(q + q + q, i);
                    if (end < 0) fail('unclosed string');
                    let r = s.slice(i, end);
                    i = end + 3;
                    while (s[i] === q) { r += q; i++; }
                    return q === '"' ? unesc(r.replace(/\\\r?\n\s*/g, '')) : r;
                }
                i++;
                let r = '';
                while (i < s.length && s[i] !== q) {
                    if (s[i] === '\n') fail('newline in string');
                    if (q === '"' && s[i] === '\\') { r += s[i] + s[i + 1]; i += 2; continue; }
                    r += s[i++];
                }
                i++;
                return q === '"' ? unesc(r) : r;
            }
            function key() {
                const parts = [];
                for (; ;) {
                    ws();
                    if (s[i] === '"' || s[i] === "'") parts.push(str());
                    else { const st = i; while (i < s.length && /[A-Za-z0-9_-]/.test(s[i])) i++; if (st === i) fail('bad key'); parts.push(s.slice(st, i)); }
                    ws();
                    if (s[i] !== '.') return parts;
                    i++;
                }
            }
            const setPath = (o, parts, v) => { let t = o; parts.slice(0, -1).forEach((p) => { if (!isObj(t[p])) t[p] = {}; t = t[p]; }); t[parts[parts.length - 1]] = v; };
            function val() {
                ws();
                const c = s[i];
                if (c === '"' || c === "'") return str();
                if (c === '[') {
                    i++;
                    const a = [];
                    for (; ;) { wsnl(); if (s[i] === ']') { i++; return a; } if (i >= s.length) fail('unclosed array'); a.push(val()); wsnl(); if (s[i] === ',') i++; }
                }
                if (c === '{') {
                    i++;
                    const o = {};
                    for (; ;) { ws(); if (s[i] === '}') { i++; return o; } if (i >= s.length || s[i] === '\n') fail('unclosed table'); const k = key(); if (s[i++] !== '=') fail('expected ='); setPath(o, k, val()); ws(); if (s[i] === ',') i++; }
                }
                const st = i;
                while (i < s.length && !/[,\]}\r\n#]/.test(s[i])) i++;
                const t = s.slice(st, i).trim();
                if (!t) fail('missing value');
                if (t === 'true' || t === 'false') return t === 'true';
                const n = t.replace(/_/g, '');
                if (/^[+-]?(\d+(\.\d+)?([eE][+-]?\d+)?|0x[0-9a-fA-F]+|0o[0-7]+|0b[01]+|inf|nan)$/.test(n)) return Number(n.replace(/inf/, 'Infinity').replace(/nan/, 'NaN'));
                return t;
            }
            for (; ;) {
                wsnl();
                if (i >= s.length) return root;
                if (s[i] === '[') {
                    const arr = s[i + 1] === '[';
                    i += arr ? 2 : 1;
                    const parts = key();
                    i += arr ? 2 : 1;
                    let t = root;
                    parts.forEach((p, n) => {
                        if (n === parts.length - 1 && arr) { if (!Array.isArray(t[p])) t[p] = []; const o = {}; t[p].push(o); t = o; return; }
                        if (Array.isArray(t[p])) t = t[p][t[p].length - 1];
                        else { if (!isObj(t[p])) t[p] = {}; t = t[p]; }
                    });
                    cur = t;
                    continue;
                }
                const k = key();
                if (s[i++] !== '=') fail('expected =');
                setPath(cur, k, val());
            }
        }

        // YAML (Bukkit/Paper configs, plugin data) - js-yaml is loaded the first time it is needed
        let yamlLib = null;
        const loadYaml = () => yamlLib || (yamlLib = new Promise((res, rej) => {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/js-yaml/4.1.0/js-yaml.min.js';
            s.onload = () => res(window.jsyaml);
            s.onerror = () => { yamlLib = null; rej(new Error('the YAML parser could not be loaded')); };
            document.head.appendChild(s);
        }));
        // the JSON round trip turns YAML dates into plain strings
        const parseYAML = async (text) => JSON.parse(JSON.stringify((await loadYaml()).load(text) ?? null));

        /* ---------- archives: zip / jar (random access) and tar / tar.gz (streamed) ---------- */
        async function zipList(blob) {
            const tail = new Uint8Array(await blob.slice(Math.max(0, blob.size - 66000)).arrayBuffer());
            const tv = new DataView(tail.buffer);
            let eo = -1;
            for (let i = tail.length - 22; i >= 0; i--) if (tv.getUint32(i, true) === 0x06054b50) { eo = i; break; }
            if (eo < 0) throw new Error('not a zip file');
            let count = tv.getUint16(eo + 10, true), size = tv.getUint32(eo + 12, true), off = tv.getUint32(eo + 16, true);
            if ((off === 0xffffffff || count === 0xffff) && eo >= 20 && tv.getUint32(eo - 20, true) === 0x07064b50) {
                const z64 = Number(tv.getBigUint64(eo - 12, true));
                const z = new DataView(await blob.slice(z64, z64 + 56).arrayBuffer());
                count = Number(z.getBigUint64(32, true)); size = Number(z.getBigUint64(40, true)); off = Number(z.getBigUint64(48, true));
            }
            const cd = new Uint8Array(await blob.slice(off, off + size).arrayBuffer()), dv = new DataView(cd.buffer), td = new TextDecoder();
            const out = [];
            for (let p = 0, n = 0; n < count && p + 46 <= cd.length && dv.getUint32(p, true) === 0x02014b50; n++) {
                const nl = dv.getUint16(p + 28, true), xl = dv.getUint16(p + 30, true), cl = dv.getUint16(p + 32, true);
                const time = dv.getUint16(p + 12, true), date = dv.getUint16(p + 14, true);
                const z = { name: td.decode(cd.subarray(p + 46, p + 46 + nl)).replace(/\\/g, '/'), method: dv.getUint16(p + 10, true), csize: dv.getUint32(p + 20, true), usize: dv.getUint32(p + 24, true), loff: dv.getUint32(p + 42, true) };
                z.time = new Date(1980 + (date >> 9), ((date >> 5) & 15) - 1, date & 31, time >> 11, (time >> 5) & 63, (time & 31) * 2).getTime();
                // zip64: oversized values live in the extra field
                for (let x = p + 46 + nl; x + 4 <= p + 46 + nl + xl; x += 4 + dv.getUint16(x + 2, true)) {
                    if (dv.getUint16(x, true) !== 1) continue;
                    let y = x + 4;
                    if (z.usize === 0xffffffff) { z.usize = Number(dv.getBigUint64(y, true)); y += 8; }
                    if (z.csize === 0xffffffff) { z.csize = Number(dv.getBigUint64(y, true)); y += 8; }
                    if (z.loff === 0xffffffff) z.loff = Number(dv.getBigUint64(y, true));
                }
                out.push(z);
                p += 46 + nl + xl + cl;
            }
            return out;
        }
        async function zipData(blob, z) {
            const lh = new DataView(await blob.slice(z.loff, z.loff + 30).arrayBuffer());
            const start = z.loff + 30 + lh.getUint16(26, true) + lh.getUint16(28, true);
            return blob.slice(start, start + z.csize);
        }
        async function zipRead(blob, z) {
            const raw = await zipData(blob, z);
            if (z.method === 0) return new Uint8Array(await raw.arrayBuffer());
            if (z.method === 8) return new Uint8Array(await new Response(raw.stream().pipeThrough(new DecompressionStream('deflate-raw'))).arrayBuffer());
            throw new Error('unsupported zip compression (method ' + z.method + ')');
        }
        const zipBlobOf = async (blob, z) => (z.method === 0 ? zipData(blob, z) : new Blob([await zipRead(blob, z)]));

        // pulls exact byte counts out of a stream without copying everything that is skipped
        function byteReader(stream) {
            const r = stream.getReader(), q = [];
            let off = 0, avail = 0, done = false;
            const pull = async () => { const { value, done: d } = await r.read(); if (d) done = true; else if (value.length) { q.push(value); avail += value.length; } };
            async function consume(n, copy) {
                while (avail < n && !done) await pull();
                n = Math.min(n, avail);
                const out = copy ? new Uint8Array(n) : null;
                for (let w = 0; w < n;) {
                    const c = q[0], k = Math.min(n - w, c.length - off);
                    if (copy) out.set(c.subarray(off, off + k), w);
                    w += k; off += k;
                    if (off >= c.length) { q.shift(); off = 0; }
                }
                avail -= n;
                return out;
            }
            return {
                take: (n) => consume(n, true),
                async skip(n) { while (n > 0) { if (!avail) { if (done) return; await pull(); continue; } const k = Math.min(n, avail); await consume(k, false); n -= k; } },
            };
        }
        // calls want(path) for each file; keeps the bytes of the ones it accepts
        async function tarList(blob, gz, want) {
            let stream = blob.stream();
            if (gz) stream = stream.pipeThrough(new DecompressionStream('gzip'));
            const rd = byteReader(stream), td = new TextDecoder(), out = [];
            let longName = null, pax = null;
            for (; ;) {
                const h = await rd.take(512);
                if (!h || h.length < 512 || h.every((b) => b === 0)) return out;
                const str = (o, n) => { let e = o; while (e < o + n && h[e]) e++; return td.decode(h.subarray(o, e)); };
                const size = parseInt(str(124, 12).trim() || '0', 8) || 0, padded = Math.ceil(size / 512) * 512;
                const type = String.fromCharCode(h[156] || 48);
                let name = str(0, 100);
                if (str(257, 5) === 'ustar' && str(345, 155)) name = str(345, 155) + '/' + name;
                if (longName) name = longName;
                if (pax && pax.path) name = pax.path;
                longName = pax = null;
                if (type === 'L') { longName = td.decode((await rd.take(padded)).subarray(0, size)).replace(/\0+$/, ''); continue; }
                if (type === 'x') { pax = {}; for (const m of td.decode((await rd.take(padded)).subarray(0, size)).matchAll(/\d+ ([^=]+)=([^\n]*)\n/g)) pax[m[1]] = m[2]; continue; }
                const path = name.replace(/^\.\//, '');
                if ((type === '0' || type === '7') && size < 512 * 1024 * 1024 && want(path)) out.push({ path, data: (await rd.take(padded)).subarray(0, size) });
                else await rd.skip(padded);
            }
        }

        /* =====================================================================
           Game knowledge tables
           ===================================================================== */
        const MC_COLORS = {
            black: '#000000', dark_blue: '#0000AA', dark_green: '#00AA00', dark_aqua: '#00AAAA', dark_red: '#AA0000', dark_purple: '#AA00AA',
            gold: '#FFAA00', gray: '#AAAAAA', dark_gray: '#555555', blue: '#5555FF', green: '#55FF55', aqua: '#55FFFF', red: '#FF5555',
            light_purple: '#FF55FF', yellow: '#FFFF55', white: '#FFFFFF',
        };
        const CODE_COLORS = '0123456789abcdef'.split('').reduce((o, c, i) => (o[c] = Object.values(MC_COLORS)[i], o), {});
        const GAMEMODES = ['Survival', 'Creative', 'Adventure', 'Spectator'];
        const DIMS = { 'minecraft:overworld': ['Overworld', '#5fbf3f'], 'minecraft:the_nether': ['Nether', '#d0503a'], 'minecraft:the_end': ['The End', '#c9c27a'] };
        const dimName = (d) => (DIMS[d] || [pretty(d)])[0];
        const dimColor = (d) => (DIMS[d] || [0, '#5aa8ff'])[1];
        const VERSIONS = [[2586, '1.16.5'], [2730, '1.17.1'], [2975, '1.18.2'], [3120, '1.19.2'], [3218, '1.19.3'], [3337, '1.19.4'], [3463, '1.20'], [3465, '1.20.1'], [3578, '1.20.2'], [3698, '1.20.3'], [3700, '1.20.4'], [3837, '1.20.5'], [3839, '1.20.6'], [3953, '1.21'], [3955, '1.21.1'], [4080, '1.21.2'], [4082, '1.21.3'], [4189, '1.21.4'], [4325, '1.21.5'], [4435, '1.21.6'], [4438, '1.21.7'], [4440, '1.21.8'], [4554, '1.21.9'], [4556, '1.21.10']];
        const versionOf = (dv) => {
            if (typeof dv !== 'number') return null;
            let best = null;
            for (const [n, v] of VERSIONS) if (dv >= n) best = [n, v];
            if (!best) return 'pre-1.16';
            return best[0] === dv ? best[1] : best[1] + '+';
        };

        const TOOL_DUR = { wooden: 59, stone: 131, iron: 250, golden: 32, diamond: 1561, netherite: 2031, copper: 190 };
        const ARMOR_DUR = { leather: [55, 80, 75, 65], chainmail: [165, 240, 225, 195], iron: [165, 240, 225, 195], golden: [77, 112, 105, 91], diamond: [363, 528, 495, 429], netherite: [407, 592, 555, 481], copper: [121, 176, 165, 143] };
        const ARMOR_PTS = { leather: [1, 3, 2, 1], chainmail: [2, 5, 4, 1], iron: [2, 6, 5, 2], golden: [2, 5, 3, 1], diamond: [3, 8, 6, 3], netherite: [3, 8, 6, 3], copper: [2, 4, 3, 1] };
        const ARMOR_TOUGH = { diamond: 2, netherite: 3 };
        const PIECES = ['helmet', 'chestplate', 'leggings', 'boots'];
        const MISC_DUR = { elytra: 432, trident: 250, bow: 384, crossbow: 465, shield: 336, fishing_rod: 64, shears: 238, flint_and_steel: 64, carrot_on_a_stick: 25, warped_fungus_on_a_stick: 100, mace: 500, brush: 64, turtle_helmet: 275, wolf_armor: 64 };
        function maxDamageOf(id) {
            if (MISC_DUR[id]) return MISC_DUR[id];
            let m = /^(\w+?)_(sword|pickaxe|axe|shovel|hoe|spear)$/.exec(id);
            if (m && TOOL_DUR[m[1]]) return TOOL_DUR[m[1]];
            m = /^(\w+?)_(helmet|chestplate|leggings|boots)$/.exec(id);
            if (m && ARMOR_DUR[m[1]]) return ARMOR_DUR[m[1]][PIECES.indexOf(m[2])];
            return 0;
        }
        const EPIC = new Set('enchanted_golden_apple dragon_egg command_block chain_command_block repeating_command_block command_block_minecart structure_block structure_void jigsaw barrier light debug_stick elytra mace heavy_core'.split(' '));
        const RARE = new Set('beacon conduit end_crystal golden_apple trident nether_star totem_of_undying heart_of_the_sea sniffer_egg dragon_head enchanted_book'.split(' '));
        const UNCOMMON = new Set('experience_bottle dragon_breath skeleton_skull wither_skeleton_skull player_head zombie_head creeper_head piglin_head ominous_trial_key ominous_bottle trial_key'.split(' '));
        const SINGLE_LVL = new Set('mending silk_touch aqua_affinity flame infinity channeling multishot binding_curse vanishing_curse'.split(' '));
        const OLD_EFFECTS = [null, 'speed', 'slowness', 'haste', 'mining_fatigue', 'strength', 'instant_health', 'instant_damage', 'jump_boost', 'nausea', 'regeneration', 'resistance', 'fire_resistance', 'water_breathing', 'invisibility', 'blindness', 'night_vision', 'hunger', 'weakness', 'poison', 'wither', 'health_boost', 'absorption', 'saturation', 'glowing', 'levitation', 'luck', 'unluck', 'slow_falling', 'conduit_power', 'dolphins_grace', 'bad_omen', 'hero_of_the_village', 'darkness'];
        const EFFECT_COLOR = {
            speed: '#33ebff', slowness: '#8bafe0', haste: '#d9c043', mining_fatigue: '#4a4217', strength: '#ffc700', instant_health: '#f82423', instant_damage: '#a9656a',
            jump_boost: '#fdff84', nausea: '#551d4a', regeneration: '#cd5cab', resistance: '#9146f0', fire_resistance: '#ff9900', water_breathing: '#98dac0',
            invisibility: '#f6f6f6', blindness: '#1f1f23', night_vision: '#c2ff66', hunger: '#587653', weakness: '#484d48', poison: '#87a363', wither: '#736156',
            health_boost: '#f87d23', absorption: '#2552a5', saturation: '#f82423', glowing: '#94a061', levitation: '#ceffff', luck: '#59c106', unluck: '#c0a44d',
            slow_falling: '#f3cfb9', conduit_power: '#1dc2d1', dolphins_grace: '#88a3be', bad_omen: '#0b6138', hero_of_the_village: '#44ff44', darkness: '#292721',
            trial_omen: '#16a6a6', raid_omen: '#de4058', wind_charged: '#bdc9ff', weaving: '#78695a', oozing: '#99ffa3', infested: '#8c9b8c',
        };
        const BAD_EFFECTS = new Set('slowness mining_fatigue instant_damage nausea blindness hunger weakness poison wither glowing levitation unluck bad_omen darkness wind_charged weaving oozing infested'.split(' '));
        const ATTR_DEFAULT = {
            max_health: 20, max_absorption: 0, movement_speed: 0.1, attack_damage: 1, attack_speed: 4, attack_knockback: 0, armor: 0, armor_toughness: 0,
            knockback_resistance: 0, luck: 0, block_interaction_range: 4.5, entity_interaction_range: 3, step_height: 0.6, safe_fall_distance: 3,
            fall_damage_multiplier: 1, gravity: 0.08, jump_strength: 0.42, scale: 1, sneaking_speed: 0.3, submerged_mining_speed: 0.2, block_break_speed: 1,
            mining_efficiency: 0, oxygen_bonus: 0, water_movement_efficiency: 0, movement_efficiency: 0, burning_time: 1, explosion_knockback_resistance: 0,
            sweeping_damage_ratio: 0, flying_speed: 0.4, camera_distance: 4,
        };

        // What each top-level player key means (shown in the raw tree + "other data")
        const GLOSSARY = {
            DataVersion: 'Data format version - tells you which Minecraft version saved this file', Pos: 'Exact position [x, y, z]', Rotation: 'Facing direction [yaw, pitch] in degrees',
            Motion: 'Velocity [x, y, z] in blocks/tick', Dimension: 'Dimension the player is in', Health: 'Current health (2 = one heart)', AbsorptionAmount: 'Golden (absorption) hearts',
            foodLevel: 'Hunger bar (20 = full)', foodSaturationLevel: 'Hidden saturation - drains before hunger does', foodExhaustionLevel: 'Hidden exhaustion - at 4 it drains saturation/hunger',
            foodTickTimer: 'Timer for natural regen / starvation', XpLevel: 'Experience level', XpP: 'Progress to the next level (0-1)', XpTotal: 'Total XP points collected',
            XpSeed: 'Seed for the enchanting table offers', Score: 'Score shown on the death screen', Inventory: 'Main inventory and hotbar', EnderItems: 'Ender chest contents',
            equipment: 'Armor and offhand (1.21.5+)', SelectedItemSlot: 'Hotbar slot currently held (0-8)', playerGameType: 'Game mode: 0 survival, 1 creative, 2 adventure, 3 spectator',
            previousPlayerGameType: 'Game mode before the last switch (F3+F4)', abilities: 'Flight, build and invulnerability abilities', active_effects: 'Status effects currently active',
            ActiveEffects: 'Status effects currently active (pre-1.20.2)', attributes: 'Attributes like movement speed, reach and max health', Attributes: 'Attributes (pre-1.20.5)',
            recipeBook: 'Unlocked recipes and recipe book settings', respawn: 'Bed / respawn anchor spawn point', SpawnX: 'Respawn X (old format)', SpawnY: 'Respawn Y (old format)', SpawnZ: 'Respawn Z (old format)',
            LastDeathLocation: 'Where the player last died (used by recovery compasses)', ender_pearls: 'Thrown ender pearls that are still in flight / loaded', Air: 'Breath left underwater (300 = full)',
            Fire: 'Ticks until the fire goes out (-20 = not burning)', OnGround: 'Standing on a block', FallFlying: 'Gliding with an elytra', fall_distance: 'Blocks fallen so far',
            FallDistance: 'Blocks fallen so far', Invulnerable: 'Cannot take damage', PortalCooldown: 'Ticks before a portal can be used again', HurtTime: 'Red-flash ticks after damage',
            HurtByTimestamp: 'Game tick of the last time this player was hurt', DeathTime: 'Ticks spent dead (death animation)', SleepTimer: 'Ticks spent in a bed', seenCredits: 'Has seen the End credits (beaten the dragon)',
            UUID: 'Unique player ID', Brain: 'AI memories (unused for players)', warden_spawn_tracker: 'Sculk shrieker warnings - level 4 summons a warden', spawn_extra_particles_on_fall: 'Mace smash particle flag',
            current_impulse_context_reset_grace_time: 'Grace period after a wind charge / explosion launch', ignore_fall_damage_from_current_explosion: 'Wind charge fall-damage immunity',
            current_explosion_impact_pos: 'Where the last launching explosion happened', enteredNetherPosition: 'Where the player entered the Nether (advancement tracking)',
            RootVehicle: 'The entity being ridden when the player logged off', ShoulderEntityLeft: 'Parrot on the left shoulder', ShoulderEntityRight: 'Parrot on the right shoulder',
            'Bukkit.updateLevel': 'Bukkit/Spigot/Paper server data version', BukkitValues: 'Plugin data stored on the player (PersistentDataContainer)', bukkit: 'Bukkit server data',
            Paper: 'Paper server data', 'Paper.Origin': 'Where the entity was first created (Paper)', arcade: 'Data from the Arcade / Essential addons mod', 'neoforge:attachments': 'NeoForge mod data',
            ForgeCaps: 'Forge mod capabilities', ForgeData: 'Forge mod data', 'cardinal_components': 'Fabric Cardinal Components mod data', WorldUUIDLeast: 'World UUID (Bukkit)', WorldUUIDMost: 'World UUID (Bukkit)',
            'ender_pearl_dimension': 'Dimension the pearl is in', CustomName: 'Custom name', Tags: 'Scoreboard tags added with /tag',
        };

        // Vanilla advancements: "id|Title|icon" per tab, in in-game order. Unknown ids (mods/datapacks) still show, with a guessed name.
        const ADV = {
            story: ['root|Minecraft|grass_block', 'mine_stone|Stone Age|wooden_pickaxe', 'upgrade_tools|Getting an Upgrade|stone_pickaxe', 'smelt_iron|Acquire Hardware|iron_ingot', 'obtain_armor|Suit Up|iron_chestplate', 'lava_bucket|Hot Stuff|lava_bucket', 'iron_tools|Isn\'t It Iron Pick|iron_pickaxe', 'deflect_arrow|Not Today, Thank You|shield', 'form_obsidian|Ice Bucket Challenge|obsidian', 'mine_diamond|Diamonds!|diamond', 'enter_the_nether|We Need to Go Deeper|flint_and_steel', 'shiny_gear|Cover Me with Diamonds|diamond_chestplate', 'enchant_item|Enchanter|enchanted_book', 'cure_zombie_villager|Zombie Doctor|golden_apple', 'follow_ender_eye|Eye Spy|ender_eye', 'enter_the_end|The End?|end_stone'],
            nether: ['root|Nether|red_nether_bricks', 'return_to_sender|Return to Sender|fire_charge', 'find_bastion|Those Were the Days|polished_blackstone_bricks', 'obtain_ancient_debris|Hidden in the Depths|ancient_debris', 'fast_travel|Subspace Bubble|map', 'find_fortress|A Terrible Fortress|nether_bricks', 'obtain_crying_obsidian|Who is Cutting Onions?|crying_obsidian', 'distract_piglin|Oh Shiny|gold_ingot', 'ride_strider|This Boat Has Legs|warped_fungus_on_a_stick', 'uneasy_alliance|Uneasy Alliance|ghast_tear', 'loot_bastion|War Pigs|chest', 'use_lodestone|Country Lode, Take Me Home|lodestone', 'netherite_armor|Cover Me in Debris|netherite_chestplate', 'get_wither_skull|Spooky Scary Skeleton|wither_skeleton_skull', 'obtain_blaze_rod|Into Fire|blaze_rod', 'charge_respawn_anchor|Not Quite "Nine" Lives|respawn_anchor', 'ride_strider_in_overworld_lava|Feels Like Home|warped_fungus_on_a_stick', 'explore_nether|Hot Tourist Destinations|netherite_boots', 'summon_wither|Withering Heights|nether_star', 'brew_potion|Local Brewery|potion', 'create_beacon|Bring Home the Beacon|beacon', 'all_potions|A Furious Cocktail|milk_bucket', 'create_full_beacon|Beaconator|beacon', 'all_effects|How Did We Get Here?|bucket'],
            end: ['root|The End|end_stone', 'kill_dragon|Free the End|dragon_head', 'dragon_egg|The Next Generation|dragon_egg', 'enter_end_gateway|Remote Getaway|ender_pearl', 'respawn_dragon|The End... Again...|end_crystal', 'dragon_breath|You Need a Mint|dragon_breath', 'find_end_city|The City at the End of the Game|purpur_block', 'elytra|Sky\'s the Limit|elytra', 'levitate|Great View From Up Here|shulker_shell'],
            adventure: ['root|Adventure|map', 'voluntary_exile|Voluntary Exile|white_banner', 'spyglass_at_parrot|Is It a Bird?|spyglass', 'kill_a_mob|Monster Hunter|iron_sword', 'read_power_of_chiseled_bookshelf|The Power of Books|chiseled_bookshelf', 'trade|What a Deal!|emerald', 'trim_with_any_armor_pattern|Crafting a New Look|dune_armor_trim_smithing_template', 'honey_block_slide|Sticky Situation|honey_block', 'ol_betsy|Ol\' Betsy|crossbow', 'lightning_rod_with_villager_no_fire|Surge Protector|lightning_rod', 'fall_from_world_height|Caves & Cliffs|water_bucket', 'salvage_sherd|Respecting the Remnants|brush', 'avoid_vibration|Sneak 100|sculk_sensor', 'sleep_in_bed|Sweet Dreams|red_bed', 'hero_of_the_village|Hero of the Village|white_banner', 'spyglass_at_ghast|Is It a Balloon?|spyglass', 'throw_trident|A Throwaway Joke|trident', 'kill_mob_near_sculk_catalyst|It Spreads|sculk_catalyst', 'shoot_arrow|Take Aim|bow', 'kill_all_mobs|Monsters Hunted|diamond_sword', 'totem_of_undying|Postmortal|totem_of_undying', 'summon_iron_golem|Hired Help|carved_pumpkin', 'trade_at_world_height|Star Trader|emerald', 'trim_with_all_exclusive_armor_patterns|Smithing with Style|silence_armor_trim_smithing_template', 'two_birds_one_arrow|Two Birds, One Arrow|crossbow', 'whos_the_pillager_now|Who\'s the Pillager Now?|crossbow', 'arbalistic|Arbalistic|crossbow', 'craft_decorated_pot_using_only_sherds|Careful Restoration|decorated_pot', 'adventuring_time|Adventuring Time|diamond_boots', 'play_jukebox_in_meadows|Sound of Music|jukebox', 'walk_on_powder_snow_with_leather_boots|Light as a Rabbit|leather_boots', 'spyglass_at_dragon|Is It a Plane?|spyglass', 'very_very_frightening|Very Very Frightening|trident', 'sniper_duel|Sniper Duel|arrow', 'bullseye|Bullseye|target', 'minecraft_trials_edition|Minecraft: Trial(s) Edition|chiseled_tuff', 'under_lock_and_key|Under Lock and Key|trial_key', 'revaulting|Revaulting|ominous_trial_key', 'lighten_up|Lighten Up|copper_bulb', 'blowback|Blowback|wind_charge', 'who_needs_rockets|Who Needs Rockets?|wind_charge', 'crafters_crafting_crafters|Crafters Crafting Crafters|crafter', 'overoverkill|Over-Overkill|mace', 'heart_transplanter|Heart Transplanter|creaking_heart'],
            husbandry: ['root|Husbandry|hay_block', 'safely_harvest_honey|Bee Our Guest|honey_bottle', 'breed_an_animal|The Parrots and the Bats|wheat', 'allay_deliver_item_to_player|You\'ve Got a Friend in Me|cookie', 'ride_a_boat_with_a_goat|Whatever Floats Your Goat!|oak_boat', 'tame_an_animal|Best Friends Forever|lead', 'make_a_sign_glow|Glow and Behold!|glow_ink_sac', 'fishy_business|Fishy Business|fishing_rod', 'silk_touch_nest|Total Beelocation|bee_nest', 'tadpole_in_a_bucket|Bukkit Bukkit|tadpole_bucket', 'obtain_sniffer_egg|Smells Interesting|sniffer_egg', 'plant_seed|A Seedy Place|wheat_seeds', 'wax_on|Wax On|honeycomb', 'bred_all_animals|Two by Two|golden_carrot', 'allay_deliver_cake_to_note_block|Birthday Song|cake', 'complete_catalogue|A Complete Catalogue|cod', 'tactical_fishing|Tactical Fishing|pufferfish_bucket', 'leash_all_frog_variants|When the Squad Hops into Town|lead', 'feed_snifflet|Little Sniffs|torchflower_seeds', 'balanced_diet|A Balanced Diet|apple', 'obtain_netherite_hoe|Serious Dedication|netherite_hoe', 'wax_off|Wax Off|stone_axe', 'axolotl_in_a_bucket|The Cutest Predator|axolotl_bucket', 'froglights|With Our Powers Combined!|verdant_froglight', 'plant_any_sniffer_seed|Planting the Past|pitcher_pod', 'kill_axolotl_target|The Healing Power of Friendship!|tropical_fish_bucket', 'remove_wolf_armor|Shear Brilliance|shears', 'repair_wolf_armor|Good as New|wolf_armor', 'whole_pack|Whole Pack|bone', 'place_dried_ghast_in_water|Stay Hydrated!|dried_ghast'],
        };
        const ADV_INFO = {};
        for (const [cat, list] of Object.entries(ADV)) list.forEach((s) => { const [id, title, icon] = s.split('|'); ADV_INFO[`minecraft:${cat}/${id}`] = { title, icon }; });
        const ADV_CHALLENGE = new Set('nether/all_potions nether/all_effects nether/create_full_beacon nether/netherite_armor nether/explore_nether nether/uneasy_alliance end/respawn_dragon adventure/kill_all_mobs adventure/adventuring_time adventure/arbalistic adventure/two_birds_one_arrow adventure/sniper_duel adventure/bullseye adventure/trim_with_all_exclusive_armor_patterns adventure/very_very_frightening adventure/overoverkill husbandry/bred_all_animals husbandry/complete_catalogue husbandry/balanced_diet husbandry/obtain_netherite_hoe husbandry/whole_pack husbandry/leash_all_frog_variants husbandry/froglights'.split(' ').map((s) => 'minecraft:' + s));
        const ADV_CAT_ICON = { story: 'grass_block', nether: 'red_nether_bricks', end: 'end_stone', adventure: 'map', husbandry: 'hay_block' };

        // statistics: movement stats (cm) with colours for the travel bar, and how to label custom stats
        const TRAVEL = [['walk_one_cm', 'Walking', '#7ed957'], ['sprint_one_cm', 'Sprinting', '#c9e85a'], ['crouch_one_cm', 'Sneaking', '#8a9a5b'], ['swim_one_cm', 'Swimming', '#3d8cff'], ['walk_on_water_one_cm', 'On water', '#5ab4ff'], ['walk_under_water_one_cm', 'Under water', '#2a5fb0'], ['aviate_one_cm', 'Elytra', '#b77cff'], ['fly_one_cm', 'Flying', '#e6e6e6'], ['climb_one_cm', 'Climbing', '#a5763a'], ['fall_one_cm', 'Falling', '#d05050'], ['boat_one_cm', 'Boat', '#c08a4a'], ['minecart_one_cm', 'Minecart', '#9a9a9a'], ['horse_one_cm', 'Horse', '#8a5a2a'], ['pig_one_cm', 'Pig', '#f0a0b0'], ['strider_one_cm', 'Strider', '#b04848'], ['happy_ghast_one_cm', 'Happy ghast', '#f2f2f2'], ['nautilus_one_cm', 'Nautilus', '#4fb8a8']];
        const STAT_CATS = [['mined', 'Mined', 'diamond_pickaxe'], ['crafted', 'Crafted', 'crafting_table'], ['used', 'Used', 'flint_and_steel'], ['broken', 'Broken', 'iron_sword'], ['picked_up', 'Picked up', 'hopper'], ['dropped', 'Dropped', 'dropper'], ['killed', 'Killed', 'diamond_sword'], ['killed_by', 'Killed by', 'skeleton_skull'], ['custom', 'General', 'book']];
        function fmtStat(key, v) {
            const k = strip(key);
            if (/_one_cm$/.test(k)) return v >= 100000 ? num(v / 100000, v >= 1e7 ? 0 : 1) + ' km' : num(v / 100, 0) + ' m';
            if (/^(play_time|play_one_minute|total_world_time|time_since_death|time_since_rest|sneak_time)$/.test(k)) {
                const h = v / 72000;
                return h >= 1 ? num(h, 1) + ' h' : num(v / 1200, 1) + ' min';
            }
            if (/^damage_/.test(k)) return num(v / 20, 0) + ' hearts';
            return num(v);
        }
        // entities have no item render - show their spawn egg (or a stand-in) instead
        const MOB_ICON = { player: 'player_head', ender_dragon: 'dragon_head', wither: 'nether_star', giant: 'zombie_head', illusioner: 'bow', area_effect_cloud: 'lingering_potion', lightning_bolt: 'lightning_rod', tnt: 'tnt', falling_block: 'sand', arrow: 'arrow' };
        const mobIcon = (id) => MOB_ICON[strip(id)] || strip(id) + '_spawn_egg';
        // "mined" stats use block ids - some blocks have no item of their own (redstone_wire, carrots, oak_wall_sign...)
        const BLOCK_ITEM = {
            redstone_wire: 'redstone', tripwire: 'string', carrots: 'carrot', potatoes: 'potato', beetroots: 'beetroot', cocoa: 'cocoa_beans',
            sweet_berry_bush: 'sweet_berries', cave_vines: 'glow_berries', cave_vines_plant: 'glow_berries', torchflower_crop: 'torchflower_seeds',
            pitcher_crop: 'pitcher_pod', melon_stem: 'melon_seeds', attached_melon_stem: 'melon_seeds', pumpkin_stem: 'pumpkin_seeds',
            attached_pumpkin_stem: 'pumpkin_seeds', kelp_plant: 'kelp', bamboo_sapling: 'bamboo', twisting_vines_plant: 'twisting_vines',
            weeping_vines_plant: 'weeping_vines', tall_seagrass: 'seagrass', big_dripleaf_stem: 'big_dripleaf', water: 'water_bucket',
            lava: 'lava_bucket', bubble_column: 'water_bucket', powder_snow: 'powder_snow_bucket', fire: 'flint_and_steel', soul_fire: 'flint_and_steel',
            frosted_ice: 'ice', piston_head: 'piston', moving_piston: 'piston', nether_portal: 'obsidian', end_portal: 'end_portal_frame',
            end_gateway: 'end_portal_frame', redstone_wall_torch: 'redstone_torch',
        };
        const blockIcon = (id) => {
            const b = strip(id);
            if (BLOCK_ITEM[b]) return BLOCK_ITEM[b];
            if (b.startsWith('potted_')) return 'flower_pot';
            if (b.endsWith('candle_cake')) return 'cake';
            if (b === 'wall_torch') return 'torch';
            return b.replace(/_wall_(torch|sign|hanging_sign|banner|head|skull|fan)$/, '_$1');
        };

        // map item colours: base colour id = byte >> 2, brightness = byte & 3
        const MAP_BASE = [null, [127, 178, 56], [247, 233, 163], [199, 199, 199], [255, 0, 0], [160, 160, 255], [167, 167, 167], [0, 124, 0], [255, 255, 255], [164, 168, 184], [151, 109, 77], [112, 112, 112], [64, 64, 255], [143, 119, 72], [255, 252, 245], [216, 127, 51], [178, 76, 216], [102, 153, 216], [229, 229, 51], [127, 204, 25], [242, 127, 165], [76, 76, 76], [153, 153, 153], [76, 127, 153], [127, 63, 178], [51, 76, 178], [102, 76, 51], [102, 127, 51], [153, 51, 51], [25, 25, 25], [250, 238, 77], [92, 219, 213], [74, 128, 255], [0, 217, 58], [129, 86, 49], [112, 2, 0], [209, 177, 161], [159, 82, 36], [149, 87, 108], [112, 108, 138], [186, 133, 36], [103, 117, 53], [160, 77, 78], [57, 41, 35], [135, 107, 98], [87, 92, 92], [122, 73, 88], [76, 62, 92], [76, 50, 35], [76, 82, 42], [142, 60, 46], [37, 22, 16], [189, 48, 49], [148, 63, 97], [92, 25, 29], [22, 126, 134], [58, 142, 140], [86, 44, 62], [20, 180, 133], [100, 100, 100], [216, 175, 147], [127, 167, 150]];
        const MAP_SHADE = [180, 220, 255, 135];

        // mcMMO flatfile columns: [skill, level column, xp column]
        const MCMMO = [['Mining', 1, 4], ['Woodcutting', 5, 6], ['Repair', 7, 15], ['Unarmed', 8, 16], ['Herbalism', 9, 17], ['Excavation', 10, 18], ['Archery', 11, 19], ['Swords', 12, 20], ['Axes', 13, 21], ['Acrobatics', 14, 22], ['Taming', 24, 25], ['Fishing', 34, 35], ['Alchemy', 39, 40], ['Crossbows', 46, 45], ['Tridents', 48, 47], ['Maces', 53, 52]];
        const MCMMO_ICON = { Mining: 'iron_pickaxe', Woodcutting: 'iron_axe', Repair: 'anvil', Unarmed: 'leather', Herbalism: 'wheat', Excavation: 'iron_shovel', Archery: 'bow', Swords: 'iron_sword', Axes: 'golden_axe', Acrobatics: 'feather', Taming: 'bone', Fishing: 'fishing_rod', Alchemy: 'brewing_stand', Crossbows: 'crossbow', Tridents: 'trident', Maces: 'mace' };

        // config keys whose values are hidden until clicked (and never shared)
        const PRIVATE_KEY = /pass(word)?|secret|token|api[-_.]?key|private[-_.]?key|webhook|seed|^server-ip$|^ip$|last-?ip|ip-?address|^mail$|credential|salt|jdbc|license/i;
        const DEATH_RE = /^(was |were |died|drowned|blew up|hit the ground|fell |went up in flames|went off with a bang|walked into|burned to death|tried to swim in lava|suffocated|starved|froze to death|withered away|experienced kinetic energy|discovered the floor|didn't want to live|left the confines|is no more)/;

        /* =====================================================================
           Text components (names, lore)
           ===================================================================== */
        function textHTML(comp, base = {}) {
            if (comp === null || comp === undefined) return '';
            if (typeof comp === 'string') {
                const t = comp.trim();
                if ((t.startsWith('{') && t.endsWith('}')) || (t.startsWith('[') && t.endsWith(']')) || (t.startsWith('"') && t.endsWith('"'))) {
                    try { return textHTML(JSON.parse(t), base); } catch (e) { }
                }
                return legacyHTML(comp, base);
            }
            if (typeof comp === 'number') return legacyHTML(String(comp), base);
            if (Array.isArray(comp)) {
                if (!comp.length) return '';
                const first = isObj(comp[0]) ? comp[0] : { text: comp[0] };
                const st = styleOf(first, base);
                return textHTML(first, base) + comp.slice(1).map((c) => textHTML(c, st)).join('');
            }
            if (!isObj(comp)) return '';
            const st = styleOf(comp, base);
            let txt = '';
            if (comp.text !== undefined) txt = String(comp.text);
            else if (comp.translate !== undefined) txt = translate(comp);
            else if (comp.score) txt = '[score]';
            else if (comp.selector) txt = comp.selector;
            else if (comp.keybind) txt = '[' + comp.keybind + ']';
            else if (comp[''] !== undefined) txt = String(comp['']);
            let out = txt ? legacyHTML(txt, st) : '';
            if (Array.isArray(comp.extra)) out += comp.extra.map((c) => textHTML(c, st)).join('');
            return out;
        }
        function translate(c) {
            const k = String(c.translate);
            const m = /^(?:item|block|entity|enchantment|effect)\.(?:minecraft\.)?(.+)$/.exec(k);
            if (m) return pretty(m[1]);
            if (c.fallback && !/^[a-z]+\.[a-z_.]+$/.test(c.fallback)) return c.fallback;
            if (Array.isArray(c.with)) return k + ' ' + c.with.map((w) => (typeof w === 'string' ? w : w.text || '')).join(' ');
            return k;
        }
        const truthy = (v) => v === true || v === 1 || v === '1' || v === 'true';
        function styleOf(c, base) {
            const st = { ...base };
            if (c.color) st.color = MC_COLORS[c.color] || (String(c.color).startsWith('#') ? c.color : st.color);
            for (const k of ['bold', 'italic', 'underlined', 'strikethrough', 'obfuscated']) if (c[k] !== undefined) st[k] = truthy(c[k]);
            return st;
        }
        function spanFor(t, st) {
            if (!t) return '';
            let css = '';
            if (st.color) css += `color:${st.color};`;
            if (st.bold) css += 'font-weight:700;';
            if (st.italic) css += 'font-style:italic;';
            const deco = [st.underlined && 'underline', st.strikethrough && 'line-through'].filter(Boolean).join(' ');
            if (deco) css += `text-decoration:${deco};`;
            if (st.obfuscated) css += 'filter:blur(2px);';
            return css ? `<span style="${css}">${esc(t)}</span>` : esc(t);
        }
        function legacyHTML(s, st) {
            if (!s.includes('§')) return spanFor(s, st);
            const parts = s.split('§');
            let cur = { ...st }, out = spanFor(parts[0], cur);
            for (const part of parts.slice(1)) {
                const code = part[0] ? part[0].toLowerCase() : '';
                const rest = part.slice(1);
                if (CODE_COLORS[code]) cur = { color: CODE_COLORS[code] };
                else if (code === 'l') cur.bold = true; else if (code === 'o') cur.italic = true;
                else if (code === 'n') cur.underlined = true; else if (code === 'm') cur.strikethrough = true;
                else if (code === 'k') cur.obfuscated = true; else if (code === 'r') cur = { ...st };
                out += spanFor(rest, cur);
            }
            return out;
        }
        const plain = (html) => html.replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'");

        /* =====================================================================
           Item model - handles 1.20.5+ components and older "tag" items
           ===================================================================== */
        function enchList(e, stored) {
            if (!e) return [];
            if (Array.isArray(e)) return e.map((x) => ({ id: strip(x.id), lvl: x.lvl ?? x.level ?? 1, stored }));
            const lv = isObj(e.levels) ? e.levels : e;
            return Object.entries(lv).filter(([k, v]) => typeof v === 'number').map(([k, v]) => ({ id: strip(k), lvl: v, stored }));
        }

        function containerSize(id, contents) {
            const fixed = { hopper: 5, hopper_minecart: 5, dispenser: 9, dropper: 9, crafter: 9, brewing_stand: 5, furnace: 3, blast_furnace: 3, smoker: 3, campfire: 4, soul_campfire: 4, chiseled_bookshelf: 6, decorated_pot: 1, jukebox: 1, lectern: 1 };
            const maxSlot = contents.reduce((m, c) => Math.max(m, c.slot ?? 0), -1) + 1;
            return Math.max(fixed[id] || 27, maxSlot);
        }

        // a player head's skin, as something mc-heads.net understands: texture hash > uuid > name.
        // minecraft:profile (1.20.5+) or SkullOwner (older) - a plain name string or {name, id, properties}
        function headOf(p) {
            if (typeof p === 'string') return /^\w{1,16}$/.test(p) ? p : null;
            if (!isObj(p)) return null;
            const props = p.properties ?? p.Properties;
            const tex = Array.isArray(props) ? props.find((x) => isObj(x) && x.name === 'textures') : isObj(props) && Array.isArray(props.textures) ? props.textures[0] : null;
            const val = isObj(tex) ? tex.value ?? tex.Value : null;
            if (typeof val === 'string') {
                try {
                    const m = /textures\.minecraft\.net\/texture\/([0-9a-f]{32,64})/i.exec(atob(val));
                    if (m) return m[1];
                } catch (e) { }
            }
            const id = Array.isArray(p.id ?? p.Id) ? uuidOf(p.id ?? p.Id) : typeof (p.id ?? p.Id) === 'string' ? p.id ?? p.Id : null;
            if (id && UUID_RE.test(id)) return id.replace(/-/g, '');
            const name = p.name ?? p.Name;
            return typeof name === 'string' && /^\w{1,16}$/.test(name) ? name : null;
        }

        function normItem(r) {
            if (!isObj(r) || !r.id) return null;
            const full = String(r.id);
            const id = strip(full);
            const c = isObj(r.components) ? r.components : {};
            const tag = isObj(r.tag) ? r.tag : {};
            const disp = isObj(tag.display) ? tag.display : {};
            const it = { raw: r, full, id, base: id.includes(':') ? id.split(':').pop() : id, mod: id.includes(':') ? id.split(':')[0] : '', count: r.count ?? r.Count ?? 1, c, tag };
            it.customName = c['minecraft:custom_name'] ?? disp.Name;
            it.itemName = c['minecraft:item_name'];
            it.lore = c['minecraft:lore'] ?? disp.Lore ?? [];
            if (!Array.isArray(it.lore)) it.lore = [it.lore];
            it.ench = enchList(c['minecraft:enchantments'] ?? tag.Enchantments ?? tag.ench).concat(enchList(c['minecraft:stored_enchantments'] ?? tag.StoredEnchantments, true));
            it.damage = c['minecraft:damage'] ?? tag.Damage ?? 0;
            it.maxDamage = c['minecraft:max_damage'] ?? maxDamageOf(it.base);
            it.unbreakable = 'minecraft:unbreakable' in c || truthy(tag.Unbreakable);
            const go = c['minecraft:enchantment_glint_override'];
            it.glint = go !== undefined ? truthy(go) : it.ench.length > 0 || ['enchanted_golden_apple', 'experience_bottle', 'nether_star', 'end_crystal', 'debug_stick', 'written_book', 'enchanted_book'].includes(it.base);
            const cmd = c['minecraft:custom_model_data'] ?? tag.CustomModelData;
            it.cmd = typeof cmd === 'number' ? cmd : isObj(cmd) && Array.isArray(cmd.floats) && cmd.floats.length ? cmd.floats[0] : null;
            if (it.base === 'player_head') it.head = headOf(c['minecraft:profile'] ?? tag.SkullOwner);
            it.rarity = c['minecraft:rarity'] || (EPIC.has(it.base) ? 'epic' : RARE.has(it.base) ? 'rare' : UNCOMMON.has(it.base) ? 'uncommon' : 'common');
            if (it.ench.length && it.rarity !== 'epic') it.rarity = it.rarity === 'rare' ? 'epic' : 'rare';

            const cont = c['minecraft:container'] ?? (isObj(tag.BlockEntityTag) ? tag.BlockEntityTag.Items : undefined) ?? (isObj(c['minecraft:block_entity_data']) ? c['minecraft:block_entity_data'].Items : undefined);
            if (Array.isArray(cont)) {
                it.contents = cont.map((x) => (isObj(x.item) ? { slot: x.slot ?? 0, item: normItem(x.item) } : { slot: x.Slot ?? x.slot ?? 0, item: normItem(x) })).filter((x) => x.item);
                it.size = containerSize(it.base, it.contents);
            }
            const bun = c['minecraft:bundle_contents'] ?? (/bundle$/.test(it.base) ? tag.Items : undefined);
            if (Array.isArray(bun)) it.bundle = bun.map(normItem).filter(Boolean);
            const mapId = c['minecraft:map_id'] ?? tag.map;
            if (typeof mapId === 'number') it.mapId = mapId;
            // storage mods (Sophisticated Backpacks...) keep the contents in world data, keyed by a uuid on the item
            if (!it.contents && CTX.backpacks.size) {
                const u = storageUuid(c) || storageUuid(tag);
                if (u && CTX.backpacks.has(u)) {
                    it.bpUuid = u;
                    it.contents = CTX.backpacks.get(u).map((x, i) => ({ slot: x.Slot ?? x.slot ?? i, item: normItem(x) })).filter((x) => x.item);
                    it.size = containerSize(it.base, it.contents);
                }
            }
            const rc = RARITY_COLOR[it.rarity] || '#FFFFFF';
            const name = it.customName !== undefined ? textHTML(it.customName, { color: rc, italic: true }) : it.itemName !== undefined ? textHTML(it.itemName, { color: rc }) : spanFor(langName(it) || pretty(it.base), { color: rc });
            it.nameHTML = name;
            it.namePlain = plain(name);
            return it;
        }

        const RARITY_COLOR = { common: '#FFFFFF', uncommon: '#FFFF55', rare: '#55FFFF', epic: '#FF55FF' };

        function storageUuid(o) {
            if (!isObj(o)) return null;
            for (const [k, v] of Object.entries(o)) {
                if (!/uuid/i.test(k)) continue;
                const u = normUuid(v);
                if (u) return u;
            }
            return null;
        }
        const dashUuid = (k) => (k && k.length === 32 ? `${k.slice(0, 8)}-${k.slice(8, 12)}-${k.slice(12, 16)}-${k.slice(16, 20)}-${k.slice(20)}` : k);
        const normUuid = (v) => {
            if (Array.isArray(v) && v.length === 4 && v.every(Number.isInteger)) return uuidOf(v);
            const m = typeof v === 'string' && v.match(/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i);
            return m ? dashUuid(m[0].toLowerCase().replace(/-/g, '')) : null;
        };
        // Bukkit stores the world a player is in as two longs
        const worldUuid = (d) => {
            if (d.WorldUUIDMost === undefined || d.WorldUUIDLeast === undefined) return null;
            try {
                const h = (v) => BigInt.asUintN(64, BigInt(v)).toString(16).padStart(16, '0');
                return dashUuid(h(d.WorldUUIDMost) + h(d.WorldUUIDLeast));
            } catch (e) { return null; }
        };

        /* ---------- names + textures from dropped mod jars and resource packs ---------- */
        const LANG = new Map();         // translation key -> English name
        const TEXREF = new Map();       // "namespace:name" -> {blob, e} zip entry, read the first time it is shown
        const TEX = new Map();          // "namespace:name" -> object URL
        const BLANK = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        const langName = (it) => (it.mod ? langGet(it.mod, [`item.${it.mod}.${it.base}`, `block.${it.mod}.${it.base}`]) : null);
        const langId = (id) => { const m = /^([a-z0-9_.-]+):(.+)$/.exec(String(id)); return m && m[1] !== 'minecraft' ? langGet(m[1], [`item.${m[1]}.${m[2]}`, `block.${m[1]}.${m[2]}`, `entity.${m[1]}.${m[2]}`]) : null; };
        function langGet(ns, keys) {
            for (const k of keys) if (LANG.has(k)) return LANG.get(k);
            const s = SRVMODS.get(ns);
            if (s && s.l && !langAsked.has(ns)) wantLang(ns);
            return null;
        }

        /* ---------- mod textures + names saved on the site (cache/mods, see api.php) ---------- */
        // namespace -> {t: textures, l: names}. Uploaded once by anyone, used by everyone after that.
        const SRVMODS = new Map();
        const srvModsReady = fetch('api.php?mods=index').then((r) => (r.ok ? r.json() : null)).then((j) => {
            if (!isObj(j)) return false;
            for (const [ns, v] of Object.entries(j)) if (isObj(v)) SRVMODS.set(ns, v);
            if (SRVMODS.size && (players.length || CTX.files.length)) srvModsRefresh();
            return true;
        }).catch(() => false);
        const srvTex = (key) => { const ns = key.slice(0, key.indexOf(':')), s = SRVMODS.get(ns); return ns !== 'minecraft' && s && s.t ? 'api.php?tex=' + encodeURIComponent(key) : null; };
        // names are fetched per namespace the first time an item from that mod is shown
        const langAsked = new Set(), langWant = new Set();
        let langTimer = 0;
        function wantLang(ns) {
            langAsked.add(ns);
            langWant.add(ns);
            clearTimeout(langTimer);
            langTimer = setTimeout(async () => {
                const list = [...langWant];
                langWant.clear();
                try {
                    const j = await (await fetch('api.php?mods=lang&ns=' + encodeURIComponent(list.join(',')))).json();
                    let n = 0;
                    if (isObj(j)) for (const [k, v] of Object.entries(j)) if (typeof v === 'string' && !LANG.has(k)) { LANG.set(k, v); n++; }
                    if (n) srvModsRefresh();
                } catch (e) { }
            }, 30);
        }
        // item names are worked out when a player is analysed, so redo that and draw again
        function srvModsRefresh() {
            if (batch) return setTimeout(srvModsRefresh, 200);
            players.forEach((p) => (p.P = analyse(p.P.d)));
            render();
        }
        // after a mod jar is read: send the textures and names the site doesn't have yet, in batches of ~3 MB
        let modQueue = Promise.resolve(), modTally = null;
        function saveModToSite(info, blob, tex, names) {
            if (!tex.length && !Object.keys(names).length) return;
            const tally = (modTally = modTally || { mods: 0, tex: 0, names: 0, err: '', pending: 0 });
            tally.pending++;
            modQueue = modQueue.then(async () => {
                if (!await srvModsReady) return;
                const count = (list) => list.reduce((m, ns) => m.set(ns, (m.get(ns) || 0) + 1), new Map());
                const texNs = count(tex.map(([k]) => k.split(':')[0])), nameNs = count(Object.keys(names).map((k) => k.split('.')[1]));
                const short = (ns, n, f) => ((SRVMODS.get(ns) || {})[f] || 0) < n;
                const sendTex = tex.filter(([k]) => { const ns = k.split(':')[0]; return short(ns, texNs.get(ns), 't'); });
                const sendNames = [...nameNs].some(([ns, n]) => short(ns, n, 'l')) ? names : {};
                if (!sendTex.length && !Object.keys(sendNames).length) return;
                const mod = { id: info.id, name: info.name, version: info.version, loader: info.loader };
                const counts = { t: Object.fromEntries(texNs), l: Object.fromEntries(nameNs) };
                let part = { mod, counts, lang: sendNames, tex: {} }, size = 0, got = 0;
                const send = async () => {
                    const r = await fetch('api.php?mods=upload', { method: 'POST', headers: { 'Content-Type': 'application/octet-stream' }, body: await gzip(JSON.stringify(part)) });
                    const j = await r.json().catch(() => ({}));
                    if (!r.ok) throw new Error(j.error || 'HTTP ' + r.status);
                    tally.tex += j.textures || 0;
                    tally.names += j.names || 0;
                    got += (j.textures || 0) + (j.names || 0);
                    part = { mod, counts, lang: {}, tex: {} };
                    size = 0;
                };
                for (const [k, z] of sendTex) {
                    const u = await zipRead(blob, z);
                    if (u.length > 256 * 1024) continue;
                    part.tex[k] = b64(u);
                    size += u.length;
                    if (size > 3 * 1024 * 1024) await send();
                }
                if (size || Object.keys(part.lang).length) await send();
                if (got) tally.mods++;
                for (const [ns, n] of texNs) { const s = SRVMODS.get(ns) || { t: 0, l: 0 }; s.t = Math.max(s.t, n); SRVMODS.set(ns, s); }
                for (const [ns, n] of nameNs) { const s = SRVMODS.get(ns) || { t: 0, l: 0 }; s.l = Math.max(s.l, n); SRVMODS.set(ns, s); }
            }).catch((err) => { console.warn('mod upload', info.file, err); tally.err = err.message; }).then(() => {
                if (--tally.pending) return;
                if (modTally === tally) modTally = null;
                if (tally.mods) toast(`Saved ${num(tally.tex)} textures and ${num(tally.names)} names from ${tally.mods} mod${tally.mods === 1 ? '' : 's'} to the site - nobody needs to upload ${tally.mods === 1 ? 'it' : 'them'} again.`, 'note');
                else if (tally.err) toast(`Could not save the mod textures to the site: ${tally.err}`, 'err');
            });
        }
        const texKey = (it) => { const id = typeof it === 'string' ? strip(it) : it.id; return id.includes(':') ? id : 'minecraft:' + id; };
        function texURL(key) {
            if (TEX.has(key)) return TEX.get(key);
            const ref = TEXREF.get(key);
            if (!ref) return null;
            TEX.set(key, BLANK);
            zipRead(ref.blob, ref.e).then((u) => {
                const url = URL.createObjectURL(new Blob([u], { type: 'image/png' }));
                TEX.set(key, url);
                $$(`img[data-tex="${CSS.escape(key)}"]`).forEach((img) => {
                    img.src = url;
                    if (img.parentElement && img.parentElement.style.getPropertyValue('--m')) img.parentElement.style.setProperty('--m', `url("${url}")`);
                });
            }).catch(() => TEX.set(key, MISSING));
            return BLANK;
        }
        const texImg = (key, fallback) => (TEXREF.has(key) ? `<img src="${texURL(key)}" data-tex="${esc(key)}" data-f="2" alt="" loading="lazy">` : imgTag(fallback));

        // filled maps: drawn from data/map_<id>.dat
        function mapURL(id) {
            const m = CTX.maps.get(String(id));
            if (!m) return null;
            if (m.url) return m.url;
            const cv = document.createElement('canvas');
            cv.width = cv.height = 128;
            const g = cv.getContext('2d'), img = g.createImageData(128, 128);
            for (let i = 0; i < 16384; i++) {
                const v = m.colors[i] || 0, base = MAP_BASE[v >> 2];
                if (!base) continue;
                const s = MAP_SHADE[v & 3];
                img.data[i * 4] = (base[0] * s) / 255;
                img.data[i * 4 + 1] = (base[1] * s) / 255;
                img.data[i * 4 + 2] = (base[2] * s) / 255;
                img.data[i * 4 + 3] = 255;
            }
            g.putImageData(img, 0, 0);
            return (m.url = cv.toDataURL());
        }

        /* =====================================================================
           Icons (minecraftitems.xyz via api.php, local textures, missing texture)
           ===================================================================== */
        const MISSING = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2" shape-rendering="crispEdges"><rect width="2" height="2" fill="#000"/><rect width="1" height="1" fill="#f0f"/><rect x="1" y="1" width="1" height="1" fill="#f0f"/></svg>');
        // resource packs: [{id, name, status, on, def?}] in priority order (first = highest)
        // def = file name of a site pack from ./resourcepacks (offered to everyone, can't be removed)
        function rpPacks() {
            const packs = store.get('nbt.rp.packs', []);
            const old = store.get('nbt.rp.active', null);      // single-pack setting from before multi-pack support
            if (typeof old === 'string') { packs.forEach((p) => (p.on = p.id === old)); store.set('nbt.rp.packs', packs); store.set('nbt.rp.active', null); }
            return packs;
        }
        let rpActive = '';
        // packs that came with a share link: used instead of your own ticks until you keep or dismiss them
        let rpShared = null;
        const rpUsed = () => (rpShared || rpPacks().filter((p) => p.on && p.id)).slice(0, 5);
        const refreshRp = () => (rpActive = rpUsed().map((p) => p.id).join(','));
        refreshRp();
        // the API draws these as flat squares - the local textures are proper 3D icons
        const LOCAL_FIRST = /shulker_box$/;
        const localFirst = (id) => !rpActive && LOCAL_FIRST.test(id);
        function iconURL(it) {
            const id = typeof it === 'string' ? it : it.base;
            // skinned player heads: 3D isometric render with the hat layer (minecraftitems.xyz only draws Steve)
            if (typeof it !== 'string' && it.head) return `https://mc-heads.net/head/${encodeURIComponent(it.head)}/128`;
            if (typeof it !== 'string' && it.mapId !== undefined) { const m = mapURL(it.mapId); if (m) return m; }
            const local = texURL(texKey(it)) || srvTex(texKey(it));
            if (local) return local;
            if (localFirst(id)) return `/minecraft/tools/item-list/minecraft_${id}.png`;
            let u = 'api.php?item=' + encodeURIComponent(id);
            if (rpActive) {
                u += '&rp=' + rpActive;
                if (typeof it !== 'string' && it.cmd !== null && it.cmd !== undefined) u += '&cmd=' + it.cmd;
            }
            return u;
        }
        const imgTag = (it, lazy = true) => {
            const id = typeof it === 'string' ? it : it.base;
            const url = iconURL(it), obj = typeof it !== 'string';
            // data-f="2": no fallback chain (maps and textures from dropped jars / packs)
            const attr = obj && it.head ? '' : obj && url.startsWith('data:image/png') ? ' data-map="1" data-f="2"' : TEXREF.has(texKey(it)) ? ` data-tex="${esc(texKey(it))}" data-f="2"` : localFirst(id) ? ' data-f="1"' : '';
            return `<img src="${url}" data-id="${esc(id)}"${attr} alt=""${lazy ? ' loading="lazy"' : ''} decoding="async">`;
        };
        // fallback chain: API -> local texture -> missing texture
        document.addEventListener('error', (e) => {
            const img = e.target;
            if (img.tagName !== 'IMG' || !img.dataset.id) return;
            const step = +(img.dataset.f || 0);
            img.dataset.f = step + 1;
            const next = step === 0 ? `/minecraft/tools/item-list/minecraft_${img.dataset.id}.png` : step === 1 ? MISSING : null;
            if (!next) return;
            img.src = next;
            if (img.parentElement && img.parentElement.style.getPropertyValue('--m')) img.parentElement.style.setProperty('--m', `url("${next}")`);
        }, true);

        /* =====================================================================
           Pixel-art status icons
           ===================================================================== */
        const SHAPES = {
            heart: [' XX XX ', 'XXXXXXX', 'XXXXXXX', ' XXXXX ', '  XXX  ', '   X   '],
            food: ['   bbb ', '  bmmmb', '  bmmmb', '  bmmb ', ' WWbb  ', 'WW     ', 'W      '],
            armor: ['XX   XX', 'XXXXXXX', 'XXXXXXX', ' XXXXX ', ' XXXXX ', ' XXXXX '],
            air: ['  XXX  ', ' XWXXX ', 'XWXXXXX', 'XXXXXXX', 'XXXXXXX', ' XXXXX ', '  XXX  '],
        };
        function pix(shape, colors, frac = 1, flip = false) {
            const rows = SHAPES[shape], w = 7, h = rows.length;
            let r = `<svg viewBox="-1 -1 ${w + 2} ${h + 2}">`;
            rows.forEach((row, y) => [...row].forEach((ch, x) => {
                if (ch === ' ') return;
                const full = flip ? x >= w * (1 - frac) : x < w * frac;
                r += `<rect x="${x}" y="${y}" width="1.02" height="1.02" fill="${full ? colors[ch] || colors.X : colors.empty}"/>`;
            }));
            return r + '</svg>';
        }
        function iconRow(shape, value, max, colors, flip) {
            let out = '';
            const n = Math.ceil(max / 2);
            for (let i = 0; i < n; i++) {
                const v = Math.max(0, Math.min(2, value - i * 2));
                out += pix(shape, colors, v / 2, flip);
            }
            return out;
        }

        /* =====================================================================
           App state
           ===================================================================== */
        const players = [];
        let active = -1;
        let REG = [];            // slot registry for the current render
        let NODES = [];          // raw tree lazy nodes
        let query = '';
        let view = 'player';     // 'player' or 'server' (world / server / plugin / mod files)

        // everything that is not a player file: server lists, world data, plugin + mod data.
        // It only lives in this tab - share links get a per-player extract (linkFor().pub). IPs, chat and commands are never kept.
        function freshCtx() {
            return {
                files: [], names: new Map(), usercache: new Map(), ops: new Map(), opsLoaded: false, whitelist: new Set(), whitelistLoaded: false,
                bans: new Map(), bansLoaded: false, props: null, level: null, scoreboard: null, maps: new Map(), worlds: new Map(),
                data: [], pets: new Map(), hotbars: null, servers: null, logEvents: [], logSeq: 0, logFiles: [], logUuids: new Map(), logDirty: false, sess: null,
                configs: [], ess: new Map(), lpUsers: new Map(), lpGroups: new Map(), mcmmo: [], gpPlayers: new Map(), gpClaims: [], wg: [],
                towny: { residents: new Map(), towns: new Map(), nations: new Map() }, mv: new Map(), mods: [], packs: [],
                ftb: { chapters: [], lang: new Map(), progress: new Map(), teams: [], chunks: new Map() }, backpacks: new Map(), schems: [],
                bpStore: new Map(), bpLog: new Map(), bpSeen: [], regions: [], regionDone: new Set(), raw: [],
            };
        }
        const CTX = freshCtx();

        function reg(item, where) { REG.push({ item, where }); return REG.length - 1; }

        function slotHTML(item, where, extra = '') {
            if (!item) return `<div class="slot ${extra}"></div>`;
            const k = reg(item, where);
            const url = iconURL(item);
            const pct = item.maxDamage && item.damage ? Math.max(0, 1 - item.damage / item.maxDamage) : null;
            return `<div class="slot has ${extra}${item.glint ? ' glint' : ''}" data-k="${k}" style="--m:url(&quot;${url}&quot;)">` +
                imgTag(item) +
                (item.count > 1 ? `<span class="cnt">${item.count}</span>` : '') +
                (pct !== null ? `<span class="dur"><i style="width:${pct * 100}%;background:hsl(${pct * 120},100%,45%)"></i></span>` : '') +
                (item.contents && item.contents.length || item.bundle && item.bundle.length ? '<span class="container-dot"></span>' : '') +
                '</div>';
        }

        function gridHTML(bySlot, start, n, where, opts = {}) {
            let h = '';
            for (let i = start; i < start + n; i++) h += slotHTML(bySlot[i], `${where} › Slot ${i}`, opts.sel === i ? 'sel' : '');
            return `<div class="slots"${opts.cols ? ` style="grid-template-columns:repeat(${opts.cols},var(--s))"` : ''}>${h}</div>`;
        }

        // empty armor slots show a dark silhouette, like in game
        const PH = { head: 'leather_helmet', chest: 'leather_chestplate', legs: 'leather_leggings', feet: 'leather_boots', offhand: 'shield' };
        const phSlot = (k) => `<div class="slot"><span class="ph" style="background-image:url('/minecraft/tools/item-list/minecraft_${PH[k]}.png')"></span></div>`;

        /* =====================================================================
           Gather data from a player compound
           ===================================================================== */
        function analyse(d) {
            const P = { d };
            const inv = Array.isArray(d.Inventory) ? d.Inventory : [];
            P.inv = {};
            P.equip = {};
            const legacyEquip = { 103: 'head', 102: 'chest', 101: 'legs', 100: 'feet', '-106': 'offhand' };
            for (const r of inv) {
                const it = normItem(r);
                if (!it) continue;
                const s = r.Slot ?? r.slot;
                if (legacyEquip[s]) P.equip[legacyEquip[s]] = it; else P.inv[s] = it;
            }
            if (isObj(d.equipment)) for (const [k, v] of Object.entries(d.equipment)) { const it = normItem(v); if (it) P.equip[k] = it; }
            P.ender = {};
            for (const r of (Array.isArray(d.EnderItems) ? d.EnderItems : [])) { const it = normItem(r); if (it) P.ender[r.Slot ?? r.slot] = it; }

            // walk every item (including inside shulkers / bundles) for totals + lodestones
            P.owned = new Map();
            P.points = [];
            P.stats = { items: 0, stacks: 0, enchanted: 0, containers: 0 };
            P.mapIds = new Set();
            P.bpIds = new Set();
            const walk = (it, where) => {
                P.stats.items += it.count; P.stats.stacks++;
                if (it.ench.length) P.stats.enchanted++;
                if (it.mapId !== undefined) P.mapIds.add(String(it.mapId));
                if (it.bpUuid) P.bpIds.add(it.bpUuid);
                const e = P.owned.get(it.base) || { it, total: 0, where: new Map() };
                e.total += it.count;
                e.where.set(where, (e.where.get(where) || 0) + it.count);
                P.owned.set(it.base, e);
                const lt = it.c['minecraft:lodestone_tracker'];
                if (lt && isObj(lt.target) && Array.isArray(lt.target.pos)) P.points.push({ label: plain(it.nameHTML) + ' target', pos: lt.target.pos, dim: lt.target.dimension, kind: 'lodestone' });
                if (it.contents) { if (it.contents.length) P.stats.containers++; it.contents.forEach((x) => walk(x.item, `${where} › ${it.namePlain}`)); }
                if (it.bundle) it.bundle.forEach((x) => walk(x, `${where} › ${it.namePlain}`));
            };
            Object.values(P.equip).forEach((it) => walk(it, 'Equipment'));
            Object.values(P.inv).forEach((it) => walk(it, 'Inventory'));
            Object.values(P.ender).forEach((it) => walk(it, 'Ender Chest'));
            // Trinkets / Curios / other mod slots
            P.modInv = modInventories(d);
            P.modInv.forEach((g) => g.items.forEach((x) => walk(x.item, g.label)));

            // attributes (new + old format)
            P.attrs = [];
            for (const a of (Array.isArray(d.attributes) ? d.attributes : Array.isArray(d.Attributes) ? d.Attributes : [])) {
                const id = strip(a.id ?? a.Name).replace(/^generic\.|^player\./, '');
                const base = a.base ?? a.Base ?? 0;
                const mods = (a.modifiers ?? a.Modifiers ?? []).map((m) => ({ id: m.id ?? m.Name ?? '', amount: m.amount ?? m.Amount ?? 0, op: m.operation ?? ['add_value', 'add_multiplied_base', 'add_multiplied_total'][m.Operation ?? 0] }));
                let v = base + mods.filter((m) => m.op === 'add_value').reduce((s, m) => s + m.amount, 0);
                v *= 1 + mods.filter((m) => m.op === 'add_multiplied_base').reduce((s, m) => s + m.amount, 0);
                for (const m of mods.filter((m) => m.op === 'add_multiplied_total')) v *= 1 + m.amount;
                P.attrs.push({ id, base, mods, value: v, def: ATTR_DEFAULT[id] });
            }
            const maxH = P.attrs.find((a) => a.id === 'max_health');
            P.maxHealth = maxH ? maxH.value : 20;

            // armor points from equipment
            let armor = 0, tough = 0;
            for (const [slot, it] of Object.entries(P.equip)) {
                const mods = it.c['minecraft:attribute_modifiers'];
                const list = Array.isArray(mods) ? mods : isObj(mods) && Array.isArray(mods.modifiers) ? mods.modifiers : null;
                if (list) {
                    for (const m of list) {
                        const t = strip(m.type).replace(/^generic\./, '');
                        if (t === 'armor') armor += m.amount || 0;
                        if (t === 'armor_toughness') tough += m.amount || 0;
                    }
                    continue;
                }
                const m = /^(\w+?)_(helmet|chestplate|leggings|boots)$/.exec(it.base);
                if (m && ARMOR_PTS[m[1]]) { armor += ARMOR_PTS[m[1]][PIECES.indexOf(m[2])]; tough += ARMOR_TOUGH[m[1]] || 0; }
                if (it.base === 'turtle_helmet') armor += 2;
            }
            P.armor = armor; P.tough = tough;

            // effects (new + old)
            P.effects = (Array.isArray(d.active_effects) ? d.active_effects : Array.isArray(d.ActiveEffects) ? d.ActiveEffects : []).map((e) => {
                let id = e.id ?? e.Id;
                if (typeof id === 'number') id = OLD_EFFECTS[id] || 'effect_' + id;
                id = strip(id);
                const chain = [];
                let h = e.hidden_effect ?? e.HiddenEffect;
                while (isObj(h)) { chain.push(h); h = h.hidden_effect ?? h.HiddenEffect; }
                return { id, amp: e.amplifier ?? e.Amplifier ?? 0, dur: e.duration ?? e.Duration ?? 0, ambient: truthy(e.ambient ?? e.Ambient), particles: (e.show_particles ?? e.ShowParticles) !== 0, hidden: chain };
            });

            // locations
            const pos = Array.isArray(d.Pos) ? d.Pos : null;
            const dim = d.Dimension !== undefined ? (typeof d.Dimension === 'number' ? ['minecraft:the_nether', 'minecraft:overworld', 'minecraft:the_end'][d.Dimension + 1] : d.Dimension) : 'minecraft:overworld';
            P.dim = dim;
            if (pos) P.points.unshift({ label: 'Player', pos, dim, kind: 'player' });
            const rs = isObj(d.respawn) ? d.respawn : d.SpawnX !== undefined ? { pos: [d.SpawnX, d.SpawnY, d.SpawnZ], dimension: d.SpawnDimension } : null;
            if (rs && Array.isArray(rs.pos)) P.points.push({ label: 'Respawn point', pos: rs.pos, dim: rs.dimension || 'minecraft:overworld', kind: 'spawn' });
            if (isObj(d.LastDeathLocation) && Array.isArray(d.LastDeathLocation.pos)) P.points.push({ label: 'Last death', pos: d.LastDeathLocation.pos, dim: d.LastDeathLocation.dimension, kind: 'death' });
            (Array.isArray(d.ender_pearls) ? d.ender_pearls : []).forEach((p, i) => { if (Array.isArray(p.Pos)) P.points.push({ label: 'Ender pearl ' + (i + 1), pos: p.Pos, dim: p.ender_pearl_dimension || dim, kind: 'pearl' }); });
            if (isObj(d.enteredNetherPosition)) P.points.push({ label: 'Entered Nether', pos: [d.enteredNetherPosition.x, d.enteredNetherPosition.y, d.enteredNetherPosition.z], dim: 'minecraft:overworld', kind: 'misc' });

            P.uuid = uuidOf(d.UUID);
            return P;
        }

        // item lists inside mod data: Trinkets (cardinal_components), Curios (ForgeCaps / neoforge:attachments) and friends
        function modInventories(d) {
            const found = [];
            const isItem = (x) => isObj(x) && typeof x.id === 'string' && (x.Count !== undefined || x.count !== undefined || isObj(x.components) || isObj(x.tag));
            const walk = (v, path, depth) => {
                if (depth > 9 || !v || typeof v !== 'object') return;
                if (Array.isArray(v)) {
                    if (v.length && v.every(isItem)) { found.push({ path, raw: v }); return; }
                    v.forEach((x, i) => walk(x, path.concat(isObj(x) && typeof (x.Identifier ?? x.identifier) === 'string' ? x.Identifier ?? x.identifier : String(i)), depth + 1));
                    return;
                }
                for (const [k, x] of Object.entries(v)) walk(x, path.concat(k), depth + 1);
            };
            for (const k of ['cardinal_components', 'ForgeCaps', 'neoforge:attachments', 'fabric:attachments', 'ForgeData']) if (isObj(d[k])) walk(d[k], [], 0);
            const NOISE = /^(Items|items|Stacks|stacks|StacksHandler|Curios|curios|Cosmetics|cosmetics|Inventory|inventory|\d+)$/;
            return found.map(({ path, raw }) => {
                const label = path.filter((p) => !NOISE.test(p)).map((p) => pretty(p.includes(':') ? p.split(':')[0] : p)).join(' › ') || 'Mod items';
                const items = raw.map((x, i) => ({ slot: x.Slot ?? x.slot ?? i, item: normItem(x) })).filter((x) => x.item && x.item.base !== 'air');
                return { label: label + (path.some((p) => /^cosmetics$/i.test(p)) ? ' (cosmetic)' : ''), items };
            }).filter((g) => g.items.length);
        }

        /* =====================================================================
           Rendering
           ===================================================================== */
        const HANDLED = new Set(['Inventory', 'EnderItems', 'equipment', 'SelectedItemSlot', 'Health', 'foodLevel', 'foodSaturationLevel', 'foodExhaustionLevel', 'foodTickTimer', 'XpLevel', 'XpP', 'XpTotal', 'XpSeed', 'Score', 'Air', 'AbsorptionAmount', 'active_effects', 'ActiveEffects', 'attributes', 'Attributes', 'abilities', 'playerGameType', 'previousPlayerGameType', 'Pos', 'Rotation', 'Motion', 'Dimension', 'respawn', 'SpawnX', 'SpawnY', 'SpawnZ', 'SpawnDimension', 'SpawnForced', 'SpawnAngle', 'LastDeathLocation', 'ender_pearls', 'recipeBook', 'UUID', 'DataVersion', 'Fire', 'OnGround', 'FallFlying', 'fall_distance', 'FallDistance', 'Invulnerable', 'PortalCooldown', 'HurtTime', 'HurtByTimestamp', 'DeathTime', 'SleepTimer', 'seenCredits', 'warden_spawn_tracker', 'enteredNetherPosition', 'bukkit', 'Paper', 'Spigot.ticksLived', 'WorldUUIDLeast', 'WorldUUIDMost']);

        function render() {
            REG = []; NODES = [];
            const hasCtx = CTX.files.length > 0;
            const bpN = bpList().length;
            if (!hasCtx) view = 'player';
            else if (!players.length && view === 'player') view = 'server';
            if (view === 'backpacks' && !bpN) view = players.length ? 'player' : 'server';
            $('#drop').classList.toggle('hide', players.length > 0 || hasCtx);
            $('#viewer').classList.toggle('hide', players.length === 0 && !hasCtx);
            const srvBtn = $('#srvBtn');
            srvBtn.classList.toggle('hide', !hasCtx || (!players.length && view === 'server'));
            srvBtn.textContent = view === 'server' ? 'Back to player' : 'World & server';
            const bpBtn = $('#bpBtn');
            bpBtn.classList.toggle('hide', !bpN);
            bpBtn.textContent = view === 'backpacks' ? 'Close backpacks' : `Backpacks (${num(bpN)})`;
            $('#shareBtn').classList.toggle('hide', view !== 'player' || !players.length);
            renderTabs();
            disposeSkins();
            if (view === 'server') { $('#player').innerHTML = serverViewHTML(); applySearch(); return; }
            if (view === 'backpacks') { $('#player').innerHTML = bpViewHTML(); applySearch(); return; }
            if (active < 0) { $('#player').innerHTML = ''; return; }
            const pl = players[active];
            const P = pl.P, d = P.d;
            let L;
            try { L = linkFor(pl); } catch (e) { console.error(e); L = { pub: {}, points: [] }; }
            const sections = [];

            sections.push(heroHTML(pl, L));
            sections.push(`<div class="section card" id="s-inv"><h2>${imgTag('chest', false)} Inventory <span class="n">${P.stats.stacks} stacks · ${num(P.stats.items)} items</span></h2><div class="guis">${invHTML(P)}${enderHTML(P)}${modInvHTML(P)}</div></div>`);
            if (pl.extras.stats) sections.push(statsHTML(pl.extras.stats));
            if (pl.extras.advancements) sections.push(advHTML(pl.extras.advancements));
            if (P.owned.size) sections.push(ownedHTML(P));

            const cards = [];
            // server / plugin cards - one bad value (e.g. from an old share link) only drops that card
            const safe = (f) => { try { return f(); } catch (e) { console.error(e); return ''; } };
            const srv = safe(() => serverCardHTML(pl, L));
            if (srv) cards.push(srv);
            const points = P.points.concat(L.points);
            if (points.length) cards.push(locHTML({ ...P, points }));
            for (const f of [essHTML, permHTML, townyHTML, mcmmoHTML, claimsHTML, sessHTML, petsHTML, questsHTML]) { const h = safe(() => f(L)); if (h) cards.push(h); }
            if (P.effects.length) cards.push(effectsHTML(P));
            if (P.attrs.length) cards.push(attrsHTML(P));
            if (isObj(d.abilities)) cards.push(abilitiesHTML(d));
            cards.push(vitalsHTML(d));
            if (Array.isArray(d.ender_pearls) && d.ender_pearls.length) cards.push(pearlsHTML(P));
            if (isObj(d.warden_spawn_tracker)) cards.push(wardenHTML(d.warden_spawn_tracker));
            const other = Object.keys(d).filter((k) => !HANDLED.has(k));
            if (other.length) cards.push(otherHTML(d, other));
            sections.push(`<div class="masonry">${cards.join('')}</div>`);
            if (isObj(d.recipeBook)) sections.push(recipesHTML(d.recipeBook));
            if (!pl.extras.stats || !pl.extras.advancements) sections.push(extraBarHTML(pl));
            sections.push(rawHTML(d));

            const jumps = [['s-inv', 'Inventory'], ['s-stats', 'Statistics'], ['s-adv', 'Advancements'], ['s-owned', 'All items'], ['s-server', 'Server'], ['s-loc', 'Locations'], ['s-ess', 'Essentials'], ['s-perm', 'Permissions'], ['s-towny', 'Towny'], ['s-mcmmo', 'mcMMO'], ['s-claims', 'Claims'], ['s-sess', 'Sessions'], ['s-pets', 'Pets'], ['s-quests', 'Quests'], ['s-eff', 'Effects'], ['s-attr', 'Attributes'], ['s-abil', 'Abilities'], ['s-vit', 'Vitals'], ['s-pearl', 'Pearls'], ['s-warden', 'Warden'], ['s-other', 'Other data'], ['s-recipe', 'Recipes'], ['s-raw', 'Raw NBT']];
            const html = sections.join('');
            const jumpHTML = '<nav class="jump">' + jumps.filter(([id]) => html.includes(`id="${id}"`)).map(([id, t]) => `<a href="#${id}">${t}</a>`).join('') + '</nav>';
            $('#player').innerHTML = jumpHTML + html;
            applySearch();
            mountSkins();
        }

        // shown only while stats or advancements are missing - no empty sections
        function extraBarHTML(pl) {
            const missing = [!pl.extras.stats && 'statistics', !pl.extras.advancements && 'advancements'].filter(Boolean).join(' and ');
            const raw = pl.P.uuid || '&lt;uuid&gt;';
            return `<div class="section extra-bar"><button class="btn" data-act="extra">+ Add ${missing}</button>
                <span class="muted">From the same world: <span class="mono">stats/${raw}.json</span> and <span class="mono">advancements/${raw}.json</span>. You can also drop them anywhere on the page.</span></div>`;
        }

        /* ---------- 3D skins (skinview3d, loaded on first use) ---------- */
        let skinLib = null;
        let skinViewers = [];
        const loadSkinLib = () => skinLib || (skinLib = new Promise((res, rej) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/skinview3d@3.1.0/bundles/skinview3d.bundle.js';
            s.onload = () => res(window.skinview3d);
            s.onerror = rej;
            document.head.appendChild(s);
        }));
        function disposeSkins() {
            skinViewers.forEach((v) => { v.stop(); try { v.viewer.dispose(); } catch (e) { } });
            skinViewers = [];
        }
        async function mountSkins() {
            const els = $$('#player .skin3d');
            if (!els.length) return;
            let lib;
            try { lib = await loadSkinLib(); } catch (e) { return; }     // keep the 2D render
            if (!els[0].isConnected) return;                            // re-rendered while loading
            for (const el of els) {
                const w = el.clientWidth, h = el.clientHeight;
                if (!w || !h) continue;
                const canvas = document.createElement('canvas');
                const viewer = new lib.SkinViewer({ canvas, width: w, height: h });
                viewer.controls.enableRotate = false;
                viewer.controls.enableZoom = false;
                viewer.controls.enablePan = false;
                viewer.autoRotate = false;
                viewer.fov = 40;
                viewer.zoom = +el.dataset.zoom || 0.9;
                viewer.animation = new lib.IdleAnimation();
                el.appendChild(canvas);
                const uuid = el.dataset.skin;
                Promise.resolve(viewer.loadSkin(`https://crafthead.net/skin/${uuid || 'MHF_Steve'}`)).then(() => { const img = $('img', el); if (img) img.remove(); }).catch(() => canvas.remove());
                if (uuid) Promise.resolve(viewer.loadCape(`https://crafthead.net/cape/${uuid}`, { backEquipment: el.dataset.elytra ? 'elytra' : 'cape' })).catch(() => { });
                skinViewers.push(spinner(el, viewer));
            }
        }
        // drag to rotate freely; on release it eases back upright and keeps slowly spinning
        function spinner(el, viewer) {
            const obj = viewer.playerWrapper;
            const SPIN = 0.6;                 // rad/s idle spin
            let rx = 0, ry = 0.5, vy = SPIN, drag = null, raf = 0, last = performance.now();
            const norm = (a) => Math.atan2(Math.sin(a), Math.cos(a));   // shortest way back upright
            const down = (e) => { drag = { x: e.clientX, y: e.clientY, t: performance.now() }; try { el.setPointerCapture(e.pointerId); } catch (err) { } };
            const move = (e) => {
                if (!drag) return;
                const now = performance.now(), dx = e.clientX - drag.x, dy = e.clientY - drag.y, dt = Math.max(1, now - drag.t) / 1000;
                ry += dx * 0.012; rx += dy * 0.012;
                vy = (dx * 0.012) / dt;           // fling speed
                drag = { x: e.clientX, y: e.clientY, t: now };
            };
            const up = () => { drag = null; rx = norm(rx); };
            el.addEventListener('pointerdown', down);
            el.addEventListener('pointermove', move);
            el.addEventListener('pointerup', up);
            el.addEventListener('pointercancel', up);
            const io = new IntersectionObserver(([en]) => (viewer.renderPaused = !en.isIntersecting));
            io.observe(el);
            const tick = (now) => {
                const dt = Math.min(0.05, (now - last) / 1000);
                last = now;
                if (!drag) {
                    rx *= Math.exp(-dt * 5);                       // ease back upright
                    vy += (SPIN - vy) * (1 - Math.exp(-dt * 2));    // fling settles into the idle spin
                    ry += vy * dt;
                }
                obj.rotation.x = rx;
                obj.rotation.y = ry;
                raf = requestAnimationFrame(tick);
            };
            raf = requestAnimationFrame(tick);
            return { viewer, stop() { cancelAnimationFrame(raf); io.disconnect(); } };
        }
        function skinHTML(P, zoom) {
            const raw = P.uuid ? P.uuid.replace(/-/g, '') : '';
            const elytra = P.equip.chest && P.equip.chest.base === 'elytra';
            return `<div class="skin3d" data-skin="${raw}" data-zoom="${zoom}"${elytra ? ' data-elytra="1"' : ''} title="Drag to rotate">` +
                `<img src="${raw ? `https://crafthead.net/body/${raw}/180` : iconURL('armor_stand')}" alt="" onerror="this.remove()"></div>`;
        }

        /* ---------- player picker ---------- */
        // last activity: the server's own timestamps beat file dates (downloading files resets those)
        function activityOf(pl) {
            const d = pl.P.d, b = isObj(d.bukkit) ? d.bukkit : {}, paper = isObj(d.Paper) ? d.Paper : {};
            const t = [b.lastPlayed, paper.LastSeen, paper.LastLogin].map(Number).filter((n) => n > 1e11);
            return t.length ? Math.max(...t) : pl.modified || 0;
        }
        // ticks played, from world/stats/<uuid>.json - null when no stats file is loaded
        function playTicksOf(pl) {
            const raw = pl.extras.stats && pl.extras.stats.data;
            if (!isObj(raw)) return null;
            const c = isObj(raw.stats) ? raw.stats['minecraft:custom'] || raw.stats.custom || {} : raw;
            const v = c['minecraft:play_time'] ?? c.play_time ?? c['minecraft:play_one_minute'] ?? c.play_one_minute ?? c['stat.playOneMinute'];
            return typeof v === 'number' ? v : null;
        }
        // most play time first when any stats are loaded, otherwise most recent login first
        function sortedPlayers() {
            const list = players.map((pl, i) => ({ pl, i, t: activityOf(pl), play: playTicksOf(pl) }));
            const byPlay = list.some((r) => r.play !== null);
            list.sort((a, b) => (byPlay ? (b.play ?? -1) - (a.play ?? -1) : 0) || b.t - a.t || labelOf(a.pl).localeCompare(labelOf(b.pl)));
            return { list, byPlay };
        }
        const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
        function ago(ms) {
            if (!ms) return '';
            const s = (ms - Date.now()) / 1000;
            for (const [u, n] of [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]]) if (Math.abs(s) >= n) return rtf.format(Math.round(s / n), u);
            return 'just now';
        }
        const avatarOf = (pl) => (pl.P.uuid ? `https://crafthead.net/avatar/${pl.P.uuid.replace(/-/g, '')}/32` : iconURL('player_head'));
        const labelOf = (pl) => pl.name || pl.file;
        let pickOpen = false, pickFilter = '', pickKb = 0;

        function renderTabs() {
            const box = $('#tabs');
            if (!box.querySelector('.pick-btn')) {
                box.innerHTML = '<button class="pick-btn" data-pick aria-haspopup="listbox"></button>' +
                    '<div class="pick-menu hide"><input type="search" placeholder="Filter players…" autocomplete="off"><div class="pick-list" role="listbox"></div><div class="pick-foot"></div></div>';
                const input = $('input', box);
                input.addEventListener('input', () => { pickFilter = input.value; pickKb = 0; renderPickList(); });
                input.addEventListener('keydown', (e) => {
                    const rows = $$('.pick-row', box);
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); pickKb = (pickKb + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % Math.max(rows.length, 1); renderPickList(); }
                    if (e.key === 'Enter' && rows[pickKb]) { e.preventDefault(); rows[pickKb].click(); }
                    if (e.key === 'Escape') { e.stopPropagation(); togglePicker(false); }
                });
            }
            const pl = players[active];
            if (pl) $('.pick-btn', box).innerHTML = `<img src="${avatarOf(pl)}" alt="" onerror="this.src='${MISSING}'"><span class="nm">${esc(view === 'server' ? 'World & server' : view === 'backpacks' ? 'Backpacks' : labelOf(pl))}</span>` +
                (players.length > 1 ? `<span class="n">${players.length} players</span>` : '') + '<span class="car">▾</span>';
            else $('.pick-btn', box).innerHTML = '<span class="nm" style="padding-left:6px">No players loaded</span>';
            if (pickOpen) renderPickList();
        }
        function renderPickList() {
            const box = $('#tabs');
            const f = pickFilter.trim().toLowerCase();
            const { list: all, byPlay } = sortedPlayers();
            const list = all.filter(({ pl }) => !f || `${labelOf(pl)} ${pl.file} ${pl.P.uuid || ''}`.toLowerCase().includes(f));
            $('.pick-list', box).innerHTML = list.map(({ pl, i, t, play }, n) => `<div class="pick-row${i === active ? ' on' : ''}${n === pickKb ? ' kb' : ''}" data-tab="${i}" role="option" title="${esc(pl.file)}">` +
                `<img src="${avatarOf(pl)}" alt="" loading="lazy" onerror="this.src='${MISSING}'"><div class="grow"><b>${esc(labelOf(pl))}</b>` +
                `<small>${play !== null ? esc(fmtStat('play_time', play)) + ' played · ' : ''}${t ? 'active ' + esc(ago(t)) : 'no activity date'}${pl.name ? ' · ' + esc(pl.file) : ''}</small></div>` +
                `<button class="x" data-close="${i}" title="Remove">✕</button></div>`).join('') || '<div class="muted" style="padding:10px;font-size:13px">No players match.</div>';
            $('.pick-foot', box).innerHTML = `<span>${list.length} of ${players.length} · ${byPlay ? 'most play time' : 'most recently active'} first</span>${players.length > 1 ? '<button class="btn small" data-act="closeall">Remove all</button>' : ''}`;
        }
        function togglePicker(open = !pickOpen) {
            pickOpen = open;
            const box = $('#tabs');
            box.classList.toggle('open', open);
            $('.pick-menu', box).classList.toggle('hide', !open);
            if (open) {
                pickFilter = ''; pickKb = 0;
                const input = $('input', box);
                input.value = '';
                renderPickList();
                input.focus();
            }
        }

        // name lookups: cached ones are instant, the rest go to playerdb a few at a time (bulk world uploads)
        const nameQueue = [];
        let nameBusy = 0;
        function pumpNames() {
            while (nameBusy < 4 && nameQueue.length) {
                const job = nameQueue.shift();
                nameBusy++;
                job().finally(() => { nameBusy--; pumpNames(); });
            }
        }

        function heroHTML(pl, L) {
            const P = pl.P, d = P.d, X = L.pub;
            const gm = GAMEMODES[d.playerGameType] ?? null;
            const ver = versionOf(d.DataVersion);
            const raw = P.uuid ? P.uuid.replace(/-/g, '') : '';
            const badges = [];
            if (gm) badges.push(`<span class="badge ${['g', 'p', 'b', 'r'][d.playerGameType] || ''}">${gm}</span>`);
            if (P.dim) badges.push(`<span class="badge" style="border-color:${dimColor(P.dim)}">${esc(dimName(P.dim))}</span>`);
            if (ver) badges.push(`<span class="badge">Minecraft ${ver}</span>`);
            if (truthy(d.seenCredits)) badges.push('<span class="badge p">Beaten the Ender Dragon</span>');
            if (d['Bukkit.updateLevel'] !== undefined || d.BukkitValues || d.bukkit) badges.push('<span class="badge b">Bukkit / Paper server</span>');
            if (d.arcade || d.ForgeCaps || d['neoforge:attachments'] || d.cardinal_components) badges.push('<span class="badge b">Modded</span>');
            if (truthy(d.FallFlying)) badges.push('<span class="badge">Gliding</span>');
            if ((d.Fire ?? -20) > 0) badges.push('<span class="badge r">On fire</span>');
            if (X.op) badges.push(`<span class="badge p" title="Permission level ${esc(X.op.level)}">Operator · level ${esc(X.op.level)}</span>`);
            if (X.ban) badges.push(`<span class="badge r" title="${esc(X.ban.reason || '')}">Banned</span>`);
            if (X.whitelisted) badges.push('<span class="badge g">Whitelisted</span>');
            else if (X.whitelisted === false) badges.push('<span class="badge">Not whitelisted</span>');
            if (P.uuid && P.uuid[14] === '3') badges.push('<span class="badge" title="Version 3 UUID - made from the name on an offline-mode (cracked) server">Offline-mode account</span>');
            if (X.team) badges.push(`<span class="badge">Team ${teamHTML(X.team)}</span>`);
            if (X.lp && X.lp.primary) badges.push(`<span class="badge b">Group: ${esc(X.lp.primary)}</span>`);
            if (X.towny && X.towny.town) badges.push(`<span class="badge">${X.towny.mayor ? 'Mayor of ' : 'Town: '}${esc(X.towny.town)}</span>`);
            if (X.sess && X.sess.online) badges.push('<span class="badge g">Online when the log ended</span>');
            if (X.ess && Array.isArray(X.ess.flags)) for (const f of X.ess.flags) badges.push(`<span class="badge${/muted|jailed/.test(f) ? ' r' : ''}">${esc(ESS_FLAG[f] || f)}</span>`);
            const hp = d.Health ?? 0;
            const tiles = [
                [num(d.XpLevel ?? 0), 'XP level'], [num(d.XpTotal ?? 0), 'Total XP'], [num(d.Score ?? 0), 'Score'],
                [num(P.stats.items), 'Items owned'], [num(P.owned.size), 'Unique items'], [num(P.stats.enchanted), 'Enchanted'],
                [num(P.stats.containers), 'Filled containers'],
            ];
            if (isObj(d.recipeBook) && Array.isArray(d.recipeBook.recipes)) tiles.push([num(d.recipeBook.recipes.length), 'Recipes unlocked']);
            const absorb = d.AbsorptionAmount || 0;
            return `<div class="section card hero">
                ${skinHTML(P, 0.85)}
                <div>
                    <h1>${esc(pl.name || (P.uuid ? 'Unknown player' : pl.file))}</h1>
                    ${X.ess && X.ess.nick ? `<div class="muted" style="font-size:14px">Nickname: ${ampHTML(X.ess.nick)}</div>` : ''}
                    ${P.uuid ? `<div class="uuid muted mono" data-copy="${P.uuid}" title="Click to copy">${P.uuid}</div>` : ''}
                    <div class="badges">${badges.join('')}</div>
                    <div class="vit">
                        <div><div class="vlabel">Health ${num(hp, 1)} / ${num(P.maxHealth)}${absorb ? ` + ${num(absorb, 1)}` : ''}</div>
                            <div class="icons">${iconRow('heart', hp, P.maxHealth, { X: '#ff1f1f', empty: '#3a1010' })}${absorb ? iconRow('heart', absorb, absorb, { X: '#ffcc1f', empty: '#3a3010' }) : ''}</div></div>
                        <div><div class="vlabel">Hunger ${d.foodLevel ?? '?'} / 20 · saturation ${num(d.foodSaturationLevel ?? 0, 1)}</div>
                            <div class="icons rtl">${iconRow('food', d.foodLevel ?? 0, 20, { m: '#c76e2e', b: '#7a3d12', W: '#eee', empty: '#3a2a1a' }, true)}</div></div>
                        <div><div class="vlabel">Armor ${P.armor}${P.tough ? ` · toughness ${P.tough}` : ''}</div>
                            <div class="icons">${iconRow('armor', P.armor, 20, { X: '#d8d8d8', empty: '#3a3a3a' })}</div></div>
                        <div><div class="vlabel">Air ${d.Air ?? 300} / 300</div>
                            <div class="icons rtl">${iconRow('air', Math.round(((d.Air ?? 300) / 300) * 20), 20, { X: '#3d8cff', W: '#fff', empty: '#1a2a3a' }, true)}</div></div>
                        <div class="xp"><span class="xplvl">${d.XpLevel ?? 0}</span><div class="xpbar"><i style="width:${Math.min(100, (d.XpP || 0) * 100)}%"></i></div></div>
                    </div>
                    <div class="tiles">${tiles.map(([v, l]) => `<div class="tile"><b>${v}</b><span>${l}</span></div>`).join('')}</div>
                </div>
            </div>`;
        }

        function invHTML(P) {
            const e = P.equip;
            const armorCol = ['head', 'chest', 'legs', 'feet'].map((k) => e[k] ? slotHTML(e[k], 'Equipment › ' + pretty(k)) : phSlot(k)).join('');
            const off = e.offhand ? slotHTML(e.offhand, 'Equipment › Offhand') : phSlot('offhand');
            const extraEquip = Object.keys(e).filter((k) => !['head', 'chest', 'legs', 'feet', 'offhand', 'mainhand'].includes(k));
            const sel = P.d.SelectedItemSlot ?? -1;
            // the 2x2 crafting grid is decoration - player files never store what was in it
            const cs = (area, cls = '') => `<div class="slot ${cls}" style="grid-area:${area}"></div>`;
            const craft = `<div class="craft"><div class="ttl">Crafting</div>${cs('2/1')}${cs('2/2')}${cs('3/1')}${cs('3/2')}<span class="arrow" style="grid-area:2/3/4/4">➜</span>${cs('2/4/4/5', 'out')}</div>`;
            return `<div class="gui"><div class="inv">
                    <div class="col">${armorCol}</div>
                    <div class="model skin-wrap">${skinHTML(P, 0.75).replace('class="skin3d"', 'class="skin3d" style="position:absolute;inset:0"')}</div>
                    <div class="col">${off}</div>${craft}
                </div>
                ${extraEquip.length ? `<div class="ttl">Other equipment</div><div class="slots">${extraEquip.map((k) => slotHTML(e[k], 'Equipment › ' + pretty(k))).join('')}</div>` : ''}
                ${gridHTML(P.inv, 9, 27, 'Inventory')}
                ${gridHTML(P.inv, 0, 9, 'Hotbar', { sel })}
                ${extraInvSlots(P)}
            </div>`;
        }
        function extraInvSlots(P) {
            const odd = Object.keys(P.inv).filter((s) => +s < 0 || +s > 35);
            if (!odd.length) return '';
            return `<div class="ttl" style="margin-top:8px">Other slots</div><div class="slots">${odd.map((s) => slotHTML(P.inv[s], 'Inventory › Slot ' + s)).join('')}</div>`;
        }
        function enderHTML(P) {
            return `<div class="gui"><div class="ttl">Ender Chest</div>${gridHTML(P.ender, 0, 27, 'Ender Chest')}</div>`;
        }
        // one GUI per mod (Trinkets, Curios...), a labelled row per slot group
        function modInvHTML(P) {
            const byRoot = new Map();
            for (const g of P.modInv) {
                const [root, ...rest] = g.label.split(' › ');
                if (!byRoot.has(root)) byRoot.set(root, []);
                byRoot.get(root).push({ ...g, sub: rest.join(' › ') });
            }
            return [...byRoot].map(([root, gs]) => `<div class="gui"><div class="ttl">${esc(root)}</div><div class="modslots">${gs.map((g) => {
                const by = {};
                g.items.forEach((x) => (by[x.slot] = x.item));
                const n = Math.min(27, Math.max(g.items.length, ...g.items.map((x) => x.slot + 1)));
                return `<div class="ms">${g.sub ? `<span>${esc(g.sub)}</span>` : ''}<div class="slots" style="grid-template-columns:repeat(${Math.min(9, n)},var(--s))">${Array.from({ length: n }, (_, i) => slotHTML(by[i], `${root}${g.sub ? ' › ' + g.sub : ''} › Slot ${i}`)).join('')}</div></div>`;
            }).join('')}</div></div>`).join('');
        }

        function ownedHTML(P) {
            const list = [...P.owned.values()].sort((a, b) => b.total - a.total);
            return `<div class="card section" id="s-owned"><h2>${imgTag('bundle', false)} Everything they own <span class="n">${list.length} unique · counts include shulkers &amp; bundles</span></h2>
                <div class="owned">${list.map((e) => {
                    const k = reg(e.it, 'All items');
                    const where = [...e.where].map(([w, n]) => `${w}: ${n}`).join('\n');
                    return `<div class="own" data-own="${esc(e.it.base)}" data-k="${k}" title="${esc(where)}"><div class="ico${e.it.glint ? ' glint' : ''}" style="--m:url(&quot;${iconURL(e.it)}&quot;)">${imgTag(e.it)}</div><b>${num(e.total)}</b></div>`;
                }).join('')}</div></div>`;
        }

        function locHTML(P) {
            const byDim = new Map();
            P.points.forEach((p) => { const k = p.dim || 'minecraft:overworld'; if (!byDim.has(k)) byDim.set(k, []); byDim.get(k).push(p); });
            const me = P.points.find((p) => p.kind === 'player');
            const color = { player: '#7ed957', spawn: '#5aa8ff', death: '#ff5555', pearl: '#3fd6a8', lodestone: '#ffd84a', misc: '#bbbbbb', home: '#ff9f40', pet: '#ff7ad9', claim: '#b3a1ff' };
            // radar per dimension: centred on the player (or first point), true direction, log-scaled distance
            const maps = [...byDim].map(([dim, pts]) => {
                if (pts.length < 2) return '';
                const c = pts.find((p) => p.kind === 'player') || pts[0];
                const W = 400, R = 180, maxD = Math.max(...pts.map((p) => Math.hypot(p.pos[0] - c.pos[0], p.pos[2] - c.pos[2])), 10);
                const L = Math.log10(maxD + 1);
                const rad = (d) => (Math.log10(d + 1) / L) * (R - 14);
                const rings = [10, 100, 1000, 10000, 100000, 1000000].filter((r) => r < maxD * 1.05);
                const placed = [];
                const marks = pts.map((p) => {
                    const dx = p.pos[0] - c.pos[0], dz = p.pos[2] - c.pos[2], d = Math.hypot(dx, dz);
                    const r = rad(d), a = Math.atan2(dz, dx);
                    const x = W / 2 + Math.cos(a) * r, y = W / 2 + Math.sin(a) * r;
                    let ly = y + 4;
                    while (placed.some((q) => Math.abs(q[0] - x) < 90 && Math.abs(q[1] - ly) < 12)) ly += 12;
                    placed.push([x, ly]);
                    const right = x < W - 120;
                    return `<g><circle cx="${x}" cy="${y}" r="${p === c ? 7 : 5}" fill="${color[p.kind]}" stroke="#000" stroke-width="2"/><text x="${right ? x + 9 : x - 9}" y="${ly}" text-anchor="${right ? 'start' : 'end'}">${esc(p.label)}${p === c ? '' : ' · ' + (d >= 1000 ? num(d / 1000, 1) + 'k' : Math.round(d))}</text></g>`;
                }).join('');
                return `<div class="map" style="margin-top:10px"><div class="vlabel" style="color:${dimColor(dim)}">${esc(dimName(dim))} radar - true direction, distance on a log scale (north up)</div><svg viewBox="0 0 ${W} ${W}">
                    ${rings.map((r) => `<circle cx="${W / 2}" cy="${W / 2}" r="${rad(r)}" fill="none" stroke="#333" stroke-dasharray="3 4"/><text x="${W / 2 + 3}" y="${W / 2 - rad(r) - 3}" style="fill:#777;stroke-width:0">${r >= 1000 ? r / 1000 + 'k' : r}</text>`).join('')}
                    <line x1="${W / 2}" y1="8" x2="${W / 2}" y2="${W - 8}" stroke="#2c2c2c"/><line x1="8" y1="${W / 2}" x2="${W - 8}" y2="${W / 2}" stroke="#2c2c2c"/>
                    <text x="${W / 2}" y="14" text-anchor="middle" style="fill:#f66">N</text>${marks}</svg></div>`;
            }).join('');
            const dist = (a, b) => (a.dim === b.dim ? Math.round(Math.hypot(a.pos[0] - b.pos[0], a.pos[1] - b.pos[1], a.pos[2] - b.pos[2])) : null);
            const rows = P.points.map((p) => {
                const [x, y, z] = p.pos.map((v) => Math.round(v * 10) / 10);
                const dd = me && p !== me ? dist(me, p) : null;
                const nether = p.dim === 'minecraft:overworld' ? ` · Nether ≈ ${Math.floor(p.pos[0] / 8)}, ${Math.floor(p.pos[2] / 8)}` : p.dim === 'minecraft:the_nether' ? ` · Overworld ≈ ${Math.floor(p.pos[0] * 8)}, ${Math.floor(p.pos[2] * 8)}` : '';
                return `<div class="rowi"><span style="width:12px;height:12px;border-radius:50%;background:${color[p.kind]};flex:none"></span><div class="grow"><b>${esc(p.label)}</b><br><small class="mono">${x}, ${y}, ${z}</small> <small>· ${esc(dimName(p.dim || 'minecraft:overworld'))}${nether}</small></div>${dd !== null ? `<small>${num(dd)} m away</small>` : ''}<button class="btn small" data-copy="/tp @s ${x} ${y} ${z}" title="Copy teleport command">/tp</button></div>`;
            }).join('');
            const rot = P.d.Rotation;
            let facing = '';
            if (Array.isArray(rot)) {
                const yaw = ((rot[0] % 360) + 360) % 360;
                const dir = ['South', 'South-west', 'West', 'North-west', 'North', 'North-east', 'East', 'South-east'][Math.round(yaw / 45) % 8];
                facing = `<div class="rowi"><svg width="34" height="34" viewBox="-17 -17 34 34"><circle r="15" fill="#1a1a1a" stroke="#555"/><text y="-7" text-anchor="middle" style="font-size:8px;fill:#f66">N</text><g transform="rotate(${yaw + 180})"><path d="M0 -12 L4 4 L0 1 L-4 4 Z" fill="#7ed957"/></g></svg><div class="grow"><b>Facing ${dir}</b><br><small>yaw ${num(rot[0], 1)}°, pitch ${num(rot[1], 1)}° (${rot[1] > 30 ? 'looking down' : rot[1] < -30 ? 'looking up' : 'looking ahead'})</small></div></div>`;
            }
            return `<div class="card" id="s-loc"><h2>${imgTag('compass', false)} Locations <span class="n">${P.points.length} points</span></h2><div class="rows">${facing}${rows}</div>${maps}</div>`;
        }

        function effectsHTML(P) {
            return `<div class="card" id="s-eff"><h2>${imgTag('potion', false)} Active effects <span class="n">${P.effects.length}</span></h2><div class="rows">${P.effects.map((e) => {
                const col = EFFECT_COLOR[e.id] || '#888';
                const maxDur = Math.max(e.dur, ...e.hidden.map((h) => h.duration ?? h.Duration ?? 0));
                const flags = [e.ambient && 'beacon', !e.particles && 'no particles', BAD_EFFECTS.has(e.id) && 'harmful'].filter(Boolean);
                const hidden = e.hidden.length ? `<br><small>then ${e.hidden.map((h) => `${roman((h.amplifier ?? h.Amplifier ?? 0) + 1)} for ${ticks(h.duration ?? h.Duration ?? 0)}`).join(', then ')}</small>` : '';
                return `<div class="rowi"><div class="effdot" style="background:${col}">${roman(e.amp + 1)}</div><div class="grow"><b style="color:${BAD_EFFECTS.has(e.id) ? '#ff8080' : '#fff'}">${esc(pretty(e.id))} ${e.amp ? roman(e.amp + 1) : ''}</b> <small>${flags.join(' · ')}</small>${hidden}
                    <div class="timebar"><i style="width:${e.dur < 0 ? 100 : Math.min(100, (e.dur / Math.max(maxDur, 1)) * 100)}%;background:${col}"></i></div></div><span class="mono">${ticks(e.dur)}</span></div>`;
            }).join('')}</div></div>`;
        }

        function attrsHTML(P) {
            const fmt = (v) => num(v, Math.abs(v) < 10 ? 3 : 1);
            const isChanged = (a) => a.mods.length > 0 || a.def === undefined || Math.abs(a.def - a.value) > 1e-6;
            const changed = P.attrs.filter(isChanged).sort((a, b) => b.mods.length - a.mods.length || a.id.localeCompare(b.id));
            const normal = P.attrs.filter((a) => !isChanged(a)).sort((a, b) => a.id.localeCompare(b.id));
            const rows = changed.map((a) => {
                // bar shows the value relative to vanilla default (half-full = default)
                const ratio = a.def ? Math.min(1, a.value / (a.def * 2)) : a.value ? 1 : 0;
                const mods = a.mods.map((m) => `${m.op === 'add_value' ? (m.amount >= 0 ? '+' : '') + fmt(m.amount) : (m.amount >= 0 ? '+' : '') + fmt(m.amount * 100) + '%'} <span class="muted">${esc(pretty(String(m.id).replace(/^minecraft:/, '')))}</span>`).join('<br>');
                return `<div class="rowi attr"><div class="grow"><b>${esc(pretty(a.id))}</b> <span class="chg">${fmt(a.value)}</span>${a.value !== a.base ? ` <small>(base ${fmt(a.base)})</small>` : ''}${a.def !== undefined ? ` <small>default ${fmt(a.def)}</small>` : ''}
                    ${mods ? `<br><small>${mods}</small>` : ''}${a.def !== undefined ? `<div class="bar"><i style="width:${ratio * 100}%;background:#ffd84a"></i></div>` : ''}</div></div>`;
            }).join('');
            return `<div class="card" id="s-attr"><h2>${imgTag('golden_apple', false)} Attributes <span class="n">${changed.length} modified · ${normal.length} default</span></h2><div class="rows">${rows}</div>
                ${normal.length ? `<h3 class="vlabel" style="margin:12px 0 6px;font-weight:400">At vanilla default</h3><div class="kv">${normal.map((a) => `<span>${esc(pretty(a.id))}</span><span>${fmt(a.value)}</span>`).join('')}</div>` : ''}</div>`;
        }

        function abilitiesHTML(d) {
            const a = d.abilities;
            const flag = (k, label, icon) => `<div class="rowi"><img src="${iconURL(icon)}" data-id="${icon}" style="width:26px;height:26px;image-rendering:pixelated" alt="" loading="lazy"><div class="grow">${label}</div><span class="badge ${truthy(a[k]) ? 'g' : ''}">${truthy(a[k]) ? 'Yes' : 'No'}</span></div>`;
            const extra = Object.keys(a).filter((k) => !['flying', 'mayfly', 'instabuild', 'invulnerable', 'mayBuild', 'walkSpeed', 'flySpeed'].includes(k));
            return `<div class="card" id="s-abil"><h2>${imgTag('feather', false)} Abilities <span class="n">${esc(GAMEMODES[d.playerGameType] ?? '')}${d.previousPlayerGameType !== undefined && d.previousPlayerGameType >= 0 ? ' · was ' + esc(GAMEMODES[d.previousPlayerGameType] ?? d.previousPlayerGameType) : ''}</span></h2><div class="rows">
                ${flag('mayfly', 'Can fly', 'elytra')}${flag('flying', 'Currently flying', 'phantom_membrane')}${flag('instabuild', 'Instant build (creative)', 'grass_block')}
                ${flag('invulnerable', 'Invulnerable', 'totem_of_undying')}${flag('mayBuild', 'Can place &amp; break blocks', 'diamond_pickaxe')}
                <div class="rowi"><div class="grow">Walk speed <b>${num(a.walkSpeed, 3)}</b> · Fly speed <b>${num(a.flySpeed, 3)}</b></div></div>
                ${extra.map((k) => `<div class="rowi"><div class="grow">${esc(k)}</div><span class="mono">${esc(JSON.stringify(a[k]))}</span></div>`).join('')}
            </div></div>`;
        }

        function vitalsHTML(d) {
            const rows = [
                ['Health', d.Health !== undefined ? num(d.Health, 2) : null], ['Absorption', d.AbsorptionAmount],
                ['Food level', d.foodLevel], ['Saturation', d.foodSaturationLevel !== undefined ? num(d.foodSaturationLevel, 2) : null],
                ['Exhaustion', d.foodExhaustionLevel !== undefined ? num(d.foodExhaustionLevel, 2) + ' / 4' : null], ['Food tick timer', d.foodTickTimer],
                ['XP level', d.XpLevel], ['XP progress', d.XpP !== undefined ? num(d.XpP * 100, 1) + '%' : null], ['Total XP', d.XpTotal !== undefined ? num(d.XpTotal) : null],
                ['Enchant seed', d.XpSeed], ['Score', d.Score !== undefined ? num(d.Score) : null],
                ['Air', d.Air !== undefined ? `${d.Air} / 300 (${Math.round(d.Air / 20)}s)` : null],
                ['Fire', d.Fire !== undefined ? (d.Fire > 0 ? `burning for ${ticks(d.Fire)}` : `not burning (${d.Fire})`) : null],
                ['Fall distance', (d.fall_distance ?? d.FallDistance) !== undefined ? num(d.fall_distance ?? d.FallDistance, 2) + ' blocks' : null],
                ['On ground', d.OnGround !== undefined ? (truthy(d.OnGround) ? 'Yes' : 'No') : null], ['Elytra gliding', d.FallFlying !== undefined ? (truthy(d.FallFlying) ? 'Yes' : 'No') : null],
                ['Invulnerable', d.Invulnerable !== undefined ? (truthy(d.Invulnerable) ? 'Yes' : 'No') : null],
                ['Portal cooldown', d.PortalCooldown !== undefined ? d.PortalCooldown + ' ticks' : null], ['Hurt time', d.HurtTime], ['Last hurt (game tick)', d.HurtByTimestamp],
                ['Death time', d.DeathTime], ['Sleep timer', d.SleepTimer], ['Seen credits', d.seenCredits !== undefined ? (truthy(d.seenCredits) ? 'Yes' : 'No') : null],
                ['Selected hotbar slot', d.SelectedItemSlot !== undefined ? d.SelectedItemSlot + 1 : null],
                ['Motion', Array.isArray(d.Motion) ? d.Motion.map((v) => num(v, 3)).join(', ') : null],
                ['Data version', d.DataVersion !== undefined ? `${d.DataVersion} (${versionOf(d.DataVersion)})` : null],
            ].filter(([, v]) => v !== null && v !== undefined);
            return `<div class="card" id="s-vit"><h2>${imgTag('cooked_beef', false)} Vitals &amp; misc</h2><div class="kv">${rows.map(([k, v]) => `<span>${k}</span><span>${esc(v)}</span>`).join('')}</div></div>`;
        }

        function pearlsHTML(P) {
            const me = P.points.find((p) => p.kind === 'player');
            return `<div class="card" id="s-pearl"><h2>${imgTag('ender_pearl', false)} Ender pearls in flight <span class="n">${P.d.ender_pearls.length}</span></h2><div class="rows">${P.d.ender_pearls.map((p, i) => {
                const pos = p.Pos || [0, 0, 0];
                const dim = p.ender_pearl_dimension || P.dim;
                const dd = me && me.dim === dim ? Math.round(Math.hypot(pos[0] - me.pos[0], pos[2] - me.pos[2])) : null;
                return `<div class="rowi"><img src="${iconURL('ender_pearl')}" data-id="ender_pearl" style="width:26px;height:26px;image-rendering:pixelated" alt="" loading="lazy"><div class="grow"><b>Pearl ${i + 1}</b> <small>${esc(dimName(dim))}</small><br><small class="mono">${pos.map((v) => Math.round(v)).join(', ')}</small>${dd !== null ? ` <small>· ${num(dd)} blocks from player</small>` : ''}<br><small>${truthy(p.HasBeenShot) ? 'thrown' : ''} ${truthy(p.LeftOwner) ? '· left owner' : ''} ${uuidOf(p.Owner) === P.uuid ? '· owned by this player' : ''}</small></div><button class="btn small" data-copy="/tp @s ${pos.map((v) => Math.round(v)).join(' ')}">/tp</button></div>`;
            }).join('')}</div></div>`;
        }

        function wardenHTML(w) {
            const lvl = w.warning_level ?? 0;
            return `<div class="card" id="s-warden"><h2>${imgTag('sculk_shrieker', false)} Warden warnings</h2>
                <div style="display:flex;gap:6px;margin-bottom:10px">${[1, 2, 3, 4].map((i) => `<div style="flex:1;height:14px;border-radius:4px;background:${i <= lvl ? (i === 4 ? '#ff4040' : '#2ab7c4') : '#3a3a3a'}"></div>`).join('')}</div>
                <div class="kv"><span>Warning level</span><span>${lvl} / 4${lvl >= 3 ? ' - one more shriek summons a warden!' : ''}</span><span>Cooldown</span><span>${ticks(w.cooldown_ticks ?? 0)}</span><span>Since last warning</span><span>${ticks(w.ticks_since_last_warning ?? 0)} (level drops by 1 every 10 min)</span></div></div>`;
        }

        function recipeIcon(id) {
            let s = strip(id).replace(/_from_.*$|_smithing.*$|_(smelting|blasting|smoking|campfire_cooking|stonecutting)$/, '');
            s = s.replace(/^(dye_|undye_)/, '');
            return s;
        }
        function recipesHTML(rb) {
            const rec = Array.isArray(rb.recipes) ? rb.recipes : [];
            const fresh = new Set(Array.isArray(rb.toBeDisplayed) ? rb.toBeDisplayed : []);
            const flags = Object.entries(rb).filter(([k, v]) => typeof v === 'number').map(([k, v]) => `<span class="badge ${truthy(v) ? 'g' : ''}">${esc(k.replace(/^is/, '').replace(/([A-Z])/g, ' $1').trim())}: ${truthy(v) ? 'on' : 'off'}</span>`).join('');
            return `<div class="card section" id="s-recipe"><h2>${imgTag('crafting_table', false)} Recipe book <span class="n">${rec.length} unlocked · ${fresh.size} not yet viewed</span></h2>
                <div class="badges">${flags}</div>
                <details data-lazy="recipes"><summary class="btn small" style="list-style:none">Show all ${rec.length} recipes</summary><div class="chips" style="margin-top:10px" data-fill></div></details></div>`;
        }
        function fillRecipes(el) {
            const rb = players[active].P.d.recipeBook;
            const fresh = new Set(rb.toBeDisplayed || []);
            el.innerHTML = [...rb.recipes].sort().map((r) => `<span class="chip${fresh.has(r) ? ' new' : ''}" data-s="${esc(r.toLowerCase())}" title="${esc(r)}${fresh.has(r) ? ' (not viewed yet)' : ''}"><span class="ico">${imgTag(recipeIcon(r))}</span>${esc(pretty(r))}</span>`).join('');
            applySearch();
        }

        /* ---------- statistics (world/stats/<uuid>.json) ---------- */
        function statsHTML(ex) {
            const raw = ex.data;
            let st = isObj(raw.stats) ? raw.stats : null;
            if (!st) {      // pre-1.13 flat "stat.xxx" format
                st = { 'minecraft:custom': {} };
                for (const [k, v] of Object.entries(raw)) if (typeof v === 'number') st['minecraft:custom'][k] = v;
            }
            const cat = (c) => st['minecraft:' + c] || st[c] || {};
            const custom = cat('custom');
            const g = (k) => custom['minecraft:' + k] ?? custom[k] ?? 0;
            const sum = (o) => Object.values(o).reduce((s, v) => s + (typeof v === 'number' ? v : 0), 0);
            const play = g('play_time') || g('play_one_minute') || g('stat.playOneMinute');
            const travel = TRAVEL.map(([k, label, col]) => [k, label, col, g(k)]).filter((t) => t[3] > 0);
            const dist = travel.reduce((s, t) => s + t[3], 0);
            const tiles = [
                [fmtStat('play_time', play), 'Play time', 1], [num(g('deaths')), 'Deaths', 1], [num(g('mob_kills')), 'Mob kills'], [num(g('player_kills')), 'Player kills'],
                [fmtStat('walk_one_cm', dist), 'Distance travelled'], [num(g('jump')), 'Jumps'], [num(g('damage_dealt') / 20, 0), 'Hearts of damage dealt'],
                [num(g('damage_taken') / 20, 0), 'Hearts of damage taken'], [num(sum(cat('mined'))), 'Blocks mined'], [num(sum(cat('crafted'))), 'Items crafted'],
                [fmtStat('time_since_death', g('time_since_death')), 'Since last death'], [num(g('traded_with_villager')), 'Villager trades'], [num(g('enchant_item')), 'Items enchanted'],
                [num(g('fish_caught')), 'Fish caught'], [num(g('animals_bred')), 'Animals bred'], [num(g('raid_win')), 'Raids won'], [num(g('sleep_in_bed')), 'Nights slept'],
            ].filter(([v, , always]) => always || (v !== '0' && !/^0(\.0)? /.test(v)));

            const tabs = STAT_CATS.map(([c, label, icon]) => [c, label, icon, Object.entries(cat(c)).filter(([, v]) => typeof v === 'number' && v > 0).sort((a, b) => b[1] - a[1])]).filter((t) => t[3].length);
            const first = (tabs.find((t) => t[0] === 'mined') || tabs[0] || [])[0];
            const panel = ([c, , , list]) => `<div class="stgrid${c === first ? '' : ' hide'}" data-spanel="${c}">${list.map(([id, v]) => {
                const label = langId(id) || pretty(id);
                if (c === 'custom') return `<div class="sti" data-s="${esc((id + ' ' + label).toLowerCase())}"><div><span title="${esc(id)}">${esc(label)}</span><b>${esc(fmtStat(id, v))}</b></div></div>`;
                const icon = c === 'killed' || c === 'killed_by' ? mobIcon(id) : blockIcon(id);
                return `<div class="sti" data-s="${esc((id + ' ' + label).toLowerCase())}" title="${esc(id)}"><span class="ico">${imgTag(icon)}</span><div><span>${esc(label)}</span><b>${num(v)}</b></div></div>`;
            }).join('')}</div>`;

            return `<div class="card section" id="s-stats"><h2>${imgTag('writable_book', false)} Statistics <span class="n">${esc(ex.file)}</span></h2>
                <div class="tiles">${tiles.map(([v, l]) => `<div class="tile"><b>${esc(v)}</b><span>${l}</span></div>`).join('')}</div>
                ${dist ? `<div style="margin-top:14px"><div class="vlabel">How they travelled · ${esc(fmtStat('walk_one_cm', dist))} total</div>
                    <div class="travel">${travel.map(([, label, col, v]) => `<i style="width:${(v / dist) * 100}%;background:${col}" title="${label}: ${esc(fmtStat('walk_one_cm', v))}"></i>`).join('')}</div>
                    <div class="legend">${travel.sort((a, b) => b[3] - a[3]).map(([, label, col, v]) => `<span style="--c:${col}">${label} ${esc(fmtStat('walk_one_cm', v))}</span>`).join('')}</div></div>` : ''}
                <div class="stabs">${tabs.map(([c, label, icon, list]) => `<button data-stab="${c}" class="${c === first ? 'on' : ''}">${label}<small>${list.length}</small></button>`).join('')}</div>
                ${tabs.map(panel).join('')}</div>`;
        }

        /* ---------- advancements (world/advancements/<uuid>.json) ---------- */
        function advHTML(ex) {
            const data = ex.data;
            const entries = Object.entries(data).filter(([, v]) => isObj(v));
            const recipes = entries.filter(([k]) => /:recipes\//.test(k));
            const groups = new Map();
            const add = (cat, id, v) => { if (!groups.has(cat)) groups.set(cat, new Map()); groups.get(cat).set(id, v); };
            for (const cat of Object.keys(ADV)) ADV[cat].forEach((s) => add(cat, `minecraft:${cat}/${s.split('|')[0]}`, data[`minecraft:${cat}/${s.split('|')[0]}`]));
            for (const [k, v] of entries) {
                if (/:recipes\//.test(k)) continue;
                const [ns, path] = k.includes(':') ? k.split(':') : ['minecraft', k];
                const cat = ns === 'minecraft' ? path.split('/')[0] : `${ns}: ${path.split('/')[0]}`;
                add(cat, k, v);
            }
            let doneAll = 0, totalAll = 0;
            const cats = [...groups].map(([cat, map]) => {
                const list = [...map];
                const done = list.filter(([, v]) => v && truthy(v.done)).length;
                doneAll += done; totalAll += list.length;
                const tiles = list.map(([id, v]) => {
                    const info = ADV_INFO[id] || { title: pretty(id.split('/').pop()), icon: 'book' };
                    const crit = v && isObj(v.criteria) ? Object.entries(v.criteria) : [];
                    const isDone = v && truthy(v.done);
                    const when = crit.map(([, t]) => String(t)).sort().pop();
                    const cls = ['adv', isDone ? 'done' : crit.length ? 'part' : '', ADV_CHALLENGE.has(id) ? 'chal' : ''].join(' ');
                    const tip = [info.title, isDone ? `Completed ${when ? when.slice(0, 16) : ''}` : crit.length ? `In progress - ${crit.length} criteria done` : 'Not started', id, crit.length > 1 ? 'Done: ' + crit.map(([c]) => pretty(c)).join(', ') : ''].filter(Boolean).join('\n');
                    return `<div class="${cls}" data-s="${esc((id + ' ' + info.title).toLowerCase())}" title="${esc(tip)}"><span class="frame"><span class="ico">${imgTag(info.icon)}</span></span>${esc(info.title)}<small>${isDone ? (when ? esc(when.slice(0, 10)) : 'done') : crit.length ? crit.length + ' criteria' : ''}</small></div>`;
                }).join('');
                const name = ADV[cat] ? pretty(cat) : cat;
                return `<div class="adv-cat"><h3>${ADV_CAT_ICON[cat] ? imgTag(ADV_CAT_ICON[cat], false).replace('<img', '<img style="width:20px;height:20px"') : ''}${esc(name)}<span class="n">${done} / ${list.length}</span></h3>
                    <div class="progress"><i style="width:${list.length ? (done / list.length) * 100 : 0}%"></i></div><div class="advs">${tiles}</div></div>`;
            });
            const recDone = recipes.filter(([, v]) => truthy(v.done)).length;
            return `<div class="card section" id="s-adv"><h2>${imgTag('knowledge_book', false)} Advancements <span class="n">${doneAll} / ${totalAll} done · ${num(recDone)} recipe unlocks · ${esc(ex.file)}</span></h2>
                <div class="progress" style="height:10px"><i style="width:${totalAll ? (doneAll / totalAll) * 100 : 0}%"></i></div>${cats.join('')}</div>`;
        }

        function otherHTML(d, keys) {
            return `<div class="card" id="s-other"><h2>${imgTag('book', false)} Other data <span class="n">${keys.length} keys</span></h2>
                <p class="muted" style="font-size:13px;margin:0 0 8px">Everything else in the file - mod, plugin and server data included.</p>
                <div class="tree">${keys.map((k) => treeNode(k, d[k], 0, true)).join('')}</div></div>`;
        }

        /* =====================================================================
           Server, world, plugin and mod data for one player.
           pub - safe to put in share links (also what a share link brings back).
           IPs, chat, commands, mail, seeds and passwords are never read into it.
           ===================================================================== */
        const fmtDate = (ms) => (ms ? new Date(ms).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '');
        const dateAgo = (ms) => (ms ? `${esc(fmtDate(ms))} <small class="muted">(${esc(ago(ms))})</small>` : null);
        const dur = (ms) => { const m = Math.max(0, Math.round(ms / 60000)); return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`; };
        const ampHTML = (s) => legacyHTML(String(s ?? '').replace(/&([0-9a-fk-or])/gi, '§$1'), {});
        const kvHTML = (rows) => `<div class="kv">${rows.filter((r) => r && r[1] !== null && r[1] !== undefined && r[1] !== '').map(([k, v]) => `<span>${k}</span><span>${v}</span>`).join('')}</div>`;
        const tpBtn = (x, y, z) => (Number.isFinite(+x) && Number.isFinite(+z) ? `<button class="btn small" data-copy="/tp @s ${Math.round(x)} ${Number.isFinite(+y) && y !== null ? Math.round(y) : '~'} ${Math.round(z)}" title="Copy teleport command">/tp</button>` : '');
        const xyz = (x, y, z) => [x, y, z].map((v) => (Number.isFinite(+v) && v !== null ? Math.round(v) : '~')).join(', ');
        const parseMcDate = (s) => {
            if (typeof s !== 'string') return 0;
            const m = /^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2}) ([+-]\d{2})(\d{2})$/.exec(s.trim());
            return Date.parse(m ? `${m[1]}T${m[2]}${m[3]}:${m[4]}` : s) || 0;
        };
        const nameFor = (k) => CTX.names.get(k) || (players.find((p) => p.key === k) || {}).name || dashUuid(k);
        const ESS_FLAG = { afk: 'AFK', godmode: 'God mode', muted: 'Muted', jailed: 'Jailed', socialspy: 'Social spy', npc: 'NPC', tpoff: 'Teleports off', baltopExempt: 'Hidden from baltop' };
        const teamHTML = (t) => `${t.prefix ? textHTML(t.prefix) : ''}${t.display !== undefined && t.display !== null ? textHTML(t.display, { color: MC_COLORS[t.color] }) : spanFor(t.name, { color: MC_COLORS[t.color] })}${t.suffix ? textHTML(t.suffix) : ''}`;
        const itemIdOf = (v) => (typeof v === 'string' ? strip(v) : isObj(v) && typeof v.id === 'string' ? strip(v.id) : null);

        // which Bukkit world name / dimension a location is in
        function worldDim(w) {
            const s = String(w || ''), main = (CTX.props && CTX.props.data['level-name']) || 'world';
            const env = String((CTX.mv.get(s) || {}).environment || '').toUpperCase();
            if (env === 'NETHER' || s === main + '_nether' || /^DIM-1$/i.test(s)) return 'minecraft:the_nether';
            if (env === 'THE_END' || s === main + '_the_end' || /^DIM1$/i.test(s)) return 'minecraft:the_end';
            if (!s || s === main || s.includes(':')) return s.includes(':') ? s : 'minecraft:overworld';
            return s;
        }

        // every lowercased name this player is known by (scoreboards, logs and Towny go by name)
        function namesOf(pl) {
            const s = new Set();
            const add = (n) => { if (n) s.add(String(n).toLowerCase()); };
            add(pl.name);
            add(pl.key && CTX.names.get(pl.key));
            add(isObj(pl.P.d.bukkit) && pl.P.d.bukkit.lastKnownName);
            for (const [n, k] of CTX.logUuids) if (k === pl.key) add(n);
            return s;
        }

        function lpAllNodes(user) {
            const all = [...user.nodes], seen = new Set();
            const visit = (g, depth) => {
                g = String(g).toLowerCase();
                if (seen.has(g) || depth > 6) return;
                seen.add(g);
                const nodes = CTX.lpGroups.get(g) || [];
                all.push(...nodes);
                nodes.filter((n) => n.value && /^group\./.test(n.key)).forEach((n) => visit(n.key.slice(6), depth + 1));
            };
            user.nodes.filter((n) => n.value && /^group\./.test(n.key)).forEach((n) => visit(n.key.slice(6), 0));
            if (user.primary) visit(user.primary, 0);
            return all;
        }
        const lpMeta = (nodes, type) => {
            let best = null;
            for (const n of nodes) {
                const m = n.value && new RegExp(`^${type}\\.(-?\\d+)\\.(.*)$`).exec(n.key);
                if (m && (!best || +m[1] > best[0])) best = [+m[1], m[2]];
            }
            return best ? best[1] : null;
        };

        function petOf(e) {
            const items = [];
            const push = (x) => { if (isObj(x) && typeof x.id === 'string') items.push(x); };
            (Array.isArray(e.Items) ? e.Items : []).forEach(push);
            [e.SaddleItem, e.ArmorItem, e.DecorItem, e.body_armor_item, e.saddle].forEach(push);
            if (isObj(e.equipment)) Object.values(e.equipment).forEach(push);
            [...(Array.isArray(e.ArmorItems) ? e.ArmorItems : []), ...(Array.isArray(e.HandItems) ? e.HandItems : [])].forEach(push);
            return { id: String(e.id || ''), name: e.CustomName ?? null, pos: Array.isArray(e.Pos) ? e.Pos.map(Number) : null, dim: e._dim || 'minecraft:overworld', hp: typeof e.Health === 'number' ? e.Health : null, sitting: truthy(e.Sitting ?? e.OrderedToSit ?? 0), baby: (e.Age ?? 0) < 0, items: items.slice(0, 40) };
        }

        const b64 = (u) => { let s = ''; for (let i = 0; i < u.length; i += 8192) s += String.fromCharCode.apply(null, u.subarray(i, i + 8192)); return btoa(s); };
        const unb64 = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0));

        function linkFor(pl) {
            const key = pl.key, names = namesOf(pl), d = pl.P.d, uuid = pl.P.uuid;
            const live = {};
            if (key) {
                const op = CTX.ops.get(key);
                if (op) live.op = { level: op.level ?? 4, bypass: truthy(op.bypassesPlayerLimit) };
                else if (CTX.opsLoaded) live.op = false;
                if (CTX.whitelistLoaded) live.whitelisted = CTX.whitelist.has(key);
                const ban = CTX.bans.get(key);
                if (ban) live.ban = { reason: ban.reason || '', source: ban.source || '', created: ban.created || '', expires: ban.expires || '' };
                else if (CTX.bansLoaded) live.ban = false;
                const uc = CTX.usercache.get(key);
                if (uc && uc.seen) live.cacheSeen = uc.seen;
            }
            const wu = worldUuid(d);
            if (wu && CTX.worlds.has(wu)) live.world = CTX.worlds.get(wu);

            const sb = CTX.scoreboard;
            if (sb) {
                const ids = new Set([...names, uuid || '-']);
                const scores = sb.scores.filter((s) => ids.has(String(s.Name).toLowerCase())).map((s) => { const o = sb.objectives.get(s.Objective) || {}; return { obj: s.Objective, name: o.name ?? null, criteria: o.criteria || '', score: s.Score }; });
                if (scores.length) live.scores = scores;
                const t = sb.teams.find((t) => (t.Players || []).some((p) => ids.has(String(p).toLowerCase())));
                if (t) live.team = { name: t.Name, display: t.DisplayName ?? null, color: t.TeamColor || '', prefix: t.MemberNamePrefix ?? null, suffix: t.MemberNameSuffix ?? null };
            }

            const ess = key && CTX.ess.get(key);
            if (ess) {
                const loc = (l) => (isObj(l) && l.x !== undefined ? { world: l['world-name'] || CTX.worlds.get(String(l.world)) || String(l.world || ''), x: +l.x, y: +l.y, z: +l.z } : null);
                const ts = isObj(ess.timestamps) ? ess.timestamps : {};
                const flags = ['afk', 'godmode', 'muted', 'jailed', 'socialspy', 'npc'].filter((f) => truthy(ess[f]));
                if (ess.teleportenabled === false || ess['teleport-enabled'] === false) flags.push('tpoff');
                if (truthy(ess['baltop-exempt'])) flags.push('baltopExempt');
                live.ess = {
                    name: ess.lastAccountName ?? ess['last-account-name'] ?? null, money: ess.money !== undefined && ess.money !== null ? String(ess.money) : null,
                    nick: ess.nickname ?? ess.nick ?? null, flags, jail: ess.jail || null, muteUntil: +ts.mute || 0, login: +ts.login || 0, logoutAt: +ts.logout || 0,
                    homes: isObj(ess.homes) ? Object.entries(ess.homes).map(([n, l]) => ({ name: n, ...loc(l) })).filter((h) => Number.isFinite(h.x)) : [],
                    logout: loc(ess.logoutlocation ?? ess['logout-location']), last: loc(ess.lastlocation ?? ess['last-location']),
                    kits: isObj(ts.kits) ? Object.keys(ts.kits) : [], powertools: isObj(ess.powertools) ? Object.keys(ess.powertools).length : 0,
                    ignore: Array.isArray(ess.ignore) ? ess.ignore.length : 0,
                };
            }

            const lp = key && CTX.lpUsers.get(key);
            if (lp) {
                const all = lpAllNodes(lp);
                live.lp = {
                    primary: lp.primary || 'default', groups: lp.nodes.filter((n) => n.value && /^group\./.test(n.key)).map((n) => n.key.slice(6)),
                    prefix: lpMeta(all, 'prefix'), suffix: lpMeta(all, 'suffix'),
                    perms: lp.nodes.filter((n) => !/^(group|prefix|suffix|meta|weight|displayname)\./.test(n.key)).slice(0, 500).map((n) => ({ k: n.key, v: n.value, ctx: n.context ? Object.entries(n.context).map(([a, b]) => `${a}=${b}`).join(', ') : '', exp: n.expiry || 0 })),
                };
            }

            const mc = CTX.mcmmo.find((r) => (key && r.uuid === key) || names.has(r.name.toLowerCase()));
            if (mc) live.mcmmo = { skills: mc.skills, power: Object.values(mc.skills).reduce((s, [l]) => s + l, 0) };

            if (key) {
                const gp = CTX.gpPlayers.get(key), claims = CTX.gpClaims.filter((c) => c.owner === key);
                if (gp || claims.length) live.gp = { accrued: gp ? gp.accrued : null, bonus: gp ? gp.bonus : null, claims: claims.map((c) => ({ id: c.id, world: c.world, a: c.a, b: c.b, area: c.area, parent: c.parent, trusted: c.trusted })) };
            }
            const inGroup = (g) => (key && g.ids.includes(key)) || [...names].some((n) => g.names.includes(n));
            const wg = CTX.wg.map((r) => { const role = inGroup(r.owners) ? 'owner' : inGroup(r.members) ? 'member' : null; return role && { id: r.id, world: r.world, role, type: r.type || '', priority: r.priority ?? 0, min: r.min || null, max: r.max || null }; }).filter(Boolean);
            if (wg.length) live.wg = wg;

            const res = (key && [...CTX.towny.residents.values()].find((r) => uuidKey(r.uuid) === key)) || [...names].map((n) => CTX.towny.residents.get(n)).find(Boolean);
            if (res) {
                const town = res.town ? CTX.towny.towns.get(String(res.town).toLowerCase()) : null;
                live.towny = { town: res.town || null, nation: (town && town.nation) || null, mayor: !!(town && names.has(String(town.mayor || '').toLowerCase())), ranks: res['town-ranks'] || '', nationRanks: res['nation-ranks'] || '', title: res.title || '', surname: res.surname || '', registered: +res.registered || 0, lastOnline: +res.lastOnline || 0, friends: String(res.friends || '').split(',').filter(Boolean) };
            }

            const pets = key && CTX.pets.get(key);
            if (pets && pets.length) live.pets = pets.slice(0, 300).map(petOf);

            if (CTX.sess) {
                const ps = [...names].map((n) => CTX.sess.players.get(n)).filter(Boolean);
                if (ps.length) {
                    const sessions = ps.flatMap((p) => p.sessions).sort((a, b) => a.start - b.start);
                    const deaths = ps.flatMap((p) => p.deaths).sort((a, b) => a[0] - b[0]);
                    const lastS = sessions[sessions.length - 1];
                    live.sess = {
                        files: CTX.logFiles.length, count: sessions.length, total: sessions.reduce((s, x) => s + ((x.end || CTX.sess.last) - x.start), 0),
                        first: sessions.length ? sessions[0].start : 0, last: lastS ? lastS.end || lastS.start : 0, online: sessions.some((s) => s.online),
                        recent: sessions.slice(-25).reverse().map((s) => ({ start: s.start, end: s.end, why: s.why || '', online: !!s.online, world: s.world || null, pos: s.pos || null })),
                        deaths: deaths.slice(-40), adv: ps.reduce((s, p) => s + p.adv.length, 0),
                    };
                }
            }

            const F = CTX.ftb;
            if (uuid && (F.chapters.length || F.chunks.size)) {
                const team = F.teams.find((t) => t.type === 'party' && t.members.includes(uuid)) || F.teams.find((t) => t.id === uuid);
                const tid = team ? team.id : uuid;
                const prog = F.progress.get(tid) || F.progress.get(uuid);
                if (F.chapters.length && prog) {
                    const done = new Set(Object.keys(prog.completed).map((k) => k.toLowerCase()));
                    const chapters = [...F.chapters].sort((a, b) => a.order - b.order).map((c) => ({ title: ftbTitle('chapter', c.id, c.title) || pretty(c.file), icon: itemIdOf(c.icon), done: c.quests.filter((q) => done.has(q.id.toLowerCase())).length, total: c.quests.length }));
                    live.quests = { team: team && team.type === 'party' ? team.name || 'Party' : null, chapters, done: chapters.reduce((s, c) => s + c.done, 0), total: chapters.reduce((s, c) => s + c.total, 0) };
                }
                const ch = F.chunks.get(tid) || F.chunks.get(uuid);
                if (ch) live.chunks = { ...ch, team: team && team.type === 'party' ? team.name || 'Party' : null };
            }

            // map pictures + backpack contents travel with the share so the inventory still renders
            const maps = {};
            for (const id of pl.P.mapIds) { const m = CTX.maps.get(id); if (m && Object.keys(maps).length < 32) maps[id] = { c: b64(m.colors), scale: m.scale, dim: m.dim, x: m.x, z: m.z, locked: m.locked }; }
            if (Object.keys(maps).length) live.maps = maps;
            const bps = {};
            for (const u of pl.P.bpIds) if (CTX.backpacks.has(u)) bps[u] = CTX.backpacks.get(u);
            if (Object.keys(bps).length) live.backpacks = bps;

            // what this player brought from a share link, overridden by anything loaded here
            const pub = { ...(isObj(pl.extras.ctx) ? pl.extras.ctx : {}), ...live };
            const points = [];
            if (pub.ess) {
                (pub.ess.homes || []).forEach((h) => points.push({ label: `Home "${h.name}"`, pos: [h.x, h.y, h.z], dim: worldDim(h.world), kind: 'home' }));
                if (pub.ess.logout && Number.isFinite(pub.ess.logout.x)) points.push({ label: 'Logout spot (Essentials)', pos: [pub.ess.logout.x, pub.ess.logout.y, pub.ess.logout.z], dim: worldDim(pub.ess.logout.world), kind: 'misc' });
            }
            (pub.pets || []).slice(0, 40).forEach((p) => p.pos && points.push({ label: (p.name !== null && plain(textHTML(p.name))) || pretty(p.id), pos: p.pos, dim: p.dim, kind: 'pet' }));
            if (pub.gp) pub.gp.claims.filter((c) => c.parent < 0).forEach((c) => points.push({ label: `Claim ${c.id}`, pos: [(c.a.x + c.b.x) / 2, c.a.y, (c.a.z + c.b.z) / 2], dim: worldDim(c.world), kind: 'claim' }));
            return { pub, points };
        }
        const ftbTitle = (type, id, title) => (title ? String(title) : CTX.ftb.lang.get(`${type}.${id}.title`) || null);

        function serverCardHTML(pl, L) {
            const d = pl.P.d, b = isObj(d.bukkit) ? d.bukkit : {}, pp = isObj(d.Paper) ? d.Paper : {}, X = L.pub;
            const ms = (v) => (+v > 1e11 ? dateAgo(+v) : null);
            const wu = worldUuid(d);
            const rows = [
                ['First joined', ms(b.firstPlayed)], ['Last played', ms(b.lastPlayed)], ['Last login', ms(pp.LastLogin)], ['Last seen', ms(pp.LastSeen)],
                ['Last known name', b.lastKnownName ? esc(b.lastKnownName) : null],
                ['World', X.world ? esc(X.world) : wu ? `<span class="mono" title="Drop the world's uid.dat to see its name">${esc(wu)}</span>` : null],
                ['Ticks lived', d['Spigot.ticksLived'] !== undefined ? esc(ticks(d['Spigot.ticksLived'])) : null],
                ['Server data version', d['Bukkit.updateLevel'] !== undefined ? esc(d['Bukkit.updateLevel']) : null],
                ['Operator', X.op ? `Level ${esc(X.op.level)}${X.op.bypass ? ' · bypasses the player limit' : ''}` : X.op === false ? 'No' : null],
                ['Whitelisted', X.whitelisted === true ? 'Yes' : X.whitelisted === false ? 'No' : null],
                ['Banned', X.ban ? `<b style="color:#ff8080">Yes</b>${X.ban.reason ? ' - ' + esc(X.ban.reason) : ''}<br><small class="muted">by ${esc(X.ban.source || '?')} · ${esc(X.ban.created || '?')} · expires ${esc(X.ban.expires || 'never')}</small>` : X.ban === false ? 'No' : null],
                ['Last login (user cache)', X.cacheSeen ? dateAgo(X.cacheSeen) : null],
                ['Team', X.team ? teamHTML(X.team) : null],
            ];
            const scores = (X.scores || []).map((s) => `<div class="rowi"><div class="grow">${s.name !== null && s.name !== undefined ? textHTML(s.name) : esc(s.obj)} <small class="mono">${esc(s.obj)}</small>${s.criteria && s.criteria !== 'dummy' ? ` <small>${esc(s.criteria)}</small>` : ''}</div><b>${esc(num(s.score))}</b></div>`).join('');
            if (!rows.some((r) => r[1] !== null && r[1] !== undefined) && !scores) return '';
            return `<div class="card" id="s-server"><h2>${imgTag('command_block', false)} Server</h2>${kvHTML(rows)}${scores ? `<h3 class="sub">Scoreboard</h3><div class="rows">${scores}</div>` : ''}</div>`;
        }

        function essHTML(L) {
            const E = L.pub.ess;
            if (!E) return '';
            const loc = (l) => (l && Number.isFinite(l.x) ? `<span class="mono">${xyz(l.x, l.y, l.z)}</span> <small>${esc(l.world || '')}</small> ${tpBtn(l.x, l.y, l.z)}` : null);
            const rows = [
                ['Balance', E.money !== null && E.money !== '' ? esc(Number.isFinite(+E.money) ? num(+E.money, 2) : E.money) : null], ['Nickname', E.nick ? ampHTML(E.nick) : null],
                ['Account name', E.name ? esc(E.name) : null], ['Last login', dateAgo(E.login)], ['Last logout', dateAgo(E.logoutAt)],
                ['Logout spot', loc(E.logout)], ['Last teleported from', loc(E.last)], ['Muted until', E.muteUntil > Date.now() ? dateAgo(E.muteUntil) : null],
                ['Jail', E.jail ? esc(E.jail) : null], ['Kits used', E.kits && E.kits.length ? esc(E.kits.join(', ')) : null],
                ['Power tools', E.powertools ? esc(E.powertools) : null], ['Ignoring', E.ignore ? esc(E.ignore) + ' players' : null],
            ];
            const homes = (E.homes || []).map((h) => `<div class="rowi"><span class="dot" style="background:#ff9f40"></span><div class="grow"><b>${esc(h.name)}</b><br><small class="mono">${xyz(h.x, h.y, h.z)}</small> <small>${esc(h.world || '')}</small></div>${tpBtn(h.x, h.y, h.z)}</div>`).join('');
            return `<div class="card" id="s-ess"><h2>${imgTag('gold_ingot', false)} EssentialsX <span class="n">${(E.homes || []).length} home${(E.homes || []).length === 1 ? '' : 's'}</span></h2>
                ${E.flags && E.flags.length ? `<div class="badges">${E.flags.map((f) => `<span class="badge">${esc(ESS_FLAG[f] || f)}</span>`).join('')}</div>` : ''}${kvHTML(rows)}
                ${homes ? `<h3 class="sub">Homes</h3><div class="rows">${homes}</div>` : ''}</div>`;
        }

        function permHTML(L) {
            const P = L.pub.lp;
            if (!P) return '';
            const chips = (P.perms || []).map((n) => `<span class="chip" data-s="${esc(n.k.toLowerCase())}" style="padding:2px 7px;${n.v ? '' : 'color:#ff8080;text-decoration:line-through'}" title="${esc(n.ctx || 'global')}${n.exp ? ' · expires ' + esc(fmtDate(n.exp * 1000)) : ''}">${esc(n.k)}${n.ctx ? ` <small class="muted">${esc(n.ctx)}</small>` : ''}</span>`).join('');
            return `<div class="card" id="s-perm"><h2>${imgTag('name_tag', false)} LuckPerms <span class="n">${(P.perms || []).length} permission${(P.perms || []).length === 1 ? '' : 's'}</span></h2>
                ${kvHTML([['Primary group', esc(P.primary)], ['Groups', P.groups && P.groups.length ? P.groups.map((g) => `<span class="badge b">${esc(g)}</span>`).join(' ') : null], ['Prefix', P.prefix ? ampHTML(P.prefix) : null], ['Suffix', P.suffix ? ampHTML(P.suffix) : null]])}
                ${chips ? `<div class="chips" style="margin-top:10px">${chips}</div>` : ''}</div>`;
        }

        function townyHTML(L) {
            const T = L.pub.towny;
            if (!T) return '';
            return `<div class="card" id="s-towny"><h2>${imgTag('white_banner', false)} Towny</h2>${kvHTML([
                ['Town', T.town ? esc(T.town) + (T.mayor ? ' <span class="badge g">Mayor</span>' : '') : 'None'], ['Nation', T.nation ? esc(T.nation) : null],
                ['Town ranks', T.ranks ? esc(T.ranks) : null], ['Nation ranks', T.nationRanks ? esc(T.nationRanks) : null],
                ['Title', T.title ? ampHTML(T.title) : null], ['Surname', T.surname ? ampHTML(T.surname) : null],
                ['Registered', dateAgo(T.registered)], ['Last online', dateAgo(T.lastOnline)], ['Friends', T.friends && T.friends.length ? esc(T.friends.join(', ')) : null],
            ])}</div>`;
        }

        function mcmmoHTML(L) {
            const M = L.pub.mcmmo;
            if (!M) return '';
            return `<div class="card" id="s-mcmmo"><h2>${imgTag('experience_bottle', false)} mcMMO <span class="n">power level ${esc(num(M.power))}</span></h2><div class="stgrid">${Object.entries(M.skills).map(([n, [lvl, xp]]) =>
                `<div class="sti" data-s="${esc(n.toLowerCase())}"><span class="ico">${imgTag(Object.hasOwn(MCMMO_ICON, n) ? MCMMO_ICON[n] : 'book')}</span><div><span>${esc(n)}</span><b>${esc(num(lvl))}</b> <small class="muted">${esc(num(xp))} xp</small></div></div>`).join('')}</div></div>`;
        }

        function claimsHTML(L) {
            const { gp, wg, chunks } = L.pub;
            if (!gp && !wg && !chunks) return '';
            let h = '';
            if (gp) {
                const used = gp.claims.filter((c) => c.parent < 0).reduce((s, c) => s + c.area, 0), total = (gp.accrued || 0) + (gp.bonus || 0);
                h += `<h3 class="sub" style="margin-top:0">GriefPrevention</h3>${kvHTML([['Claim blocks', gp.accrued !== null ? `${esc(num(total))} <small class="muted">(${esc(num(gp.accrued))} earned + ${esc(num(gp.bonus))} bonus)</small>` : null], ['In use', esc(num(used))], ['Left', gp.accrued !== null ? esc(num(total - used)) : null]])}
                    <div class="rows" style="margin-top:6px">${gp.claims.map((c) => `<div class="rowi"><span class="dot" style="background:#b3a1ff"></span><div class="grow"><b>Claim ${esc(c.id)}</b>${c.parent >= 0 ? ' <small>subdivision</small>' : ''}<br><small class="mono">${esc(c.a.x)}, ${esc(c.a.z)} → ${esc(c.b.x)}, ${esc(c.b.z)}</small> <small>${esc(c.world)} · ${esc(num(c.area))} blocks${c.trusted ? ` · ${esc(c.trusted)} trusted` : ''}</small></div>${tpBtn((c.a.x + c.b.x) / 2, null, (c.a.z + c.b.z) / 2)}</div>`).join('')}</div>`;
            }
            if (wg) {
                h += `<h3 class="sub">WorldGuard regions</h3><div class="rows">${wg.map((r) => {
                    const b = isObj(r.min) && isObj(r.max) ? `${esc(r.min.x)}, ${esc(r.min.y)}, ${esc(r.min.z)} → ${esc(r.max.x)}, ${esc(r.max.y)}, ${esc(r.max.z)}` : esc(r.type);
                    return `<div class="rowi"><div class="grow"><b>${esc(r.id)}</b> <span class="badge ${r.role === 'owner' ? 'g' : ''}">${esc(r.role)}</span><br><small class="mono">${b}</small> <small>${esc(r.world)} · priority ${esc(r.priority)}</small></div>${isObj(r.min) && isObj(r.max) ? tpBtn((r.min.x + r.max.x) / 2, null, (r.min.z + r.max.z) / 2) : ''}</div>`;
                }).join('')}</div>`;
            }
            if (chunks) {
                const total = Object.values(chunks.byDim || {}).reduce((s, n) => s + n, 0);
                h += `<h3 class="sub">FTB Chunks${chunks.team ? ' · ' + esc(chunks.team) : ''}</h3>${kvHTML([['Claimed chunks', esc(num(total))], ['Force loaded', chunks.forced ? esc(num(chunks.forced)) : null], ...Object.entries(chunks.byDim || {}).map(([dm, n]) => [esc(dimName(dm)), esc(num(n))])])}`;
            }
            return `<div class="card" id="s-claims"><h2>${imgTag('golden_shovel', false)} Claims &amp; regions</h2>${h}</div>`;
        }

        function sessHTML(L) {
            const S = L.pub.sess;
            if (!S) return '';
            const tiles = [[num(S.count), 'Sessions'], [dur(S.total), 'Time online'], [S.first ? new Date(S.first).toLocaleDateString() : '?', 'First seen'], [S.last ? ago(S.last) : '?', 'Last seen'], [num(S.deaths.length), 'Deaths logged'], [num(S.adv), 'Advancements logged']];
            const quiet = /^(left|Disconnected|disconnect\.quitting|Disconnected by client)$/i;
            const rows = S.recent.map((s) => `<div class="rowi"><div class="grow"><b>${esc(fmtDate(s.start))}</b><br><small>${s.end ? dur(s.end - s.start) + (s.why && !quiet.test(s.why) ? ' · ' + esc(s.why) : '') : s.online ? 'still online when the log ended' : 'end not logged'}${s.world ? ' · ' + esc(s.world) : ''}</small></div>${s.pos ? `<small class="mono">${xyz(...s.pos)}</small>` : ''}</div>`).join('');
            const deaths = S.deaths.slice(-15).reverse().map(([t, m]) => `<div class="rowi"><span class="ico">${imgTag('skeleton_skull')}</span><div class="grow">${esc(m)}<br><small>${esc(fmtDate(t))}</small></div></div>`).join('');
            return `<div class="card" id="s-sess"><h2>${imgTag('clock', false)} Sessions <span class="n">from ${esc(num(S.files))} log file${S.files === 1 ? '' : 's'}</span></h2>
                <div class="tiles">${tiles.map(([v, l]) => `<div class="tile"><b>${esc(v)}</b><span>${l}</span></div>`).join('')}</div>
                ${rows ? `<h3 class="sub">Recent sessions</h3><div class="rows scroll" style="max-height:300px">${rows}</div>` : ''}
                ${deaths ? `<h3 class="sub">Deaths</h3><div class="rows">${deaths}</div>` : ''}</div>`;
        }

        function petsHTML(L) {
            const list = L.pub.pets;
            if (!list || !list.length) return '';
            return `<div class="card" id="s-pets"><h2>${imgTag('lead', false)} Pets <span class="n">${list.length} owned</span></h2><div class="rows">${list.map((p) => {
                const name = p.name !== null && p.name !== undefined ? textHTML(p.name) : '';
                const items = (p.items || []).map(normItem).filter((it) => it && it.base !== 'air');
                return `<div class="rowi" style="flex-wrap:wrap"><span class="ico">${imgTag(mobIcon(p.id))}</span><div class="grow"><b>${name || esc(pretty(p.id))}</b>${name ? ` <small>${esc(pretty(p.id))}</small>` : ''}<br>
                    <small>${p.hp !== null ? esc(num(p.hp, 1)) + ' hp · ' : ''}${esc(dimName(p.dim))}${p.pos ? ` · <span class="mono">${xyz(...p.pos)}</span>` : ''}${p.sitting ? ' · sitting' : ''}${p.baby ? ' · baby' : ''}</small></div>${p.pos ? tpBtn(...p.pos) : ''}
                    ${items.length ? `<div class="slots" style="grid-template-columns:repeat(${Math.min(9, items.length)},var(--s));flex-basis:100%;margin-top:4px">${items.map((it, j) => slotHTML(it, `Pets › ${plain(name) || pretty(p.id)} › ${j + 1}`)).join('')}</div>` : ''}</div>`;
            }).join('')}</div></div>`;
        }

        function questsHTML(L) {
            const Q = L.pub.quests;
            if (!Q) return '';
            return `<div class="card" id="s-quests"><h2>${imgTag('book', false)} FTB Quests <span class="n">${esc(num(Q.done))} / ${esc(num(Q.total))}${Q.team ? ' · team ' + esc(Q.team) : ''}</span></h2>
                <div class="progress" style="height:10px"><i style="width:${Q.total ? (Q.done / Q.total) * 100 : 0}%"></i></div>
                ${(Q.chapters || []).map((c) => `<div class="adv-cat"><h3>${c.icon ? imgTag(String(c.icon), false).replace('<img', '<img style="width:20px;height:20px"') : ''}${ampHTML(c.title)}<span class="n">${esc(c.done)} / ${esc(c.total)}</span></h3><div class="progress"><i style="width:${c.total ? (c.done / c.total) * 100 : 0}%"></i></div></div>`).join('')}</div>`;
        }

        /* =====================================================================
           World & server page (never shared)
           ===================================================================== */
        let FILEBLK = [];       // config / data files shown on the page, filled into a tree when opened
        function serverViewHTML() {
            FILEBLK = [];
            const S = [], cards = [];
            const groups = new Map();
            CTX.files.forEach((f) => groups.set(f.kind, (groups.get(f.kind) || 0) + 1));
            S.push(`<div class="section card"><h2>${imgTag('command_block', false)} World &amp; server <span class="n">${num(CTX.files.length)} file${CTX.files.length === 1 ? '' : 's'}</span><button class="btn small" data-act="clearctx" title="Forget all world and server files">Clear</button></h2>
                <p class="muted" style="font-size:13px;margin:0 0 8px">Read in your browser only. Private values - IP addresses, chat, passwords, seeds - are never shown, and nothing on this page goes into share links. Player pages show what applies to each player.</p>
                <div class="badges">${[...groups].map(([k, n]) => `<span class="badge">${esc(k)}${n > 1 ? ' × ' + num(n) : ''}</span>`).join('')}</div></div>`);
            if (CTX.level) cards.push(worldHTML());
            if (CTX.props) cards.push(propsHTML());
            if (CTX.bans.size) cards.push(bansHTML());
            if (CTX.scoreboard) cards.push(scoreboardHTML());
            if (CTX.servers) cards.push(serversHTML());
            const plug = pluginsHTML();
            if (plug) cards.push(plug);
            if (CTX.packs.length) cards.push(packsHTML());
            if (cards.length) S.push(`<div class="masonry">${cards.join('')}</div>`);
            S.push(rosterHTML());
            if (CTX.sess) S.push(logsHTML());
            if (CTX.maps.size) S.push(mapsHTML());
            if (CTX.hotbars) S.push(hotbarsHTML());
            if (CTX.mods.length) S.push(modsHTML());
            if (CTX.ftb.chapters.length) S.push(questBookHTML());
            if (CTX.schems.length) S.push(schemsHTML());
            if (CTX.configs.length) S.push(filesHTML('w-config', 'Config files', 'comparator', CTX.configs));
            if (CTX.regions.length) S.push(regionsHTML());
            const rawList = worldRawList();
            if (rawList.length) S.push(filesHTML('w-data', 'World data <small class="muted">raw NBT</small>', 'structure_block', rawList, true));
            const html = S.join('');
            const jumps = [['w-region', 'Region files'],['w-world', 'World'], ['w-props', 'server.properties'], ['w-bans', 'Bans'], ['w-score', 'Scoreboard'], ['w-servers', 'Server list'], ['w-plugins', 'Plugins'], ['w-packs', 'Resource packs'], ['w-players', 'Players'], ['w-logs', 'Logs'], ['w-maps', 'Maps'], ['w-hotbar', 'Hotbars'], ['w-mods', 'Mods'], ['w-quests', 'Quests'], ['w-schem', 'Schematics'], ['w-config', 'Configs'], ['w-data', 'Raw world data']];
            return '<nav class="jump">' + jumps.filter(([id]) => html.includes(`id="${id}"`)).map(([id, t]) => `<a href="#${id}">${t}</a>`).join('') + '</nav>' + html;
        }

        function worldHTML() {
            const L = CTX.level.data;
            const day = L.DayTime ?? L.Time ?? 0, tod = ((day % 24000) + 24000) % 24000;
            const clock = `${String(Math.floor((tod / 1000 + 6) % 24)).padStart(2, '0')}:${String(Math.floor(((tod % 1000) / 1000) * 60)).padStart(2, '0')}`;
            const spawn = isObj(L.spawn) && Array.isArray(L.spawn.pos) ? L.spawn.pos : L.SpawnX !== undefined ? [L.SpawnX, L.SpawnY, L.SpawnZ] : null;
            const rules = isObj(L.GameRules) ? L.GameRules : isObj(L.game_rules) ? L.game_rules : null;
            const packs = isObj(L.DataPacks) ? L.DataPacks : null;
            const diff = L.Difficulty ?? (isObj(L.difficulty_settings) ? L.difficulty_settings.difficulty : undefined);
            const rows = [
                ['Name', L.LevelName !== undefined ? esc(L.LevelName) : null],
                ['Version', isObj(L.Version) ? esc(L.Version.Name) + (truthy(L.Version.Snapshot) ? ' (snapshot)' : '') : L.DataVersion ? esc(versionOf(L.DataVersion)) : null],
                ['Game mode', L.GameType !== undefined ? esc(GAMEMODES[L.GameType] ?? L.GameType) : null],
                ['Difficulty', diff !== undefined ? esc(typeof diff === 'number' ? ['Peaceful', 'Easy', 'Normal', 'Hard'][diff] ?? diff : pretty(diff)) + (truthy(L.DifficultyLocked) ? ' (locked)' : '') : null],
                ['Hardcore', L.hardcore !== undefined ? (truthy(L.hardcore) ? 'Yes' : 'No') : null],
                ['Cheats', L.allowCommands !== undefined ? (truthy(L.allowCommands) ? 'Allowed' : 'Off') : null],
                ['Day', `${num(Math.floor(day / 24000) + 1)} · ${clock}`],
                ['Weather', truthy(L.thundering) ? `Thunderstorm (${ticks(L.thunderTime || 0)} left)` : truthy(L.raining) ? `Rain (${ticks(L.rainTime || 0)} left)` : L.clearWeatherTime ? `Clear (${ticks(L.clearWeatherTime)} left)` : 'Clear'],
                ['World spawn', spawn ? `<span class="mono">${esc(spawn.join(', '))}</span> ${tpBtn(...spawn)}` : null],
                ['World border', L.BorderSize !== undefined ? `${esc(num(L.BorderSize))} wide around ${esc(num(L.BorderCenterX ?? 0))}, ${esc(num(L.BorderCenterZ ?? 0))}` : null],
                ['Last played', +L.LastPlayed > 1e11 ? dateAgo(+L.LastPlayed) : null],
                ['Ender Dragon', isObj(L.DragonFight) ? (truthy(L.DragonFight.DragonKilled) ? 'Killed' : 'Alive') + (truthy(L.DragonFight.PreviouslyKilled) ? ' · has been killed before' : '') : null],
                ['Wandering trader', L.WanderingTraderSpawnChance !== undefined ? `${esc(L.WanderingTraderSpawnChance)}% chance, next try in ${ticks(L.WanderingTraderSpawnDelay || 0)}` : null],
            ];
            const chip = (p, on) => `<span class="chip" data-s="${esc(String(p).toLowerCase())}" style="padding:2px 7px${on ? '' : ';opacity:.5'}"${on ? '' : ' title="disabled"'}>${esc(p)}</span>`;
            return `<div class="card" id="w-world"><h2>${imgTag('grass_block', false)} World <span class="n">${esc(CTX.level.file)}</span></h2>${kvHTML(rows)}
                ${packs ? `<h3 class="sub">Data packs</h3><div class="chips">${(packs.Enabled || []).map((p) => chip(p, true)).join('')}${(packs.Disabled || []).map((p) => chip(p, false)).join('')}</div>` : ''}
                ${rules ? `<details style="margin-top:10px"><summary class="btn small" style="list-style:none">Game rules (${Object.keys(rules).length})</summary><div class="kv" style="margin-top:8px">${Object.entries(rules).sort().map(([k, v]) => `<span>${esc(k)}</span><span>${esc(v)}</span>`).join('')}</div></details>` : ''}</div>`;
        }

        function propsHTML() {
            const p = CTX.props.data;
            const top = ['motd', 'level-name', 'gamemode', 'difficulty', 'hardcore', 'max-players', 'online-mode', 'white-list', 'enforce-whitelist', 'pvp', 'view-distance', 'simulation-distance', 'spawn-protection', 'allow-flight', 'server-port', 'enable-command-block', 'enforce-secure-profile'];
            const val = (k, v) => (k === 'motd' ? legacyHTML(String(v), {}) : `<span class="mono">${esc(v)}</span>`);
            const shown = (k) => !(PRIVATE_KEY.test(k) && String(p[k]) !== '');
            const main = top.filter((k) => k in p && shown(k)), rest = Object.keys(p).filter((k) => !top.includes(k) && shown(k)).sort();
            return `<div class="card" id="w-props"><h2>${imgTag('comparator', false)} Server settings <span class="n">${esc(CTX.props.file)}</span></h2>
                ${p['online-mode'] === 'false' ? '<div class="note" style="font-size:13px">Offline mode: player UUIDs are made from their names, so name lookups only work with this server\'s usercache.json.</div>' : ''}
                ${kvHTML(main.map((k) => [esc(k), val(k, p[k])]))}
                ${rest.length ? `<details style="margin-top:10px"><summary class="btn small" style="list-style:none">All ${Object.keys(p).length} settings</summary><div class="kv" style="margin-top:8px">${rest.map((k) => `<span>${esc(k)}</span><span>${val(k, p[k])}</span>`).join('')}</div></details>` : ''}</div>`;
        }

        function bansHTML() {
            return `<div class="card" id="w-bans"><h2>${imgTag('barrier', false)} Banned players <span class="n">${CTX.bans.size}</span></h2><div class="rows scroll" style="max-height:360px">${[...CTX.bans].map(([k, b]) =>
                `<div class="rowi srow" data-s="${esc((nameFor(k) + ' ' + k).toLowerCase())}"><div class="grow"><b>${esc(nameFor(k))}</b>${b.reason ? ' - ' + esc(b.reason) : ''}<br><small>by ${esc(b.source || '?')} · ${esc(b.created || '?')} · expires ${esc(b.expires || 'never')}</small></div></div>`).join('')}</div></div>`;
        }

        function scoreboardHTML() {
            const sb = CTX.scoreboard;
            const holder = (n) => { const k = uuidKey(n); return k && k.length === 32 && /^[0-9a-f]+$/.test(k) ? nameFor(k) : n; };
            const objs = [...sb.objectives].map(([id, o], i) => {
                const list = sb.scores.filter((s) => s.Objective === id).sort((a, b) => b.Score - a.Score);
                return `<details${i < 2 ? ' open' : ''} style="margin-top:6px"><summary style="cursor:pointer">${o.name !== undefined && o.name !== null ? textHTML(o.name) : esc(id)} <small class="mono muted">${esc(id)} · ${esc(o.criteria || '')} · ${list.length}</small></summary>
                    <div class="rows" style="margin-top:4px">${list.slice(0, 15).map((s, n) => `<div class="rowi srow" data-s="${esc(String(holder(s.Name)).toLowerCase())}"><small class="muted" style="width:20px">${n + 1}</small><div class="grow">${esc(holder(s.Name))}</div><b>${esc(num(s.Score))}</b></div>`).join('')}${list.length > 15 ? `<small class="muted">and ${list.length - 15} more</small>` : ''}</div></details>`;
            }).join('');
            const teams = sb.teams.map((t) => `<div class="rowi"><div class="grow">${teamHTML({ name: t.Name, display: t.DisplayName, color: t.TeamColor, prefix: t.MemberNamePrefix, suffix: t.MemberNameSuffix })} <small class="mono muted">${esc(t.Name)}</small><br><small>${esc((t.Players || []).slice(0, 20).join(', '))}${(t.Players || []).length > 20 ? '…' : ''}</small></div><small>${(t.Players || []).length} members</small></div>`).join('');
            const slots = Object.entries(sb.slots || {}).map(([k, v]) => `<span class="badge">${esc(k.replace(/^slot_/, 'slot '))}: ${esc(v)}</span>`).join('');
            return `<div class="card" id="w-score"><h2>${imgTag('oak_sign', false)} Scoreboard <span class="n">${sb.objectives.size} objectives · ${sb.teams.length} teams</span></h2>
                ${slots ? `<div class="badges">${slots}</div>` : ''}${objs}${teams ? `<h3 class="sub">Teams</h3><div class="rows">${teams}</div>` : ''}</div>`;
        }

        function serversHTML() {
            const list = CTX.servers.list;
            return `<div class="card" id="w-servers"><h2>${imgTag('ender_eye', false)} Server list <span class="n">${list.length} · servers.dat</span></h2><div class="rows">${list.map((s) => {
                const icon = typeof s.icon === 'string' && /^[A-Za-z0-9+/=]+$/.test(s.icon) ? `<img src="data:image/png;base64,${s.icon}" alt="" style="width:32px;height:32px;image-rendering:pixelated;border-radius:4px;flex:none">` : `<span class="ico">${imgTag('grass_block')}</span>`;
                return `<div class="rowi">${icon}<div class="grow"><b>${esc(s.name || 'Minecraft Server')}</b>${truthy(s.hidden) ? ' <small>hidden</small>' : ''}</div>${s.acceptTextures !== undefined ? `<small>${truthy(s.acceptTextures) ? 'server pack: on' : 'server pack: off'}</small>` : ''}</div>`;
            }).join('')}</div></div>`;
        }

        function pluginsHTML() {
            const parts = [];
            const count = (label, n) => (n ? [label, num(n)] : null);
            parts.push(kvHTML([count('EssentialsX users', CTX.ess.size), count('LuckPerms users', CTX.lpUsers.size), count('mcMMO users', CTX.mcmmo.length), count('GriefPrevention players', CTX.gpPlayers.size),
                CTX.gpClaims.length ? ['GriefPrevention claims', `${num(CTX.gpClaims.length)} · ${num(CTX.gpClaims.filter((c) => c.parent < 0).reduce((s, c) => s + c.area, 0))} blocks`] : null,
                count('WorldGuard regions', CTX.wg.length), count('Towny residents', CTX.towny.residents.size), count('FTB teams', CTX.ftb.teams.length),
                count('Backpacks indexed', CTX.backpacks.size), CTX.pets.size ? ['Pets found', `${num([...CTX.pets.values()].reduce((s, l) => s + l.length, 0))} for ${num(CTX.pets.size)} owners`] : null]));
            if (CTX.mv.size) parts.push(`<h3 class="sub">Multiverse worlds</h3><div class="rows">${[...CTX.mv].map(([n, w]) => `<div class="rowi"><div class="grow"><b>${esc(n)}</b>${w.alias ? ' · ' + ampHTML(w.alias) : ''}</div><small>${esc(w.environment || '')}</small></div>`).join('')}</div>`);
            if (CTX.lpGroups.size) {
                const members = (g) => [...CTX.lpUsers.values()].filter((u) => String(u.primary || '').toLowerCase() === g || u.nodes.some((n) => n.value && n.key.toLowerCase() === 'group.' + g)).length;
                parts.push(`<h3 class="sub">LuckPerms groups</h3><div class="rows">${[...CTX.lpGroups].map(([g, nodes]) => {
                    const pre = lpMeta(nodes, 'prefix'), w = nodes.map((n) => /^weight\.(\d+)$/.exec(n.key)).find(Boolean);
                    return `<div class="rowi"><div class="grow"><b>${esc(g)}</b> ${pre ? ampHTML(pre) : ''}<br><small>${nodes.length} nodes${w ? ' · weight ' + w[1] : ''}</small></div><small>${members(g)} users</small></div>`;
                }).join('')}</div>`);
            }
            if (CTX.towny.towns.size) {
                const res = [...CTX.towny.residents.values()];
                parts.push(`<h3 class="sub">Towny towns</h3><div class="rows">${[...CTX.towny.towns.values()].map((t) => `<div class="rowi"><div class="grow"><b>${esc(t.name)}</b>${t.nation ? ' · ' + esc(t.nation) : ''}<br><small>mayor ${esc(t.mayor || '?')}</small></div><small>${res.filter((r) => String(r.town || '').toLowerCase() === String(t.name).toLowerCase()).length} residents</small></div>`).join('')}</div>`);
            }
            if (CTX.mcmmo.length) {
                const top = CTX.mcmmo.map((r) => [r.name, Object.values(r.skills).reduce((s, [l]) => s + l, 0)]).sort((a, b) => b[1] - a[1]).slice(0, 10);
                parts.push(`<h3 class="sub">mcMMO power levels</h3><div class="rows">${top.map(([n, p], i) => `<div class="rowi srow" data-s="${esc(n.toLowerCase())}"><small class="muted" style="width:20px">${i + 1}</small><div class="grow">${esc(n)}</div><b>${num(p)}</b></div>`).join('')}</div>`);
            }
            if (CTX.wg.length) {
                const byWorld = new Map();
                CTX.wg.forEach((r) => byWorld.set(r.world, (byWorld.get(r.world) || 0) + 1));
                parts.push(`<h3 class="sub">WorldGuard regions per world</h3>${kvHTML([...byWorld].map(([w, n]) => [esc(w), num(n)]))}`);
            }
            const body = parts.filter((p) => p && p !== '<div class="kv"></div>');
            return body.length ? `<div class="card" id="w-plugins"><h2>${imgTag('writable_book', false)} Plugin &amp; mod data</h2>${body.join('')}</div>` : '';
        }

        function packsHTML() {
            return `<div class="card" id="w-packs"><h2>${imgTag('painting', false)} Local resource packs</h2><p class="muted" style="font-size:13px;margin:0 0 8px">Item textures from these packs replace the normal icons on this device only. Packs loaded later win.</p><div class="rows">${CTX.packs.map((p) =>
                `<div class="rowi modrow">${texImg('packicon:' + p.file, 'painting')}<div class="grow"><b>${esc(p.file)}</b><br><small class="desc">${p.desc ? textHTML(p.desc) : ''}</small></div><small>${num(p.textures)} textures</small></div>`).join('')}</div></div>`;
        }

        function rosterHTML() {
            const keys = new Set([...CTX.usercache.keys(), ...CTX.ops.keys(), ...CTX.whitelist, ...CTX.bans.keys(), ...CTX.ess.keys(), ...CTX.lpUsers.keys(), ...CTX.gpPlayers.keys()]);
            if (!keys.size) return '';
            players.forEach((p) => p.key && keys.add(p.key));
            const rows = [...keys].map((k) => ({ k, name: nameFor(k), op: CTX.ops.get(k), wl: CTX.whitelist.has(k), ban: CTX.bans.get(k), seen: (CTX.usercache.get(k) || {}).seen || 0, pi: players.findIndex((p) => p.key === k) }))
                .sort((a, b) => (b.pi >= 0) - (a.pi >= 0) || b.seen - a.seen || String(a.name).localeCompare(String(b.name)));
            return `<div class="card section" id="w-players"><h2>${imgTag('player_head', false)} Players known to the server <span class="n">${num(rows.length)}</span></h2>
                <p class="muted" style="font-size:13px;margin:0 0 8px">From usercache, ops, whitelist, bans, EssentialsX, LuckPerms and GriefPrevention. Filter with the search box; click a loaded player to open them.</p>
                <div class="rows scroll" style="max-height:520px">${rows.slice(0, 3000).map((r) => `<div class="rowi srow"${r.pi >= 0 ? ` data-tab="${r.pi}" style="cursor:pointer"` : ''} data-s="${esc((r.name + ' ' + r.k).toLowerCase())}">
                    <img src="https://crafthead.net/avatar/${esc(r.k)}/32" alt="" loading="lazy" style="width:26px;height:26px;border-radius:4px;image-rendering:pixelated;flex:none" onerror="this.src='${MISSING}'">
                    <div class="grow"><b>${esc(r.name)}</b><br><small class="mono">${esc(dashUuid(r.k))}</small></div>
                    ${r.pi >= 0 ? '<span class="badge g">Loaded</span>' : ''}${r.op ? `<span class="badge p">OP ${esc(r.op.level ?? 4)}</span>` : ''}${r.wl ? '<span class="badge">Whitelisted</span>' : ''}${r.ban ? `<span class="badge r" title="${esc(r.ban.reason || '')}">Banned</span>` : ''}
                    ${r.seen ? `<small title="Last login (from the user cache)">${esc(ago(r.seen))}</small>` : ''}</div>`).join('')}</div></div>`;
        }

        function logsHTML() {
            const S = CTX.sess;
            const list = [...S.players.values()].map((p) => ({ ...p, total: p.sessions.reduce((s, x) => s + ((x.end || S.last) - x.start), 0), last: p.sessions.length ? p.sessions[p.sessions.length - 1].end || S.last : 0 })).sort((a, b) => b.total - a.total);
            const pidx = (name) => players.findIndex((p) => namesOf(p).has(name.toLowerCase()));
            return `<div class="card section" id="w-logs"><h2>${imgTag('clock', false)} Logs <span class="n">${num(CTX.logFiles.length)} file${CTX.logFiles.length === 1 ? '' : 's'}</span></h2>
                <div class="tiles">${[[S.first < Infinity ? new Date(S.first).toLocaleDateString() : '?', 'From'], [S.last ? new Date(S.last).toLocaleDateString() : '?', 'To'], [num(list.length), 'Players'], [num(list.reduce((s, p) => s + p.sessions.length, 0)), 'Sessions']].map(([v, l]) => `<div class="tile"><b>${esc(v)}</b><span>${l}</span></div>`).join('')}</div>
                ${S.versions.length ? `<div class="badges" style="margin-top:10px">${S.versions.map((v) => `<span class="badge">Minecraft ${esc(v)}</span>`).join('')}</div>` : ''}
                <h3 class="sub">Most time online</h3><div class="rows scroll">${list.slice(0, 200).map((p, i) => { const pi = pidx(p.name); return `<div class="rowi srow"${pi >= 0 ? ` data-tab="${pi}" style="cursor:pointer"` : ''} data-s="${esc(p.name.toLowerCase())}"><small class="muted" style="width:26px">${i + 1}</small><div class="grow"><b>${esc(p.name)}</b><br><small>${num(p.sessions.length)} sessions · ${p.deaths.length} deaths${p.sessions.some((s) => s.online) ? ' · online at the end of the log' : ''}</small></div><div style="text-align:right"><b>${dur(p.total)}</b><br><small>${p.last ? esc(ago(p.last)) : ''}</small></div></div>`; }).join('')}</div></div>`;
        }

        function mapsHTML() {
            const ids = [...CTX.maps.keys()].sort((a, b) => a - b);
            return `<div class="card section" id="w-maps"><h2>${imgTag('filled_map', false)} Maps <span class="n">${num(ids.length)}</span></h2><div class="maps">${ids.slice(0, 600).map((id) => {
                const m = CTX.maps.get(id);
                return `<figure><img class="mapimg" src="${mapURL(id)}" alt="Map ${esc(id)}" loading="lazy"><figcaption><b style="color:var(--text)">#${esc(id)}</b> · 1:${2 ** (m.scale || 0)} · ${esc(dimName(m.dim || 'minecraft:overworld'))}<br>centre ${esc(m.x)}, ${esc(m.z)}${m.locked ? ' · locked' : ''}</figcaption></figure>`;
            }).join('')}</div></div>`;
        }

        function hotbarsHTML() {
            const d = CTX.hotbars.data;
            const rows = Array.from({ length: 9 }, (_, r) => (Array.isArray(d[String(r)]) ? d[String(r)] : []).map((x) => { const it = normItem(x); return it && it.base !== 'air' ? it : null; }));
            return `<div class="card section" id="w-hotbar"><h2>${imgTag('chest', false)} Saved creative hotbars <span class="n">${esc(CTX.hotbars.file)}</span></h2><div class="gui">${rows.map((items, r) =>
                `<div style="display:flex;align-items:center;gap:8px;margin-top:${r ? 4 : 0}px"><span class="mono" style="width:12px;color:#888">${r + 1}</span><div class="slots">${Array.from({ length: 9 }, (_, i) => slotHTML(items[i], `Saved hotbar ${r + 1} › Slot ${i + 1}`)).join('')}</div></div>`).join('')}</div></div>`;
        }

        function modsHTML() {
            const list = [...CTX.mods].sort((a, b) => String(a.name || a.file).localeCompare(String(b.name || b.file)));
            return `<div class="card section" id="w-mods"><h2>${imgTag('crafting_table', false)} Mods <span class="n">${num(list.length)} · ${num(LANG.size)} names · ${num(TEXREF.size)} textures</span></h2>
                <div class="stgrid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">${list.map((m) => `<div class="rowi modrow srow" data-s="${esc(`${m.name} ${m.id} ${m.file}`.toLowerCase())}">${texImg('modicon:' + (m.id || m.file), 'crafting_table')}<div class="grow"><b>${esc(m.name || m.file)}</b> <small>${esc(m.version || '')}</small><br><small class="mono">${esc(m.id || '')}</small> <small>${esc(m.loader || '')}</small>${m.desc ? `<br><small class="desc muted">${esc(m.desc)}</small>` : ''}</div></div>`).join('')}</div></div>`;
        }

        function questBookHTML() {
            const F = CTX.ftb;
            const total = F.chapters.reduce((s, c) => s + c.quests.length, 0);
            const teams = [...F.progress].map(([id, p]) => {
                const t = F.teams.find((x) => x.id === id);
                const done = new Set(Object.keys(p.completed).map((k) => k.toLowerCase()));
                return { name: t ? t.name || (t.type === 'player' ? nameFor(uuidKey(id)) : 'Party') : nameFor(uuidKey(id)), done: F.chapters.reduce((s, c) => s + c.quests.filter((q) => done.has(q.id.toLowerCase())).length, 0) };
            }).sort((a, b) => b.done - a.done);
            return `<div class="card section" id="w-quests"><h2>${imgTag('book', false)} FTB Quests <span class="n">${F.chapters.length} chapters · ${num(total)} quests</span></h2>
                <div class="stgrid">${[...F.chapters].sort((a, b) => a.order - b.order).map((c) => `<div class="sti" data-s="${esc(String(ftbTitle('chapter', c.id, c.title) || c.file).toLowerCase())}">${c.icon ? `<span class="ico">${imgTag(itemIdOf(c.icon) || 'book')}</span>` : ''}<div><span>${ampHTML(ftbTitle('chapter', c.id, c.title) || pretty(c.file))}</span><b>${c.quests.length}</b> <small class="muted">quests</small></div></div>`).join('')}</div>
                ${teams.length ? `<h3 class="sub">Progress</h3><div class="rows">${teams.map((t) => `<div class="rowi srow" data-s="${esc(String(t.name).toLowerCase())}"><div class="grow"><b>${esc(t.name)}</b><div class="progress" style="margin:4px 0 0"><i style="width:${total ? (t.done / total) * 100 : 0}%"></i></div></div><small>${num(t.done)} / ${num(total)}</small></div>`).join('')}</div>` : ''}</div>`;
        }

        // schematics from more than one folder get a folder browser instead of one card each
        const SCHEM = { dir: null, sel: -1 };
        const schemDir = (s) => { const p = s.file.replace(/\\/g, '/'), i = p.lastIndexOf('/'); return i < 0 ? '' : p.slice(0, i); };
        function schemsHTML() {
            if (new Set(CTX.schems.map(schemDir)).size < 2) return CTX.schems.map((s, i) => schemHTML(s, i)).join('');
            return `<div class="card section" id="w-schem">${schemBrowserHTML()}</div>`;
        }
        function schemTree() {
            const root = { name: '', path: '', kids: new Map(), files: [], total: 0 };
            CTX.schems.forEach((s, i) => {
                let n = root;
                n.total++;
                for (const part of schemDir(s).split('/').filter(Boolean)) {
                    if (!n.kids.has(part)) n.kids.set(part, { name: part, path: n.path ? n.path + '/' + part : part, kids: new Map(), files: [], total: 0 });
                    n = n.kids.get(part);
                    n.total++;
                }
                n.files.push(i);
            });
            // skip leading folders that only hold one folder
            while (!root.files.length && root.kids.size === 1) { const k = [...root.kids.values()][0]; root.kids = k.kids; root.files = k.files; root.path = k.path; root.name = k.name; }
            return root;
        }
        function schemBrowserHTML() {
            const root = schemTree(), byPath = new Map();
            (function index(n) { byPath.set(n.path, n); n.kids.forEach(index); })(root);
            if (!byPath.has(SCHEM.dir)) SCHEM.dir = root.path;
            if (!CTX.schems[SCHEM.sel]) SCHEM.sel = -1;
            const cur = byPath.get(SCHEM.dir), folders = byPath.size - 1;
            const sorted = (m) => [...m.values()].sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true }));
            const tree = (n, d) => `<button class="fnode${n === cur ? ' on' : ''}" data-act="sdir" data-dir="${esc(n.path)}" style="padding-left:${8 + d * 14}px" title="${esc(n.path || 'All schematics')}">
                    ${imgTag(n.kids.size ? 'chest' : 'barrel')}<span class="grow">${esc(n === root ? n.name || 'All' : n.name)}</span><small>${num(n.total)}</small></button>${sorted(n.kids).map((k) => tree(k, d + 1)).join('')}`;
            // breadcrumb from the browser root down to the open folder
            const crumbs = [root];
            if (cur !== root) { let p = root.path; for (const part of cur.path.slice(root.path ? root.path.length + 1 : 0).split('/')) { p = p ? p + '/' + part : part; crumbs.push(byPath.get(p)); } }
            const here = new Set(cur.files);
            const fileRow = (i) => {
                const s = CTX.schems[i], m = s.meta || {}, off = !here.has(i);
                return `<button class="rowi srow fitem${i === SCHEM.sel ? ' on' : ''}" data-act="ssel" data-i="${i}" data-s="${esc(String(m.name || s.name).toLowerCase())}"${off ? ' data-off' : ''}>
                    <span class="ico">${imgTag('structure_block')}</span><span class="grow"><b>${esc(m.name || s.name)}</b><br><small>${esc(s.kind)}${s.size ? ' · ' + esc(s.size.map((v) => Math.abs(v)).join('×')) : ''} · ${esc(num(s.total))} blocks${off ? ' · <span class="mono">' + esc(schemDir(s)) + '</span>' : ''}</small></span></button>`;
            };
            const folderRow = (k) => `<button class="rowi fitem" data-act="sdir" data-dir="${esc(k.path)}"><span class="ico">${imgTag('chest')}</span><span class="grow"><b>${esc(k.name)}</b></span><small>${num(k.total)} schematic${k.total === 1 ? '' : 's'}</small></button>`;
            const order = CTX.schems.map((s, i) => i).sort((a, b) => String(CTX.schems[a].name).localeCompare(CTX.schems[b].name, undefined, { numeric: true }));
            const sel = CTX.schems[SCHEM.sel];
            return `<h2>${imgTag('structure_block', false)} Schematics <span class="n">${num(CTX.schems.length)} in ${num(folders)} folder${folders === 1 ? '' : 's'}</span></h2>
                <div class="fbrowse">
                    <nav class="ftree">${tree(root, 0)}</nav>
                    <div class="fpane">
                        <div class="fcrumbs">${crumbs.map((n, j) => (j === crumbs.length - 1 ? `<b>${esc(n.name || 'All')}</b>` : `<button data-act="sdir" data-dir="${esc(n.path)}">${esc(n.name || 'All')}</button>`)).join('<span>/</span>')}</div>
                        <div class="rows">${sorted(cur.kids).map(folderRow).join('')}${order.map(fileRow).join('')}</div>
                    </div>
                </div>
                ${sel ? `<div class="fsel"><h3>${esc((sel.meta || {}).name || sel.name)} <span class="n">${esc(sel.kind)}</span></h3>${schemBody(sel)}</div>` : '<p class="muted" style="font-size:13px;margin:10px 0 0">Pick a schematic to see its size and materials.</p>'}`;
        }
        function schemPick(a, el) {
            if (a === 'sdir') { SCHEM.dir = el.dataset.dir; SCHEM.sel = -1; }
            else SCHEM.sel = +el.dataset.i;
            const card = $('#w-schem');
            card.innerHTML = schemBrowserHTML();
            applySearch();
            if (a === 'ssel' && innerWidth < 700) $('.fsel', card).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function schemHTML(s, i) {
            const m = s.meta || {};
            return `<div class="card section"${i === 0 ? ' id="w-schem"' : ''}><h2>${imgTag('structure_block', false)} ${esc(m.name || s.name)} <span class="n">${esc(s.kind)}</span></h2>${schemBody(s)}</div>`;
        }
        function schemBody(s) {
            const m = s.meta || {};
            const created = +m.created > 1e11 ? +m.created : 0;
            return `${kvHTML([['File', `<span class="mono">${esc(s.file)}</span>`], ['Size', s.size ? esc(s.size.map((v) => Math.abs(v)).join(' × ')) : null], ['Author', m.author ? esc(m.author) : null], ['Description', m.desc ? esc(m.desc) : null], ['Created', dateAgo(created)],
                    ['Blocks', esc(num(s.total))], ['Block types', esc(num(s.list.length))], ['Block entities', s.tiles ? esc(num(s.tiles)) : null], ['Entities', s.entities ? esc(num(s.entities)) : null]])}
                <h3 class="sub">Materials</h3><div class="chips">${s.list.slice(0, 1000).map(([n, c]) => `<span class="chip" data-s="${esc(n.toLowerCase())}" title="${esc(n)}"><span class="ico">${imgTag(blockIcon(n))}</span>${esc(langId(n) || pretty(n))} × ${esc(num(c))}${c >= 64 ? ` <small class="muted">(${esc(num(Math.floor(c / 64)))} stacks${c % 64 ? ' + ' + (c % 64) : ''})</small>` : ''}</span>`).join('')}</div>`;
        }

        /* =====================================================================
           Backpacks: every Sophisticated Backpacks (or similar) backpack found in backpack storage,
           region / entities files and player files, with where it is and what is in it
           ===================================================================== */
        let BPC = null;          // {sig, list, byKey} - rebuilt when files or players change
        let BPSEL = null;        // key of the backpack open on the Backpacks page
        const BP_SLOTS = { backpack: 27, copper_backpack: 45, iron_backpack: 54, gold_backpack: 81, diamond_backpack: 108, netherite_backpack: 120 };
        function bpList() {
            const sig = `${CTX.files.length}|${CTX.bpSeen.length}|${CTX.bpStore.size}|${players.map((p) => p.key || p.file).join()}`;
            if (BPC && BPC.sig === sig) return BPC.list;
            const byKey = new Map();
            const get = (k) => { if (!byKey.has(k)) byKey.set(k, { key: k, uuid: k.startsWith('item:') ? null : k, locs: [], raws: [] }); return byKey.get(k); };
            const add = (h, loc, fallbackKey) => {
                if (!isBp(h.raw.id) && !(h.uuid && CTX.bpStore.has(h.uuid))) return;
                const b = get(h.uuid || fallbackKey);
                b.raws.push(h.raw);
                b.locs.push({ ...loc, chain: h.chain });
            };
            CTX.bpSeen.forEach((h, i) => add(h, h, `item:w${i}`));
            players.forEach((p) => {
                const d = p.P.d, pos = Array.isArray(d.Pos) ? d.Pos : null;
                for (const [k, v] of Object.entries(d)) {
                    if (!v || typeof v !== 'object') continue;
                    const where = k === 'Inventory' ? 'Inventory' : k === 'EnderItems' ? 'Ender chest' : k === 'equipment' ? 'Equipment' : 'Mod slots';
                    bpFind(v, [], [where]).forEach((h, j) => add(h, { kind: 'player', player: p, dim: p.P.dim, pos }, `item:${p.key || p.file}:${k}:${j}`));
                }
            });
            // backpacks kept inside other backpacks
            for (const [u, s] of CTX.bpStore) bpFind(s.entry, [], []).forEach((h, j) => { if (h.uuid !== u) add(h, { kind: 'inside', parent: u }, `item:in:${u}:${j}`); });
            for (const u of CTX.bpStore.keys()) get(u);
            for (const u of CTX.bpLog.keys()) get(u);
            for (const b of byKey.values()) {
                b.log = ((b.uuid && CTX.bpLog.get(b.uuid)) || []).slice().sort((x, y) => (+y.accessTime || 0) - (+x.accessTime || 0));
                b.store = (b.uuid && CTX.bpStore.get(b.uuid)) || null;
                const last = b.log[0] || {};
                const logId = Object.entries(last).find(([k, v]) => /registry|itemid/i.test(k) && typeof v === 'string');
                b.synthetic = !b.raws.length;
                b.item = normItem(b.raws[0] || { id: logId ? logId[1] : 'sophisticatedbackpacks:backpack', count: 1, components: b.uuid ? { 'sophisticatedcore:storage_uuid': b.uuid } : {} });
                if (b.synthetic && typeof last.backpackName === 'string' && last.backpackName) { b.item.nameHTML = esc(last.backpackName); b.item.namePlain = last.backpackName; }
                b.contents = b.item.contents || [];
                b.total = b.contents.reduce((s, x) => s + x.item.count, 0);
            }
            const list = [...byKey.values()].sort((a, b) => b.contents.length - a.contents.length || a.item.namePlain.localeCompare(b.item.namePlain));
            BPC = { sig, list, byKey };
            return list;
        }
        const bpColor = (v) => (typeof v === 'number' ? `#${(v >>> 0 & 0xffffff).toString(16).padStart(6, '0')}` : null);
        // the first value under the item's components / tag whose key matches re
        const bpVal = (it, re) => { for (const o of [it.c, it.tag]) for (const [k, v] of Object.entries(o)) if (re.test(k)) return v; return undefined; };
        function bpFindKey(v, re, depth = 0) {
            if (depth > 8 || !isObj(v)) return null;
            for (const [k, x] of Object.entries(v)) if (re.test(k) && isObj(x)) return x;
            for (const x of Object.values(v)) { const r = bpFindKey(x, re, depth + 1); if (r) return r; }
            return null;
        }
        function bpWhere(l, short) {
            if (l.kind === 'player') return `${esc(l.chain.join(' › '))} of <b>${esc(labelOf(l.player))}</b>`;
            if (l.kind === 'inside') {
                const p = BPC && BPC.byKey.get(l.parent);
                return `Inside ${short ? 'another backpack' : `<button class="linkish" data-act="bpsel" data-b="${esc(l.parent)}">${p ? p.item.namePlain : 'another backpack'}</button>`}`;
            }
            const at = l.pos && l.pos.every((v) => Number.isFinite(+v)) ? l.pos.map((v) => Math.floor(v)).join(', ') : '?';
            return `${esc(l.placed ? 'Placed' : l.chain.join(' › '))} at <span class="mono">${at}</span> · ${esc(dimName(l.dim || 'minecraft:overworld'))}`;
        }
        // names of every player uuid under keys like "owner" in the item or its storage entry
        function bpOwners(b) {
            const out = new Set();
            const walk = (v, depth) => {
                if (depth > 10 || !v || typeof v !== 'object') return;
                for (const [k, x] of Object.entries(v)) {
                    if (/owner/i.test(k)) { const u = normUuid(x); if (u) out.add(nameFor(uuidKey(u))); else if (typeof x === 'string' && x && !/[{}]/.test(x)) out.add(x); }
                    if (x && typeof x === 'object') walk(x, depth + 1);
                }
            };
            walk(b.item.raw, 0);
            if (b.store) walk(b.store.entry, 0);
            return [...out];
        }
        function bpSearchText(b) {
            return [b.item.namePlain, b.item.full, b.uuid || '', ...b.locs.map((l) => plain(bpWhere(l, true))), ...b.log.map((r) => r.playerName), ...b.contents.map((x) => x.item.namePlain + ' ' + x.item.id)].join(' ').toLowerCase();
        }
        function bpRowHTML(b) {
            const l = b.locs[0];
            const where = l ? bpWhere(l, true) + (b.locs.length > 1 ? ` <span class="muted">+${b.locs.length - 1} more</span>` : '') : b.store ? 'Location not found' : 'No contents saved';
            return `<button class="rowi srow fitem${b.key === BPSEL ? ' on' : ''}" data-act="bpsel" data-b="${esc(b.key)}" data-s="${esc(bpSearchText(b))}">
                <span class="ico">${imgTag(b.item)}</span><span class="grow"><b>${b.item.nameHTML}</b><br><small>${where}</small></span><small>${b.contents.length ? `${num(b.total)} items` : 'empty'}</small></button>`;
        }
        function bpViewHTML() {
            const list = bpList();
            if (!BPC.byKey.has(BPSEL)) BPSEL = list.length ? list[0].key : null;
            const pending = CTX.regions.filter((r) => !CTX.regionDone.has(r.path)), mb = pending.reduce((s, r) => s + mcaSize(r), 0) / 1e6;
            const located = list.filter((b) => b.locs.length).length, items = list.reduce((s, b) => s + b.total, 0);
            const notes = [];
            if (!CTX.bpStore.size) notes.push('No backpack storage loaded, so contents are unknown - add <span class="mono">data/sophisticatedbackpacks.dat</span> from the world.');
            if (!CTX.regions.length) notes.push('To find placed backpacks and ones in chests or on the ground, drop the whole world folder (with <span class="mono">region/</span> and <span class="mono">entities/</span>).');
            else if (pending.length) notes.push(`${num(pending.length)} region file${pending.length === 1 ? ' has' : 's have'} not been scanned yet, so some locations may be missing.`);
            const sel = BPSEL && BPC.byKey.get(BPSEL);
            return `<div class="section card" id="b-list"><h2>${imgTag(list[0] ? list[0].item : 'chest', false)} Backpacks <span class="n">${num(list.length)} found · ${num(located)} located · ${num(items)} items inside</span>
                    ${pending.length ? `<button class="btn small primary" data-act="scanmca">Scan world${mb >= 1 ? ` (${num(Math.round(mb))} MB)` : ''}</button>` : ''}</h2>
                ${notes.map((n) => `<p class="muted" style="font-size:13px;margin:0 0 6px">${n}</p>`).join('')}
                <div class="bpbrowse"><div class="bprows">${list.map(bpRowHTML).join('')}</div>
                <div class="bpsel" id="b-sel">${sel ? bpDetailHTML(sel) : ''}</div></div></div>`;
        }
        function bpDetailHTML(b) {
            const it = b.item;
            const slots = +bpVal(it, /inventory_?slots/i) || 0, upSlots = bpVal(it, /upgrade_?slots/i);
            const maxSlot = b.contents.reduce((m, x) => Math.max(m, +x.slot || 0), -1);
            const size = Math.max(slots || BP_SLOTS[it.base] || 27, maxSlot + 1);
            const main = bpColor(bpVal(it, /main_?color|cloth_?color/i)), accent = bpColor(bpVal(it, /accent_?color|border_?color|trim_?color/i));
            const holders = [...new Set(b.locs.filter((l) => l.kind === 'player').map((l) => labelOf(l.player)))];
            const owners = bpOwners(b), last = b.log[0];
            const when = (r) => (+r.accessTime > 1e11 ? dateAgo(+r.accessTime) : r.accessTime !== undefined ? esc(String(r.accessTime)) : '');
            const rows = [
                ['Type', esc(langId(it.full) || pretty(it.base))],
                ['Storage id', b.uuid ? `<span class="mono">${esc(b.uuid)}</span> <button class="btn small" data-copy="${esc(b.uuid)}">Copy</button>` : '<span class="muted">none - never opened, so nothing is stored</span>'],
                ['Used', b.store ? `${num(b.contents.length)} / ${num(size)} slots · ${num(b.total)} items` : '<span class="muted">contents unknown (no backpack storage loaded)</span>'],
                ['Upgrade slots', upSlots !== undefined ? esc(String(upSlots)) : null],
                ['Colours', main || accent ? [main, accent].filter(Boolean).map((c) => `<span class="swatch" style="background:${c}"></span><span class="mono">${c}</span>`).join(' ') : null],
                ['Held by', holders.length ? holders.map(esc).join(', ') : null],
                ['Owner', owners.length ? owners.map(esc).join(', ') : null],
                ['Last opened by', last ? `${esc(last.playerName)} ${when(last)}` : null],
                ['Storage file', b.store ? `<span class="mono">${esc(b.store.file)}</span>` : null],
            ];
            const locs = b.locs.map((l) => `<div class="rowi bploc"><span class="grow">${bpWhere(l)}</span>${l.kind !== 'inside' && l.pos ? tpBtn(...l.pos) : ''}</div>`).join('');
            const bySlot = {};
            b.contents.forEach((x) => (bySlot[x.slot] = x.item));
            const grid = b.store ? `<h3 class="sub">Contents</h3><div class="gui"><div class="slots" style="grid-template-columns:repeat(9,var(--s))">${Array.from({ length: size }, (_, i) => slotHTML(bySlot[i], `${it.namePlain} › Slot ${i}`)).join('')}</div></div>` : '';
            const upInv = b.store && bpFindKey(b.store.entry, /^upgrade_?inventory$/i);
            const ups = upInv && Array.isArray(upInv.Items) ? upInv.Items.map((x, i) => ({ slot: x.Slot ?? x.slot ?? i, item: normItem(isObj(x.item) ? x.item : x) })).filter((x) => x.item) : [];
            const upGrid = ups.length ? `<h3 class="sub">Upgrades</h3><div class="gui"><div class="slots">${ups.map((x) => slotHTML(x.item, `${it.namePlain} › Upgrade ${x.slot}`)).join('')}</div></div>` : '';
            const log = b.log.length > 1 ? `<h3 class="sub">Access log</h3><div class="rows">${b.log.map((r) => `<div class="rowi"><span class="grow">${esc(r.playerName)}</span><small>${when(r)}</small></div>`).join('')}</div>` : '';
            const tree = (title, v) => (isObj(v) ? `<details class="fileblk"><summary>${title}</summary><div class="tree">${Object.entries(maskTree(v)).map(([k, x]) => treeNode(k, x, 1)).join('')}</div></details>` : '');
            const anchor = b.locs.find((l) => l.anchor);
            return `<div class="bphead">${slotHTML(it, 'Backpacks')}<div class="grow"><div style="font-size:20px;font-weight:700;text-shadow:2px 2px #000">${it.nameHTML}</div><div class="muted mono" style="font-size:12px">${esc(it.full)}</div></div></div>
                ${kvHTML(rows)}
                <h3 class="sub">Where it is</h3>${locs ? `<div class="rows">${locs}</div>` : `<p class="muted" style="font-size:13px">Not found in any loaded player file${CTX.regions.length ? ' or scanned region file' : ' - drop the world folder to search the world'}.</p>`}
                ${grid}${upGrid}${log}
                <h3 class="sub">Raw NBT</h3>
                ${tree('Backpack storage entry', b.store && b.store.entry)}${tree(b.synthetic ? 'Item (rebuilt from storage - not found in the world)' : 'Item', it.raw)}${tree(`Container: ${esc(anchor ? bpLabel(anchor.anchor.id || '?') : '')}`, anchor && anchor.anchor)}`;
        }
        function bpPick(key) {
            BPSEL = key;
            const sel = BPC && BPC.byKey.get(key), box = $('#b-sel');
            if (!sel || !box) return;
            $$('#b-list .bprows .fitem').forEach((el) => el.classList.toggle('on', el.dataset.b === key));
            box.innerHTML = bpDetailHTML(sel);
            if (innerWidth < 700) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // values under keys like "password" or "secret" are swapped for a click-to-show placeholder
        function maskTree(v, key = '') {
            if (Array.isArray(v)) return v.map((x) => maskTree(x, key));
            if (isObj(v)) { const o = {}; for (const [k, x] of Object.entries(v)) o[k] = maskTree(x, k); return o; }
            return PRIVATE_KEY.test(String(key)) && v !== '' && v !== null && typeof v !== 'boolean' ? { __priv: v } : v;
        }
        // searchable: the page search looks through every file's keys and values (see applySearch)
        function filesHTML(id, title, icon, files, searchable) {
            const list = files.map((f) => `<details class="fileblk" data-blk="${FILEBLK.push(f) - 1}"><summary class="mono">${esc(f.file)}</summary></details>`).join('');
            return `<div class="card section" id="${id}"><h2>${imgTag(icon, false)} ${title} <span class="n">${num(files.length)}</span></h2>` +
                (searchable ? `<p class="muted" style="font-size:13px;margin:0 0 8px">Open a file to explore every key, or use the search box to look through all of them.</p><div class="hits tree hide" id="hits"></div><div id="rawTree">${list}</div>` : list) + '</div>';
        }
        const maskedOf = (f) => f.masked || (f.masked = maskTree(f.data));
        // every world / data file as raw NBT: the NBT files, plus JSON lists nothing else understood
        function worldRawList() {
            const seen = new Set(CTX.raw.map((f) => f.file));
            return CTX.raw.concat(CTX.data.filter((f) => !seen.has(f.file)));
        }

        /* ---------- region files: a list of chunks, each opened as raw NBT ---------- */
        let CHUNKS = [];
        function regionsHTML() {
            CHUNKS = [];
            const pending = CTX.regions.filter((r) => !CTX.regionDone.has(r.path)), mb = pending.reduce((s, r) => s + mcaSize(r), 0) / 1e6;
            const byDim = new Map();
            CTX.regions.forEach((e) => { const k = `${dimName(mcaDim(e.path))} · ${/(^|\/)entities\//i.test(e.path.replace(/\\/g, '/')) ? 'entities' : 'blocks'}`; if (!byDim.has(k)) byDim.set(k, []); byDim.get(k).push(e); });
            const sortKey = (e) => { const m = /r\.(-?\d+)\.(-?\d+)\.mca$/i.exec(e.path) || [0, 0, 0]; return Math.abs(+m[1]) + Math.abs(+m[2]); };
            return `<div class="card section" id="w-region"><h2>${imgTag('grass_block', false)} Region files <span class="n">${num(CTX.regions.length)}</span>
                    ${pending.length ? `<button class="btn small primary" data-act="scanmca" title="Read every chunk looking for backpacks and owned pets">Scan ${num(pending.length)} for backpacks${mb >= 1 ? ` (${num(Math.round(mb))} MB)` : ''}</button>` : ''}</h2>
                <p class="muted" style="font-size:13px;margin:0 0 8px">${pending.length ? 'Not scanned yet - press Scan to find placed backpacks, backpacks in chests and on the ground, and owned pets.' : 'Scanned for backpacks and owned pets.'} Open a file to browse its chunks as raw NBT.</p>
                ${[...byDim].map(([k, list]) => `<details class="fileblk"><summary>${esc(k)} <span class="n">${num(list.length)}</span></summary>${list.sort((a, b) => sortKey(a) - sortKey(b)).slice(0, 2000).map((e) =>
                    `<details class="fileblk" data-blk="${FILEBLK.push({ file: e.path, mca: e }) - 1}"><summary class="mono">${esc(e.path.split('/').pop())}</summary></details>`).join('')}${list.length > 2000 ? `<div class="muted">and ${num(list.length - 2000)} more</div>` : ''}</details>`).join('')}</div>`;
        }
        async function fillRegion(det, f) {
            det.insertAdjacentHTML('beforeend', '<div class="muted tl">Reading…</div>');
            try {
                const chunks = mcaChunks(f.mca, await f.mca.read());
                det.lastElementChild.remove();
                det.insertAdjacentHTML('beforeend', chunks.length ? chunks.map((ch) => `<details class="fileblk" data-chunk="${CHUNKS.push(ch) - 1}"><summary class="mono">Chunk ${ch.cx}, ${ch.cz} <small class="muted">blocks ${ch.cx * 16}, ${ch.cz * 16}</small></summary></details>`).join('') : '<div class="muted tl">No chunks saved in this file.</div>');
            } catch (err) { det.lastElementChild.textContent = 'Could not read this file: ' + err.message; }
        }
        async function fillChunk(det) {
            if (det.dataset.filled) return;
            det.dataset.filled = 1;
            try {
                const data = maskTree(readNBT(await CHUNKS[+det.dataset.chunk].read()));
                det.insertAdjacentHTML('beforeend', `<div class="tree">${Object.entries(data).map(([k, v]) => treeNode(k, v, 0)).join('')}</div>`);
            } catch (err) { det.insertAdjacentHTML('beforeend', `<div class="muted tl">Could not read this chunk: ${esc(err.message)}</div>`); }
        }
        function fillBlock(det) {
            if (det.dataset.filled) return;
            det.dataset.filled = 1;
            const f = FILEBLK[+det.dataset.blk];
            if (f.mca) { fillRegion(det, f); return; }
            if (f.text !== undefined) { det.insertAdjacentHTML('beforeend', `<pre class="cfg">${esc(f.text)}</pre>`); return; }
            const data = maskedOf(f);
            const entries = Array.isArray(data) ? data.map((x, i) => [i, x]) : isObj(data) ? Object.entries(data) : [['value', data]];
            det.insertAdjacentHTML('beforeend', `<div class="tree">${entries.slice(0, 500).map(([k, v]) => treeNode(k, v, 0)).join('')}${entries.length > 500 ? `<div class="muted">and ${entries.length - 500} more keys</div>` : ''}</div>`);
        }

        /* ---------- raw tree ---------- */
        function hintFor(k, v) {
            if (Array.isArray(v) && v.length === 4 && v.every(Number.isInteger) && /uuid|owner|^id$/i.test(String(k))) return uuidOf(v);
            if (typeof v === 'number' && /duration|ticks|timer|cooldown|time$/i.test(String(k)) && v > 40) return ticks(v);
            return '';
        }
        function leafVal(v) {
            if (typeof v === 'string') return `<span class="v string">"${esc(v)}"</span>`;
            if (Array.isArray(v)) return `<span class="v arr">[${v.map((x) => (typeof x === 'number' ? num(x, 4) : esc(JSON.stringify(x)))).join(', ')}]</span>`;
            if (isObj(v)) return '<span class="ty">{}</span>';
            return `<span class="v number">${esc(String(v))}</span>`;
        }
        function treeNode(k, v, depth, top) {
            if (isObj(v) && '__priv' in v && Object.keys(v).length === 1) return '';
            const hint = hintFor(k, v);
            const gl = top && GLOSSARY[k] ? `<span class="hint">// ${esc(GLOSSARY[k])}</span>` : '';
            const h = hint ? `<span class="hint">${esc(hint)}</span>` : '';
            const isLeafArr = Array.isArray(v) && (v.length === 0 || (v.length <= 12 && v.every((x) => typeof x !== 'object')));
            if ((isObj(v) && Object.keys(v).length) || (Array.isArray(v) && !isLeafArr)) {
                const n = NODES.push(v) - 1;
                const size = Array.isArray(v) ? `[${v.length}]` : `{${Object.keys(v).length}}`;
                return `<details data-n="${n}" data-d="${depth}"><summary><span class="k">${esc(k)}</span> <span class="ty">${size}</span>${h}${gl}</summary></details>`;
            }
            return `<div class="tl"><span class="k">${esc(k)}</span>: ${leafVal(v)}${h}${gl}</div>`;
        }
        function fillNode(det) {
            if (det.dataset.filled) return;
            det.dataset.filled = 1;
            const v = NODES[+det.dataset.n], depth = +det.dataset.d + 1;
            const entries = Array.isArray(v) ? v.map((x, i) => [i, x]) : Object.entries(v);
            const LIMIT = 250;
            const render = (from) => entries.slice(from, from + LIMIT).map(([k, x]) => treeNode(k, x, depth)).join('') +
                (entries.length > from + LIMIT ? `<button class="btn small more" data-more="${from + LIMIT}">Show ${Math.min(LIMIT, entries.length - from - LIMIT)} more of ${entries.length - from - LIMIT}</button>` : '');
            det.insertAdjacentHTML('beforeend', render(0));
            det._more = render;
        }
        function rawHTML(d) {
            return `<div class="card section" id="s-raw"><h2>${imgTag('name_tag', false)} Raw NBT <span class="n">every key in the file</span>
                <button class="btn small" data-act="expand">Expand all</button><button class="btn small" data-act="json">Download JSON</button></h2>
                <div class="hits tree hide" id="hits"></div>
                <div class="tree" id="rawTree">${Object.keys(d).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' })).map((k) => treeNode(k, d[k], 0, true)).join('')}</div></div>`;
        }

        /* =====================================================================
           Tooltip + drawer
           ===================================================================== */
        function tipLines(it, full = false) {
            const L = [];
            L.push(`<div class="nm">${it.nameHTML}</div>`);
            if (it.mapId !== undefined) L.push(`<div style="color:#AAAAAA">Map #${esc(it.mapId)}${CTX.maps.has(String(it.mapId)) ? '' : ' (drop data/map_' + esc(it.mapId) + '.dat to see it)'}</div>`);
            for (const e of it.ench) {
                const curse = /curse/.test(e.id);
                L.push(`<div style="color:${curse ? '#FF5555' : '#AAAAAA'}">${esc(pretty(e.id))}${e.lvl === 1 && SINGLE_LVL.has(e.id.split(':').pop()) ? '' : ' ' + roman(e.lvl)}</div>`);
            }
            const pc = it.c['minecraft:potion_contents'] ?? (it.tag.Potion ? { potion: it.tag.Potion } : null);
            if (pc) {
                const pot = typeof pc === 'string' ? pc : pc.potion;
                if (pot) L.push(`<div style="color:#5555FF">${esc(pretty(pot))}</div>`);
                (pc.custom_effects || []).forEach((e) => L.push(`<div style="color:#5555FF">${esc(pretty(e.id))} ${roman((e.amplifier || 0) + 1)} (${ticks(e.duration || 0)})</div>`));
            }
            const trim = it.c['minecraft:trim'] ?? it.tag.Trim;
            if (trim) L.push(`<div style="color:#AAAAAA">Upgrade:</div><div style="color:#bbb">&nbsp;${esc(pretty(trim.pattern))} Armor Trim</div><div style="color:#bbb">&nbsp;${esc(pretty(trim.material))} Material</div>`);
            const book = it.c['minecraft:written_book_content'];
            if (book) L.push(`<div style="color:#AAAAAA">by ${esc(book.author || '?')}</div><div style="color:#AAAAAA">${['Original', 'Copy of original', 'Copy of a copy', 'Tattered'][book.generation || 0]}</div>`);
            if (it.contents && it.contents.length) {
                const sorted = [...it.contents].sort((a, b) => a.slot - b.slot);
                sorted.slice(0, 5).forEach((x) => L.push(`<div style="color:#fff">${esc(x.item.namePlain)} x${x.item.count}</div>`));
                if (sorted.length > 5) L.push(`<div style="color:#fff;font-style:italic">and ${sorted.length - 5} more...</div>`);
            }
            if (it.bundle && it.bundle.length) {
                const total = it.bundle.reduce((s, x) => s + (x.count || 1), 0);
                L.push(`<div style="color:#AAAAAA">${it.bundle.length} stack${it.bundle.length > 1 ? 's' : ''} · ${total} item${total > 1 ? 's' : ''}</div>`);
                it.bundle.slice(0, 4).forEach((x) => L.push(`<div style="color:#fff">${esc(x.namePlain)} x${x.count || 1}</div>`));
            }
            for (const line of it.lore) L.push(`<div class="l">${textHTML(line, { color: MC_COLORS.dark_purple, italic: true }) || '&nbsp;'}</div>`);
            const lt = it.c['minecraft:lodestone_tracker'];
            if (lt && lt.target) L.push(`<div style="color:#AAAAAA">Lodestone: ${esc((lt.target.pos || []).join(', '))} (${esc(dimName(lt.target.dimension))})</div>`);
            const mods = it.c['minecraft:attribute_modifiers'];
            const mlist = Array.isArray(mods) ? mods : isObj(mods) && Array.isArray(mods.modifiers) ? mods.modifiers : [];
            if (mlist.length) {
                const bySlot = {};
                mlist.forEach((m) => (bySlot[m.slot || 'any'] = bySlot[m.slot || 'any'] || []).push(m));
                for (const [slot, ms] of Object.entries(bySlot)) {
                    L.push(`<div style="color:#AAAAAA;margin-top:4px">When ${slot === 'mainhand' ? 'in Main Hand' : slot === 'offhand' ? 'in Off Hand' : slot === 'any' ? 'equipped' : 'on ' + pretty(slot === 'chest' ? 'body' : slot)}:</div>`);
                    ms.forEach((m) => {
                        const pct = m.operation && m.operation !== 'add_value';
                        const v = pct ? m.amount * 100 : m.amount;
                        L.push(`<div style="color:${v >= 0 ? '#5555FF' : '#FF5555'}">${v >= 0 ? '+' : ''}${num(v, 2)}${pct ? '%' : ''} ${esc(pretty(strip(m.type).replace(/^generic\./, '')))}</div>`);
                    });
                }
            }
            if (it.unbreakable) L.push('<div style="color:#5555FF">Unbreakable</div>');
            if (it.maxDamage && (it.damage || full)) L.push(`<div style="color:#fff">Durability: ${it.maxDamage - it.damage} / ${it.maxDamage}</div>`);
            if (it.cmd !== null) L.push(`<div style="color:#555">Custom model data: ${num(it.cmd)}</div>`);
            const nComp = Object.keys(it.c).length || Object.keys(it.tag).length;
            L.push(`<div class="id">${esc(it.full)}${nComp ? ` · ${nComp} component${nComp > 1 ? 's' : ''}` : ''}</div>`);
            return L.join('');
        }

        const tip = $('#tip');
        let tipK = -1;
        function showTip(k, x, y) {
            if (k !== tipK) { tip.innerHTML = tipLines(REG[k].item); tipK = k; }
            tip.classList.remove('hide');
            const w = tip.offsetWidth, h = tip.offsetHeight;
            let left = x + 16, top = y - 12;
            if (left + w > innerWidth - 8) left = x - w - 16;
            if (top + h > innerHeight - 8) top = innerHeight - h - 8;
            tip.style.left = Math.max(8, left) + 'px';
            tip.style.top = Math.max(8, top) + 'px';
        }
        const hideTip = () => { tip.classList.add('hide'); tipK = -1; };

        const drawer = $('#drawer');
        let stack = [];
        function openItem(k, push) {
            if (!push) stack = [];
            stack.push(k);
            const { item: it, where } = REG[k];
            $$('.slot.pick').forEach((s) => s.classList.remove('pick'));
            if (stack.length === 1) { const s = document.querySelector(`#player [data-k="${k}"]`); if (s && s.classList.contains('slot')) s.classList.add('pick'); }
            const crumbs = stack.map((kk, i) => i === stack.length - 1 ? esc(REG[kk].item.namePlain) : `<a data-crumb="${i}">${esc(REG[kk].item.namePlain)}</a>`).join(' › ');
            let contents = '';
            if (it.contents) {
                const bySlot = {};
                it.contents.forEach((x) => (bySlot[x.slot] = x.item));
                const cols = it.size === 5 ? 5 : it.size === 9 ? 3 : 9;
                const total = it.contents.reduce((s, x) => s + x.item.count, 0);
                contents = `<h3>Contents · ${it.contents.length} stacks, ${num(total)} items</h3><div class="gui"><div class="slots" style="grid-template-columns:repeat(${cols},var(--s))">${Array.from({ length: it.size }, (_, i) => slotHTML(bySlot[i], `${it.namePlain} › Slot ${i}`)).join('')}</div></div>`;
            }
            if (it.bundle) {
                contents += `<h3>Bundle · ${it.bundle.length} stacks</h3><div class="gui"><div class="slots" style="grid-template-columns:repeat(${Math.min(9, Math.max(4, it.bundle.length))},var(--s))">${it.bundle.map((x, i) => slotHTML(x, `${it.namePlain} › #${i + 1}`)).join('')}</div></div>`;
            }
            const map = it.mapId !== undefined && CTX.maps.get(String(it.mapId));
            if (map) contents = `<h3>Map #${esc(it.mapId)}</h3><img class="mapimg" src="${mapURL(it.mapId)}" alt=""><div class="muted" style="font-size:12px;margin-top:4px">Scale 1:${2 ** (map.scale || 0)} · ${esc(dimName(map.dim || 'minecraft:overworld'))} · centre ${esc(map.x)}, ${esc(map.z)}${map.locked ? ' · locked' : ''}</div>` + contents;
            const facts = [['Location', esc(where)], ['ID', `<span class="mono">${esc(it.full)}</span>`], ['Count', it.count]];
            if (it.bpUuid) facts.push(['Contents from', 'world data (backpack storage)']);
            if (it.maxDamage) facts.push(['Durability', `${it.maxDamage - it.damage} / ${it.maxDamage} (${Math.round((1 - it.damage / it.maxDamage) * 100)}%)`]);
            if (it.ench.length) facts.push(['Enchantments', it.ench.length]);
            const rc = it.c['minecraft:repair_cost'] ?? it.tag.RepairCost;
            if (rc !== undefined) facts.push(['Anvil repair cost', `${rc} level${rc === 1 ? '' : 's'}${rc >= 39 ? ' (too expensive!)' : ''}`]);
            if (it.mod) facts.push(['Mod', esc(it.mod)]);
            const giveCmd = '/give @s ' + it.full + (Object.keys(it.c).length ? '[' + Object.entries(it.c).map(([kk, v]) => `${kk}=${toSNBT(v)}`).join(',') + ']' : '') + (it.count > 1 ? ' ' + it.count : '');
            const n0 = NODES.length;
            drawer.innerHTML = `<button class="close" data-act="close" title="Close (Esc)">×</button>
                <div class="crumbs">${crumbs}</div>
                <div class="head"><div class="ico${it.glint ? ' glint' : ''}" style="--m:url(&quot;${iconURL(it)}&quot;)">${imgTag(it, false)}</div>
                    <div><div style="font-size:20px;font-weight:700;text-shadow:2px 2px #000">${it.nameHTML}</div><div class="muted">${esc(pretty(it.base))}${it.count > 1 ? ' × ' + it.count : ''}</div></div></div>
                <div class="tipbox">${tipLines(it, true)}</div>
                ${contents}
                <h3>Details</h3><div class="kv">${facts.map(([a, b]) => `<span>${a}</span><span>${b}</span>`).join('')}</div>
                <h3>Give command <button class="btn small" data-copy="${esc(giveCmd)}">Copy</button></h3>
                <div class="mono muted" style="font-size:12px;word-break:break-all;max-height:90px;overflow:auto">${esc(giveCmd)}</div>
                <h3>All item data</h3><div class="tree">${Object.entries(it.raw).map(([kk, v]) => treeNode(kk, v, 1)).join('')}</div>`;
            $$('details', drawer).forEach((dd) => { if (+dd.dataset.n >= n0 && +dd.dataset.d <= 1) { dd.open = true; fillNode(dd); } });
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            drawer.scrollTop = 0;
            hideTip();
        }
        function closeDrawer() {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            $$('.slot.pick').forEach((s) => s.classList.remove('pick'));
            stack = [];
        }
        function toSNBT(v) {
            if (typeof v === 'string') return JSON.stringify(v).replace(/\\u00a7/g, '§');
            if (typeof v === 'number') return String(v);
            if (Array.isArray(v)) return '[' + v.map(toSNBT).join(',') + ']';
            if (isObj(v)) return '{' + Object.entries(v).map(([k, x]) => (/^[A-Za-z0-9_.+-]+$/.test(k) ? k : JSON.stringify(k)) + ':' + toSNBT(x)).join(',') + '}';
            return String(v);
        }

        /* =====================================================================
           Search
           ===================================================================== */
        function itemText(it) {
            if (it._s) return it._s;
            let s = [it.full, it.namePlain, ...it.ench.map((e) => e.id + ' ' + pretty(e.id)), ...it.lore.map((l) => plain(textHTML(l)))].join(' ');
            if (it.contents) s += ' ' + it.contents.map((x) => itemText(x.item)).join(' ');
            if (it.bundle) s += ' ' + it.bundle.map(itemText).join(' ');
            return (it._s = s.toLowerCase());
        }
        function applySearch() {
            const q = query.trim().toLowerCase();
            const root = $('#player');
            root.classList.toggle('searching', !!q);
            if (q) {
                $$('[data-k]', root).forEach((el) => el.classList.toggle('hit', itemText(REG[+el.dataset.k].item).includes(q)));
                $$('.chip, .sti, .adv, .srow', root).forEach((el) => el.classList.toggle('hit', (el.dataset.s || '').includes(q.replace(/ /g, '_')) || el.textContent.toLowerCase().includes(q)));
            }
            const hits = $('#hits'), tree = $('#rawTree');
            if (!hits) return;
            if (q.length < 2) { hits.classList.add('hide'); tree.classList.remove('hide'); return; }
            const out = [];
            const reSrc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const re = new RegExp(reSrc(q), 'i');
            const mark = (s) => esc(s).replace(new RegExp(reSrc(esc(q)), 'gi'), (m) => `<mark>${m}</mark>`);
            (function walk(v, path) {
                for (const [k, x] of Array.isArray(v) ? v.map((x, i) => [i, x]) : Object.entries(v)) {
                    if (out.length >= 300) return;
                    if (isObj(x) && '__priv' in x) continue;       // masked private value - never searchable
                    const p = Array.isArray(v) ? `${path}[${k}]` : path ? `${path}.${k}` : String(k);
                    if (x !== null && typeof x === 'object') {
                        if (re.test(String(k))) out.push([p, Array.isArray(x) ? `[${x.length}]` : `{${Object.keys(x).length}}`]);
                        walk(x, p);
                    } else if (re.test(String(k)) || re.test(String(x))) out.push([p, JSON.stringify(x)]);
                }
            })(view === 'server' ? Object.fromEntries(worldRawList().map((f) => [f.file, maskedOf(f)])) : players[active].P.d, '');
            hits.innerHTML = `<div class="muted" style="margin-bottom:6px">${out.length >= 300 ? '300+' : out.length} matches for “${esc(q)}”</div>` + out.map(([p, v]) => `<div class="tl"><span class="k">${mark(p)}</span> = <span class="v string">${mark(v)}</span></div>`).join('');
            hits.classList.remove('hide');
            tree.classList.add('hide');
        }

        /* =====================================================================
           Loading files
           ===================================================================== */
        const UUID_RE = /[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}/i;
        function toast(msg, cls) {
            const box = $('#errors'), el = document.createElement('div');
            el.className = cls;
            el.textContent = msg;
            el.title = 'Click to dismiss';
            el.onclick = () => el.remove();
            box.appendChild(el);
            while (box.childElementCount > 4) box.firstElementChild.remove();
            setTimeout(() => el.remove(), cls === 'err' ? 9000 : 5000);
        }
        // while loadEntries runs, messages are collected and summed up in one toast
        let batch = null;
        const showError = (msg) => (batch ? batch.errors.push(msg) : toast(msg, 'err'));
        const showNote = (msg) => (batch ? batch.notes.push(msg) : toast(msg, 'note'));
        const uuidKey = (s) => (s ? String(s).toLowerCase().replace(/-/g, '') : null);
        const fileUuid = (name) => { const m = String(name).match(UUID_RE); return m ? uuidKey(m[0]) : null; };
        const pendingExtras = new Map();    // uuid -> {stats, advancements} waiting for that player's file

        function unwrap(data) {
            if (isObj(data) && isObj(data['']) && Object.keys(data).length === 1) data = data[''];
            return data;
        }
        // what a parsed NBT / JSON / SNBT compound is - by file name first, then by its keys
        const PLAYERISH = ['Inventory', 'EnderItems', 'Pos', 'UUID', 'playerGameType', 'abilities', 'XpLevel', 'foodLevel', 'Health', 'Rotation', 'recipeBook', 'equipment'];
        function classify(data, name = '', path = '') {
            const n = name.toLowerCase(), p = path.toLowerCase().replace(/\\/g, '/'), sd = isObj(data.data) ? data.data : null;
            if (n === 'level.dat' || (isObj(data.Data) && !data.Inventory && ('LevelName' in data.Data || 'GameRules' in data.Data || 'WorldGenSettings' in data.Data || 'version' in data.Data))) return 'level';
            if (n === 'hotbar.nbt' || ['0', '1', '2', '3', '4', '5', '6', '7', '8'].every((k) => Array.isArray(data[k]))) return 'hotbar';
            if (n === 'servers.dat' || (Array.isArray(data.servers) && Object.keys(data).length <= 2)) return 'servers';
            if (/^map_\d+\.dat$/.test(n) || (sd && Array.isArray(sd.colors) && sd.colors.length === 16384)) return 'map';
            if (n === 'scoreboard.dat' || (sd && (Array.isArray(sd.Objectives) || Array.isArray(sd.PlayerScores)))) return 'scoreboard';
            if (/backpack/.test(n) && sd) return 'backpacks';
            if ((Array.isArray(data.size) && (Array.isArray(data.palette) || Array.isArray(data.palettes)) && Array.isArray(data.blocks)) || isObj(data.Schematic) || (isObj(data.Regions) && isObj(data.Metadata)) || (data.Width !== undefined && data.Length !== undefined && data.Height !== undefined)) return 'schem';
            if (isObj(data.users) && isObj(data.groups)) return 'lp-export';
            if (/luckperms\//.test(p) || (data.uuid && (data.primaryGroup !== undefined || data['primary-group'] !== undefined))) return 'lp';
            if (isObj(data.stats) || Object.keys(data).some((k) => /^stat\./.test(k))) return 'stats';
            const vals = Object.entries(data).filter(([k]) => k !== 'DataVersion');
            if (vals.length && vals.every(([k, v]) => k.includes(':') && isObj(v) && ('criteria' in v || 'done' in v))) return 'advancements';
            if (/(^|\/)data\/[^/]+\.dat$/.test(p) || (sd && Object.keys(data).every((k) => k === 'data' || k === 'DataVersion'))) return 'data';
            if (PLAYERISH.some((k) => k in data)) return 'player';
            return 'data';
        }

        function addPlayer(file, data, modified, extras = {}) {
            const P = analyse(data);
            const pl = { file, P, name: null, modified: modified || 0, extras: { ...extras }, key: uuidKey(P.uuid) || fileUuid(file) };
            if (pl.key && pendingExtras.has(pl.key)) { Object.assign(pl.extras, pendingExtras.get(pl.key)); pendingExtras.delete(pl.key); }
            const dup = pl.key ? players.findIndex((p) => p.key === pl.key) : -1;
            if (dup >= 0) {
                // one tab per player - keep whichever file was saved most recently
                const old = players[dup];
                if (old.modified > pl.modified) {
                    old.extras = { ...pl.extras, ...old.extras };
                    active = dup;
                    showNote(`Skipped ${file} - ${old.file} for the same player is newer.`);
                    return old;
                }
                pl.extras = { ...old.extras, ...pl.extras };
                pl.name = old.name;
                players[dup] = pl;
                active = dup;
                showNote(`Replaced ${old.file} with the newer ${file} (same player).`);
            } else {
                players.push(pl);
                active = players.length - 1;
            }
            lookupName(pl);
            return pl;
        }

        function attachExtra(kind, file, data, uuid) {
            const pl = uuid ? players.find((p) => p.key === uuid) : players[active];
            if (!pl) {
                if (!uuid) return showError(`Load a player file before ${file}.`);
                pendingExtras.set(uuid, { ...(pendingExtras.get(uuid) || {}), [kind]: { file, data } });
                return showNote(`Got the ${kind} in ${file} - they will show once that player's file is loaded.`);
            }
            pl.extras[kind] = { file, data };
            active = players.indexOf(pl);
        }

        /* ---------- which files in a folder / archive are worth reading ---------- */
        // first match wins; null = ignore
        const ROUTES = [
            [/(^|\/)(playerdata|players\/data)\/[^/]+\.dat(_old)?$/, 'nbt'],
            [/(^|\/)(players\/)?(stats|advancements)\/[^/]+\.json$/, 'nbt'],
            [/(^|\/)level\.dat$/, 'nbt'],
            [/(^|\/)uid\.dat$/, 'uid'],
            [/(^|\/)(region|entities)\/r\.-?\d+\.-?\d+\.mca$/, 'mca'],
            [/(^|\/)data\/ftb[^/]*\/.+\.snbt$/, 'ftb'],
            [/(^|\/)data\/[^/]+\.dat$/, 'nbt'],
            [/(^|\/)(usercache|ops|whitelist|banned-players|banned-ips)\.json$/, 'nbt'],
            [/(^|\/)server\.properties$/, 'props'],
            [/(^|\/)logs\/[^/]+\.log(\.gz)?$/, 'log'],
            [/(^|\/)plugins\/essentials\/userdata\/[^/]+\.yml$/, 'yaml'],
            [/(^|\/)plugins\/luckperms\/yaml-storage\/(users|groups)\/[^/]+\.yml$/, 'yaml'],
            [/(^|\/)plugins\/luckperms\/json-storage\/(users|groups)\/[^/]+\.json$/, 'lpjson'],
            [/(^|\/)plugins\/mcmmo\/flatfile\/mcmmo\.users$/, 'mcmmo'],
            [/(^|\/)plugins\/griefpreventiondata\/playerdata\/[0-9a-f-]{36}$/, 'gp'],
            [/(^|\/)plugins\/griefpreventiondata\/claimdata\/[^/]+\.yml$/, 'yaml'],
            [/(^|\/)plugins\/worldguard\/worlds\/[^/]+\/regions\.yml$/, 'yaml'],
            [/(^|\/)towny\/data\/(residents|towns|nations)\/[^/]+\.txt$/, 'towny'],
            [/(^|\/)plugins\/multiverse-core\/worlds2?\.yml$/, 'yaml'],
            [/(^|\/)plugins\/[^/]+\/config\.yml$/, 'config'],
            [/(^|\/)(bukkit|spigot|paper|paper-global|paper-world-defaults|paper-world|purpur|pufferfish|commands|permissions|help|leaves)\.yml$/, 'config'],
            [/(^|\/)mods\/[^/]+\.jar$/, 'archive'],
            [/(^|\/)config\/ftbquests\/quests\/(chapters\/[^/]+|lang\/.+)\.snbt$/, 'ftb'],
            [/(^|\/)config\/ftbquests\//, null],
            [/(^|\/)(serverdata\/)?ftb(quests|teams|chunks)\/.+\.snbt$/, 'ftb'],
            [/(^|\/)(serverconfig|defaultconfigs)\/[^/]+\.toml$/, 'config'],
            [/(^|\/)config\/[^/]+\.(toml|json5?|properties|cfg|ya?ml)$/, 'config'],
            [/(^|\/)hotbar\.nbt$/, 'nbt'],
            [/(^|\/)servers\.dat$/, 'nbt'],
            [/\.(schem|schematic|litematic)$/, 'schem'],
            [/(^|\/)(schematics|structures|generated\/[^/]+\/structures)\/.+\.nbt$/, 'schem'],
        ];
        // folders never worth walking into (huge, or nothing to read)
        // region/ and entities/ are walked, but their files are only listed - they are read when scanned (scanRegions)
        const SKIP_DIRS = /^(poi|\.?voxy|datapacks|backups?|cache|\.cache|libraries|versions|bundler|crash-reports|debug|screenshots|resourcepacks|shaderpacks|\.git|node_modules|dynmap|bluemap|squaremap|pl3xmap|web|tiles|coreprotect|\.paper-remapped|\.fabric|journeymap|xaero\w*|assets|natives|downloads)$/i;
        const SKIP_PATH = /(^|\/)(poi|\.?voxy|datapacks|backups?|cache|libraries|versions|bundler|crash-reports|dynmap|bluemap|squaremap|pl3xmap|coreprotect|assets)\//i;
        function kindOf(path) {
            const p = path.replace(/\\/g, '/').toLowerCase();
            for (const [re, k] of ROUTES) if (re.test(p)) return k;
            return null;
        }
        // a file picked or dropped on its own - guess from the name
        function kindOfLoose(name) {
            const k = kindOf(name);
            if (k) return k;
            const n = name.toLowerCase();
            if (/\.(zip|jar|tar|tgz|tar\.gz)$/.test(n)) return 'archive';
            if (/\.mca$/.test(n)) return 'mca';
            if (/\.log(\.gz)?$/.test(n)) return 'log';
            if (/\.ya?ml$/.test(n)) return 'yaml';
            if (/\.(toml|json5|cfg|properties)$/.test(n)) return 'config';
            if (/\.users$/.test(n)) return 'mcmmo';
            if (UUID_RE.test(n) && !n.includes('.')) return 'gp';
            return 'nbt';
        }

        const ctxFile = (e, kind) => CTX.files.push({ path: e.path, kind });
        const readText = async (e) => textOf(await inflate(await e.read()));

        let progEl = null;
        function progress(msg) {
            // a server sync shows loadEntries' progress on its own bar
            if (srvJob) { if (msg) { const m = /(\d+) \/ (\d+)/.exec(msg); srvStatus(msg, m ? +m[1] : 0, m ? +m[2] : 0); } return; }
            if (!msg) { if (progEl) progEl.remove(); progEl = null; return; }
            if (!progEl || !progEl.isConnected) { progEl = document.createElement('div'); progEl.className = 'note'; $('#errors').appendChild(progEl); }
            progEl.textContent = msg;
        }

        /* =====================================================================
           My server's stats: stats/, advancements/ and playerdata from assets.craftyplayz.com,
           mirrored into IndexedDB so a later visit only downloads what changed.
           Nothing here runs until "View my server stats" is pressed.
           ===================================================================== */
        const SRV_BASE = 'https://assets.craftyplayz.com/minecraft-players/';
        // folder on the server -> the folder it is in a world, so ROUTES files it like a dropped world
        const SRV_DIRS = { stats: 'stats', advancements: 'advancements', data: 'playerdata' };
        let srvJob = null, srvVia = null, srvLoaded = false;

        // records: {key: 'dir/name', dir, name, bytes, hash, etag, lastModified, fetched}
        // falls back to memory (no saving between visits) when IndexedDB is unavailable, e.g. some private windows
        const srvCache = {
            db: null, mem: null,
            open() {
                if (!this.db) this.db = new Promise((res, rej) => {
                    const r = indexedDB.open('nbt-server', 1);
                    r.onupgradeneeded = () => r.result.createObjectStore('files', { keyPath: 'key' });
                    r.onsuccess = () => res(r.result);
                    r.onerror = () => rej(r.error);
                    r.onblocked = () => rej(new Error('the cache is open in another tab'));
                });
                return this.db;
            },
            async tx(mode, fn) {
                const db = await this.open();
                return new Promise((res, rej) => {
                    const t = db.transaction('files', mode), req = fn(t.objectStore('files'));
                    t.oncomplete = () => res(req && req.result);
                    t.onerror = t.onabort = () => rej(t.error || new Error('the cache write was aborted'));
                });
            },
            async all() {
                if (!this.mem) {
                    try { return await this.tx('readonly', (s) => s.getAll()); } catch (e) { console.warn('Server stats cache unavailable:', e); this.mem = new Map(); }
                }
                return [...this.mem.values()];
            },
            // one record per transaction: a file is either fully saved or not at all
            async put(rec) { if (this.mem) this.mem.set(rec.key, rec); else await this.tx('readwrite', (s) => s.put(rec)); },
            async drop(keys) {
                if (!keys.length) return;
                if (this.mem) keys.forEach((k) => this.mem.delete(k));
                else await this.tx('readwrite', (s) => keys.forEach((k) => s.delete(k)));
            },
        };
        // FNV-1a, to spot a damaged cache entry and to compare downloads that have no ETag / Last-Modified
        const srvHash = (u) => { let h = 0x811c9dc5; for (let i = 0; i < u.length; i++) h = Math.imul(h ^ u[i], 0x01000193); return (h >>> 0).toString(16) + '-' + u.length; };
        function srvIntact(name, u) {
            if (!(u instanceof Uint8Array) || !u.length) return false;
            if (/\.json$/i.test(name)) { try { JSON.parse(textOf(u)); return true; } catch (e) { return false; } }
            if (/\.dat$/i.test(name)) return u.length > 18 && u[0] === 0x1f && u[1] === 0x8b;
            return true;
        }
        const srvValid = (r) => !!r && srvIntact(r.name, r.bytes) && r.hash === srvHash(r.bytes);

        const srvUrl = (via, dir, name) => via === 'direct' ? `${SRV_BASE}${dir}/${name ? encodeURIComponent(name) : ''}` : `api.php?srv=${dir}${name ? '&f=' + encodeURIComponent(name) : ''}`;
        async function srvFetch(dir, name, headers = {}) {
            const go = (via) => fetch(srvUrl(via, dir, name), { headers, cache: 'no-store', signal: AbortSignal.timeout(30000) });
            if (srvVia) return go(srvVia);
            // the asset server only allows some origins (CORS) - api.php fetches it for the rest
            try { const r = await go('direct'); srvVia = 'direct'; return r; } catch (err) { }
            const r = await go('proxy');
            if (r.ok) srvVia = 'proxy';
            return r;
        }
        // an Apache directory listing -> the file names directly inside it
        async function srvList(dir) {
            const r = await srvFetch(dir);
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            if (!/index of/i.test(doc.title + ' ' + ($('h1', doc) || {}).textContent)) throw new Error('not a directory listing');
            const root = new URL(dir + '/', SRV_BASE), names = new Set();
            for (const a of $$('a[href]', doc)) {
                const u = new URL(a.getAttribute('href'), root);
                if (u.origin !== root.origin || u.search || !u.pathname.startsWith(root.pathname)) continue;
                const rest = u.pathname.slice(root.pathname.length);
                if (rest && !rest.includes('/')) names.add(decodeURIComponent(rest));
            }
            return [...names];
        }
        // one file -> {rec, state: 'same' | 'changed' | 'new', unsaved}; throws if it could not be fetched
        async function srvPull({ dir, name, key, rec }) {
            const h = {};
            if (rec && rec.etag) h['If-None-Match'] = rec.etag;
            if (rec && rec.lastModified) h['If-Modified-Since'] = rec.lastModified;
            const r = await srvFetch(dir, name, h);
            if (r.status === 304 && rec) return { rec, state: 'same' };
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const bytes = new Uint8Array(await r.arrayBuffer());
            if (!srvIntact(name, bytes)) throw new Error('the download was incomplete or damaged');
            const next = { key, dir, name, bytes, hash: srvHash(bytes), etag: r.headers.get('etag') || '', lastModified: r.headers.get('last-modified') || '', fetched: Date.now() };
            // without a validator the server sends the file every time - the bytes decide, not the size
            const same = rec && rec.hash === next.hash;
            if (same && rec.etag === next.etag && rec.lastModified === next.lastModified) return { rec, state: 'same' };
            let unsaved = false;
            try { await srvCache.put(next); } catch (e) { console.warn(e); unsaved = true; }
            return { rec: next, state: same ? 'same' : rec ? 'changed' : 'new', unsaved };
        }

        function srvStatus(msg, done, total) {
            const box = $('#srvSync'), bar = $('.progress', box), drop = $('#drop');
            // once players are open the drop zone is hidden - show the bar above the viewer instead
            if (drop.classList.contains('hide')) { if (box.parentElement === drop) $('#app').insertBefore(box, $('#viewer')); }
            else if (box.parentElement !== drop) drop.insertBefore(box, $('details', drop));
            box.classList.remove('hide');
            $('.srv-msg', box).textContent = msg;
            $('.srv-act', box).innerHTML = '';
            bar.classList.remove('hide');
            bar.classList.toggle('ind', !(total > 0));
            $('i', bar).style.width = total > 0 ? Math.min(100, (done / total) * 100) + '%' : '';
        }
        // msg empty -> hide; actions: [[label, data-srv value, primary?]]
        function srvEnd(msg, actions = []) {
            const box = $('#srvSync');
            if (!msg) return box.classList.add('hide');
            srvStatus(msg);
            $('.progress', box).classList.add('hide');
            $('.srv-act', box).innerHTML = actions.map(([l, a, p]) => `<button class="btn small${p ? ' primary' : ''}" data-srv="${a}">${esc(l)}</button>`).join('');
        }
        // a warning that stays until dismissed (toasts fade before anyone could press Retry)
        function srvWarn(msg) {
            $$('#errors .srv-warn').forEach((x) => x.remove());
            const el = document.createElement('div');
            el.className = 'err srv-warn';
            el.innerHTML = `${esc(msg)} <button class="btn small" data-srv="sync">Retry</button> <button class="btn small" data-srv="dismiss">Dismiss</button>`;
            $('#errors').appendChild(el);
        }

        // a second press while one is running just waits for the same sync
        function srvSync(cachedOnly) {
            if (!srvJob) {
                $('#mySrv').disabled = true;
                $$('#errors .srv-warn').forEach((x) => x.remove());
                srvJob = srvRun(cachedOnly)
                    .catch((err) => { console.error(err); srvEnd('Something went wrong: ' + err.message, [['Try again', 'sync', true]]); })
                    .finally(() => { srvJob = null; $('#mySrv').disabled = false; });
            }
            return srvJob;
        }

        // bots.json next to this page: Carpet bots to leave out - ["<uuid>", ...] or [{"name": ..., "uuid": ...}, ...]
        // no file = no bots; if it can't be read, the last list seen is used
        async function srvBots() {
            try {
                const r = await fetch('bots.json', { cache: 'no-cache' });
                if (r.status === 404) { store.set('nbt.srv.bots', []); return new Set(); }
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                const ids = (await r.json()).map((b) => uuidKey(typeof b === 'string' ? b : b && b.uuid)).filter(Boolean);
                store.set('nbt.srv.bots', ids);
                return new Set(ids);
            } catch (err) { console.warn('bots.json:', err); return new Set(store.get('nbt.srv.bots', [])); }
        }

        async function srvRun(cachedOnly) {
            srvStatus('Checking saved files…');
            const bots = await srvBots(), isBot = (name) => bots.has(fileUuid(name));
            const usable = new Map(), bad = [];
            // a bot's saved files are dropped like damaged ones, so a bot added to bots.json disappears next time
            for (const r of await srvCache.all()) (srvValid(r) && !isBot(r.name) ? usable.set(r.key, r) : bad.push(r.key));
            // a damaged entry is thrown away, so it is downloaded again like a missing one
            if (bad.length) await srvCache.drop(bad).catch(console.warn);
            if (cachedOnly && usable.size) return srvOpen([...usable.values()], new Set(usable.keys()), []);
            const last = store.get('nbt.srv.synced', 0);
            const fallback = (why) => usable.size
                ? srvEnd(`${why} You can open the copy saved in this browser (${num(usable.size)} files${last ? ', last updated ' + new Date(last).toLocaleString() : ''}).`, [['Open saved copy', 'cached', true], ['Try again', 'sync']])
                : srvEnd(`${why} Nothing has been saved in this browser yet.`, [['Try again', 'sync', true]]);
            if (!navigator.onLine) return fallback('You seem to be offline.');

            // 1. the three listings - the first one also settles direct vs api.php
            srvStatus('Discovering files…');
            const settle = (p) => p.then((v) => ({ v }), (e) => ({ e }));
            const dirs = Object.keys(SRV_DIRS), lists = [await settle(srvList(dirs[0]))];
            lists.push(...await Promise.all(dirs.slice(1).map((d) => settle(srvList(d)))));
            if (lists.every((l) => l.e)) return fallback(`Could not reach the server (${lists[0].e.message}).`);

            // 2. compare with the cache
            const warn = [], jobs = [], keep = [], gone = [];
            dirs.forEach((dir, i) => {
                const names = lists[i].v, mine = [...usable.values()].filter((r) => r.dir === dir);
                if (!names) { warn.push(`could not list ${dir}/ (${lists[i].e.message})`); return keep.push(...mine); }
                // an empty listing is more likely a server hiccup than every player leaving - keep what is saved
                if (!names.length) { if (mine.length) warn.push(`the server listed no files in ${dir}/`); return keep.push(...mine); }
                const live = new Set();
                for (const name of names) {
                    // .dat_old is the server's previous save of the .dat next to it
                    if (/\.dat_old$/i.test(name) || isBot(name) || !kindOf(`${SRV_DIRS[dir]}/${name}`)) continue;
                    live.add(name);
                    jobs.push({ dir, name, key: `${dir}/${name}`, rec: usable.get(`${dir}/${name}`) });
                }
                // gone from the server: forget only our own copy of that file
                gone.push(...mine.filter((r) => !live.has(r.name)).map((r) => r.key));
            });
            if (gone.length) await srvCache.drop(gone).catch(console.warn);
            if (!jobs.length && !keep.length) return srvEnd('The server has no player files right now.', [['Try again', 'sync', true]]);

            // 3. fetch what is missing or changed, a few at a time
            const out = [...keep], changed = new Set(), failed = [];
            let done = 0, added = 0, updated = 0, unsaved = 0;
            const tick = () => srvStatus(added + updated ? `Downloading files… ${num(done)} / ${num(jobs.length)} checked · ${num(added + updated)} downloaded` : `Checking for updates… ${num(done)} / ${num(jobs.length)}`, done, jobs.length);
            tick();
            let next = 0;
            await Promise.all(Array.from({ length: Math.min(6, jobs.length) }, async () => {
                while (next < jobs.length) {
                    const j = jobs[next++];
                    try {
                        const r = await srvPull(j);
                        out.push(r.rec);
                        if (r.state !== 'same') changed.add(j.key);
                        if (r.state === 'new') added++;
                        if (r.state === 'changed') updated++;
                        if (r.unsaved) unsaved++;
                    } catch (err) {
                        console.warn(`Server stats: ${j.key}:`, err);
                        j.err = err.name === 'TimeoutError' ? 'timed out' : err instanceof TypeError ? 'network error' : err.message;
                        failed.push(j);
                        if (j.rec) out.push(j.rec);   // keep showing the saved copy
                    }
                    done++;
                    tick();
                }
            }));
            if (!out.length) return fallback(`Could not download any of the ${num(jobs.length)} files.`);

            // 4. say exactly what is missing or stale
            if (failed.length) {
                const stale = failed.filter((j) => j.rec).length, missing = failed.length - stale;
                const why = [...new Set(failed.map((j) => j.err))].slice(0, 3).join(', ');
                warn.unshift(`${num(failed.length)} file${failed.length === 1 ? '' : 's'} could not be downloaded (${why})` + [stale && `${num(stale)} shown from the saved copy`, missing && `${num(missing)} not available yet`].filter(Boolean).map((s) => ' - ' + s).join(''));
            }
            if (unsaved) warn.push(`${num(unsaved)} file${unsaved === 1 ? '' : 's'} could not be saved in this browser (storage full?) and will be downloaded again next time`);
            if (!warn.length) store.set('nbt.srv.synced', Date.now());
            await srvOpen(out, changed, warn);
            if (added + updated) toast(`Server stats: ${[added && `${num(added)} new`, updated && `${num(updated)} updated`].filter(Boolean).join(', ')} · ${num(out.length - added - updated)} unchanged`, 'note');
        }

        // 5. into the normal loading pipeline, then 6. open the viewer
        async function srvOpen(recs, changed, warn) {
            // already showing the server's players: only the files that changed need reading again
            if (srvLoaded && players.length) recs = recs.filter((r) => changed.has(r.key));
            if (recs.length) {
                srvStatus('Loading stats…');
                let n = 0;
                await loadEntries(recs.map((r) => ({
                    name: r.name, path: `${SRV_DIRS[r.dir]}/${r.name}`, modified: Date.parse(r.lastModified) || r.fetched,
                    // let the page paint now and then - JSON files parse without ever yielding
                    read: async () => { if (++n % 8 === 0) await new Promise((res) => setTimeout(res)); return r.bytes; },
                })));
                srvLoaded = true;
                srvStatus('Opening stats…', 1, 1);
                if (players.length) scrollTo({ top: $('#viewer').offsetTop - 10, behavior: 'smooth' });
            } else toast('Server stats are already up to date.', 'note');
            srvEnd();
            if (warn.length) srvWarn('Server stats: ' + warn.join('; ') + '.');
        }

        // a zip / jar / tar: a mod, a resource pack, or a zipped world / server folder
        async function openArchive(e) {
            const n = e.name.toLowerCase();
            const blob = e.blob || new Blob([await e.read()]);
            const want = (path) => !SKIP_PATH.test(path) && (kindOf(path) || (!path.includes('/') && /\.(dat|dat_old|nbt)$/i.test(path) ? 'nbt' : null));
            if (/\.(tar|tgz|tar\.gz)$/.test(n)) {
                const files = await tarList(blob, !/\.tar$/.test(n), (p) => !!want(p));
                const out = [];
                for (const f of files) {
                    const kind = want(f.path);
                    const child = { name: f.path.split('/').pop(), path: f.path, modified: e.modified, kind, read: async () => f.data };
                    if (kind === 'archive') out.push(...await openArchive({ ...child, blob: new Blob([f.data]) }).catch((err) => (showError(`Could not open ${f.path}: ${err.message}`), [])));
                    else out.push(child);
                }
                return out;
            }
            const list = await zipList(blob);
            const has = (p) => list.some((z) => z.name === p);
            if (has('fabric.mod.json') || has('quilt.mod.json') || has('META-INF/mods.toml') || has('META-INF/neoforge.mods.toml') || has('mcmod.info')) { await loadMod(e, blob, list); return []; }
            const mcmeta = list.find((z) => /^([^/]+\/)?pack\.mcmeta$/.test(z.name));
            if (mcmeta) {
                const root = mcmeta.name.slice(0, -'pack.mcmeta'.length);
                if (list.some((z) => z.name.startsWith(root + 'assets/'))) { await loadPack(e, blob, list, root); return []; }
            }
            const out = [];
            for (const z of list) {
                if (z.name.endsWith('/')) continue;
                const kind = want(z.name);
                if (!kind) continue;
                const child = { name: z.name.split('/').pop(), path: z.name, modified: z.time || e.modified, kind, read: () => zipRead(blob, z) };
                if (kind === 'archive') {
                    try { out.push(...await openArchive({ ...child, blob: await zipBlobOf(blob, z) })); } catch (err) { showError(`Could not open ${z.name}: ${err.message}`); }
                } else out.push(child);
            }
            if (!out.length) showNote(`Nothing the viewer understands was found in ${e.name}.`);
            return out;
        }

        async function loadMod(e, blob, list) {
            const get = (p) => list.find((z) => z.name === p);
            const text = async (z) => textOf(await zipRead(blob, z));
            let info = { file: e.name, id: '', name: '', version: '', loader: '', desc: '', icon: '' };
            try {
                if (get('fabric.mod.json')) {
                    const j = parseLoose(await text(get('fabric.mod.json')));
                    info = { ...info, loader: 'Fabric', id: j.id, name: j.name || j.id, version: j.version, desc: j.description, icon: typeof j.icon === 'string' ? j.icon : isObj(j.icon) ? Object.values(j.icon).pop() : '' };
                } else if (get('quilt.mod.json')) {
                    const q = parseLoose(await text(get('quilt.mod.json'))).quilt_loader || {}, m = q.metadata || {};
                    info = { ...info, loader: 'Quilt', id: q.id, name: m.name || q.id, version: q.version, desc: m.description, icon: typeof m.icon === 'string' ? m.icon : '' };
                } else if (get('META-INF/neoforge.mods.toml') || get('META-INF/mods.toml')) {
                    const neo = !!get('META-INF/neoforge.mods.toml');
                    const t = parseTOML(await text(get(neo ? 'META-INF/neoforge.mods.toml' : 'META-INF/mods.toml')));
                    const m = (Array.isArray(t.mods) && t.mods[0]) || {};
                    let version = String(m.version || '');
                    if (/\$\{/.test(version) && get('META-INF/MANIFEST.MF')) { const v = /Implementation-Version:\s*(.+)/.exec(await text(get('META-INF/MANIFEST.MF'))); if (v) version = v[1].trim(); }
                    info = { ...info, loader: neo ? 'NeoForge' : 'Forge', id: m.modId, name: m.displayName || m.modId, version, desc: m.description, icon: m.logoFile || t.logoFile || '' };
                } else if (get('mcmod.info')) {
                    let j = parseLoose(await text(get('mcmod.info')));
                    if (isObj(j) && Array.isArray(j.modList)) j = j.modList;
                    const m = (Array.isArray(j) ? j[0] : j) || {};
                    info = { ...info, loader: 'Forge (legacy)', id: m.modid, name: m.name, version: m.version, desc: m.description, icon: m.logoFile || '' };
                }
            } catch (err) { showNote(`${e.name}: could not read the mod details (${err.message}).`); }
            // item textures first; block textures stand in for block items (exact name, then _side / _front / _top)
            // mine / names: what gets saved to the site for everyone (saveModToSite)
            const blocks = [], mine = [], names = {};
            let tex = 0;
            for (const z of list) {
                const m = /^assets\/([^/]+)\/textures\/(item|items|block|blocks)\/([^/]+)\.png$/.exec(z.name);
                if (m && m[1] !== 'minecraft') {
                    if (m[2][0] === 'i') { TEXREF.set(`${m[1]}:${m[3]}`, { blob, e: z }); mine.push([`${m[1]}:${m[3]}`, z]); tex++; } else blocks.push([m[1], m[3], z]);
                    continue;
                }
                const l = /^assets\/([^/]+)\/lang\/en_us\.(json|lang)$/i.exec(z.name);
                if (l) try {
                    const t = await text(z);
                    for (const [k, v] of Object.entries(l[2].toLowerCase() === 'json' ? parseLoose(t) : parseProps(t))) if (typeof v === 'string') {
                        LANG.set(k, v);
                        if (/^(item|block|entity)\.[a-z0-9_.-]+$/.test(k) && k.split('.')[1] !== 'minecraft') names[k] = v;
                    }
                } catch (err) { }
            }
            const rank = (n) => (/_side$/.test(n) ? 1 : /_front$/.test(n) ? 2 : /_top$/.test(n) ? 3 : 0);
            blocks.sort((a, b) => rank(a[1]) - rank(b[1]));
            for (const [ns, n, z] of blocks) {
                const k = `${ns}:${n.replace(/_(side|front|top)$/, '')}`;
                if (!TEXREF.has(k)) { TEXREF.set(k, { blob, e: z }); mine.push([k, z]); tex++; }
            }
            saveModToSite(info, blob, mine.filter(([k]) => /^[a-z0-9_-][a-z0-9_.-]*:[a-z0-9_-][a-z0-9_.-]*$/.test(k)), names);
            const icon = info.icon && get(String(info.icon).replace(/^\.?\//, ''));
            if (icon) TEXREF.set('modicon:' + (info.id || e.name), { blob, e: icon });
            CTX.mods.push({ ...info, textures: tex });
            ctxFile(e, 'Mod');
        }

        async function loadPack(e, blob, list, root) {
            let n = 0, desc = '';
            for (const z of list) {
                if (!z.name.startsWith(root)) continue;
                const m = /^assets\/([^/]+)\/textures\/items?\/([^/]+)\.png$/.exec(z.name.slice(root.length));
                if (!m) continue;
                const k = `${m[1]}:${m[2]}`;
                TEXREF.set(k, { blob, e: z });
                TEX.delete(k);      // later packs win
                n++;
            }
            try { const j = parseLoose(textOf(await zipRead(blob, list.find((z) => z.name === root + 'pack.mcmeta')))); desc = isObj(j.pack) ? j.pack.description ?? '' : ''; } catch (err) { }
            const icon = list.find((z) => z.name === root + 'pack.png');
            if (icon) TEXREF.set('packicon:' + e.name, { blob, e: icon });
            CTX.packs.push({ file: e.name, desc, textures: n });
            ctxFile(e, 'Resource pack');
        }

        /* ---------- handlers for each kind of world / server / plugin file ---------- */
        function loadList(name, arr) {
            const n = name.toLowerCase(), has = (k) => arr.some((x) => isObj(x) && k in x);
            const kind = /usercache/.test(n) ? 'usercache' : /^ops/.test(n) ? 'ops' : /whitelist|allowlist/.test(n) ? 'whitelist' : /banned-ips/.test(n) ? 'ipbans' : /banned-players/.test(n) ? 'bans'
                : has('ip') ? 'ipbans' : has('level') ? 'ops' : has('reason') ? 'bans' : has('expiresOn') ? 'usercache' : arr.length && arr.every((x) => isObj(x) && x.uuid && x.name) ? 'whitelist' : null;
            if (!kind) return null;
            if (kind === 'ipbans') return 'IP bans (not shown)';
            for (const x of arr) {
                const k = isObj(x) && uuidKey(x.uuid);
                if (!k) continue;
                if (x.name) CTX.names.set(k, String(x.name));
                // the cache entry expires a month after the player's last login
                if (kind === 'usercache') CTX.usercache.set(k, { name: x.name, seen: parseMcDate(x.expiresOn) ? parseMcDate(x.expiresOn) - 2592000000 : 0 });
                if (kind === 'ops') CTX.ops.set(k, x);
                if (kind === 'whitelist') CTX.whitelist.add(k);
                if (kind === 'bans') CTX.bans.set(k, x);
            }
            if (kind === 'ops') CTX.opsLoaded = true;
            if (kind === 'whitelist') CTX.whitelistLoaded = true;
            if (kind === 'bans') CTX.bansLoaded = true;
            return { usercache: 'User cache', ops: 'Operators', whitelist: 'Whitelist', bans: 'Bans' }[kind];
        }

        function lpNodes(o) {
            const nodes = [];
            const add = (key, p = {}) => nodes.push({ key: String(key), value: p.value !== false && p.value !== 'false', context: isObj(p.context) && Object.keys(p.context).length ? p.context : null, expiry: +p.expiry || 0 });
            const list = (arr, prefix = '') => (Array.isArray(arr) ? arr : isObj(arr) ? Object.entries(arr).map(([k, v]) => ({ [k]: v })) : []).forEach((x) => {
                if (typeof x === 'string') return add(prefix + x);
                if (!isObj(x)) return;
                if (x.permission || x.key) return add(x.permission || x.key, x);         // json storage / export
                if (x.group) return add('group.' + x.group, x);
                const [k, v] = Object.entries(x)[0] || [];
                if (k) add(prefix + k, isObj(v) ? v : {});
            });
            list(o.permissions); list(o.nodes); list(o.parents, 'group.');
            for (const type of ['prefix', 'suffix']) {
                for (const x of Array.isArray(o[type + 'es']) ? o[type + 'es'] : []) {
                    if (!isObj(x)) continue;
                    if (typeof x[type] === 'string') { add(`${type}.${x.priority || 0}.${x[type]}`, x); continue; }
                    const [txt, v] = Object.entries(x)[0] || [];
                    if (txt) add(`${type}.${(isObj(v) && v.priority) || 0}.${txt}`, isObj(v) ? v : {});
                }
            }
            return nodes;
        }
        function lpUser(o, fallbackId) {
            const k = uuidKey(o.uuid || fallbackId);
            if (!k) return;
            const name = o.name || o.username;
            CTX.lpUsers.set(k, { name, primary: o['primary-group'] || o.primaryGroup || 'default', nodes: lpNodes(o) });
            if (name && !CTX.names.has(k)) CTX.names.set(k, String(name));
        }

        // the main inventory list somewhere under a storage entry ("inventory" wins over upgrades / filters)
        const findItems = (v, depth = 0) => {
            if (depth > 8 || !v || typeof v !== 'object') return null;
            if (Array.isArray(v.Items) && v.Items.every((x) => isObj(x))) return v.Items;
            if (isObj(v.inventory)) { const r = findItems(v.inventory, depth + 1); if (r) return r; }
            for (const x of Object.values(v)) { const r = isObj(x) ? findItems(x, depth + 1) : null; if (r) return r; }
            return null;
        };
        function indexStorage(root, file = '') {
            let n = 0;
            const walk = (v, depth) => {
                if (depth > 12 || !v || typeof v !== 'object') return;
                if (Array.isArray(v)) { v.forEach((x) => walk(x, depth + 1)); return; }
                const u = storageUuid(v);
                // Sophisticated Backpacks' access log: who last opened each backpack, and when
                if (u && typeof v.playerName === 'string') { if (!CTX.bpLog.has(u)) CTX.bpLog.set(u, []); CTX.bpLog.get(u).push(v); n++; return; }
                const items = u && findItems(v);
                if (items) { CTX.backpacks.set(u, items); CTX.bpStore.set(u, { file, entry: v }); n++; return; }
                for (const x of Object.values(v)) walk(x, depth + 1);
            };
            walk(root, 0);
            return n;
        }

        function schemInfo(name, d) {
            const counts = new Map();
            const add = (n, c = 1) => { n = strip(String(n).replace(/\[.*$/, '')); if (/^(air|cave_air|void_air|structure_void)$/.test(n)) return; counts.set(n, (counts.get(n) || 0) + c); };
            let size = null, kind = '', meta = {}, entities = 0, tiles = 0;
            if (Array.isArray(d.size) && Array.isArray(d.blocks)) {
                kind = 'Structure';
                size = d.size;
                const pal = Array.isArray(d.palette) ? d.palette : (d.palettes || [])[0] || [];
                for (const b of d.blocks) { const p = pal[b.state]; if (p) add(p.Name); if (b.nbt) tiles++; }
                entities = (d.entities || []).length;
            } else if (isObj(d.Regions) && isObj(d.Metadata)) {
                kind = 'Litematica schematic';
                const m = d.Metadata, es = m.EnclosingSize;
                meta = { name: m.Name, author: m.Author, desc: m.Description, created: m.TimeCreated };
                size = isObj(es) ? [es.x, es.y, es.z] : null;
                for (const r of Object.values(d.Regions)) {
                    if (!isObj(r)) continue;
                    const pal = r.BlockStatePalette || [], s = r.Size || {}, vol = Math.abs(s.x * s.y * s.z);
                    entities += (r.Entities || []).length; tiles += (r.TileEntities || []).length;
                    if (!vol || vol > 6e7 || !pal.length) continue;
                    // block states are packed back to back across the longs, low bits first
                    const bits = Math.max(2, 32 - Math.clz32(pal.length - 1)), mask = bits >= 32 ? 0xffffffff : (1 << bits) - 1;
                    const longs = r.BlockStates || [], w = new Uint32Array(longs.length * 2 + 1);
                    longs.forEach((v, i) => { const b = BigInt.asUintN(64, BigInt(v)); w[i * 2] = Number(b & 0xffffffffn); w[i * 2 + 1] = Number(b >> 32n); });
                    const tally = new Uint32Array(pal.length);
                    for (let i = 0; i < vol; i++) {
                        const o = i * bits, wi = o >>> 5, sh = o & 31;
                        let v = w[wi] >>> sh;
                        if (sh + bits > 32) v |= w[wi + 1] << (32 - sh);
                        v = (v & mask) >>> 0;
                        if (v < tally.length) tally[v]++;
                    }
                    pal.forEach((p, i) => tally[i] && add(p.Name, tally[i]));
                }
            } else {
                const s = isObj(d.Schematic) ? d.Schematic : d;
                if (s.Width === undefined || s.Height === undefined || s.Length === undefined) return null;
                size = [s.Width, s.Height, s.Length];
                const md = isObj(s.Metadata) ? s.Metadata : {};
                meta = { name: md.Name, author: md.Author, created: md.Date };
                const blocks = isObj(s.Blocks) ? s.Blocks : null, pal = blocks ? blocks.Palette : s.Palette, data = blocks ? blocks.Data : s.BlockData;
                if (isObj(pal) && Array.isArray(data)) {
                    kind = 'Sponge schematic v' + (s.Version || (blocks ? 3 : 2));
                    const byId = [];
                    for (const [n, id] of Object.entries(pal)) byId[id] = n;
                    const tally = new Map();
                    for (let i = 0, v = 0, sh = 0; i < data.length; i++) {        // varints
                        const b = data[i] & 255;
                        v |= (b & 127) << sh;
                        if (b & 128) { sh += 7; continue; }
                        tally.set(v, (tally.get(v) || 0) + 1);
                        v = 0; sh = 0;
                    }
                    for (const [id, c] of tally) if (byId[id]) add(byId[id], c);
                    tiles = ((blocks ? blocks.BlockEntities : s.BlockEntities || s.TileEntities) || []).length;
                    entities = (s.Entities || []).length;
                } else if (Array.isArray(s.Blocks)) {
                    kind = 'MCEdit schematic';
                    const map = isObj(s.SchematicaMapping) ? Object.fromEntries(Object.entries(s.SchematicaMapping).map(([n, id]) => [id, n])) : null;
                    const tally = new Map();
                    for (const b of s.Blocks) { const id = b & 255; if (id) tally.set(id, (tally.get(id) || 0) + 1); }
                    for (const [id, c] of tally) add(map && map[id] ? map[id] : 'legacy_block_' + id, c);
                    tiles = (s.TileEntities || []).length; entities = (s.Entities || []).length;
                } else return null;
            }
            const list = [...counts].sort((a, b) => b[1] - a[1]);
            return { kind, size, meta, list, total: list.reduce((s, x) => s + x[1], 0), entities, tiles, name };
        }

        // parsed NBT / JSON / SNBT: players go to `parsed`, everything else into CTX
        async function loadNbt(e, parsed) {
            let data = await parseFile(e.name, await e.read());
            if (Array.isArray(data)) {
                const what = loadList(e.name, data);
                if (what) ctxFile(e, what); else { CTX.data.push({ file: e.path, data }); ctxFile(e, 'Data'); }
                return;
            }
            data = unwrap(data);
            if (!isObj(data)) throw new Error('The file did not contain an NBT compound.');
            const kind = classify(data, e.name, e.path);
            const sd = isObj(data.data) ? data.data : {};
            // every world file stays explorable as raw NBT on the World & server page
            if (kind !== 'player' && kind !== 'stats' && kind !== 'advancements') CTX.raw.push({ file: e.path, data });
            switch (kind) {
                case 'player': case 'stats': case 'advancements':
                    parsed.push({ name: e.name, data, kind, modified: e.modified });
                    return;
                case 'level':
                    CTX.level = { file: e.path, data: data.Data };
                    ctxFile(e, 'World');
                    if (isObj(data.Data.Player)) parsed.push({ name: e.name, data: data.Data.Player, kind: 'player', modified: e.modified });
                    return;
                case 'hotbar': CTX.hotbars = { file: e.path, data }; return ctxFile(e, 'Saved hotbars');
                case 'servers': CTX.servers = { file: e.path, list: (data.servers || []).filter(isObj) }; return ctxFile(e, 'Server list');
                case 'map': {
                    const id = (/map_(\d+)/.exec(e.name) || [])[1] ?? String(CTX.maps.size);
                    const dim = typeof sd.dimension === 'number' ? ['minecraft:the_nether', 'minecraft:overworld', 'minecraft:the_end'][sd.dimension + 1] : sd.dimension;
                    CTX.maps.set(id, { colors: Uint8Array.from(sd.colors, (v) => v & 255), scale: sd.scale || 0, dim, x: sd.xCenter ?? 0, z: sd.zCenter ?? 0, locked: truthy(sd.locked) });
                    return ctxFile(e, 'Map');
                }
                case 'scoreboard':
                    CTX.scoreboard = { objectives: new Map((sd.Objectives || []).map((o) => [o.Name, { name: o.DisplayName ?? null, criteria: o.CriteriaName || '' }])), scores: sd.PlayerScores || [], teams: sd.Teams || [], slots: isObj(sd.DisplaySlots) ? sd.DisplaySlots : {} };
                    return ctxFile(e, 'Scoreboard');
                case 'backpacks':
                    if (!indexStorage(data, e.path)) { CTX.data.push({ file: e.path, data }); return ctxFile(e, 'Data'); }
                    return ctxFile(e, 'Backpack storage');
                case 'schem': {
                    const info = schemInfo(e.name, data);
                    if (!info) throw new Error('not a schematic or structure');
                    CTX.schems.push({ file: e.path, ...info });
                    return ctxFile(e, 'Schematic');
                }
                case 'lp-export':
                    for (const [u, x] of Object.entries(data.users)) if (isObj(x)) lpUser({ ...x, uuid: u });
                    for (const [g, x] of Object.entries(data.groups)) if (isObj(x)) CTX.lpGroups.set(g.toLowerCase(), lpNodes(x));
                    return ctxFile(e, 'LuckPerms export');
                case 'lp':
                    if (/\/groups\//.test(e.path.replace(/\\/g, '/').toLowerCase())) CTX.lpGroups.set(String(data.name || e.name.replace(/\.\w+$/, '')).toLowerCase(), lpNodes(data));
                    else lpUser(data, e.name.replace(/\.\w+$/, ''));
                    return ctxFile(e, 'LuckPerms');
                default:
                    // other world data (command storage, raids, mod data...) - still index any backpack-style storage in it
                    if (/(^|\/)data\//i.test(e.path.replace(/\\/g, '/')) && indexStorage(data, e.path)) return ctxFile(e, 'Backpack storage');
                    CTX.data.push({ file: e.path, data });
                    return ctxFile(e, 'Data');
            }
        }

        const HANDLERS = {
            async uid(e) {
                const u = await e.read();
                if (u.length < 16) throw new Error('too short for a uid.dat');
                const parts = e.path.replace(/\\/g, '/').split('/');
                CTX.worlds.set(dashUuid([...u.subarray(0, 16)].map((b) => b.toString(16).padStart(2, '0')).join('')), parts.length > 1 ? parts[parts.length - 2] : 'world');
                ctxFile(e, 'World id');
            },
            // region / entities files are only listed here - a world has thousands, so they are read by scanRegions()
            async mca(e) {
                if (CTX.regions.some((r) => r.path === e.path)) return;
                CTX.regions.push(e);
                ctxFile(e, /(^|\/)entities\//i.test(e.path.replace(/\\/g, '/')) ? 'Entities' : 'Region');
            },
            async props(e) { CTX.props = { file: e.path, data: parseProps(await readText(e)) }; ctxFile(e, 'Server settings'); },
            async log(e) {
                if (CTX.logFiles.includes(e.path)) return;
                parseLog(await readText(e), e.name, e.modified);
                CTX.logFiles.push(e.path);
                CTX.logDirty = true;
                ctxFile(e, 'Log');
            },
            async yaml(e) {
                const y = await parseYAML(await readText(e));
                const lp = e.path.replace(/\\/g, '/').toLowerCase(), base = e.name.replace(/\.ya?ml$/i, '');
                if (!isObj(y)) { CTX.configs.push({ file: e.path, data: y }); return ctxFile(e, 'Config'); }
                if (/essentials\/userdata\//.test(lp) || (UUID_RE.test(base) && (y.timestamps || y.money !== undefined || y.homes || y.lastAccountName || y['last-account-name']))) {
                    const k = uuidKey((base.match(UUID_RE) || [])[0]);
                    if (!k) return;
                    CTX.ess.set(k, y);
                    const n = y.lastAccountName ?? y['last-account-name'];
                    if (n && !CTX.names.has(k)) CTX.names.set(k, String(n));
                    return ctxFile(e, 'EssentialsX user');
                }
                if (/luckperms\/yaml-storage\/groups\//.test(lp)) { CTX.lpGroups.set(String(y.name || base).toLowerCase(), lpNodes(y)); return ctxFile(e, 'LuckPerms group'); }
                if (/luckperms\/yaml-storage\/users\//.test(lp) || (y.uuid && 'primary-group' in y)) { lpUser(y, base); return ctxFile(e, 'LuckPerms user'); }
                if (/claimdata\//.test(lp) || y['Lesser Boundary Corner']) {
                    const corner = (s) => { const p = String(s || '').split(';'); return p.length >= 4 ? { world: p[0], x: +p[1], y: +p[2], z: +p[3] } : null; };
                    const a = corner(y['Lesser Boundary Corner']), b = corner(y['Greater Boundary Corner']);
                    if (a && b) CTX.gpClaims.push({ id: base, owner: uuidKey(y.Owner), world: a.world, a, b, area: (Math.abs(b.x - a.x) + 1) * (Math.abs(b.z - a.z) + 1), parent: +(y['Parent Claim ID'] ?? -1), trusted: ['Builders', 'Containers', 'Accessors', 'Managers'].reduce((s, k) => s + (Array.isArray(y[k]) ? y[k].length : 0), 0) });
                    return ctxFile(e, 'GriefPrevention claim');
                }
                if (/worldguard\/worlds\//.test(lp) || (isObj(y.regions) && Object.values(y.regions).some((r) => isObj(r) && 'type' in r))) {
                    const world = (/worlds\/([^/]+)\/regions\.yml$/i.exec(e.path.replace(/\\/g, '/')) || [])[1] || '?';
                    const grp = (g) => ({ ids: (isObj(g) && Array.isArray(g['unique-ids']) ? g['unique-ids'] : []).map(uuidKey), names: (isObj(g) && Array.isArray(g.players) ? g.players : []).map((n) => String(n).toLowerCase()) });
                    for (const [id, r] of Object.entries(y.regions || {})) if (isObj(r)) CTX.wg.push({ world, id, type: r.type, priority: r.priority, min: r.min, max: r.max, owners: grp(r.owners), members: grp(r.members) });
                    return ctxFile(e, 'WorldGuard regions');
                }
                if (/multiverse-core\/worlds/.test(lp)) {
                    for (const [n, w] of Object.entries(isObj(y.worlds) ? y.worlds : y)) if (isObj(w)) CTX.mv.set(n, { alias: w.alias || '', environment: w.environment || w.env || '' });
                    return ctxFile(e, 'Multiverse worlds');
                }
                CTX.configs.push({ file: e.path, data: y });
                ctxFile(e, 'Config');
            },
            async lpjson(e) {
                const j = parseLoose(await readText(e));
                if (/\/groups\//.test(e.path.replace(/\\/g, '/').toLowerCase())) CTX.lpGroups.set(String(j.name || e.name.replace(/\.json$/i, '')).toLowerCase(), lpNodes(j));
                else lpUser(j, e.name.replace(/\.json$/i, ''));
                ctxFile(e, 'LuckPerms');
            },
            async mcmmo(e) {
                const rows = [];
                for (const line of (await readText(e)).split(/\r?\n/)) {
                    if (!line || line[0] === '#') continue;
                    const f = line.split(':');
                    if (f.length < 20) continue;
                    const skills = {};
                    for (const [n, li, xi] of MCMMO) if (f[li] !== undefined && f[li] !== '') skills[n] = [parseInt(f[li], 10) || 0, parseFloat(f[xi]) || 0];
                    rows.push({ name: f[0], uuid: uuidKey(f[41]), skills });
                }
                CTX.mcmmo = rows;
                ctxFile(e, 'mcMMO users');
            },
            async gp(e) {
                const k = uuidKey((e.name.match(UUID_RE) || [])[0]);
                if (!k) return;
                // line 1: retired last-login date, 2: accrued claim blocks, 3: bonus claim blocks
                const lines = (await readText(e)).split(/\r?\n/);
                CTX.gpPlayers.set(k, { accrued: parseInt(lines[1], 10) || 0, bonus: parseInt(lines[2], 10) || 0 });
                ctxFile(e, 'GriefPrevention player');
            },
            async towny(e) {
                const o = parseProps(await readText(e)), lp = e.path.replace(/\\/g, '/').toLowerCase(), n = e.name.replace(/\.txt$/i, '');
                const rec = { name: o.name || n, ...o };
                if (/\/towns\//.test(lp) || (!/\/residents\//.test(lp) && 'mayor' in o)) CTX.towny.towns.set(n.toLowerCase(), rec);
                else if (/\/nations\//.test(lp) || (!/\/residents\//.test(lp) && 'capital' in o)) CTX.towny.nations.set(n.toLowerCase(), rec);
                else CTX.towny.residents.set(n.toLowerCase(), rec);
                ctxFile(e, 'Towny');
            },
            async config(e) {
                const text = await readText(e), n = e.name.toLowerCase();
                let data;
                try {
                    if (n.endsWith('.toml')) data = parseTOML(text);
                    else if (/\.json5?$/.test(n)) data = parseLoose(text);
                    else if (n.endsWith('.properties')) data = parseProps(text);
                    else if (/\.ya?ml$/.test(n)) data = await parseYAML(text);
                } catch (err) { console.warn(e.path, err); }
                CTX.configs.push(data && typeof data === 'object' ? { file: e.path, data } : { file: e.path, text: text.slice(0, 300000) });
                ctxFile(e, 'Config');
            },
            async ftb(e) {
                const lp = e.path.replace(/\\/g, '/').toLowerCase(), d = parseSNBT(await readText(e)), F = CTX.ftb;
                const fileId = normUuid(e.name.replace(/\.snbt$/i, ''));
                if (/quests\/lang\//.test(lp)) { for (const [k, v] of Object.entries(d)) F.lang.set(k, Array.isArray(v) ? v.join(' ') : String(v)); return ctxFile(e, 'FTB Quests'); }
                if (/quests\/chapters\//.test(lp)) {
                    const task = (q) => { const t = (q.tasks || [])[0]; return t ? itemIdOf(t.item) : null; };
                    F.chapters.push({ id: String(d.id || ''), title: d.title || null, icon: d.icon || null, order: d.order_index ?? F.chapters.length, file: d.filename || e.name.replace(/\.snbt$/i, ''), quests: (d.quests || []).filter(isObj).map((q) => ({ id: String(q.id || ''), title: q.title || null, task: task(q) })) });
                    return ctxFile(e, 'FTB Quests');
                }
                if (/ftbteams\//.test(lp)) {
                    const members = [...Object.entries(isObj(d.ranks) ? d.ranks : {}).filter(([, r]) => !/invited|ally|enemy|none/i.test(String(r))).map(([u]) => normUuid(u)), ...(Array.isArray(d.members) ? d.members.map(normUuid) : []), normUuid(d.owner)].filter(Boolean);
                    const props = isObj(d.properties) ? d.properties : {};
                    F.teams.push({ id: normUuid(d.id) || fileId, type: String(d.type || (/\/party\//.test(lp) ? 'party' : 'player')), name: props['ftbteams:display_name'] ?? null, members });
                    return ctxFile(e, 'FTB Teams');
                }
                if (/ftbchunks\//.test(lp)) {
                    const byDim = {};
                    let forced = 0;
                    for (const [dm, list] of Object.entries(isObj(d.chunks) ? d.chunks : {})) if (Array.isArray(list)) { byDim[dm] = list.length; forced += list.filter((c) => isObj(c) && (c.force_loaded || c.forced)).length; }
                    const id = normUuid(d.id) || fileId;
                    if (id) F.chunks.set(id, { byDim, forced });
                    return ctxFile(e, 'FTB Chunks');
                }
                const id = normUuid(d.id) || fileId;
                if (id) F.progress.set(id, { completed: isObj(d.completed) ? d.completed : {} });
                ctxFile(e, 'FTB Quests progress');
            },
            async schem(e) { await loadNbt({ ...e }, []); },
        };
        const OWNER = new TextEncoder().encode('Owner');
        function hasBytes(u, pat) {
            for (let i = u.indexOf(pat[0]); i >= 0 && i <= u.length - pat.length; i = u.indexOf(pat[0], i + 1)) {
                let j = 1;
                while (j < pat.length && u[i + j] === pat[j]) j++;
                if (j === pat.length) return true;
            }
            return false;
        }

        /* ---------- region + entities files: backpacks placed or stored in the world, owned pets ---------- */
        // a backpack by its item name, not its namespace (sophisticatedbackpacks:pickup_upgrade is not one)
        const isBp = (id) => /backpack/i.test(String(id).split(':').pop());
        const BP_PAT = ['backpack', 'torage_uuid', 'ontentsUuid'].map((s) => new TextEncoder().encode(s));
        const mcaDim = (path) => {
            const p = '/' + path.replace(/\\/g, '/').toLowerCase();
            const dm = /\/dimensions\/([^/]+)\/([^/]+)\/(region|entities)\//.exec(p);
            return dm ? `${dm[1]}:${dm[2]}` : /\/dim-1\//.test(p) ? 'minecraft:the_nether' : /\/dim1\//.test(p) ? 'minecraft:the_end' : 'minecraft:overworld';
        };
        const mcaSize = (e) => (e.blob ? e.blob.size : e.size || 4e6);
        // the chunks in a region file: [{cx, cz, read() -> decompressed NBT bytes}]
        function mcaChunks(e, u) {
            const m = /r\.(-?\d+)\.(-?\d+)\.mca$/i.exec(e.path) || [0, 0, 0];
            const dv = new DataView(u.buffer, u.byteOffset, u.byteLength), out = [];
            for (let i = 0; i < 1024 && u.length >= 8192; i++) {
                const off = (dv.getUint32(i * 4) >>> 8) * 4096;
                if (!off || off + 5 > u.length) continue;
                const len = dv.getUint32(off), ct = u[off + 4];
                if (len < 2 || off + 4 + len > u.length || ct < 1 || ct > 3) continue;
                const raw = u.subarray(off + 5, off + 4 + len);
                out.push({ cx: +m[1] * 32 + (i % 32), cz: +m[2] * 32 + Math.floor(i / 32), read: () => (ct === 3 ? Promise.resolve(raw) : inflateAs(raw, ct === 1 ? 'gzip' : 'deflate')) });
            }
            return out;
        }
        // owned pets: tamed animals whose Owner is a player
        function petOwner(en, dim) {
            if (!isObj(en)) return;
            const k = 'Health' in en && strip(en.id) !== 'item' ? uuidKey(Array.isArray(en.Owner) ? uuidOf(en.Owner) : typeof en.Owner === 'string' ? en.Owner : en.OwnerUUID) : null;
            if (k) { en._dim = dim; if (!CTX.pets.has(k)) CTX.pets.set(k, []); CTX.pets.get(k).push(en); }
            (Array.isArray(en.Passengers) ? en.Passengers : []).forEach((p) => petOwner(p, dim));
        }
        const itemLike = (v) => typeof v.id === 'string' && !('x' in v && 'z' in v) && !Array.isArray(v.Pos) && ('count' in v || 'Count' in v || isObj(v.components) || isObj(v.tag));
        const bpLabel = (id) => (strip(String(id)) === 'item' ? 'Dropped item' : langId(id) || pretty(strip(String(id))));
        // every backpack-like item under v -> [{raw, uuid, chain}], chain = the containers it sits in
        function bpFind(v, out, chain, depth = 0) {
            if (depth > 48 || !v || typeof v !== 'object') return out;
            if (Array.isArray(v)) { for (const x of v) if (x && typeof x === 'object') bpFind(x, out, chain, depth + 1); return out; }
            let next = chain;
            if (itemLike(v)) {
                const u = storageUuid(v.components) || storageUuid(v.tag);
                if (u || isBp(v.id)) out.push({ raw: v, uuid: u, chain });
                next = chain.concat(bpLabel(v.id));
            }
            for (const x of Object.values(v)) if (x && typeof x === 'object') bpFind(x, out, next, depth + 1);
            return out;
        }
        function scanChunk(root, dim, file, pets) {
            const lv = isObj(root.Level) ? root.Level : root, anchors = [];
            for (const be of [lv.block_entities, lv.TileEntities].find(Array.isArray) || []) if (isObj(be)) anchors.push({ kind: 'block', raw: be, pos: [be.x, be.y, be.z] });
            for (const en of [lv.Entities, root.Entities].find(Array.isArray) || []) {
                if (!isObj(en)) continue;
                anchors.push({ kind: 'entity', raw: en, pos: Array.isArray(en.Pos) ? en.Pos : null });
                if (pets) petOwner(en, dim);
            }
            for (const a of anchors) {
                const id = String(a.raw.id || '?');
                for (const h of bpFind(a.raw, [], [bpLabel(id)])) CTX.bpSeen.push({ ...h, kind: a.kind, dim, pos: a.pos, anchor: a.raw, file, placed: a.kind === 'block' && isBp(id) && h.chain.length === 1 });
            }
        }
        let scanning = false;
        async function scanRegions(list = CTX.regions.filter((e) => !CTX.regionDone.has(e.path))) {
            if (scanning || !list.length) return 0;
            scanning = true;
            const before = CTX.bpSeen.length;
            let n = 0;
            try {
                for (const e of list) {
                    progress(`Scanning region files for backpacks and pets… ${++n} / ${list.length}`);
                    try {
                        const u = await e.read(), dim = mcaDim(e.path);
                        await Promise.all(mcaChunks(e, u).map((ch) => ch.read().then((c) => {
                            const pets = hasBytes(c, OWNER);
                            if (pets || BP_PAT.some((p) => hasBytes(c, p))) scanChunk(readNBT(c), dim, e.path, pets);
                        }).catch(() => { })));
                    } catch (err) { console.error(e.path, err); }
                    CTX.regionDone.add(e.path);
                }
            } finally { scanning = false; progress(null); }
            return CTX.bpSeen.length - before;
        }

        /* ---------- server logs: sessions, deaths, advancements, chat ---------- */
        const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        function parseLog(text, fname, modified) {
            const dm = /(\d{4})-(\d{2})-(\d{2})-\d+\.log/.exec(fname);
            const rows = [];
            let wraps = 0, lastSec = -1;
            for (const line of text.split(/\r?\n/)) {
                const tm = /^\[(?:(\d{1,2})([A-Za-z]{3})(\d{4}) )?(\d{2}):(\d{2}):(\d{2})/.exec(line);
                if (!tm) continue;
                // strip "[time] [thread/LEVEL]:", "[time LEVEL]:", Forge's "[logger/]:" and Fabric's "(Minecraft)"
                const m = /^((?:\[[^\]]*\]\s?)+)(?:\([^)]*\)\s?)?:?\s?(.*)$/.exec(line);
                if (!m) continue;
                const sec = +tm[4] * 3600 + +tm[5] * 60 + +tm[6];
                let full = null;
                if (tm[3]) full = new Date(+tm[3], MONTHS.indexOf(tm[2].toLowerCase()), +tm[1], +tm[4], +tm[5], +tm[6]).getTime();
                else { if (lastSec >= 0 && sec < lastSec - 3600) wraps++; lastSec = sec; }
                rows.push({ sec, day: wraps, full, msg: m[2] });
            }
            // dated logs start on their date; latest.log ends on the day it was last written
            const base = dm ? new Date(+dm[1], +dm[2] - 1, +dm[3]).getTime() : (() => { const d = new Date(modified || Date.now()); d.setHours(0, 0, 0, 0); return d.getTime() - wraps * 86400000; })();
            const seen = new Set();
            for (const r of rows) logEvent(r.full ?? base + r.day * 86400000 + r.sec * 1000, r.msg, seen);
        }
        function logEvent(t, msg, seen) {
            const push = (ev) => CTX.logEvents.push({ t, seq: CTX.logSeq++, ...ev });
            let m;
            if ((m = /^Starting minecraft server version (\S+)/.exec(msg))) return push({ type: 'start', ver: m[1] });
            if (/^Stopping (the )?server/.test(msg)) return push({ type: 'stop' });
            if ((m = /^UUID of player (\w{1,16}) is ([0-9a-f-]{32,36})/i.exec(msg))) {
                const k = uuidKey(m[2]);
                CTX.logUuids.set(m[1].toLowerCase(), k);
                if (!CTX.names.has(k)) CTX.names.set(k, m[1]);
                return;
            }
            if ((m = /^(\w{1,16})\[[^\]]*\] logged in with entity id \d+ at \((?:\[([^\]]+)\])?\s*(-?[\d.]+), (-?[\d.]+), (-?[\d.]+)\)/.exec(msg))) {
                seen.add(m[1].toLowerCase());
                return push({ type: 'login', name: m[1], world: m[2] || null, pos: [+m[3], +m[4], +m[5]] });
            }
            if ((m = /^(\w{1,16}) joined the game/.exec(msg))) { seen.add(m[1].toLowerCase()); return push({ type: 'join', name: m[1] }); }
            if ((m = /^(\w{1,16}) lost connection: (.*)/.exec(msg))) return push({ type: 'leave', name: m[1], reason: m[2] });
            if ((m = /^(\w{1,16}) left the game/.exec(msg))) return push({ type: 'leave', name: m[1], reason: 'left' });
            // chat and commands are private: only note that the player was active, never the text
            if ((m = /^(?:\[Not Secure\] )?<(\w{1,16})> /.exec(msg)) || (m = /^(\w{1,16}) issued server command: /.exec(msg))) return push({ type: 'act', name: m[1] });
            if ((m = /^(\w{1,16}) has (?:made the advancement|completed the challenge|reached the goal) \[(.+)\]$/.exec(msg))) return push({ type: 'adv', name: m[1], text: m[2] });
            if ((m = /^(\w{1,16}) (.+)$/.exec(msg)) && seen.has(m[1].toLowerCase()) && DEATH_RE.test(m[2])) return push({ type: 'death', name: m[1], text: msg });
        }
        function buildSessions() {
            const ev = CTX.logEvents.sort((a, b) => a.t - b.t || a.seq - b.seq);
            const P = new Map(), open = new Map(), versions = new Set();
            const get = (n) => { const k = n.toLowerCase(); if (!P.has(k)) P.set(k, { name: n, sessions: [], deaths: [], adv: [] }); return P.get(k); };
            const close = (k, t, why) => { const s = open.get(k); if (s) { s.end = t; s.why = why; open.delete(k); } };
            const seenAt = new Map();       // last log line from each player - where a session cut short by a crash ends
            let first = Infinity, last = 0;
            for (const e of ev) {
                first = Math.min(first, e.t); last = Math.max(last, e.t);
                if (e.type === 'start') { if (e.ver) versions.add(e.ver); for (const k of [...open.keys()]) close(k, seenAt.get(k) || open.get(k).start, 'server restarted - logout not logged'); continue; }
                if (e.type === 'stop') { for (const k of [...open.keys()]) close(k, e.t, 'server stopped'); continue; }
                const p = get(e.name), k = e.name.toLowerCase();
                seenAt.set(k, e.t);
                if (e.type === 'login' || (e.type === 'join' && !open.has(k))) {
                    if (e.type === 'login') close(k, e.t, 'logged in again');
                    const s = { start: e.t, end: null, world: e.world || null, pos: e.pos || null };
                    p.sessions.push(s);
                    open.set(k, s);
                } else if (e.type === 'leave') close(k, e.t, e.reason);
                else if (e.type === 'adv') p.adv.push([e.t, e.text]);
                else if (e.type === 'death') p.deaths.push([e.t, e.text]);
            }
            for (const s of open.values()) s.online = true;
            CTX.sess = { players: P, first, last, versions: [...versions] };
            CTX.logDirty = false;
        }

        async function loadEntries(entries) {
            const prev = players[active], prevKey = prev && prev.key;
            const before = new Set(players), ctxBefore = CTX.files.length, extrasBefore = new Set(players.flatMap((p) => [p.extras.stats, p.extras.advancements]).filter(Boolean));
            batch = { notes: [], errors: [] };
            // archives turn into more entries
            const queue = [];
            for (const e of entries) {
                const kind = e.kind || (e.loose ? kindOfLoose(e.path) : kindOf(e.path));
                if (kind === 'archive') {
                    progress(`Opening ${e.name}…`);
                    try { queue.push(...await openArchive(e)); } catch (err) { console.error(err); showError(`Could not open ${e.name}: ${err.message}`); }
                } else if (kind) queue.push({ ...e, kind });
            }
            const parsed = [];
            let n = 0;
            for (const e of queue) {
                if (queue.length > 20 && ++n % 10 === 0) progress(`Reading files… ${n} / ${queue.length}`);
                try {
                    if (e.kind === 'nbt') await loadNbt(e, parsed);
                    else await HANDLERS[e.kind](e);
                } catch (err) {
                    // a lone .txt that is Towny data rather than SNBT
                    if (e.kind === 'nbt' && /\.txt$/i.test(e.name)) {
                        try { if (/^(lastOnline|registered|mayor)=/m.test(await readText(e))) { await HANDLERS.towny(e); continue; } } catch (er) { }
                    }
                    console.error(e.path, err);
                    showError(`Could not read ${e.path}: ${err.message}`);
                }
            }
            // a few region files (a dropped entities/ folder, a small world) are scanned right away; big worlds wait for the button
            const pendingMca = CTX.regions.filter((r) => !CTX.regionDone.has(r.path));
            if (pendingMca.length && pendingMca.reduce((s, r) => s + mcaSize(r), 0) <= 96e6) await scanRegions(pendingMca);
            if (CTX.logDirty) buildSessions();
            progress(null);
            const ctxAdded = CTX.files.length - ctxBefore;
            // server data can change how items render (backpacks, maps, mod names) and who players are
            if (ctxAdded) players.forEach((p) => { p.P = analyse(p.P.d); lookupName(p); });
            // players first, so stats / advancements can find their owner
            parsed.filter((p) => p.kind === 'player').sort((a, b) => a.modified - b.modified).forEach((p) => addPlayer(p.name, p.data, p.modified));
            parsed.filter((p) => p.kind !== 'player').forEach((p) => attachExtra(p.kind, p.name, p.data, fileUuid(p.name)));
            if (prev) {
                // players were already open - stay on the one being viewed (it may have been swapped for a newer file)
                const i = players.findIndex((p) => p === prev || (prevKey && p.key === prevKey));
                if (i >= 0) active = i;
            } else if (parsed.filter((p) => p.kind === 'player').length > 1) active = sortedPlayers().list[0].i;
            const { notes, errors } = batch;
            batch = null;
            const added = players.filter((p) => !before.has(p));
            const extras = players.flatMap((p) => [p.extras.stats, p.extras.advancements]).filter((x) => x && !extrasBefore.has(x)).length;
            if (added.length > 1 || notes.length > 2 || ctxAdded) {
                const parts = [added.length && `${added.length} player${added.length === 1 ? '' : 's'}`, extras && `${extras} stats/advancement file${extras === 1 ? '' : 's'}`, ctxAdded && `${num(ctxAdded)} world/server file${ctxAdded === 1 ? '' : 's'}`].filter(Boolean);
                toast(parts.length ? 'Imported ' + parts.join(' · ') + (ctxAdded && players.length ? ' - see World & server' : '') : 'Nothing new was imported.', 'note');
            } else {
                if (added.length === 1) toast(`Imported ${added[0].file}`, 'note');
                notes.forEach((m) => toast(m, 'note'));
            }
            if (errors.length > 2) toast(`Could not read ${errors.length} files - see the browser console for details.`, 'err');
            else errors.forEach((m) => toast(m, 'err'));
            if (added.length) view = 'player';
            closeDrawer();
            render();
        }
        // files: [{file, path, loose}] - loose = picked on its own, so guess its kind from the name
        async function addFiles(files) {
            await loadEntries(files.map(({ file, path, loose }) => ({ name: file.name, path: path || file.name, loose, blob: file, modified: file.lastModified || Date.now(), read: async () => new Uint8Array(await file.arrayBuffer()) })));
            if (players.length || CTX.files.length) scrollTo({ top: $('#viewer').offsetTop - 10, behavior: 'smooth' });
        }

        // a dropped folder (world, server, .minecraft...): walk it and keep what ROUTES knows
        async function filesFromDrop(dt) {
            const entries = [...dt.items].map((i) => (i.webkitGetAsEntry ? i.webkitGetAsEntry() : null)).filter(Boolean);
            if (!entries.some((en) => en.isDirectory)) return [...dt.files].map((f) => ({ file: f, path: f.name, loose: true }));
            const out = [];
            const readDir = (dir) => new Promise((res) => {
                const reader = dir.createReader(), all = [];
                const next = () => reader.readEntries((ents) => { if (!ents.length) return res(all); all.push(...ents); next(); }, () => res(all));
                next();
            });
            const fileOf = (en) => new Promise((res) => en.file(res, () => res(null)));
            async function walk(en, depth) {
                if (en.isFile) {
                    const path = en.fullPath.replace(/^\//, '');
                    if (depth === 0 || kindOf(path)) { const f = await fileOf(en); if (f) out.push({ file: f, path, loose: depth === 0 }); }
                    return;
                }
                if (depth > 10 || (depth > 0 && SKIP_DIRS.test(en.name))) return;
                if (out.length && out.length % 200 === 0) progress(`Looking through the folder… ${out.length} files`);
                for (const c of await readDir(en)) await walk(c, depth + 1);
            }
            for (const en of entries) await walk(en, 0);
            progress(null);
            if (!out.length) showError('Nothing the viewer understands was found in that folder.');
            return out;
        }

        async function lookupName(pl) {
            const id = pl.P.uuid || ((pl.file.match(UUID_RE) || [])[0]);
            if (!id) return;
            const names = store.get('nbt.names', {});
            const set = (n) => { pl.name = n; renderTabs(); const h = $('#player .hero h1'); if (h && players[active] === pl) h.textContent = n; };
            // the server's own records (usercache, ops, logs...) know offline-mode names too
            const srv = CTX.names.get(uuidKey(id));
            if (srv) return set(srv);
            if (names[id]) return set(names[id]);
            // Bukkit/Paper servers store the name in the file - use it straight away, playerdb can still correct it
            const known = isObj(pl.P.d.bukkit) && pl.P.d.bukkit.lastKnownName;
            if (known) set(String(known));
            if (uuidKey(id)[12] === '3') return;        // offline-mode (version 3) uuid - Mojang has never heard of it
            nameQueue.push(async () => {
                try {
                    const r = await fetch('https://playerdb.co/api/player/minecraft/' + id);
                    const j = await r.json();
                    const n = j && j.data && j.data.player && j.data.player.username;
                    if (n) { names[id] = n; store.set('nbt.names', names); set(n); }
                } catch (e) { }
            });
            pumpNames();
        }

        /* =====================================================================
           Share links (stored server side by api.php, gzipped)
           ===================================================================== */
        const shareDlg = $('#shareDlg');
        async function gzip(str) {
            return new Uint8Array(await new Response(new Blob([str]).stream().pipeThrough(new CompressionStream('gzip'))).arrayBuffer());
        }
        let shareSeq = 0;
        async function createShare() {
            const pl = players[active];
            if (!pl) return;
            const all = players.length > 1 && $('input[name="shareScope"]:checked').value === 'all';
            const list = all ? players : [pl];
            const msg = $('#shareMsg'), input = $('#shareUrl'), seq = ++shareSeq;
            input.value = '';
            msg.textContent = 'Creating link…';
            try {
                const payload = { v: 1, players: list.map((p) => ({ file: p.file, name: p.name, data: p.P.d, extras: shareExtras(p) })) };
                if (all) payload.active = active;
                // the packs the items are drawn with travel with the link (ids only - the packs live on minecraftitems.xyz)
                const packs = rpUsed().map((p) => ({ id: p.id, name: p.name }));
                if (packs.length) payload.packs = packs;
                const r = await fetch('api.php?share=create', { method: 'POST', headers: { 'Content-Type': 'application/octet-stream' }, body: await gzip(JSON.stringify(payload)) });
                const j = await r.json();
                if (!r.ok || !j.id) throw new Error(j.error || 'Could not create the link.');
                if (seq !== shareSeq) return; // scope changed while this was uploading
                input.value = location.origin + location.pathname + '?s=' + j.id;
                input.select();
                const priv = CTX.files.length ? ' Private details (IP addresses, chat, commands, mail, passwords, seeds) are never included.' : '';
                if (all) msg.textContent = `Anyone with this link can view all ${list.length} players' inventories, statistics, advancements, server data and NBT data.${priv}`;
                else {
                    const what = [pl.extras.stats && 'statistics', pl.extras.advancements && 'advancements', payload.players[0].extras.ctx && 'server data'].filter(Boolean);
                    msg.textContent = `Anyone with this link can view ${pl.name || 'this player'}'s inventory${what.length ? ', ' + what.join(', ') : ''} and NBT data.${priv}`;
                }
                if (packs.length) msg.textContent += ` Items show with ${packs.length > 1 ? 'the resource packs' : 'the resource pack'} ${packs.map((p) => p.name).join(', ')}.`;
                history.replaceState(null, '', '?s=' + j.id);
            } catch (err) { if (seq === shareSeq) msg.textContent = 'Error: ' + err.message; }
        }
        // only linkFor().pub goes in - it never holds IPs, chat or other private data
        function shareExtras(p) {
            const x = {};
            if (p.extras.stats) x.stats = p.extras.stats;
            if (p.extras.advancements) x.advancements = p.extras.advancements;
            const pub = linkFor(p).pub;
            if (Object.keys(pub).length) x.ctx = pub;
            return x;
        }
        // maps + backpack contents from a share, so the shared inventory renders like the original
        function absorbShared(ctx) {
            if (!isObj(ctx)) return;
            if (isObj(ctx.maps)) for (const [id, m] of Object.entries(ctx.maps)) {
                if (!isObj(m) || typeof m.c !== 'string' || CTX.maps.has(id)) continue;
                try { const colors = unb64(m.c); if (colors.length === 16384) CTX.maps.set(id, { colors, scale: +m.scale || 0, dim: String(m.dim || 'minecraft:overworld'), x: +m.x || 0, z: +m.z || 0, locked: !!m.locked }); } catch (e) { }
            }
            if (isObj(ctx.backpacks)) for (const [u, items] of Object.entries(ctx.backpacks)) if (Array.isArray(items) && !CTX.backpacks.has(u)) CTX.backpacks.set(u, items);
        }
        $('#shareBtn').onclick = () => {
            const pl = players[active];
            if (!pl) return;
            const multi = players.length > 1;
            $('#shareScope').classList.toggle('hide', !multi);
            $('#shareTitle').textContent = multi ? 'Share players' : 'Share player';
            $('input[name="shareScope"][value="one"]').checked = true;
            $('#shareOneLbl').textContent = `Just ${pl.name || pl.file}`;
            $('#shareAllLbl').textContent = `All ${players.length} players`;
            shareDlg.showModal();
            createShare();
        };
        for (const r of document.querySelectorAll('input[name="shareScope"]')) r.onchange = createShare;
        $('#shareClose').onclick = () => shareDlg.close();
        $('#shareCopy').onclick = async (e) => {
            if (!$('#shareUrl').value) return;
            try { await navigator.clipboard.writeText($('#shareUrl').value); } catch (err) { $('#shareUrl').select(); document.execCommand('copy'); }
            e.target.textContent = 'Copied!';
            setTimeout(() => (e.target.textContent = 'Copy link'), 1200);
        };
        async function loadShare(id) {
            try {
                const r = await fetch('api.php?share=' + encodeURIComponent(id));
                if (!r.ok) { const j = await r.json().catch(() => ({})); throw new Error(j.error || 'HTTP ' + r.status); }
                // inflate() leaves it alone if the server already decoded it
                const j = JSON.parse(new TextDecoder().decode(await inflate(new Uint8Array(await r.arrayBuffer()))));
                (j.players || []).forEach((p) => absorbShared(p.extras && p.extras.ctx));
                useSharedPacks(j.packs);
                const added = (j.players || []).map((p) => {
                    const pl = addPlayer(p.file, p.data, 0, p.extras || {});
                    if (p.name && !pl.name) pl.name = p.name;
                    return pl;
                });
                // multi-player shares open on whichever player the sharer had selected
                if (added[j.active]) active = players.indexOf(added[j.active]);
                render();
            } catch (e) { showError('Could not open the share link: ' + e.message); }
        }

        /* =====================================================================
           Resource packs (several at once, in priority order)
           ===================================================================== */
        const dlg = $('#rpDlg');
        const PACK_ID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
        function useSharedPacks(list) {
            if (!Array.isArray(list)) return;
            const packs = list.filter((p) => isObj(p) && PACK_ID_RE.test(p.id)).slice(0, 5).map((p) => ({ id: String(p.id).toLowerCase(), name: String(p.name || 'Shared pack').slice(0, 60) }));
            if (!packs.length) return;
            rpShared = packs;
            refreshRp();
            $('#rpBtn').textContent = 'Resource packs (shared)';
        }
        // keep: the shared packs become your ticked packs, at the top of the list
        function endSharedPacks(keep) {
            const shared = rpShared;
            rpShared = null;
            $('#rpBtn').textContent = 'Resource packs';
            if (!keep) return savePacks(rpPacks());
            const mine = rpPacks(), ids = new Set(shared.map((p) => p.id));
            const rest = mine.filter((p) => !ids.has(p.id));
            rest.forEach((p) => (p.on = false));
            // packs you already had (site default packs included) keep their entry, just moved up and ticked
            savePacks([...shared.map((p) => { const had = mine.find((x) => x.id === p.id); return had ? { ...had, on: true } : { ...p, status: 'ready', on: true }; }), ...rest]);
        }
        function renderPacks() {
            if (rpShared) {
                $('#rpList').innerHTML = `<div class="rowi" style="flex-wrap:wrap"><div class="grow"><b style="font-weight:600">This share link uses:</b> ${rpShared.map((p, i) => `${i + 1}. ${esc(p.name)}`).join(', ')}</div>
                    <button class="btn small primary" id="rpKeep" title="Save these packs and use them from now on">Keep these packs</button>
                    <button class="btn small" id="rpMine" title="Go back to your own resource packs">Use my packs</button></div>`;
                return;
            }
            const packs = rpPacks();
            const on = packs.filter((p) => p.on && p.id).length;
            $('#rpList').innerHTML = packs.length
                ? packs.map((p, i) => `<div class="rowi"><input type="checkbox" data-rpon="${i}" ${p.on ? 'checked' : ''} ${p.id ? '' : 'disabled'} style="width:auto;margin:0" title="Use this pack">
                    <div class="grow"><b style="font-weight:600">${i + 1}. ${esc(p.name)}</b>${p.def ? ' <small class="muted">· site pack</small>' : ''}<br><small class="mono">${esc(p.id || '')}</small> <small>${esc(p.status || '')}</small></div>
                    <button class="btn small" data-rpmove="${i}:-1" ${i === 0 ? 'disabled' : ''} title="Higher priority">▲</button>
                    <button class="btn small" data-rpmove="${i}:1" ${i === packs.length - 1 ? 'disabled' : ''} title="Lower priority">▼</button>
                    ${p.def ? '' : `<button class="btn small" data-rpdel="${i}" title="Remove">✕</button>`}</div>`).join('') +
                  (on > 5 ? '<small class="muted">Only the top 5 ticked packs are used.</small>' : '')
                : '<div class="muted" style="font-size:13px">No packs yet - vanilla textures are used.</div>';
        }
        function savePacks(packs) {
            store.set('nbt.rp.packs', packs);
            const before = rpActive;
            refreshRp();
            if (before !== rpActive && active >= 0) render();
            renderPacks();
        }
        // merge the site packs into this device's list: new ones go at the bottom, switched off
        async function loadDefaultPacks() {
            let defs;
            try { defs = await (await fetch('api.php?rp=defaults')).json(); } catch (e) { return; }
            if (!Array.isArray(defs)) return;
            const packs = rpPacks().filter((p) => !p.def || defs.some((d) => d.file === p.def));
            for (const d of defs) {
                const p = packs.find((x) => x.def === d.file);
                if (p) {
                    if (p.id !== d.id) p.on = p.on && !!d.id;
                    Object.assign(p, { id: d.id, name: d.name, status: d.status });
                } else packs.push({ id: d.id, name: d.name, status: d.status, on: false, def: d.file });
            }
            savePacks(packs);
            packs.forEach((p) => p.def && p.id && /process|pending|queue|download/i.test(p.status) && pollPack(p.id));
        }
        async function rpStatus(id) {
            try {
                const r = await fetch('api.php?rp=status&id=' + encodeURIComponent(id));
                const j = await r.json();
                return String(j.status || (j.pack && j.pack.status) || (j.error ? 'error: ' + j.error : 'unknown'));
            } catch (e) { return 'unknown'; }
        }
        async function pollPack(id, tries = 0) {
            const st = await rpStatus(id);
            const packs = rpPacks();
            const p = packs.find((x) => x.id === id);
            if (p) { p.status = st; store.set('nbt.rp.packs', packs); }
            if (dlg.open) renderPacks();
            if (/process|pending|queue|download/i.test(st) && tries < 40) setTimeout(() => pollPack(id, tries + 1), 3000);
        }

        /* =====================================================================
           Events
           ===================================================================== */
        const fileInput = $('#file'), extraInput = $('#extraFile'), dirInput = $('#dirFile'), jarInput = $('#jarFile');
        $('#pickJar').onclick = () => jarInput.click();
        jarInput.onchange = () => { addFiles(loose(jarInput.files)); jarInput.value = ''; };
        const loose = (list) => [...list].map((f) => ({ file: f, path: f.name, loose: true }));
        $('#pick').onclick = () => fileInput.click();
        $('#pickDir').onclick = () => dirInput.click();
        $('#add').onclick = () => fileInput.click();
        fileInput.onchange = () => { addFiles(loose(fileInput.files)); fileInput.value = ''; };
        extraInput.onchange = () => { addFiles(loose(extraInput.files)); extraInput.value = ''; };
        dirInput.onchange = () => {
            const list = [...dirInput.files].map((f) => ({ file: f, path: f.webkitRelativePath || f.name, loose: false }))
                .filter((x) => !x.path.split('/').slice(1, -1).some((d) => SKIP_DIRS.test(d)) && kindOf(x.path));
            dirInput.value = '';
            if (list.length) addFiles(list); else showError('Nothing the viewer understands was found in that folder.');
        };
        $('#srvBtn').onclick = () => { view = view === 'server' ? 'player' : 'server'; closeDrawer(); render(); scrollTo({ top: $('#viewer').offsetTop - 10 }); };
        $('#bpBtn').onclick = () => { view = view === 'backpacks' ? (players.length ? 'player' : 'server') : 'backpacks'; closeDrawer(); render(); scrollTo({ top: $('#viewer').offsetTop - 10 }); };

        let dragDepth = 0;
        addEventListener('dragenter', (e) => { if (e.dataTransfer && [...e.dataTransfer.types].includes('Files')) { dragDepth++; $('#dragOverlay').classList.remove('hide'); } });
        addEventListener('dragleave', () => { if (--dragDepth <= 0) { dragDepth = 0; $('#dragOverlay').classList.add('hide'); } });
        addEventListener('dragover', (e) => e.preventDefault());
        addEventListener('drop', async (e) => {
            e.preventDefault();
            dragDepth = 0;
            $('#dragOverlay').classList.add('hide');
            const files = await filesFromDrop(e.dataTransfer);
            if (files.length) addFiles(files);
        });

        document.addEventListener('click', async (e) => {
            const t = e.target;
            const sv = t.closest('[data-srv]');
            if (sv) {
                if (sv.dataset.srv === 'dismiss') sv.closest('.srv-warn').remove();
                else srvSync(sv.dataset.srv === 'cached');
                return;
            }
            const ex = t.closest('[data-example]');
            if (ex) {
                ex.disabled = true;
                try {
                    // the player file plus its stats and advancements
                    const files = await Promise.all(ex.dataset.example.split('|').map(async (path) => {
                        const r = await fetch(path.split('/').map(encodeURIComponent).join('/'));
                        if (!r.ok) throw new Error(r.status);
                        const buf = new Uint8Array(await r.arrayBuffer());
                        return { name: path.split('/').pop(), path, loose: true, modified: Date.now(), read: async () => buf };
                    }));
                    await loadEntries(files);
                } catch (err) { showError('Could not load the example: ' + err.message); }
                ex.disabled = false;
                return;
            }
            if (pickOpen && !t.closest('#tabs')) togglePicker(false);
            if (t.closest('[data-pick]')) { if (players.length) togglePicker(); return; }
            const cl = t.closest('[data-close]');
            if (cl) {
                e.stopPropagation();
                const i = +cl.dataset.close;
                players.splice(i, 1);
                if (i < active || active >= players.length) active--;
                if (!players.length) active = -1, pickOpen = false;
                closeDrawer(); render();
                return;
            }
            const tab = t.closest('[data-tab]');
            if (tab) { active = +tab.dataset.tab; view = 'player'; togglePicker(false); closeDrawer(); render(); scrollTo({ top: $('#viewer').offsetTop - 10 }); return; }
            const stab = t.closest('[data-stab]');
            if (stab) {
                const card = stab.closest('.card');
                $$('[data-stab]', card).forEach((b) => b.classList.toggle('on', b === stab));
                $$('[data-spanel]', card).forEach((p) => p.classList.toggle('hide', p.dataset.spanel !== stab.dataset.stab));
                return;
            }
            const cp = t.closest('[data-copy]');
            if (cp) {
                try { await navigator.clipboard.writeText(cp.dataset.copy); } catch (err) { }
                const old = cp.textContent;
                if (cp.tagName === 'BUTTON') { cp.textContent = 'Copied!'; setTimeout(() => (cp.textContent = old), 1000); }
                else { cp.style.color = '#7ed957'; setTimeout(() => (cp.style.color = ''), 800); }
                return;
            }
            const own = t.closest('.own');
            if (own && !drawer.contains(own)) {
                const q = $('#q');
                q.value = query === own.dataset.own ? '' : own.dataset.own;
                query = q.value;
                applySearch();
                if (query) $('#s-inv').scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            const slot = t.closest('.slot.has');
            if (slot) { openItem(+slot.dataset.k, drawer.contains(slot)); return; }
            const crumb = t.closest('[data-crumb]');
            if (crumb) { const i = +crumb.dataset.crumb; const k = stack[i]; stack = stack.slice(0, i); openItem(k, true); return; }
            const more = t.closest('[data-more]');
            if (more) { const det = more.parentElement; more.remove(); det.insertAdjacentHTML('beforeend', det._more(+more.dataset.more)); return; }
            const act = t.closest('[data-act]');
            if (act) {
                const a = act.dataset.act;
                if (a === 'close') closeDrawer();
                if (a === 'closeall' && confirm(`Remove all ${players.length} players?`)) { players.length = 0; active = -1; pickOpen = false; closeDrawer(); render(); }
                if (a === 'extra') extraInput.click();
                if (a === 'sdir' || a === 'ssel') schemPick(a, act);
                if (a === 'bpsel') bpPick(act.dataset.b);
                if (a === 'scanmca') scanRegions().then((n) => { toast(n ? `Found ${num(n)} more backpack${n === 1 ? '' : 's'} in the world` : 'Scan finished', 'note'); render(); });
                if (a === 'clearctx' && confirm('Forget all world, server, plugin and mod files? Loaded players stay.')) {
                    TEX.forEach((u) => u.startsWith('blob:') && URL.revokeObjectURL(u));
                    TEX.clear(); TEXREF.clear(); LANG.clear();
                    Object.assign(CTX, freshCtx());
                    players.forEach((p) => (p.P = analyse(p.P.d)));
                    view = 'player';
                    closeDrawer(); render();
                }
                if (a === 'expand') {
                    const tree = $('#rawTree');
                    const open = act.textContent === 'Expand all';
                    // expand three levels deep - enough to see every item without freezing on huge lists
                    for (let lvl = 0; lvl < 3 && open; lvl++) $$('details', tree).forEach((dd) => { if (+dd.dataset.d === lvl) { fillNode(dd); dd.open = true; } });
                    if (!open) $$('details', tree).forEach((dd) => (dd.open = false));
                    act.textContent = open ? 'Collapse all' : 'Expand all';
                }
                if (a === 'json') {
                    const pl = players[active];
                    const blob = new Blob([JSON.stringify(pl.P.d, null, 2)], { type: 'application/json' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = pl.file.replace(/\.[^.]+$/, '') + '.json';
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
                }
                return;
            }
            if (drawer.classList.contains('open') && !drawer.contains(t) && !t.closest('.slot')) closeDrawer();
        });

        document.addEventListener('toggle', (e) => {
            const det = e.target;
            if (det.tagName !== 'DETAILS' || !det.open) return;
            if (det.dataset.n !== undefined) fillNode(det);
            if (det.dataset.blk !== undefined) fillBlock(det);
            if (det.dataset.chunk !== undefined) fillChunk(det);
            if (det.dataset.lazy === 'recipes') { const f = $('[data-fill]', det); if (!f.childElementCount) fillRecipes(f); }
        }, true);

        document.addEventListener('mouseover', (e) => {
            const el = e.target.closest('.slot.has, .own');
            if (!el) { if (tipK !== -1) hideTip(); return; }
            showTip(+el.dataset.k, e.clientX, e.clientY);
        });
        document.addEventListener('mousemove', (e) => {
            if (tipK === -1) return;
            const el = e.target.closest('.slot.has, .own');
            if (el) showTip(+el.dataset.k, e.clientX, e.clientY); else hideTip();
        }, { passive: true });
        addEventListener('scroll', hideTip, { passive: true });

        let qTimer;
        $('#q').addEventListener('input', (e) => { clearTimeout(qTimer); qTimer = setTimeout(() => { query = e.target.value; applySearch(); }, 80); });

        document.addEventListener('keydown', (e) => {
            const typing = /INPUT|TEXTAREA/.test(document.activeElement.tagName);
            if (e.key === '/' && !typing && players.length) { e.preventDefault(); $('#q').focus(); return; }
            if (e.key === 'Escape') {
                if (pickOpen) togglePicker(false);
                else if (drawer.classList.contains('open')) { if (stack.length > 1) { const k = stack[stack.length - 2]; stack = stack.slice(0, -2); openItem(k, true); } else closeDrawer(); }
                else if (typing && query) { $('#q').value = ''; query = ''; applySearch(); }
                return;
            }
            if (typing) return;
            if ((e.key === '[' || e.key === ']') && players.length > 1) {
                const order = sortedPlayers().list.map((r) => r.i), at = order.indexOf(active);
                active = order[(at + (e.key === ']' ? 1 : -1) + order.length) % order.length];
                view = 'player';
                closeDrawer(); render();
            }
            // arrow keys walk between slots while the drawer is open
            if (drawer.classList.contains('open') && stack.length === 1 && /^Arrow(Left|Right)$/.test(e.key)) {
                const all = $$('#player .slot.has');
                const cur = all.findIndex((s) => +s.dataset.k === stack[0]);
                const nxt = all[cur + (e.key === 'ArrowRight' ? 1 : -1)];
                if (nxt) { e.preventDefault(); openItem(+nxt.dataset.k); }
            }
        });

        // resource pack dialog
        $('#rpBtn').onclick = () => { renderPacks(); $('#rpMsg').textContent = ''; dlg.showModal(); if (!rpShared) rpPacks().forEach((p) => p.id && pollPack(p.id, 99)); };
        $('#rpClose').onclick = () => dlg.close();
        dlg.addEventListener('change', (e) => {
            if (e.target.dataset.rpon === undefined) return;
            const packs = rpPacks();
            packs[+e.target.dataset.rpon].on = e.target.checked;
            savePacks(packs);
        });
        dlg.addEventListener('click', (e) => {
            if (e.target.closest('#rpKeep, #rpMine')) { e.preventDefault(); return endSharedPacks(e.target.closest('#rpKeep') !== null); }
            const mv = e.target.closest('[data-rpmove]'), del = e.target.closest('[data-rpdel]');
            if (!mv && !del) return;
            e.preventDefault();
            const packs = rpPacks();
            if (mv) {
                const [i, d] = mv.dataset.rpmove.split(':').map(Number);
                [packs[i], packs[i + d]] = [packs[i + d], packs[i]];
            } else packs.splice(+del.dataset.rpdel, 1);
            savePacks(packs);
        });
        $('#rpAdd').onclick = async () => {
            const name = $('#rpName').value.trim(), url = $('#rpUrl').value.trim();
            const msg = $('#rpMsg');
            if (!name || !/^https:\/\//i.test(url)) { msg.textContent = 'Enter a name and a direct https:// link to the .zip.'; return; }
            msg.textContent = 'Uploading…';
            try {
                const r = await fetch('api.php?rp=upload', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name, url }) });
                const j = await r.json();
                const id = j.packId || j.id || j.resourcePackId || (j.pack && (j.pack.id || j.pack.packId));
                if (!r.ok || !id) throw new Error(j.error || j.message || 'Upload failed');
                if (rpShared) endSharedPacks(false);
                const packs = rpPacks();
                packs.unshift({ id, name, status: j.status || 'processing', on: false });
                store.set('nbt.rp.packs', packs);
                msg.textContent = 'Uploaded! The pack is being processed - tick it once it says ready.';
                $('#rpName').value = ''; $('#rpUrl').value = '';
                renderPacks();
                pollPack(id);
            } catch (err) { msg.textContent = 'Error: ' + err.message; }
        };

        const shareId = new URLSearchParams(location.search).get('s');
        if (shareId) loadShare(shareId);
        loadDefaultPacks();
    })();
    </script>

    <?php site_footer(); ?>
</body>

</html>
