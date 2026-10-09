// Footer: year, breadcrumbs and random cat fact (replaces footer.php)
(function () {
    function titleCase(s) {
        return s.replace(/\.(php|html)$/, '').replace(/_/g, ' ')
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    var year = document.getElementById('site-year');
    if (year) year.textContent = new Date().getFullYear();

    var crumbsEl = document.getElementById('site-breadcrumbs');
    if (crumbsEl) {
        var parts = decodeURIComponent(location.pathname).split('/').filter(Boolean);
        if (parts.length && parts[parts.length - 1] === 'index.html') parts.pop();
        var frag = document.createDocumentFragment();
        function link(href, text) {
            var a = document.createElement('a');
            a.style.color = 'white';
            a.href = href;
            a.textContent = text;
            return a;
        }
        frag.appendChild(link('/', 'Home'));
        var url = '/';
        parts.forEach(function (part, i) {
            url += encodeURIComponent(part) + '/';
            frag.appendChild(document.createTextNode(' > '));
            if (i < parts.length - 1) {
                frag.appendChild(link(url, titleCase(part)));
            } else {
                frag.appendChild(document.createTextNode(titleCase(part)));
            }
        });
        crumbsEl.appendChild(frag);
    }

    var factEl = document.getElementById('site-catfact');
    if (factEl) {
        fetch('https://meowfacts.herokuapp.com')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                factEl.textContent = (d.data || []).map(function (f) {
                    return 'Random cat fact: ' + f;
                }).join(' ');
            })
            .catch(function () { });
    }
})();

// Shared helpers for pages
window.site = {
    shuffle: function (a) {
        a = a.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = a[i]; a[i] = a[j]; a[j] = t;
        }
        return a;
    },
    decode: function (html) {
        return new DOMParser().parseFromString(String(html), 'text/html').documentElement.textContent;
    },
    shortenNumber: function (n) {
        if (n >= 1000) {
            var suffix = ['', 'k', 'M', 'B', 'T'];
            var scale = Math.floor(Math.log10(n) / 3);
            return (n / Math.pow(10, scale * 3)).toFixed(1).replace('.0', '') + suffix[scale];
        }
        return n;
    },
    esc: function (v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    },
    json: function (url) {
        return fetch(url).then(function (r) {
            if (!r.ok) throw new Error(url + ' ' + r.status);
            return r.json();
        });
    },
    // ?url=<key> redirects through /shorten.json (http/https targets only)
    shorten: function () {
        var key = new URLSearchParams(location.search).get('url');
        if (!key) return;
        site.json('/shorten.json').then(function (map) {
            if (Object.prototype.hasOwnProperty.call(map, key) && /^https?:\/\//i.test(map[key])) {
                location.replace(map[key]);
            }
        }).catch(function () { });
    }
};
