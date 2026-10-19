<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/tools-device-check.css']];
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
        <h1 class="page-title">Device Check</h1>
        <p class="page-lede">Test your keyboard, mouse, mic, speakers, camera, screen and controller. Everything runs in your browser; nothing is recorded or uploaded.</p>

        <nav class="stabs dc-tabs" id="dcTabs" role="tablist" aria-label="Test">
            <button type="button" class="on" data-tab="keyboard">Keyboard</button>
            <button type="button" data-tab="mouse">Mouse</button>
            <button type="button" data-tab="cps">Click speed</button>
            <button type="button" data-tab="mic">Microphone</button>
            <button type="button" data-tab="speakers">Speakers</button>
            <button type="button" data-tab="webcam">Webcam</button>
            <button type="button" data-tab="touch">Touchscreen</button>
            <button type="button" data-tab="screen">Dead pixels</button>
            <button type="button" data-tab="gamepad">Controller</button>
        </nav>

        <section class="card" data-panel="keyboard">
            <h2>Keyboard <span class="n" id="kbCount"></span></h2>
            <p class="muted dc-help">Press every key. Keys turn green once they've worked. Hold several at once to check how many your keyboard can register together.</p>
            <div class="dc-kb" id="kb"></div>
            <div class="dc-row">
                <div class="kv" id="kbInfo"><span>Last key</span><span>—</span></div>
                <button type="button" class="btn small" id="kbReset">Reset</button>
            </div>
        </section>

        <section class="card" data-panel="mouse" hidden>
            <h2>Mouse</h2>
            <p class="muted dc-help">Click every button and scroll inside the box. Side buttons won't navigate away here.</p>
            <div class="dc-area dc-mouse" id="mouseArea" tabindex="0" aria-label="Mouse test area">
                <div class="tiles" id="mouseTiles"></div>
            </div>
            <p class="muted dc-help" id="mouseFault">Double-click faults: 0. If this goes up when you click once, the switch is wearing out.</p>
        </section>

        <section class="card" data-panel="cps" hidden>
            <h2>Click speed</h2>
            <div class="stabs" id="cpsTime" aria-label="Duration">
                <button type="button" data-s="1">1 s</button>
                <button type="button" class="on" data-s="5">5 s</button>
                <button type="button" data-s="10">10 s</button>
                <button type="button" data-s="30">30 s</button>
            </div>
            <button type="button" class="dc-area dc-cps" id="cpsArea">Click here to start</button>
            <div class="tiles dc-gap" id="cpsStats"></div>
        </section>

        <section class="card" data-panel="mic" hidden>
            <h2>Microphone</h2>
            <p class="muted dc-help">Talk and watch the bar move. Record a few seconds to hear how you sound.</p>
            <div class="dc-row">
                <button type="button" class="btn primary" id="micGo">Start microphone</button>
                <select class="field" id="micDev" aria-label="Microphone" hidden></select>
                <button type="button" class="btn" id="micRec" hidden>Record 5 seconds</button>
            </div>
            <div class="dc-meter" id="micMeter" hidden><div class="progress"><i id="micBar"></i></div><span class="mono muted" id="micDb"></span></div>
            <audio id="micPlay" controls hidden></audio>
            <div class="kv dc-gap" id="micInfo"></div>
            <p class="err" id="micErr" hidden></p>
        </section>

        <section class="card" data-panel="speakers" hidden>
            <h2>Speakers</h2>
            <p class="muted dc-help">Each button plays a short tone. Left should only come from the left speaker.</p>
            <div class="dc-row">
                <button type="button" class="btn" data-pan="-1">Left</button>
                <button type="button" class="btn primary" data-pan="0">Both</button>
                <button type="button" class="btn" data-pan="1">Right</button>
                <button type="button" class="btn" data-sweep>Bass to treble sweep</button>
            </div>
        </section>

        <section class="card" data-panel="webcam" hidden>
            <h2>Webcam</h2>
            <div class="dc-row">
                <button type="button" class="btn primary" id="camGo">Start camera</button>
                <select class="field" id="camDev" aria-label="Camera" hidden></select>
            </div>
            <video class="dc-video" id="camVideo" autoplay playsinline muted hidden></video>
            <div class="kv dc-gap" id="camInfo"></div>
            <p class="err" id="camErr" hidden></p>
        </section>

        <section class="card" data-panel="touch" hidden>
            <h2>Touchscreen <span class="n" id="touchCount"></span></h2>
            <p class="muted dc-help">Draw with one or more fingers. Gaps in a line show dead spots.</p>
            <canvas class="dc-area dc-touch" id="touchCanvas" aria-label="Touch drawing area"></canvas>
            <div class="dc-row dc-gap"><button type="button" class="btn small" id="touchClear">Clear</button></div>
        </section>

        <section class="card" data-panel="screen" hidden>
            <h2>Dead pixels</h2>
            <p class="muted dc-help">Fills the screen with one colour. Look for dots that don't match. Click or press Space for the next colour, Esc to stop.</p>
            <div class="dc-row">
                <button type="button" class="btn primary" id="scrGo">Start full screen</button>
            </div>
            <div class="kv dc-gap" id="scrInfo"></div>
            <div class="dc-full" id="scrFull" hidden><span id="scrHint">Click for the next colour · Esc to stop</span></div>
        </section>

        <section class="card" data-panel="gamepad" hidden>
            <h2>Controller</h2>
            <p class="note" id="padWait">Plug in or pair a controller, then press any button on it.</p>
            <div id="pads"></div>
        </section>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= asset('/tools/device-check/device-check.js') ?>" defer></script>
</body>

</html>
