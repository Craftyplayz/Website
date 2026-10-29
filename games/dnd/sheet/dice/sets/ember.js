// Ember Cage: an iron lattice over a churning molten core, sparks rising off each die.
const poly = (g, c) => { g.beginPath(); c.forEach(([x, y], i) => (i ? g.lineTo(x, y) : g.moveTo(x, y))); g.closePath(); };
const shrink = (c, k) => { const m = c.reduce((a, p) => [a[0] + p[0] / c.length, a[1] + p[1] / c.length], [0, 0]); return c.map(([x, y]) => [m[0] + (x - m[0]) * k, m[1] + (y - m[1]) * k]); };
const mid = (c) => c.reduce((a, p) => [a[0] + p[0] / c.length, a[1] + p[1] / c.length], [0, 0]);

// the lattice: frame along the real edges, an inner rim, and filigree bars from each corner toward the centre
function lattice(g, S, corners, col, w) {
    g.strokeStyle = col; g.lineCap = g.lineJoin = 'round';
    g.lineWidth = w * 2.2; poly(g, corners); g.stroke();
    g.lineWidth = w * 0.8; poly(g, shrink(corners, 0.74)); g.stroke();
    const m = mid(corners);
    g.lineWidth = w * 0.9;
    for (const [x, y] of corners) {
        g.beginPath(); g.moveTo(x, y); g.lineTo(m[0] + (x - m[0]) * 0.62, m[1] + (y - m[1]) * 0.62); g.stroke();
        g.beginPath(); g.arc(x, y, w * 1.9, 0, 7); g.fillStyle = col; g.fill();
    }
    // little curls on each edge midpoint
    corners.forEach(([x, y], i) => {
        const [x2, y2] = corners[(i + 1) % corners.length], ex = (x + x2) / 2, ey = (y + y2) / 2, k = 0.86;
        g.beginPath(); g.arc(m[0] + (ex - m[0]) * k, m[1] + (ey - m[1]) * k, w * 1.1, 0, 7); g.lineWidth = w * 0.6; g.stroke();
    });
}

// where the engine draws the numbers, as fractions of the face canvas: the centre, or on a d4 three spots 0.62 of the way to the corners
const D4_SPOTS = [0, 1, 2].map((i) => [0.5 + Math.cos(i * 2.0944) * 0.49 * 0.62, 0.5 + Math.sin(i * 2.0944) * 0.49 * 0.62]);
const spots = (S, corners, d4) => (d4 ? corners.map(([x, y]) => [S / 2 + (x - S / 2) * 0.62, S / 2 + (y - S / 2) * 0.62, S * 0.15]) : [[S / 2, S / 2, S * 0.3]]);
// a dark iron plate under each number, so the bright number never sits on the bright core
function plates(g, S, corners, d4, fill, rim) {
    for (const [x, y, r] of spots(S, corners, d4)) {
        g.beginPath(); g.arc(x, y, r, 0, 7); g.fillStyle = fill; g.fill();
        g.lineWidth = r * 0.12; g.strokeStyle = rim; g.stroke();
    }
}

// shared churning overlay (created on first decorate)
let SH = null, lastDraw = -1;
function shared(THREE) {
    if (SH) return SH;
    const N = 128, cv = document.createElement('canvas'); cv.width = cv.height = N;
    const tex = new THREE.CanvasTexture(cv); tex.colorSpace = THREE.SRGBColorSpace;
    const mat = new THREE.MeshBasicMaterial({ map: tex, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false });
    // a d4 has its numbers in other places, so it gets its own copy with different holes
    const cv4 = document.createElement('canvas'); cv4.width = cv4.height = N;
    const tex4 = new THREE.CanvasTexture(cv4); tex4.colorSpace = THREE.SRGBColorSpace;
    const mat4 = new THREE.MeshBasicMaterial({ map: tex4, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false });
    const sc = document.createElement('canvas'); sc.width = sc.height = 32;
    const sg = sc.getContext('2d'), gr = sg.createRadialGradient(16, 16, 0, 16, 16, 16);
    gr.addColorStop(0, 'rgba(255,255,255,1)'); gr.addColorStop(0.3, 'rgba(255,200,90,.8)'); gr.addColorStop(1, 'rgba(255,90,0,0)');
    sg.fillStyle = gr; sg.fillRect(0, 0, 32, 32);
    const sparkTex = new THREE.CanvasTexture(sc);
    const sparkMat = new THREE.PointsMaterial({ size: 7, sizeAttenuation: false, map: sparkTex, vertexColors: true, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false });
    return (SH = { cv, g: cv.getContext('2d'), tex, mat, g4: cv4.getContext('2d'), tex4, mat4, sparkMat, sparkTex, N });
}
function churn(sh, sec) {
    const { g, N } = sh;
    g.clearRect(0, 0, N, N);
    g.globalCompositeOperation = 'lighter';
    for (let i = 0; i < 9; i++) {
        const a = sec * (0.7 + i * 0.13) + i * 2.1, x = N / 2 + Math.cos(a) * N * (0.18 + 0.05 * i % 3 * 0.5) + Math.sin(sec * 0.9 + i) * 10,
            y = N / 2 + Math.sin(a * 1.3) * N * 0.22 + Math.cos(sec * 1.1 + i * 1.7) * 10, r = N * (0.16 + 0.06 * Math.sin(sec * 1.5 + i));
        const gr = g.createRadialGradient(x, y, 0, x, y, r);
        gr.addColorStop(0, i % 2 ? 'rgba(255,210,70,.55)' : 'rgba(255,110,10,.5)'); gr.addColorStop(1, 'rgba(255,60,0,0)');
        g.fillStyle = gr; g.fillRect(0, 0, N, N);
    }
    // copy for the d4, then cut soft holes where the numbers sit so the churn never washes over them
    sh.g4.clearRect(0, 0, N, N); sh.g4.globalCompositeOperation = 'source-over'; sh.g4.drawImage(sh.cv, 0, 0);
    const hole = (c, x, y, r) => { const h = c.createRadialGradient(x, y, r * 0.7, x, y, r * 1.15); h.addColorStop(0, 'rgba(0,0,0,1)'); h.addColorStop(1, 'rgba(0,0,0,0)');
        c.globalCompositeOperation = 'destination-out'; c.fillStyle = h; c.fillRect(0, 0, N, N); c.globalCompositeOperation = 'source-over'; };
    hole(g, N / 2, N / 2, N * 0.3);
    for (const [x, y] of D4_SPOTS) hole(sh.g4, x * N, y * N, N * 0.15);
    sh.tex.needsUpdate = sh.tex4.needsUpdate = true;
}

const NS = 28;
export default {
    // bright bold numbers on dark plates: they glow like hot metal and read at a glance
    name: 'Ember Cage', ink: '#ffe9a8', metal: 0.55, rough: 0.38, glow: 0.55, bumpScale: 4,
    paint(g, S, seed, corners, d4) {
        const r = (() => { let s = seed * 97 + 5; return () => ((s = (s * 16807) % 2147483647) / 2147483647); })();
        g.fillStyle = '#120806'; g.fillRect(0, 0, S, S);
        // molten core inside the cage
        g.save(); poly(g, shrink(corners, 0.9)); g.clip();
        const gr = g.createRadialGradient(S / 2, S / 2, 0, S / 2, S / 2, S * 0.5);
        gr.addColorStop(0, '#ffe27a'); gr.addColorStop(0.4, '#e8801c'); gr.addColorStop(1, '#7a2204');
        g.fillStyle = gr; g.fillRect(0, 0, S, S);
        for (let i = 0; i < 30; i++) { // darker crust cracks and bright blobs
            g.fillStyle = r() < 0.5 ? 'rgba(120,25,0,.28)' : 'rgba(255,230,120,.22)';
            g.beginPath(); g.arc(r() * S, r() * S, 8 + r() * 24, 0, 7); g.fill();
        }
        g.restore();
        // dark iron frame; the rim glows faintly where the heat leaks
        lattice(g, S, corners, '#070403', 14);
        g.strokeStyle = 'rgba(255,120,30,.55)'; g.lineWidth = 2; poly(g, shrink(corners, 0.9)); g.stroke();
        plates(g, S, corners, d4, '#160a05', '#070403');
    },
    bump(g, S, seed, corners, d4) { lattice(g, S, corners, '#fff', 14); plates(g, S, corners, d4, '#555', '#fff'); },
    decorate({ THREE, mesh, faces, sides }) {
        const sh = shared(THREE);
        const ov = new THREE.Mesh(mesh.geometry, sides === 4 ? sh.mat4 : sh.mat); ov.scale.setScalar(1.004); mesh.add(ov);
        const pos = new Float32Array(NS * 3), col = new Float32Array(NS * 3), age = new Float32Array(NS), life = new Float32Array(NS);
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(pos, 3)); geo.setAttribute('color', new THREE.BufferAttribute(col, 3));
        const pts = new THREE.Points(geo, sh.sparkMat); pts.frustumCulled = false; mesh.add(pts);
        const spawn = (i) => {
            const f = faces[(Math.random() * faces.length) | 0], a = Math.random() - 0.5, b = Math.random() - 0.5;
            pos[i * 3] = f.c.x + (f.u.x * a + f.v.x * b) * f.r * 1.2 + f.n.x * 0.1;
            pos[i * 3 + 1] = f.c.y + (f.u.y * a + f.v.y * b) * f.r * 1.2 + f.n.y * 0.1;
            pos[i * 3 + 2] = f.c.z + (f.u.z * a + f.v.z * b) * f.r * 1.2 + f.n.z * 0.1;
            life[i] = 0.8 + Math.random() * 1.2; age[i] = 0;
        };
        for (let i = 0; i < NS; i++) { spawn(i); age[i] = Math.random() * life[i]; }
        const q = new THREE.Quaternion(), up = new THREE.Vector3(), vel = new THREE.Vector3();
        const place = (dt) => {
            q.copy(mesh.quaternion).invert(); up.set(0, 1, 0).applyQuaternion(q);
            for (let i = 0; i < NS; i++) {
                age[i] += dt; if (age[i] > life[i]) spawn(i);
                const k = age[i] / life[i], p = i * 3, drift = 0.6 * dt;
                pos[p] += up.x * drift - vel.x * dt * 0.5 + (Math.random() - 0.5) * 0.01;
                pos[p + 1] += up.y * drift - vel.y * dt * 0.5; pos[p + 2] += up.z * drift - vel.z * dt * 0.5;
                const f = (1 - k) * Math.min(1, k * 6);
                col[p] = f; col[p + 1] = f * (0.45 + 0.4 * (1 - k)); col[p + 2] = f * 0.12;
            }
            geo.attributes.position.needsUpdate = geo.attributes.color.needsUpdate = true;
        };
        place(0);
        return {
            tick(sec, dt, body) {
                if (sec - lastDraw > 0.05 || sec < lastDraw) { lastDraw = sec; churn(sh, sec); }
                if (body) { q.copy(mesh.quaternion).invert(); vel.set(body.velocity.x, body.velocity.y, body.velocity.z).applyQuaternion(q); } else vel.set(0, 0, 0);
                place(dt);
            },
            dispose() { mesh.remove(ov); mesh.remove(pts); geo.dispose(); },
        };
    },
    sound: {
        hit({ ac, out, noise, vol, onTable, t }) {
            const base = (onTable ? 520 : 900) * (0.85 + Math.random() * 0.3), g = ac.createGain();
            g.gain.setValueAtTime(vol * 0.45, t); g.gain.exponentialRampToValueAtTime(0.001, t + 0.3); g.connect(out);
            for (const m of [1, 2.76, 5.4]) { const o = ac.createOscillator(); o.type = 'sine'; o.frequency.value = base * m; o.connect(g); o.start(t); o.stop(t + 0.3); }
            // ember crackle: a few tiny noise ticks
            const s = ac.createBufferSource(); s.buffer = noise; const hp = ac.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = 3500;
            const cg = ac.createGain(); cg.gain.setValueAtTime(0.0001, t);
            for (let i = 0; i < 4; i++) { const tt = t + 0.01 + Math.random() * 0.2; cg.gain.setValueAtTime(vol * 0.35, tt); cg.gain.exponentialRampToValueAtTime(0.0005, tt + 0.02); }
            s.connect(hp).connect(cg).connect(out); s.start(t, Math.random() * 0.15, 0.25);
        },
        throw({ ac, out, noise, t }) {
            const s = ac.createBufferSource(); s.buffer = noise; s.loop = true;
            const bp = ac.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = 0.8;
            bp.frequency.setValueAtTime(250, t); bp.frequency.exponentialRampToValueAtTime(1800, t + 0.45);
            const g = ac.createGain(); g.gain.setValueAtTime(0.0001, t); g.gain.linearRampToValueAtTime(0.5, t + 0.2); g.gain.exponentialRampToValueAtTime(0.001, t + 0.6);
            s.connect(bp).connect(g).connect(out); s.start(t); s.stop(t + 0.65);
        },
        settle({ ac, out, noise, t }) {
            const s = ac.createBufferSource(); s.buffer = noise; s.loop = true;
            const hp = ac.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = 5000;
            const g = ac.createGain(); g.gain.setValueAtTime(0.0001, t); g.gain.linearRampToValueAtTime(0.2, t + 0.08); g.gain.exponentialRampToValueAtTime(0.001, t + 0.9);
            s.connect(hp).connect(g).connect(out); s.start(t); s.stop(t + 0.95);
        },
    },
};
