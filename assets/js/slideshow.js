// Homepage slideshow. The page paints a small low-quality copy of the first slide; this
// loads the full-quality copy, fades it in slowly, then keeps exactly one full-quality slide
// loading ahead so every change lands on an image that is already decoded.
(() => {
    const stage = document.getElementById('hero-stage');
    const data = document.getElementById('hero-slides');
    if (!stage || !data) return;
    const slides = JSON.parse(data.textContent || '[]');
    if (!slides.length) return;

    const DWELL = 5000;   // how long a full-quality slide stays up
    const FADE = 1500;    // matches .hero-stage img in home.css
    const GRACE = 6000;   // first change only: how long past DWELL to wait before using the low copy
    const still = matchMedia('(prefers-reduced-motion: reduce)').matches;

    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    // timers keep running in a hidden tab; hold changes until it's visible again
    const visible = () => document.hidden
        ? new Promise((r) => document.addEventListener('visibilitychange', function on() {
            if (!document.hidden) { document.removeEventListener('visibilitychange', on); r(); }
        }))
        : Promise.resolve();

    const lowUrl = (s) => s.src[0][1];
    // smallest copy that covers the screen. Tall screens crop the sides (object-fit: cover), so
    // their height decides too, but without the pixel ratio, or phones would pull 5K originals.
    function fullUrl(s) {
        const need = Math.max(innerWidth * Math.min(devicePixelRatio || 1, 2), innerHeight * s.a);
        const hit = s.src.find(([w]) => w >= need);
        return (hit || s.src[s.src.length - 1])[1];
    }

    // resolves with a decoded <img>, or null if it failed
    function load(url) {
        const img = new Image();
        img.alt = '';
        img.decoding = 'async';
        img.src = url;
        return img.decode().then(() => img, () => (img.naturalWidth ? img : null));
    }

    // put img on top, fade it in, then drop whatever was under it
    function show(img) {
        img.className = '';
        stage.append(img);
        void img.offsetWidth; // commit opacity: 0 so the fade runs
        img.classList.add('on');
        return wait(still ? 0 : FADE).then(() => {
            while (stage.firstElementChild !== img) stage.firstElementChild.remove();
        });
    }

    async function run() {
        const first = load(fullUrl(slides[0]));
        // the second slide's low copy (tiny) is the fallback if its full copy is slow to arrive
        const low = !still && slides.length > 1 ? load(lowUrl(slides[1])) : null;

        const img0 = await first;
        if (img0) await show(img0);
        if (!low) return;

        for (let i = 1, firstChange = true; ; i = (i + 1) % slides.length, firstChange = false) {
            const full = load(fullUrl(slides[i])); // one full-quality download at a time
            await wait(DWELL);
            let img = await (firstChange ? Promise.race([full, wait(GRACE).then(() => false)]) : full);
            if (img === false) {
                // slow connection on the first change only: low copy now, full one fades over it.
                // From here on the slideshow waits for full quality instead of dropping back.
                const lowImg = await low;
                await visible();
                if (lowImg) await show(lowImg);
                img = await full;
            }
            await visible();
            if (img) await show(img);
        }
    }

    run();
})();
