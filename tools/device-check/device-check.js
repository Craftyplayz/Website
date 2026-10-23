// Device Check (/tools/device-check/). One panel per test; show()/hide() start and stop mics, cameras and loops,
// so nothing keeps running on a tab you can't see.
document.addEventListener('DOMContentLoaded', () => {
    const { $, $$, esc, store } = UI;
    const tests = {};
    let current = null;

    function open(id) {
        if (!tests[id]) id = 'keyboard';
        if (id === current) return;
        if (current) tests[current].hide?.();
        current = id;
        for (const b of $$('#dcTabs [data-tab]')) { const on = b.dataset.tab === id; b.classList.toggle('on', on); b.setAttribute('aria-selected', on); }
        for (const p of $$('[data-panel]')) p.hidden = p.dataset.panel !== id;
        tests[id].show?.();
        history.replaceState(null, '', '#' + id);
    }
    $('#dcTabs').addEventListener('click', e => { const b = e.target.closest('[data-tab]'); if (b) open(b.dataset.tab); });

    const kv = rows => rows.filter(r => r[1] !== '' && r[1] != null).map(([k, v]) => `<span>${esc(k)}</span><span>${esc(String(v))}</span>`).join('');

    // ---------- keyboard ----------
    {
        const L = s => [...s].map(c => 'Key' + c).join(' ');
        const D = '1234567890'.split('').map(d => 'Digit' + d).join(' ');
        // "code:label:width", "_width" is a gap, label "~" is blank (second half of a tall key)
        const BLOCKS = [
            ['Escape:Esc _1 F1 F2 F3 F4 _.5 F5 F6 F7 F8 _.5 F9 F10 F11 F12',
                `Backquote:\` ${D} Minus:- Equal:= Backspace:⌫:2`,
                `Tab::1.5 ${L('QWERTYUIOP')} BracketLeft:[ BracketRight:] Enter:↵:1.5`,
                `CapsLock:Caps:1.75 ${L('ASDFGHJKL')} Semicolon:; Quote:' Backslash:# Enter:~:1.25`,
                `ShiftLeft:Shift:1.25 IntlBackslash:\\ ${L('ZXCVBNM')} Comma:, Period:. Slash:/ ShiftRight:Shift:2.75`,
                'ControlLeft:Ctrl:1.25 MetaLeft:Win:1.25 AltLeft:Alt:1.25 Space::6.25 AltRight:AltGr:1.25 MetaRight:Win:1.25 ContextMenu:Menu:1.25 ControlRight:Ctrl:1.25'],
            ['PrintScreen:PrtSc ScrollLock:ScrLk Pause', 'Insert:Ins Home PageUp:PgUp', 'Delete:Del End PageDown:PgDn', '_3',
                '_1 ArrowUp:↑ _1', 'ArrowLeft:← ArrowDown:↓ ArrowRight:→'],
            ['_4', 'NumLock:Num NumpadDivide:/ NumpadMultiply:* NumpadSubtract:-', 'Numpad7 Numpad8 Numpad9 NumpadAdd:+',
                'Numpad4 Numpad5 Numpad6 NumpadAdd:~', 'Numpad1 Numpad2 Numpad3 NumpadEnter:Ent', 'Numpad0::2 NumpadDecimal:. NumpadEnter:~'],
        ];
        const key = t => {
            if (t[0] === '_') return `<span class="dc-sp" style="--w:${t.slice(1)}"></span>`;
            const [code, label, w = 1] = t.split(':');
            const text = label === '~' ? '' : label || code.replace(/^(Key|Digit|Numpad)/, '');
            return `<span class="dc-key" data-code="${code}" style="--w:${w}">${esc(text)}</span>`;
        };
        $('#kb').innerHTML = '<div class="dc-kbi">' + BLOCKS.map(rows => '<div class="dc-block">'
            + rows.map(r => '<div class="dc-krow">' + r.split(' ').map(key).join('') + '</div>').join('') + '</div>').join('') + '</div>';
        const all = new Set($$('#kb [data-code]').map(k => k.dataset.code));

        // show the visitor's real layout where the browser allows it (Chrome)
        navigator.keyboard?.getLayoutMap?.().then(map => map.forEach((k, code) => {
            for (const el of $$(`#kb [data-code="${code}"]`)) if (el.textContent.length === 1) el.textContent = k.toUpperCase();
        })).catch(() => {});

        const hit = new Set(), held = new Set();
        let most = 0, last = null;
        const draw = () => {
            $('#kbCount').textContent = `${hit.size} of ${all.size} tested`;
            $('#kbInfo').innerHTML = kv([['Last key', last ? (last.key === ' ' ? 'Space' : last.key) : '—'], ['Code', last?.code], ['Held now', held.size], ['Most at once', most || '']]);
        };
        const mark = (code, down) => { for (const el of $$(`#kb [data-code="${code}"]`)) { el.classList.toggle('down', down); el.classList.add('hit'); } };
        addEventListener('keydown', e => {
            if (current !== 'keyboard' || e.target.closest?.('input, select, textarea')) return;
            // keep browser shortcuts (Ctrl+R, Ctrl+W...) working; swallow everything else, including the site's "/" search
            if (!e.ctrlKey && !e.metaKey) e.preventDefault();
            e.stopPropagation();
            last = e; held.add(e.code); hit.add(e.code); most = Math.max(most, held.size);
            mark(e.code, true); draw();
        }, true);
        addEventListener('keyup', e => {
            if (current !== 'keyboard') return;
            held.delete(e.code); hit.add(e.code); // PrintScreen only sends keyup on Windows
            mark(e.code, false); draw();
        }, true);
        addEventListener('blur', () => { held.clear(); for (const el of $$('#kb .down')) el.classList.remove('down'); });
        $('#kbReset').addEventListener('click', e => {
            hit.clear(); held.clear(); most = 0; last = null;
            for (const el of $$('#kb .dc-key')) el.classList.remove('hit', 'down');
            draw(); e.currentTarget.blur();
        });
        tests.keyboard = { show: draw };
    }

    // ---------- mouse ----------
    {
        const NAMES = ['Left', 'Middle', 'Right', 'Back', 'Forward'];
        const area = $('#mouseArea'), counts = [0, 0, 0, 0, 0, 0, 0], lastDown = [];
        let faults = 0;
        $('#mouseTiles').innerHTML = [...NAMES, 'Scroll up', 'Scroll down'].map((n, i) => `<div class="tile" data-b="${i}"><b>0</b><span>${n}</span></div>`).join('');
        const tile = i => $(`#mouseTiles [data-b="${i}"]`);
        const bump = i => { counts[i]++; tile(i).firstChild.textContent = counts[i]; };
        area.addEventListener('mousedown', e => {
            e.preventDefault(); // no middle-click autoscroll
            if (e.button > 4) return;
            const now = performance.now();
            // a worn switch "bounces": two downs far quicker than anyone can click
            if (now - (lastDown[e.button] || -1e9) < 50) $('#mouseFault').textContent = `Double-click faults: ${++faults}. If this goes up when you click once, the switch is wearing out.`;
            lastDown[e.button] = now;
            bump(e.button); tile(e.button).classList.add('on');
        });
        area.addEventListener('mouseup', e => { e.preventDefault(); tile(e.button)?.classList.remove('on'); });
        area.addEventListener('mouseleave', () => { for (const t of $$('#mouseTiles .on')) t.classList.remove('on'); });
        area.addEventListener('contextmenu', e => e.preventDefault());
        area.addEventListener('auxclick', e => e.preventDefault());
        let wheelOff;
        area.addEventListener('wheel', e => {
            e.preventDefault();
            if (!e.deltaY) return;
            const i = e.deltaY < 0 ? 5 : 6;
            bump(i); tile(i).classList.add('on');
            clearTimeout(wheelOff); wheelOff = setTimeout(() => { tile(5).classList.remove('on'); tile(6).classList.remove('on'); }, 150);
        }, { passive: false });
        tests.mouse = {};
    }

    // ---------- click speed ----------
    {
        const area = $('#cpsArea');
        let secs = 5, clicks = 0, t0 = 0, raf = 0;
        const best = () => store.get('dc-cps-' + secs, 0);
        const stats = (cps, left) => {
            $('#cpsStats').innerHTML = [[cps.toFixed(1), 'clicks per second'], [clicks, 'clicks'], [left.toFixed(1) + ' s', 'left'], [best().toFixed(1), `best for ${secs} s`]]
                .map(([b, s]) => `<div class="tile"><b>${b}</b><span>${s}</span></div>`).join('');
        };
        const tick = () => {
            const t = (performance.now() - t0) / 1000, left = Math.max(0, secs - t);
            stats(clicks / Math.max(t, 0.001), left);
            if (left > 0) { raf = requestAnimationFrame(tick); return; }
            const cps = clicks / secs;
            if (cps > best()) store.set('dc-cps-' + secs, cps);
            stats(cps, 0);
            t0 = 0; area.textContent = `${cps.toFixed(1)} clicks per second. Click to go again.`;
            area.disabled = true; setTimeout(() => (area.disabled = false), 1000); // stops the last click starting a new round
        };
        const reset = () => { cancelAnimationFrame(raf); t0 = 0; clicks = 0; area.textContent = 'Click here to start'; stats(0, secs); };
        area.addEventListener('pointerdown', e => {
            e.preventDefault();
            if (area.disabled) return;
            if (!t0) { clicks = 0; t0 = performance.now(); area.textContent = 'Keep clicking!'; raf = requestAnimationFrame(tick); }
            clicks++;
        });
        area.addEventListener('contextmenu', e => e.preventDefault());
        $('#cpsTime').addEventListener('click', e => {
            const b = e.target.closest('[data-s]');
            if (!b) return;
            secs = +b.dataset.s;
            for (const x of $$('#cpsTime button')) x.classList.toggle('on', x === b);
            reset();
        });
        tests.cps = { show: reset, hide: reset };
    }

    // ---------- shared: media devices ----------
    function mediaError(box, e, what) {
        box.hidden = false;
        box.textContent = e.name === 'NotAllowedError' ? `The ${what} is blocked. Allow it from the icon in the address bar, then try again.`
            : e.name === 'NotFoundError' ? `No ${what} found.`
            : e.name === 'NotReadableError' ? `The ${what} is busy. Close other apps using it, then try again.`
            : !navigator.mediaDevices ? `This browser can't use the ${what} here.` : `Couldn't start the ${what} (${e.name || e}).`;
    }
    async function fillDevices(sel, kind, track) {
        const list = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === kind);
        const id = track.getSettings().deviceId;
        sel.innerHTML = list.map((d, i) => `<option value="${esc(d.deviceId)}">${esc(d.label || `Device ${i + 1}`)}</option>`).join('');
        sel.value = id;
        sel.hidden = list.length < 2;
    }

    // ---------- microphone ----------
    {
        const go = $('#micGo'), sel = $('#micDev'), rec = $('#micRec'), err = $('#micErr');
        let stream = null, ctx = null, raf = 0, recorder = null, gen = 0; // gen drops a start that finishes after stop()
        function stop() {
            cancelAnimationFrame(raf);
            recorder?.state === 'recording' && recorder.stop();
            stream?.getTracks().forEach(t => t.stop());
            ctx?.close();
            stream = ctx = null; gen++;
            go.textContent = 'Start microphone'; go.classList.add('primary');
            rec.hidden = true; $('#micMeter').hidden = true;
        }
        async function start(deviceId) {
            stop(); err.hidden = true;
            const my = ++gen;
            let got;
            try { got = await navigator.mediaDevices.getUserMedia({ audio: deviceId ? { deviceId: { exact: deviceId } } : true }); }
            catch (e) { if (my === gen) mediaError(err, e, 'microphone'); return; }
            if (my !== gen) { got.getTracks().forEach(t => t.stop()); return; }
            stream = got;
            const track = stream.getAudioTracks()[0], s = track.getSettings();
            fillDevices(sel, 'audioinput', track);
            $('#micInfo').innerHTML = kv([['Device', track.label], ['Sample rate', s.sampleRate ? s.sampleRate + ' Hz' : ''], ['Channels', s.channelCount]]);
            ctx = new AudioContext();
            const an = ctx.createAnalyser();
            an.fftSize = 2048;
            ctx.createMediaStreamSource(stream).connect(an);
            const buf = new Float32Array(an.fftSize), bar = $('#micBar'), db = $('#micDb');
            let peak = -100, peakAt = 0;
            const loop = () => {
                an.getFloatTimeDomainData(buf);
                let sum = 0;
                for (const v of buf) sum += v * v;
                const level = Math.max(-100, 20 * Math.log10(Math.sqrt(sum / buf.length) || 1e-5));
                if (level > peak || performance.now() - peakAt > 1500) { peak = level; peakAt = performance.now(); }
                bar.style.width = Math.min(100, Math.max(0, (level + 60) / 60 * 100)) + '%';
                db.textContent = `${level.toFixed(0)} dB · peak ${peak.toFixed(0)}`;
                raf = requestAnimationFrame(loop);
            };
            loop();
            go.textContent = 'Stop'; go.classList.remove('primary');
            $('#micMeter').hidden = false;
            rec.hidden = !window.MediaRecorder;
        }
        go.addEventListener('click', () => (stream ? stop() : start()));
        sel.addEventListener('change', () => start(sel.value));
        rec.addEventListener('click', () => {
            const chunks = [];
            recorder = new MediaRecorder(stream);
            recorder.ondataavailable = e => chunks.push(e.data);
            recorder.onstop = () => {
                const a = $('#micPlay');
                if (a.src) URL.revokeObjectURL(a.src);
                a.src = URL.createObjectURL(new Blob(chunks, { type: recorder.mimeType }));
                a.hidden = false; a.play().catch(() => {});
                rec.disabled = false; rec.textContent = 'Record 5 seconds';
            };
            recorder.start();
            rec.disabled = true;
            let left = 5;
            rec.textContent = `Recording… ${left}`;
            const t = setInterval(() => {
                if (--left > 0 && recorder.state === 'recording') { rec.textContent = `Recording… ${left}`; return; }
                clearInterval(t);
                if (recorder.state === 'recording') recorder.stop();
            }, 1000);
        });
        tests.mic = { hide: stop };
    }

    // ---------- speakers ----------
    {
        let ctx = null;
        $('[data-panel="speakers"]').addEventListener('click', e => {
            const b = e.target.closest('[data-pan], [data-sweep]');
            if (!b) return;
            ctx ||= new AudioContext();
            const t = ctx.currentTime, len = b.dataset.sweep != null ? 6 : 1;
            const osc = ctx.createOscillator(), gain = ctx.createGain(), pan = ctx.createStereoPanner();
            pan.pan.value = +(b.dataset.pan || 0);
            osc.frequency.setValueAtTime(b.dataset.sweep != null ? 30 : 440, t);
            if (len > 1) osc.frequency.exponentialRampToValueAtTime(16000, t + len);
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(0.2, t + 0.05);
            gain.gain.setValueAtTime(0.2, t + len - 0.1);
            gain.gain.linearRampToValueAtTime(0, t + len);
            osc.connect(gain).connect(pan).connect(ctx.destination);
            osc.start(t); osc.stop(t + len);
        });
        tests.speakers = {};
    }

    // ---------- webcam ----------
    {
        const go = $('#camGo'), sel = $('#camDev'), video = $('#camVideo'), err = $('#camErr');
        let stream = null, gen = 0;
        function stop() {
            stream?.getTracks().forEach(t => t.stop());
            stream = null; gen++; video.srcObject = null; video.hidden = true;
            go.textContent = 'Start camera'; go.classList.add('primary');
        }
        async function start(deviceId) {
            stop(); err.hidden = true;
            const want = { width: { ideal: 1920 }, height: { ideal: 1080 } };
            const my = ++gen;
            let got;
            try { got = await navigator.mediaDevices.getUserMedia({ video: deviceId ? { ...want, deviceId: { exact: deviceId } } : want }); }
            catch (e) { if (my === gen) mediaError(err, e, 'camera'); return; }
            if (my !== gen) { got.getTracks().forEach(t => t.stop()); return; }
            stream = got;
            const track = stream.getVideoTracks()[0], s = track.getSettings();
            fillDevices(sel, 'videoinput', track);
            video.srcObject = stream; video.hidden = false;
            $('#camInfo').innerHTML = kv([['Device', track.label], ['Resolution', s.width && `${s.width} × ${s.height}`], ['Frame rate', s.frameRate && Math.round(s.frameRate) + ' fps']]);
            go.textContent = 'Stop'; go.classList.remove('primary');
        }
        go.addEventListener('click', () => (stream ? stop() : start()));
        sel.addEventListener('change', () => start(sel.value));
        tests.webcam = { hide: stop };
    }

    // ---------- touchscreen ----------
    {
        const cv = $('#touchCanvas'), g = cv.getContext('2d'), live = new Map();
        let most = 0;
        const count = () => ($('#touchCount').textContent = `${live.size} now · ${most} at once`);
        const size = () => {
            const r = cv.getBoundingClientRect(), dpr = devicePixelRatio || 1;
            if (!r.width || (cv.width === Math.round(r.width * dpr) && cv.height === Math.round(r.height * dpr))) return;
            cv.width = Math.round(r.width * dpr); cv.height = Math.round(r.height * dpr);
            g.setTransform(dpr, 0, 0, dpr, 0, 0);
            g.lineWidth = 4; g.lineCap = 'round';
        };
        new ResizeObserver(size).observe(cv);
        const at = e => { const r = cv.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
        cv.addEventListener('pointerdown', e => {
            e.preventDefault();
            cv.setPointerCapture(e.pointerId);
            // each finger gets its own colour so crossed lines stay readable
            live.set(e.pointerId, { p: at(e), c: `hsl(${(live.size * 67 + 100) % 360} 80% 60%)` });
            most = Math.max(most, live.size); count();
        });
        cv.addEventListener('pointermove', e => {
            const s = live.get(e.pointerId);
            if (!s) return;
            const p = at(e);
            g.strokeStyle = s.c; g.beginPath(); g.moveTo(...s.p); g.lineTo(...p); g.stroke();
            s.p = p;
        });
        const up = e => { live.delete(e.pointerId); count(); };
        cv.addEventListener('pointerup', up);
        cv.addEventListener('pointercancel', up);
        $('#touchClear').addEventListener('click', () => { g.clearRect(0, 0, cv.width, cv.height); most = live.size; count(); });
        tests.touch = { show: () => { size(); count(); } };
    }

    // ---------- dead pixels ----------
    {
        const COLOURS = ['#000', '#fff', '#f00', '#0f0', '#00f', '#808080'];
        const full = $('#scrFull'), hint = $('#scrHint');
        let i = 0, hintT;
        const paint = () => { full.style.background = COLOURS[i]; };
        const close = () => { full.hidden = true; if (document.fullscreenElement) document.exitFullscreen().catch(() => {}); };
        $('#scrGo').addEventListener('click', () => {
            i = 0; paint(); full.hidden = false;
            hint.classList.remove('gone'); clearTimeout(hintT); hintT = setTimeout(() => hint.classList.add('gone'), 2000);
            full.requestFullscreen?.().catch(() => {});
        });
        full.addEventListener('click', () => { i = (i + 1) % COLOURS.length; paint(); });
        addEventListener('keydown', e => {
            if (full.hidden) return;
            if (e.key === 'Escape') close();
            else if ([' ', 'ArrowRight', 'Enter'].includes(e.key)) { i = (i + 1) % COLOURS.length; paint(); }
            else if (e.key === 'ArrowLeft') { i = (i + COLOURS.length - 1) % COLOURS.length; paint(); }
            else return;
            e.preventDefault();
        });
        document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) full.hidden = true; });

        // refresh rate: count animation frames for one second
        function measure() {
            const dpr = devicePixelRatio || 1;
            const info = hz => kv([['Screen', `${Math.round(screen.width * dpr)} × ${Math.round(screen.height * dpr)} pixels`], ['Window', `${innerWidth} × ${innerHeight}`],
                ['Pixel ratio', dpr], ['Colour depth', screen.colorDepth + '-bit'], ['Refresh rate', hz ? `about ${hz} Hz` : 'measuring…']]);
            $('#scrInfo').innerHTML = info(0);
            let n = 0;
            const t0 = performance.now();
            const f = t => { if (t - t0 < 1000) { n++; requestAnimationFrame(f); } else $('#scrInfo').innerHTML = info(Math.round(n * 1000 / (t - t0))); };
            requestAnimationFrame(f);
        }
        tests.screen = { show: measure, hide: close };
    }

    // ---------- controller ----------
    {
        const STD = ['A', 'B', 'X', 'Y', 'LB', 'RB', 'LT', 'RT', 'View', 'Menu', 'LS', 'RS', '↑', '↓', '←', '→', 'Home', 'Share'];
        let raf = 0, shape = '';
        const build = pads => {
            $('#pads').innerHTML = pads.map(p => `<div class="dc-pad" data-i="${p.index}"><h3>${esc(p.id)}</h3>`
                + '<div class="tiles">' + p.buttons.map((_, b) => `<div class="tile"><b>${p.mapping === 'standard' && STD[b] ? STD[b] : b}</b><span>0.00</span><i></i></div>`).join('') + '</div>'
                + '<div class="dc-sticks">' + Array.from({ length: Math.ceil(p.axes.length / 2) }, (_, s) => `<div class="dc-stick"><i></i><span>${p.mapping === 'standard' ? ['Left', 'Right'][s] || 'Axes ' + s : `Axes ${s * 2}, ${s * 2 + 1}`}</span></div>`).join('') + '</div>'
                + (p.vibrationActuator ? '<div class="dc-row"><button type="button" class="btn small" data-rumble>Test vibration</button></div>' : '')
                + '</div>').join('');
        };
        const loop = () => {
            const pads = [...(navigator.getGamepads?.() || [])].filter(Boolean);
            const now = pads.map(p => `${p.index}:${p.id}:${p.buttons.length}:${p.axes.length}`).join('|');
            if (now !== shape) { shape = now; build(pads); $('#padWait').hidden = pads.length > 0; }
            for (const p of pads) {
                const el = $(`#pads [data-i="${p.index}"]`);
                el.querySelectorAll('.tile').forEach((t, b) => {
                    const v = p.buttons[b].value;
                    t.classList.toggle('on', p.buttons[b].pressed);
                    t.lastElementChild.style.transform = `scaleX(${v})`;
                    t.children[1].textContent = v.toFixed(2);
                });
                el.querySelectorAll('.dc-stick i').forEach((d, s) => {
                    const x = p.axes[s * 2] || 0, y = p.axes[s * 2 + 1] || 0;
                    d.style.transform = `translate(${x * 41}px, ${y * 41}px)`;
                });
            }
            raf = requestAnimationFrame(loop);
        };
        $('#pads').addEventListener('click', e => {
            if (!e.target.closest('[data-rumble]')) return;
            const p = navigator.getGamepads()[e.target.closest('[data-i]').dataset.i];
            p?.vibrationActuator?.playEffect('dual-rumble', { duration: 600, strongMagnitude: 1, weakMagnitude: 1 }).catch(() => {});
        });
        tests.gamepad = { show: () => { shape = ''; loop(); }, hide: () => cancelAnimationFrame(raf) };
    }

    open(location.hash.slice(1));
    addEventListener('hashchange', () => open(location.hash.slice(1)));
});
