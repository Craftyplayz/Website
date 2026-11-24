// Rename Items: search, detail drawer, 3D viewer loaded on first use
document.addEventListener('DOMContentLoaded', () => {
    const items = window.MDL_ITEMS || [];
    const { $, $$, esc, debounce, copy } = UI;
    const drawer = $('#mdl-drawer'), detail = $('#mdl-detail');
    const base = '/minecraft/models/';
    const str = v => (typeof v === 'string' ? v : '');
    const url = p => (str(p) ? base + str(p).replace(/^\/+/, '') : '');
    const label = k => (k.charAt(0).toUpperCase() + k.slice(1)).replace(/_/g, ' ');
    const linkify = t => esc(t).replace(/https?:\/\/[^\s,<]+/g, u => `<a href="${u}" target="_blank" rel="noopener">${u}</a>`);
    let viewer = null; // the model-viewer import, started on the first 3D item

    // search
    const cards = $$('.mdl-card');
    const hay = cards.map(c => { const i = items[c.dataset.i] || {}; return (str(i.name) + ' ' + str(i.description)).toLowerCase(); });
    $('#mdl-q').addEventListener('input', debounce(e => {
        const q = e.target.value.trim().toLowerCase();
        let shown = 0;
        cards.forEach((c, n) => { c.hidden = !hay[n].includes(q); shown += !c.hidden; });
        $$('.mdl-sec').forEach(s => (s.hidden = !s.querySelector('.mdl-card:not([hidden])')));
        $('#mdl-count').textContent = `${shown} item${shown === 1 ? '' : 's'}`;
        $('#mdl-none').hidden = shown > 0;
    }, 80));

    function preview(i) {
        const jpg = url(i.image);
        if (i.is_3d && str(i.model_3d)) return `<div class="mdl-view" data-glb="${esc(url(i.model_3d))}">${jpg ? `<img src="${esc(jpg)}" alt="" width="256" height="256">` : ''}</div>`;
        const png = url(i.texture) || jpg;
        return png ? `<div class="mdl-view"><img class="${url(i.texture) ? 'mdl-px' : ''}" src="${esc(png)}" alt="" width="256" height="256"></div>` : '';
    }

    function render(i) {
        const name = str(i.rename_to) || str(i.name);
        const trail = name.length - name.trimEnd().length;
        const what = str(i.rename_item).replace(/^minecraft:/, '').replace(/_/g, ' ');
        const beh = i.behaviour && typeof i.behaviour === 'object' ? Object.entries(i.behaviour).filter(([, v]) => str(v)) : [];
        const p = t => (str(t) ? `<p>${esc(t)}</p>` : '');
        detail.innerHTML = `
            ${preview(i)}
            <h2>${esc(i.name)}</h2>
            ${str(i.category) ? `<div class="badges"><span class="badge">${esc(i.category)}</span>${i.is_3d ? '<span class="badge b">3D</span>' : ''}</div>` : ''}
            <section class="card mdl-type">
                <h3>Name to type in the anvil</h3>
                <div class="mdl-name"><code class="mono">${esc(name)}${trail ? `<span class="mdl-sp" aria-hidden="true">${'·'.repeat(trail)}</span>` : ''}</code>
                <button class="btn primary small" data-act="copy">Copy name</button></div>
                <small class="muted">Case-sensitive: capitals, spaces and spelling must match exactly.${trail ? ` Ends with ${trail} space${trail > 1 ? 's' : ''} (shown as dots); Copy name keeps them.` : ''}</small>
                ${what ? `<div class="kv"><span>Item to rename</span><span>${esc(what)}</span></div>` : ''}
            </section>
            ${p(i.description)}${p(i.how_to_get)}${p(i.stacking)}
            ${beh.length ? `<h3>How it looks</h3><div class="mdl-beh">${beh.map(([k, v]) => `<div class="kv"><span>${esc(label(k))}</span><span>${esc(v)}</span></div>`).join('')}</div>` : ''}
            ${str(i.credit) ? `<p class="muted mdl-credit">${linkify(i.credit)}</p>` : ''}`;
        $('[data-act="copy"]', detail).onclick = e => copy(name, e.target);
        const view = $('[data-glb]', detail);
        if (view && 'customElements' in window) load3d(view, i);
    }

    function load3d(view, i) {
        viewer = viewer || import('https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js');
        viewer.then(() => {
            if (!view.isConnected) return; // another item was opened meanwhile
            const mv = document.createElement('model-viewer');
            mv.setAttribute('src', view.dataset.glb);
            mv.setAttribute('alt', '3D model of ' + str(i.name));
            mv.setAttribute('camera-controls', '');
            mv.setAttribute('touch-action', 'pan-y');
            mv.setAttribute('interaction-prompt', 'none');
            // start from the model's front, same angle as the previews (_dev/scripts/render-model-previews.js)
            mv.setAttribute('camera-orbit', (str(i.category) === 'Rat' ? '35deg' : '215deg') + ' 65deg auto');
            if (url(i.image)) mv.setAttribute('poster', url(i.image));
            mv.addEventListener('load', () => {
                // pixel art: nearest-neighbour (GL 9728) on every texture
                mv.model.materials.forEach(m => [m.pbrMetallicRoughness.baseColorTexture, m.emissiveTexture].forEach(t => {
                    const s = t && t.texture && t.texture.sampler;
                    if (s) { s.setMagFilter(9728); s.setMinFilter(9728); }
                }));
                $('img', view)?.remove();
                mv.classList.add('ready');
            });
            view.append(mv);
        }).catch(() => {}); // the JPEG stays when 3D can't load
    }

    // the URL hash holds the item name, so links open it directly
    function fromHash() {
        let name = '';
        try { name = decodeURIComponent(location.hash.slice(1)); } catch (e) { }
        const i = name && items.find(x => x.name === name);
        if (!i) return close(false);
        render(i);
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        drawer.focus();
    }
    function close(clearHash = true) {
        if (!drawer.classList.contains('open')) return;
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        detail.innerHTML = ''; // drops the 3D viewer
        if (clearHash) history.replaceState(null, '', location.pathname + location.search);
    }
    $('.close', drawer).onclick = () => close();
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    window.addEventListener('hashchange', fromHash);
    fromHash();
});
