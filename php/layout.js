// Moved to /assets/js/site.js - this stub keeps old pages that link here working.
(function () {
    if (window.__siteJs) return;
    const s = document.createElement('script');
    s.src = '/assets/js/site.js?v=' + Math.floor(Date.now() / 864e5);   // refreshes daily
    document.head.appendChild(s);
})();
