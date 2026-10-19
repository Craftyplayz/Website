// Shared by the Steam pages (/games/steam/): art fallbacks and the cover tilt.
(function () {
    // Missing art: try the store header (data-alt) once, then hide the image so the card's text and colour show instead
    document.addEventListener('error', function (e) {
        var img = e.target;
        if (!img.closest || !img.closest('.steam')) return;
        if (img.dataset.alt && img.src !== img.dataset.alt) {
            img.src = img.dataset.alt;
            img.parentNode.classList.add('steam-noart');
        } else {
            img.style.visibility = 'hidden';
            if (img.parentNode.classList) img.parentNode.classList.add('steam-noart');
        }
    }, true);

    // Covers tilt a little towards the pointer, like the Steam library
    if (matchMedia('(prefers-reduced-motion: reduce)').matches || !matchMedia('(hover: hover)').matches) return;
    document.addEventListener('pointermove', function (e) {
        var c = e.target.closest && e.target.closest('.steam-cap, .steam-banner');
        if (!c) return;
        var r = c.getBoundingClientRect();
        c.style.setProperty('--rx', ((e.clientY - r.top) / r.height - 0.5) * -6 + 'deg');
        c.style.setProperty('--ry', ((e.clientX - r.left) / r.width - 0.5) * 6 + 'deg');
    });
    document.addEventListener('pointerout', function (e) {
        var c = e.target.closest && e.target.closest('.steam-cap, .steam-banner');
        if (c && !c.contains(e.relatedTarget)) {
            c.style.removeProperty('--rx');
            c.style.removeProperty('--ry');
        }
    });
})();
