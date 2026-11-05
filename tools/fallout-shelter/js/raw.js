// Fallout Shelter Save Editor: the raw data tree. Children are built only when a node opens. Edits go through the same commit as forms.
(() => {
    'use strict';
    const { $, toast } = UI;
    const { html, put, state, act } = window.FOS;
    const C = window.FOSCore;
    const FOS = window.FOS;
    const raw = (s) => ({ __html: s });
    const CHUNK = 200;

    let nodes = [];
    const opened = new WeakSet(); // stays open across undo and re-render
    const revealed = new WeakSet(); // deviceName stays hidden until clicked
    const reg = (n) => { nodes.push(n); return nodes.length - 1; };

    const lock = html`<span class="fos-lock">${FOS.lockIcon}${C.LOCK_MESSAGE}</span>`;
    const typeOf = (n) => ({ s: 'string', n: 'number', b: 'bool', z: 'null' }[n.t]);
    const keyHtml = (e) => (e.own.t === 'o' ? html`<span class="k">${e.k}</span>` : html`<span class="k">${e.own.items.indexOf(e)}</span>`);
    const isDevice = (e) => e && e.k === 'deviceName' && !e.own.up;

    function valueHtml(n, e) {
        if (isDevice(e) && !revealed.has(n)) return html`<button class="fos-reveal" data-reveal="${reg(n)}">Show device name</button>`;
        if (C.isLockedNode(n)) return html`<span class="v ${typeOf(n)}">${n.raw}</span>${lock}`;
        return html`<button class="fos-val v ${typeOf(n)}" data-edit="${reg(n)}" title="Edit this value">${n.raw}</button>`;
    }
    function lineHtml(e) {
        const n = e.v;
        if (n.t === 'o' || n.t === 'a') {
            const size = n.items.length;
            const locked = C.isLockedNode(n);
            const open = opened.has(n);
            return html`<details data-node="${reg(n)}" ${raw(open ? 'open' : '')}><summary>${keyHtml(e)} <span class="ty">${n.t === 'a' ? 'list' : 'object'} · ${size}</span>${locked ? lock : ''}</summary>${open ? childrenHtml(n) : ''}</details>`;
        }
        return html`<div class="tl">${keyHtml(e)}: ${valueHtml(n, e)}</div>`;
    }
    function childrenHtml(n, limit = CHUNK) {
        const list = n.items.slice(0, limit);
        return html`${list.map(lineHtml)}${n.items.length > limit ? html`<button class="btn small" data-show-more="${reg(n)}" data-limit="${limit + CHUNK}">Show ${Math.min(CHUNK, n.items.length - limit)} more of ${n.items.length}</button>` : ''}`;
    }
    function searchHtml(root, q) {
        const hits = [];
        const walk = (n, path) => {
            for (const e of n.items) {
                if (hits.length >= 300) return;
                const step = n.t === 'o' ? e.k : n.items.indexOf(e);
                const p = path + (path ? '.' : '') + step;
                const v = e.v;
                if (v.t === 'o' || v.t === 'a') {
                    if (String(step).toLowerCase().includes(q)) hits.push([p, v, e]);
                    walk(v, p);
                } else if (String(step).toLowerCase().includes(q) || (!isDevice(e) && v.raw.toLowerCase().includes(q))) hits.push([p, v, e]);
            }
        };
        if (root.t === 'o' || root.t === 'a') walk(root, '');
        if (!hits.length) return html`<p class="muted">Nothing matches this.</p>`;
        return html`<p class="muted fos-small">${hits.length >= 300 ? 'First 300 matches' : hits.length + ' matches'}</p>
            ${hits.map(([p, v, e]) => (v.t === 'o' || v.t === 'a'
                ? html`<div class="tl"><span class="fos-hitpath">${p}</span> <span class="ty">${v.t === 'a' ? 'list' : 'object'} · ${v.items.length}</span></div>`
                : html`<div class="tl"><span class="fos-hitpath">${p}</span>: ${valueHtml(v, e)}</div>`))}`;
    }

    FOS.views.raw = (el, f) => {
        nodes = [];
        const root = f.doc.root;
        const q = state.query.toLowerCase();
        put(el, html`<section class="card"><h2>Raw data</h2>
            <p class="muted fos-small">Every value in this file. Click one to edit it. Paid content can't be edited.</p>
            <div class="tree fos-tree" data-tree>${q ? searchHtml(root, q) : (root.t === 'o' || root.t === 'a') ? childrenHtml(root, 1000) : ''}</div></section>`);
    };

    function startEdit(btn) {
        const n = nodes[Number(btn.dataset.edit)];
        if (!n || C.isLockedNode(n)) return;
        const typed = n.t === 's' ? C.val(n) : n.raw;
        const wrap = document.createElement('span');
        if (n.t === 'b') {
            put(wrap, html`<select class="field" aria-label="Value"><option ${raw(n.raw === 'true' ? 'selected' : '')}>true</option><option ${raw(n.raw === 'false' ? 'selected' : '')}>false</option></select>`);
        } else {
            put(wrap, html`<input class="field" type="text" aria-label="Value" value="${typed}" size="${Math.min(40, String(typed).length + 2)}">`);
        }
        const input = wrap.firstChild;
        btn.replaceWith(wrap);
        input.focus();
        if (input.select) input.select();
        let done = false;
        const finish = (save) => {
            if (done) return;
            done = true;
            if (save) {
                let text;
                try {
                    if (n.t === 'n') text = C.numberLike(n.raw, input.value);
                    else if (n.t === 'b') text = input.value;
                    else if (n.t === 'z') text = input.value === '' || input.value === 'null' ? 'null' : guessScalar(input.value);
                    else text = C.stringRaw(input.value);
                } catch (e) {
                    toast(e.message, 'err');
                    done = false;
                    input.setAttribute('aria-invalid', 'true');
                    input.focus();
                    return;
                }
                const path = C.pathOf(n).join('.');
                if (text !== n.raw) act(path, [{ op: 'set', node: n, raw: text }], 'view');
            }
            wrap.replaceWith(rebuilt(n));
        };
        input.addEventListener('blur', () => finish(true));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); finish(true); }
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); finish(false); }
        });
    }
    function guessScalar(s) {
        if (s === 'true' || s === 'false') return s;
        if (/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(s)) return s;
        return C.stringRaw(s);
    }
    function rebuilt(n) {
        const box = document.createElement('span');
        put(box, valueHtml(n, n.up));
        return box.firstChild;
    }

    document.addEventListener('toggle', (e) => {
        const d = e.target;
        if (!d.dataset || d.dataset.node === undefined || !d.closest('[data-tree]')) return;
        const n = nodes[Number(d.dataset.node)];
        if (!n) return;
        if (d.open) {
            opened.add(n);
            if (d.children.length === 1) d.insertAdjacentHTML('beforeend', childrenHtml(n).__html);
        } else opened.delete(n);
    }, true);
    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!t.closest || !t.closest('[data-tree]')) return;
        let b;
        if ((b = t.closest('[data-edit]'))) { startEdit(b); return; }
        if ((b = t.closest('[data-reveal]'))) {
            const n = nodes[Number(b.dataset.reveal)];
            if (n) { revealed.add(n); b.replaceWith(rebuilt(n)); }
            return;
        }
        if ((b = t.closest('[data-show-more]'))) {
            const n = nodes[Number(b.dataset.showMore)];
            const parent = b.parentElement;
            if (!n || !parent) return;
            const limit = Number(b.dataset.limit);
            const head = parent.tagName === 'DETAILS' ? parent.querySelector('summary').outerHTML : '';
            parent.innerHTML = head + childrenHtml(n, limit).__html;
        }
    });
})();
