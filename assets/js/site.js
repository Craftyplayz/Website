// Site-wide behaviour: header menus, search, analytics, footer extras.
// Loaded on every page (with defer) by the PHP header, or by /php/layout.js on old .html pages.
// Plain .html pages can put <div id="site-header"></div> / <div id="site-footer"></div>
// where the header and footer should go, and this fills them in.
(function () {
    'use strict';
    if (window.__siteJs) return;
    window.__siteJs = true;

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const idle = window.requestIdleCallback || ((fn) => setTimeout(fn, 1500));

    // ---------- Google Analytics ----------
    const gtagScript = document.createElement('script');
    gtagScript.async = true;
    gtagScript.src = 'https://www.googletagmanager.com/gtag/js?id=G-QPBCTT7P8J';
    document.head.appendChild(gtagScript);
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', 'G-QPBCTT7P8J');

    // ---------- placeholders on plain .html pages ----------
    async function inject(id, part) {
        const el = document.getElementById(id);
        if (!el) return;
        try {
            const res = await fetch('/includes/header-fragment.php?part=' + part + '&path=' + encodeURIComponent(location.pathname));
            if (!res.ok) throw new Error(res.status);
            el.outerHTML = await res.text();
        } catch (e) {
            console.error('Could not load the site ' + part, e);
        }
    }

    // ---------- header ----------
    function initHeader() {
        const header = $('#header.site-header');
        if (!header || header.dataset.ready) return;
        header.dataset.ready = '1';

        // give "Untitled" pages a proper title from data/site.json
        const t = document.title.trim();
        if (header.dataset.pageTitle && (!t || /^untitled$/i.test(t) || t === 'Crafty Playz')) document.title = header.dataset.pageTitle + ' · Crafty Playz';

        const burger = $('.sh-burger', header);
        const items = $$('.sh-item.has-menu', header);
        const setOpen = (item, open) => {
            item.classList.toggle('open', open);
            const b = $('.sh-caret', item);
            if (b) b.setAttribute('aria-expanded', String(open));
        };
        const closeAll = (except) => items.forEach((i) => i !== except && setOpen(i, false));

        items.forEach((item) => {
            $('.sh-caret', item).addEventListener('click', (e) => {
                e.stopPropagation();
                const open = !item.classList.contains('open');
                closeAll(item);
                setOpen(item, open);
            });
            // keyboard: close a menu once focus leaves it
            item.addEventListener('focusout', (e) => { if (!item.contains(e.relatedTarget)) setOpen(item, false); });
            // a hover-opened menu should not stay pinned open after the mouse leaves
            item.addEventListener('mouseleave', () => { if (matchMedia('(hover: hover) and (min-width: 901px)').matches) setOpen(item, false); });
        });

        burger.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = !header.classList.contains('menu-open');
            header.classList.toggle('menu-open', open);
            burger.setAttribute('aria-expanded', String(open));
            if (!open) closeAll();
        });

        document.addEventListener('click', (e) => {
            if (header.contains(e.target)) return;
            closeAll();
            header.classList.remove('menu-open');
            burger.setAttribute('aria-expanded', 'false');
            hideResults();
        });

        document.addEventListener('keydown', (e) => {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) || document.activeElement.isContentEditable;
            if (e.key === 'Escape') {
                if (items.some((i) => i.classList.contains('open')) || header.classList.contains('menu-open')) {
                    closeAll();
                    header.classList.remove('menu-open');
                    burger.setAttribute('aria-expanded', 'false');
                }
                return;
            }
            // "/" jumps to the site search, unless the page uses "/" itself (e.g. the NBT viewer's search)
            if (e.key === '/' && !typing && !e.defaultPrevented && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                q.focus();
            }
        });

        // ---------- search ----------
        const q = $('#sh-q', header);
        const box = $('#sh-results', header);
        let pages = null, loading = null, hits = [], sel = 0;

        function load() {
            if (pages || loading) return loading;
            loading = fetch('/data/site.json').then((r) => r.json()).then((data) => {
                pages = [];
                for (const s of data.sections || []) for (const g of s.groups || []) for (const p of g.pages || []) {
                    if (p.search === false) continue;
                    pages.push({ title: p.title, url: p.url, where: s.title + (g.title !== s.title ? ' › ' + g.title : ''), text: [p.title, p.description || '', (p.keywords || []).join(' '), s.title, g.title].join(' ').toLowerCase() });
                }
                for (const l of data.links || []) pages.push({ title: l.title, url: l.url, where: '', text: (l.title + ' ' + (l.description || '')).toLowerCase() });
            }).catch(() => { pages = []; });
            return loading;
        }

        function search(term) {
            const words = term.toLowerCase().split(/\s+/).filter(Boolean);
            if (!words.length) return [];
            return pages
                .map((p) => {
                    if (!words.every((w) => p.text.includes(w))) return null;
                    const t = p.title.toLowerCase();
                    let score = 0;
                    for (const w of words) score += t.startsWith(w) ? 4 : t.includes(' ' + w) ? 3 : t.includes(w) ? 2 : 1;
                    return { p, score };
                })
                .filter(Boolean)
                .sort((a, b) => b.score - a.score || a.p.title.localeCompare(b.p.title))
                .slice(0, 8)
                .map((x) => x.p);
        }

        function render() {
            const term = q.value.trim();
            if (!term) { hideResults(); return; }
            hits = search(term);
            sel = 0;
            box.innerHTML = hits.length
                ? hits.map((p, i) => `<a href="${esc(p.url)}" role="option"${i === sel ? ' class="kb" aria-selected="true"' : ''}>${esc(p.title)}${p.where ? `<small>${esc(p.where)}</small>` : ''}</a>`).join('')
                : `<div class="sh-none">No pages match “${esc(term)}”</div>`;
            box.hidden = false;
        }
        function hideResults() { box.hidden = true; }
        function moveSel(d) {
            if (!hits.length) return;
            sel = (sel + d + hits.length) % hits.length;
            $$('a', box).forEach((a, i) => { a.classList.toggle('kb', i === sel); a.setAttribute('aria-selected', String(i === sel)); });
        }

        let timer;
        q.addEventListener('focus', load);
        q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load().then(render), 60); });
        q.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); moveSel(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveSel(-1); }
            else if (e.key === 'Enter') { if (hits[sel]) { e.preventDefault(); location.href = hits[sel].url; } }
            else if (e.key === 'Escape') { e.stopPropagation(); if (q.value) { q.value = ''; hideResults(); } else q.blur(); }
        });
    }

    // ---------- footer ----------
    function initFooter() {
        const cat = $('#catfact');
        if (!cat || cat.dataset.ready) return;
        cat.dataset.ready = '1';
        // a nice-to-have, so it waits until the page has finished loading
        idle(() => {
            fetch('https://meowfacts.herokuapp.com')
                .then((r) => r.json())
                .then((d) => { cat.textContent = 'Cat fact: ' + d.data.join(' '); })
                .catch(() => { });
        });
    }

    async function init() {
        await Promise.all([inject('site-header', 'header'), inject('site-footer', 'footer')]);
        initHeader();
        initFooter();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
