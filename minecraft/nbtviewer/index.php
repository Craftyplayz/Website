<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NBT Viewer - Crafty Playz</title>
    <meta name="description" content="Drop Minecraft player .dat files and explore every bit of their NBT data visually.">
    <link rel="preconnect" href="https://crafthead.net">
    <link rel="preconnect" href="https://mc-heads.net">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>
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
    </style>

    <div class="nbt" id="app">
        <div id="errors"></div>

        <section class="drop" id="drop">
            <img class="big-ico" src="api.php?item=chest" alt="" onerror="this.src='/minecraft/itemlist/minecraft_chest.png'">
            <h1>NBT Viewer</h1>
            <p>Drop one or more Minecraft player files here to explore their inventory, ender chest, stats and every last bit of NBT data.</p>
            <p class="muted">Files are read in your browser and only leave it if you press Share. Accepts <span class="mono">.dat</span>, <span class="mono">.dat_old</span>, <span class="mono">.nbt</span>, <span class="mono">.json</span> and SNBT (<span class="mono">/data get</span> output).<br>
                Player files live in <span class="mono">world/playerdata/&lt;uuid&gt;.dat</span> (<span class="mono">world/players/data/</span> since 26.1) - you can also drop <span class="mono">stats/</span> and <span class="mono">advancements/</span> files, or a whole world folder.</p>
            <div class="row">
                <button class="btn primary" id="pick">Choose files</button>
                <button class="btn" data-example="22159541-fde8-41e6-a104-593e1cb3a456 larger example.json">Try an example</button>
            </div>
        </section>

        <div id="viewer" class="hide">
            <div class="bar">
                <div class="tabs" id="tabs"></div>
                <input class="search" id="q" type="search" placeholder="Search items, enchants, keys…  ( / )" autocomplete="off">
                <button class="btn small" id="add" title="Add more files">+ Add</button>
                <button class="btn small" id="rpBtn" title="Render items with custom resource packs">Resource packs</button>
                <button class="btn small primary" id="shareBtn" title="Create a link to this player">Share</button>
            </div>
            <div id="player"></div>
        </div>

        <input type="file" id="file" multiple accept=".dat,.dat_old,.nbt,.json,.snbt,.txt" hidden>
        <input type="file" id="extraFile" multiple accept=".json" hidden>
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
        <h2 style="margin-top:0">Share player</h2>
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
                if (c === '{') {
                    i++; const o = {}; ws();
                    if (s[i] === '}') { i++; return o; }
                    for (; ;) {
                        ws(); const k = s[i] === '"' || s[i] === "'" ? quoted() : bare(true);
                        ws(); if (s[i++] !== ':') fail();
                        o[k] = val(); ws();
                        const d = s[i++]; if (d === '}') return o; if (d !== ',') fail();
                    }
                }
                if (c === '[') {
                    i++; ws();
                    if (/[BIL]/.test(s[i]) && s[i + 1] === ';') i += 2;
                    const a = []; ws();
                    if (s[i] === ']') { i++; return a; }
                    for (; ;) { a.push(val()); ws(); const d = s[i++]; if (d === ']') return a; if (d !== ',') fail(); }
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
            const rc = RARITY_COLOR[it.rarity] || '#FFFFFF';
            const name = it.customName !== undefined ? textHTML(it.customName, { color: rc, italic: true }) : it.itemName !== undefined ? textHTML(it.itemName, { color: rc }) : spanFor(pretty(it.base), { color: rc });
            it.nameHTML = name;
            it.namePlain = plain(name);
            return it;
        }

        const RARITY_COLOR = { common: '#FFFFFF', uncommon: '#FFFF55', rare: '#55FFFF', epic: '#FF55FF' };

        /* =====================================================================
           Icons (minecraftitems.xyz via api.php, local textures, missing texture)
           ===================================================================== */
        const MISSING = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2" shape-rendering="crispEdges"><rect width="2" height="2" fill="#000"/><rect width="1" height="1" fill="#f0f"/><rect x="1" y="1" width="1" height="1" fill="#f0f"/></svg>');
        // resource packs: [{id, name, status, on}] in priority order (first = highest)
        function rpPacks() {
            const packs = store.get('nbt.rp.packs', []);
            const old = store.get('nbt.rp.active', null);      // single-pack setting from before multi-pack support
            if (typeof old === 'string') { packs.forEach((p) => (p.on = p.id === old)); store.set('nbt.rp.packs', packs); store.set('nbt.rp.active', null); }
            return packs;
        }
        let rpActive = '';
        const refreshRp = () => (rpActive = rpPacks().filter((p) => p.on).slice(0, 5).map((p) => p.id).join(','));
        refreshRp();
        // the API draws these as flat squares - the local textures are proper 3D icons
        const LOCAL_FIRST = /shulker_box$/;
        const localFirst = (id) => !rpActive && LOCAL_FIRST.test(id);
        function iconURL(it) {
            const id = typeof it === 'string' ? it : it.base;
            // skinned player heads: 3D isometric render with the hat layer (minecraftitems.xyz only draws Steve)
            if (typeof it !== 'string' && it.head) return `https://mc-heads.net/head/${encodeURIComponent(it.head)}/128`;
            if (localFirst(id)) return `/minecraft/itemlist/minecraft_${id}.png`;
            let u = 'api.php?item=' + encodeURIComponent(id);
            if (rpActive) {
                u += '&rp=' + rpActive;
                if (typeof it !== 'string' && it.cmd !== null && it.cmd !== undefined) u += '&cmd=' + it.cmd;
            }
            return u;
        }
        const imgTag = (it, lazy = true) => {
            const id = typeof it === 'string' ? it : it.base;
            return `<img src="${iconURL(it)}" data-id="${esc(id)}"${localFirst(id) ? ' data-f="1"' : ''} alt=""${lazy ? ' loading="lazy"' : ''} decoding="async">`;
        };
        // fallback chain: API -> local texture -> missing texture
        document.addEventListener('error', (e) => {
            const img = e.target;
            if (img.tagName !== 'IMG' || !img.dataset.id) return;
            const step = +(img.dataset.f || 0);
            img.dataset.f = step + 1;
            const next = step === 0 ? `/minecraft/itemlist/minecraft_${img.dataset.id}.png` : step === 1 ? MISSING : null;
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
        const phSlot = (k) => `<div class="slot"><span class="ph" style="background-image:url('/minecraft/itemlist/minecraft_${PH[k]}.png')"></span></div>`;

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
            const walk = (it, where) => {
                P.stats.items += it.count; P.stats.stacks++;
                if (it.ench.length) P.stats.enchanted++;
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

        /* =====================================================================
           Rendering
           ===================================================================== */
        const HANDLED = new Set(['Inventory', 'EnderItems', 'equipment', 'SelectedItemSlot', 'Health', 'foodLevel', 'foodSaturationLevel', 'foodExhaustionLevel', 'foodTickTimer', 'XpLevel', 'XpP', 'XpTotal', 'XpSeed', 'Score', 'Air', 'AbsorptionAmount', 'active_effects', 'ActiveEffects', 'attributes', 'Attributes', 'abilities', 'playerGameType', 'previousPlayerGameType', 'Pos', 'Rotation', 'Motion', 'Dimension', 'respawn', 'SpawnX', 'SpawnY', 'SpawnZ', 'SpawnDimension', 'SpawnForced', 'SpawnAngle', 'LastDeathLocation', 'ender_pearls', 'recipeBook', 'UUID', 'DataVersion', 'Fire', 'OnGround', 'FallFlying', 'fall_distance', 'FallDistance', 'Invulnerable', 'PortalCooldown', 'HurtTime', 'HurtByTimestamp', 'DeathTime', 'SleepTimer', 'seenCredits', 'warden_spawn_tracker', 'enteredNetherPosition']);

        function render() {
            REG = []; NODES = [];
            $('#drop').classList.toggle('hide', players.length > 0);
            $('#viewer').classList.toggle('hide', players.length === 0);
            renderTabs();
            if (active < 0) { $('#player').innerHTML = ''; return; }
            disposeSkins();
            const pl = players[active];
            const P = pl.P, d = P.d;
            const sections = [];

            sections.push(heroHTML(pl));
            sections.push(`<div class="section card" id="s-inv"><h2>${imgTag('chest', false)} Inventory <span class="n">${P.stats.stacks} stacks · ${num(P.stats.items)} items</span></h2><div class="guis">${invHTML(P)}${enderHTML(P)}</div></div>`);
            if (pl.extras.stats) sections.push(statsHTML(pl.extras.stats));
            if (pl.extras.advancements) sections.push(advHTML(pl.extras.advancements));
            if (P.owned.size) sections.push(ownedHTML(P));

            const cards = [];
            if (P.points.length) cards.push(locHTML(P));
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

            const jumps = [['s-inv', 'Inventory'], ['s-stats', 'Statistics'], ['s-adv', 'Advancements'], ['s-owned', 'All items'], ['s-loc', 'Locations'], ['s-eff', 'Effects'], ['s-attr', 'Attributes'], ['s-abil', 'Abilities'], ['s-vit', 'Vitals'], ['s-pearl', 'Pearls'], ['s-warden', 'Warden'], ['s-other', 'Other data'], ['s-recipe', 'Recipes'], ['s-raw', 'Raw NBT']];
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
            if (pl) $('.pick-btn', box).innerHTML = `<img src="${avatarOf(pl)}" alt="" onerror="this.src='${MISSING}'"><span class="nm">${esc(labelOf(pl))}</span>` +
                (players.length > 1 ? `<span class="n">${players.length} players</span>` : '') + '<span class="car">▾</span>';
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

        function heroHTML(pl) {
            const P = pl.P, d = P.d;
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
            const color = { player: '#7ed957', spawn: '#5aa8ff', death: '#ff5555', pearl: '#3fd6a8', lodestone: '#ffd84a', misc: '#bbbbbb' };
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
                const label = pretty(id);
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
            const facts = [['Location', esc(where)], ['ID', `<span class="mono">${esc(it.full)}</span>`], ['Count', it.count]];
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
                $$('.chip, .sti, .adv', root).forEach((el) => el.classList.toggle('hit', el.dataset.s.includes(q.replace(/ /g, '_')) || el.textContent.toLowerCase().includes(q)));
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
                    const p = Array.isArray(v) ? `${path}[${k}]` : path ? `${path}.${k}` : String(k);
                    if (x !== null && typeof x === 'object') {
                        if (re.test(String(k))) out.push([p, Array.isArray(x) ? `[${x.length}]` : `{${Object.keys(x).length}}`]);
                        walk(x, p);
                    } else if (re.test(String(k)) || re.test(String(x))) out.push([p, JSON.stringify(x)]);
                }
            })(players[active].P.d, '');
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
            if (isObj(data) && isObj(data.Data) && !data.Inventory) data = data.Data;          // level.dat
            if (isObj(data) && isObj(data['']) && Object.keys(data).length === 1) data = data[''];
            return data;
        }
        // player NBT, world/stats/<uuid>.json or world/advancements/<uuid>.json?
        function classify(data) {
            if (isObj(data.stats) || Object.keys(data).some((k) => /^stat\./.test(k))) return 'stats';
            const vals = Object.entries(data).filter(([k]) => k !== 'DataVersion');
            if (vals.length && vals.every(([k, v]) => k.includes(':') && isObj(v) && ('criteria' in v || 'done' in v))) return 'advancements';
            return 'player';
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

        async function loadEntries(entries) {
            const prev = players[active], prevKey = prev && prev.key;
            const before = new Set(players);
            batch = { notes: [], errors: [] };
            const parsed = [];
            for (const f of entries) {
                try {
                    const data = unwrap(await parseFile(f.name, f.buf));
                    if (!isObj(data)) throw new Error('The file did not contain an NBT compound.');
                    parsed.push({ ...f, data, kind: classify(data) });
                } catch (e) {
                    console.error(e);
                    showError(`Could not read ${f.name}: ${e.message}`);
                }
            }
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
            const extras = parsed.length - parsed.filter((p) => p.kind === 'player').length;
            if (added.length > 1 || notes.length > 2) {
                toast(`${added.length} player${added.length === 1 ? '' : 's'} imported` + (extras ? ` · ${extras} stats/advancement file${extras === 1 ? '' : 's'}` : ''), 'note');
            } else {
                if (added.length === 1) toast(`Imported ${added[0].file}`, 'note');
                notes.forEach((m) => toast(m, 'note'));
            }
            if (errors.length > 2) toast(`Could not read ${errors.length} files - see the browser console for details.`, 'err');
            else errors.forEach((m) => toast(m, 'err'));
            closeDrawer();
            render();
        }
        async function addFiles(files) {
            await loadEntries(await Promise.all(files.map(async (f) => ({ name: f.name, buf: await f.arrayBuffer(), modified: f.lastModified || Date.now() }))));
            if (players.length) scrollTo({ top: $('#viewer').offsetTop - 10, behavior: 'smooth' });
        }

        // a dropped world folder: pick up playerdata/, stats/ and advancements/ (skip region files etc.)
        async function filesFromDrop(dt) {
            const entries = [...dt.items].map((i) => (i.webkitGetAsEntry ? i.webkitGetAsEntry() : null)).filter(Boolean);
            if (!entries.some((en) => en.isDirectory)) return [...dt.files];
            const out = [];
            const readDir = (dir) => new Promise((res) => {
                const reader = dir.createReader(), all = [];
                const next = () => reader.readEntries((ents) => { if (!ents.length) return res(all); all.push(...ents); next(); }, () => res(all));
                next();
            });
            const fileOf = (en) => new Promise((res) => en.file(res, () => res(null)));
            async function walk(en, depth) {
                if (en.isFile) {
                    if (/\.(dat|dat_old|nbt|json|snbt)$/i.test(en.name) && (depth === 0 || /\/(playerdata|players\/data|stats|advancements)\/[^/]+$/i.test(en.fullPath))) {
                        const f = await fileOf(en);
                        if (f) out.push(f);
                    }
                    return;
                }
                // 26.1+ worlds keep players in players/{data,stats,advancements}/ - only skip the world-level data/
                if (depth > 4 || (depth > 0 && /^(region|entities|poi|DIM-1|DIM1|dimensions|data|datapacks|backups|logs)$/i.test(en.name) && !/\/players\/data$/i.test(en.fullPath))) return;
                for (const c of await readDir(en)) await walk(c, depth + 1);
            }
            for (const en of entries) await walk(en, 0);
            if (!out.length) showError('No player, stats or advancement files found in that folder.');
            return out;
        }

        async function lookupName(pl) {
            const id = pl.P.uuid || ((pl.file.match(UUID_RE) || [])[0]);
            if (!id) return;
            const names = store.get('nbt.names', {});
            const set = (n) => { pl.name = n; renderTabs(); const h = $('#player .hero h1'); if (h && players[active] === pl) h.textContent = n; };
            if (names[id]) return set(names[id]);
            // Bukkit/Paper servers store the name in the file - use it straight away, playerdb can still correct it
            const known = isObj(pl.P.d.bukkit) && pl.P.d.bukkit.lastKnownName;
            if (known) set(String(known));
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
        $('#shareBtn').onclick = async () => {
            const pl = players[active];
            if (!pl) return;
            const msg = $('#shareMsg'), input = $('#shareUrl');
            input.value = '';
            msg.textContent = 'Creating link…';
            shareDlg.showModal();
            try {
                const payload = { v: 1, players: [{ file: pl.file, name: pl.name, data: pl.P.d, extras: pl.extras }] };
                const r = await fetch('api.php?share=create', { method: 'POST', headers: { 'Content-Type': 'application/octet-stream' }, body: await gzip(JSON.stringify(payload)) });
                const j = await r.json();
                if (!r.ok || !j.id) throw new Error(j.error || 'Could not create the link.');
                input.value = location.origin + location.pathname + '?s=' + j.id;
                input.select();
                const what = [pl.extras.stats && 'statistics', pl.extras.advancements && 'advancements'].filter(Boolean);
                msg.textContent = `Anyone with this link can view ${pl.name || 'this player'}'s inventory${what.length ? ', ' + what.join(', ') : ''} and NBT data.`;
                history.replaceState(null, '', '?s=' + j.id);
            } catch (err) { msg.textContent = 'Error: ' + err.message; }
        };
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
                for (const p of j.players || []) {
                    const pl = addPlayer(p.file, p.data, 0, p.extras || {});
                    if (p.name && !pl.name) pl.name = p.name;
                }
                render();
            } catch (e) { showError('Could not open the share link: ' + e.message); }
        }

        /* =====================================================================
           Resource packs (several at once, in priority order)
           ===================================================================== */
        const dlg = $('#rpDlg');
        function renderPacks() {
            const packs = rpPacks();
            const on = packs.filter((p) => p.on).length;
            $('#rpList').innerHTML = packs.length
                ? packs.map((p, i) => `<div class="rowi"><input type="checkbox" data-rpon="${i}" ${p.on ? 'checked' : ''} style="width:auto;margin:0" title="Use this pack">
                    <div class="grow"><b style="font-weight:600">${i + 1}. ${esc(p.name)}</b><br><small class="mono">${esc(p.id)}</small> <small>${esc(p.status || '')}</small></div>
                    <button class="btn small" data-rpmove="${i}:-1" ${i === 0 ? 'disabled' : ''} title="Higher priority">▲</button>
                    <button class="btn small" data-rpmove="${i}:1" ${i === packs.length - 1 ? 'disabled' : ''} title="Lower priority">▼</button>
                    <button class="btn small" data-rpdel="${i}" title="Remove">✕</button></div>`).join('') +
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
        const fileInput = $('#file'), extraInput = $('#extraFile');
        $('#pick').onclick = () => fileInput.click();
        $('#add').onclick = () => fileInput.click();
        fileInput.onchange = () => { addFiles([...fileInput.files]); fileInput.value = ''; };
        extraInput.onchange = () => { addFiles([...extraInput.files]); extraInput.value = ''; };

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
            const ex = t.closest('[data-example]');
            if (ex) {
                ex.disabled = true;
                try {
                    const r = await fetch(encodeURIComponent(ex.dataset.example));
                    if (!r.ok) throw new Error(r.status);
                    await loadEntries([{ name: ex.dataset.example, buf: await r.arrayBuffer(), modified: Date.now() }]);
                } catch (err) { showError('Could not load the example: ' + err.message); }
                ex.disabled = false;
                return;
            }
            if (pickOpen && !t.closest('#tabs')) togglePicker(false);
            if (t.closest('[data-pick]')) { togglePicker(); return; }
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
            if (tab) { active = +tab.dataset.tab; togglePicker(false); closeDrawer(); render(); scrollTo({ top: $('#viewer').offsetTop - 10 }); return; }
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
        $('#rpBtn').onclick = () => { renderPacks(); $('#rpMsg').textContent = ''; dlg.showModal(); rpPacks().forEach((p) => pollPack(p.id, 99)); };
        $('#rpClose').onclick = () => dlg.close();
        dlg.addEventListener('change', (e) => {
            if (e.target.dataset.rpon === undefined) return;
            const packs = rpPacks();
            packs[+e.target.dataset.rpon].on = e.target.checked;
            savePacks(packs);
        });
        dlg.addEventListener('click', (e) => {
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
    })();
    </script>

    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php'; ?>
</body>

</html>
