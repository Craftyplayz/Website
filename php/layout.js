// Shared site header/footer.
//
// Edit the markup in /php/header.html and /php/footer.html - every page picks it up.
//
// Plain HTML pages: put these where the header and footer should go
//     <div id="site-header"></div>
//     ...
//     <div id="site-footer"></div>
//     <script src="/php/layout.js"></script>
//
// PHP pages get the same markup from header.php / footer.php (header.php loads this script with defer).

(function () {
    const base = new URL('.', document.currentScript.src);

    // Google tag (gtag.js)
    const gtagScript = document.createElement('script');
    gtagScript.async = true;
    gtagScript.src = 'https://www.googletagmanager.com/gtag/js?id=G-QPBCTT7P8J';
    document.head.appendChild(gtagScript);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', 'G-QPBCTT7P8J');

    // Font Awesome icons (the header toggle button uses one)
    if (!document.querySelector('script[src*="kit.fontawesome.com"]')) {
        const fa = document.createElement('script');
        fa.src = 'https://kit.fontawesome.com/af6ee4244b.js';
        fa.crossOrigin = 'anonymous';
        document.head.appendChild(fa);
    }

    // swap a placeholder <div> for the partial's markup (PHP pages have no placeholder)
    async function inject(placeholderId, file) {
        const placeholder = document.getElementById(placeholderId);
        if (!placeholder) return;
        try {
            const res = await fetch(new URL(file, base));
            placeholder.outerHTML = await res.text();
        } catch (e) {
            console.error('Could not load ' + file, e);
        }
    }

    function initHeader() {
        const burger = document.querySelector('.hamburger');
        const nav = document.querySelector('.nav-links');
        if (burger && nav) {
            burger.addEventListener('click', () => nav.classList.toggle('nav-active'));
        }
        const toggle = document.getElementById('toggleHeader');
        const header = document.getElementById('header');
        if (toggle && header) {
            toggle.addEventListener('click', () => {
                header.style.display = header.style.display === 'none' ? '' : 'none';
            });
        }
    }

    function initFooter() {
        const copyright = document.getElementById('copyright');
        if (copyright) copyright.textContent = '© ' + new Date().getFullYear() + ' CraftyPlayz';

        // build a breadcrumb from the current path, e.g. Home > Tests > Speedtest
        const breadcrumbs = document.getElementById('breadcrumbs');
        if (breadcrumbs) {
            const parts = location.pathname.split('/').filter((p) => p && !/^index\.(php|html)$/.test(p));
            const crumbs = ['<a style="color: white;" href="/">Home</a>'];
            let url = '/';
            parts.forEach((part, i) => {
                url += part + '/';
                const title = decodeURIComponent(part)
                    .replace(/\.(php|html)$/, '')
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (c) => c.toUpperCase());
                crumbs.push(i < parts.length - 1 ? '<a style="color: white;" href="' + url + '">' + title + '</a>' : title);
            });
            breadcrumbs.innerHTML = crumbs.join(' > ');
        }

        const catfact = document.getElementById('catfact');
        if (catfact) {
            fetch('https://meowfacts.herokuapp.com')
                .then((res) => res.json())
                .then((data) => { catfact.textContent = 'Random cat fact: ' + data.data.join(' '); })
                .catch(() => {});
        }
    }

    Promise.all([inject('site-header', 'header.html'), inject('site-footer', 'footer.html')])
        .then(() => {
            initHeader();
            initFooter();
        });
})();
