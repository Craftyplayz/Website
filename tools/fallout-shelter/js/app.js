// Fallout Shelter Save Editor: the page shell. Opening files, the toolbar, undo, the Changes list and downloads.
// Nothing here sends save data anywhere: no fetch, no query strings, no hash, and the page title never changes.
(() => {
    'use strict';
    // Stay false until the owner has passed Checkpoints 2 and 3 in the real game.
    const FOS_STRUCTURAL = false;
    const FOS_RISKY_BULK = false;

    const TAB_LABELS = { map: 'Map', dwellers: 'Dwellers', resources: 'Resources', inventory: 'Inventory', raw: 'Raw data' };
    const TABS_BY_KIND = { vault: ['map', 'dwellers', 'resources', 'inventory', 'raw'], season: ['raw'] };

    document.addEventListener('DOMContentLoaded', () => {
        const { $, esc, debounce, toast, copy, num } = UI;
        const main = $('#main');
        const urls = JSON.parse(main.dataset.js);

        // html`...` escapes every value unless it's already html`...`, so save text can't become markup by accident
        const html = (strings, ...vals) => {
            const one = (v) => (v === null || v === undefined || v === false ? '' : Array.isArray(v) ? v.map(one).join('') : v && v.__html !== undefined ? v.__html : esc(v));
            let out = strings[0];
            vals.forEach((v, i) => { out += one(v) + strings[i + 1]; });
            return { __html: out };
        };
        const put = (el, h) => { el.innerHTML = h.__html; };

        const state = { files: [], active: null, tab: 'map', query: '', drawer: null };
        const loaded = {};
        function need(name) {
            if (!loaded[name]) {
                loaded[name] = new Promise((res, rej) => {
                    const s = document.createElement('script');
                    s.src = urls[name];
                    s.onload = res;
                    s.onerror = () => { delete loaded[name]; rej(new Error('script')); };
                    document.head.appendChild(s);
                });
            }
            return loaded[name];
        }
        const FOS = window.FOS = { state, html, put, need, act, afterEdit, setTab, openDrawer, closeDrawer, renderDrawer, showDialog, closeDialog, FOS_STRUCTURAL, FOS_RISKY_BULK, views: {} };

        /* ---------- opening files ---------- */
        const CORRUPT = new Set(['base64', 'blocks', 'padding', 'json', 'depth']);
        function message(kind, text) {
            const box = state.files.length ? $('#fosMsgs') : $('#fosStartMsgs');
            const p = document.createElement('p');
            p.className = kind;
            p.textContent = text;
            const x = document.createElement('button');
            x.className = 'fos-msg-x';
            x.setAttribute('aria-label', 'Dismiss');
            x.textContent = '×';
            x.addEventListener('click', () => p.remove());
            p.prepend(x);
            box.appendChild(p);
        }
        async function loadFiles(list) {
            const files = [...list];
            if (!files.length) return;
            const drop = $('#fosDrop');
            const old = $('#fosPick').textContent;
            $('#fosPick').textContent = 'Opening...';
            $('#fosPick').disabled = true;
            try {
                await Promise.all([need('core'), need('model'), need('views')]);
            } catch (e) {
                message('err', "Couldn't load the editor. Check your connection and try again.");
                $('#fosPick').textContent = old;
                $('#fosPick').disabled = false;
                return;
            }
            const C = window.FOSCore, M = window.FOSModel;
            const wasEmpty = !state.files.length;
            const startBox = $('#fosStartMsgs');
            startBox.textContent = '';
            const notes = [];
            for (const f of files) {
                if (!/\.(sav|dat|bkp)$/i.test(f.name)) { notes.push(['note', `${f.name} isn't a Fallout Shelter save, so it was skipped.`]); continue; }
                if (f.size > C.MAX_FILE_BYTES) { notes.push(['err', `${f.name} is over 20 MB, so it wasn't opened.`]); continue; }
                try {
                    const bytes = new Uint8Array(await f.arrayBuffer());
                    const { text, b64 } = await C.decodeFile(bytes);
                    const doc = new C.Doc(f.name, text, b64);
                    const kind = M.kindOf(doc);
                    if (kind === 'unknown') { notes.push(['note', `${f.name} opened, but it isn't a Vault Save or Season File, so it was skipped.`]); continue; }
                    const same = state.files.findIndex((x) => x.name === f.name);
                    if (same >= 0 && state.files[same].doc.isChanged()) { notes.push(['note', `${f.name} is already open with changes, so the new copy was skipped.`]); continue; }
                    const file = { name: f.name, kind, doc, base: M.checkDoc(doc, kind), _v: null };
                    file.v = () => file._v || (file._v = M.readVault(doc));
                    if (same >= 0) state.files[same] = file; else state.files.push(file);
                    const ver = M.versionWarning(doc);
                    if (ver) notes.push(['note', `${f.name} was saved by game version ${ver}. This tool was tested on version 2.6.0, so check it carefully after editing.`]);
                    if (await C.encodeText(text) !== b64) notes.push(['note', `${f.name} has unusual formatting, so a download may differ from it in more than your edits.`]);
                } catch (e) {
                    if (e.code && CORRUPT.has(e.code)) notes.push(['err', `${f.name} is damaged, so it can't be opened. Try its .bkp copy, ${f.name.replace(/\.bkp$/i, '')}.bkp, if you have one.`]);
                    else if (e.code === 'crypto') notes.push(['err', e.message]);
                    else notes.push(['err', `${f.name} couldn't be read. Try choosing it again.`]);
                }
            }
            $('#fosPick').textContent = old;
            $('#fosPick').disabled = false;
            if (state.files.length) {
                state.files.sort((a, b) => (a.kind === b.kind ? a.name.localeCompare(b.name) : a.kind === 'vault' ? -1 : 1));
                if (!state.active || !state.files.includes(state.active)) state.active = state.files.find((x) => x.kind === 'vault') || state.files[0];
                if (wasEmpty) showApp();
                setFile(state.active);
            }
            notes.forEach(([k, t]) => message(k, t));
            drop.classList.remove('over');
        }
        function showApp() {
            $('#fosStart').hidden = true;
            $('#fosApp').hidden = false;
            $('#fosHeroText').prepend($('#fosTitle')); // one h1, moved into the hero
        }

        const pick = $('#fosFile');
        $('#fosPick').addEventListener('click', () => pick.click());
        $('#fosAdd').addEventListener('click', () => pick.click());
        pick.addEventListener('change', () => { loadFiles(pick.files); pick.value = ''; });
        const hasFiles = (e) => e.dataTransfer && [...e.dataTransfer.types].includes('Files');
        document.addEventListener('dragover', (e) => { if (hasFiles(e)) { e.preventDefault(); $('#fosDrop').classList.add('over'); } });
        document.addEventListener('dragleave', (e) => { if (!e.relatedTarget) $('#fosDrop').classList.remove('over'); });
        document.addEventListener('drop', (e) => { if (hasFiles(e)) { e.preventDefault(); loadFiles(e.dataTransfer.files); } });
        $('#fosCopyPath').addEventListener('click', (e) => copy($('#fosPath').textContent, e.target));

        /* ---------- switching ---------- */
        const file = () => state.active;
        function setFile(f) {
            state.active = f;
            const tabs = TABS_BY_KIND[f.kind];
            if (!tabs.includes(state.tab)) state.tab = tabs[0];
            closeDrawer();
            renderChrome();
            renderView();
        }
        function setTab(t) {
            state.tab = t;
            state.query = '';
            $('#fosQ').value = '';
            closeDrawer();
            renderChrome();
            renderView();
        }
        function renderChrome() {
            const f = file(), M = window.FOSModel, C = window.FOSCore;
            const vault = f.kind === 'vault';
            const name = vault ? C.val(C.get(f.doc.root, 'vault', 'VaultName')) : '';
            $('#fosTitle').textContent = vault ? 'Vault ' + (name === undefined || name === '' ? '' : name) : 'Season File';
            const ver = C.val(C.get(f.doc.root, 'appVersion'));
            const mode = vault ? C.val(C.get(f.doc.root, 'vault', 'VaultMode')) : '';
            $('#fosLede').textContent = vault
                ? [f.name, mode ? mode + ' mode' : '', typeof ver === 'string' ? 'version ' + ver.trim() : ''].filter(Boolean).join(' · ')
                : f.name + ' applies to all vaults on this PC.';
            put($('#fosTabs'), html`${TABS_BY_KIND[f.kind].map((t) => html`<a href="#" data-tab="${t}" ${html([state.tab === t ? 'aria-current="page"' : ''])}>${TAB_LABELS[t]}</a>`)}`);
            const fs = $('#fosFiles');
            fs.hidden = state.files.length < 2;
            put(fs, html`${state.files.map((x, i) => html`<button class="${x === f ? 'on' : ''}" data-file="${i}" title="${x.kind === 'vault' ? 'Vault Save' : 'Season File'}">${x.name}${x.doc.isChanged() ? html`<small>changed</small>` : ''}</button>`)}`);
            renderStats();
            renderBar();
        }
        function renderStats() {
            const f = file(), C = window.FOSCore;
            const box = $('#fosStats');
            box.hidden = f.kind !== 'vault';
            if (f.kind !== 'vault') return;
            const v = f.v(), res = (k) => C.get(f.doc.root, 'vault', 'storage', 'resources', k);
            const lunch = C.get(f.doc.root, 'vault', 'LunchBoxesCount');
            const card = (n, label, tab) => {
                const raw = n && n.raw !== undefined ? n.raw : n;
                if (raw === null || raw === undefined || Number(raw) === 0) return '';
                return html`<a class="stat-card" href="#" data-tab="${tab}"><b title="${raw}">${num(Math.round(Number(raw)))}</b><span>${label}</span></a>`;
            };
            put(box, html`${[
                card(v.dwellers.length, 'Dwellers', 'dwellers'), card(v.rooms.length, 'Rooms', 'map'), card(res('Nuka'), 'Caps', 'resources'),
                card(res('NukaColaQuantum'), 'Quantum', 'resources'), card(lunch, 'Lunchboxes', 'resources'),
            ]}`);
        }
        const changedFiles = () => state.files.filter((x) => x.doc.isChanged());
        function renderBar() {
            const f = file(), n = changedFiles().length;
            $('#fosUndo').disabled = !f.doc.canUndo();
            $('#fosRedo').disabled = !f.doc.canRedo();
            $('#fosChangeN').textContent = num(state.files.reduce((a, x) => a + x.doc.changes().length, 0));
            const dl = $('#fosDownload');
            dl.textContent = `Download ${n} changed file${n === 1 ? '' : 's'}`;
            dl.disabled = !n;
            const q = $('#fosQ');
            q.setAttribute('aria-label', `Search ${TAB_LABELS[state.tab]}`);
        }
        function renderView() {
            const el = $('#fosView');
            const scroller = $('.fos-map-scroll', el);
            const pos = scroller ? [scroller.scrollLeft, scroller.scrollTop] : null;
            const t = state.tab;
            if (t === 'raw' && !FOS.views.raw) {
                put(el, html`<p class="note">Loading raw data...</p>`);
                need('raw').then(() => { if (state.tab === 'raw') renderView(); }, () => put(el, html`<p class="err">Couldn't load the raw data view. Check your connection and try again.</p>`));
                return;
            }
            FOS.views[t](el, file());
            const s2 = $('.fos-map-scroll', el);
            if (s2 && pos) { s2.scrollLeft = pos[0]; s2.scrollTop = pos[1]; }
        }

        document.addEventListener('click', (e) => {
            const tab = e.target.closest('[data-tab]');
            if (tab && main.contains(tab)) { e.preventDefault(); setTab(tab.dataset.tab); return; }
            const fb = e.target.closest('[data-file]');
            if (fb) setFile(state.files[Number(fb.dataset.file)]);
        });
        $('#fosQ').addEventListener('input', debounce((e) => { state.query = e.target.value.trim(); renderView(); }, 80));

        /* ---------- edits ---------- */
        // every change goes through Doc.commit. scope says which panel the edit came from, so a focused field isn't rebuilt under the cursor.
        function act(label, ops, scope) {
            try {
                if (!file().doc.commit(label, ops)) return false;
            } catch (e) {
                toast(e.code ? e.message : "That change couldn't be made.", 'err');
                return false;
            }
            afterEdit(scope);
            return true;
        }
        function afterEdit(scope) {
            file()._v = null;
            renderChrome();
            if (scope !== 'view') renderView();
            if (scope !== 'drawer') renderDrawer();
        }
        function undoRedo(which) {
            const f = file();
            if (which === 'undo' ? f.doc.undo() : f.doc.redo()) afterEdit();
        }
        $('#fosUndo').addEventListener('click', () => undoRedo('undo'));
        $('#fosRedo').addEventListener('click', () => undoRedo('redo'));

        /* ---------- drawer ---------- */
        const drawer = $('#fosDrawer');
        let drawerReturn = null;
        function openDrawer(what) {
            state.drawer = what;
            drawerReturn = document.activeElement;
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            renderDrawer();
            drawer.focus();
        }
        function closeDrawer() {
            if (!state.drawer) return;
            state.drawer = null;
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            $('#fosDetail').textContent = '';
            if (drawerReturn && document.contains(drawerReturn)) drawerReturn.focus();
        }
        function renderDrawer() {
            if (state.drawer && !FOS.views.drawer($('#fosDetail'), file(), state.drawer)) closeDrawer();
        }
        $('#fosDrawerClose').addEventListener('click', closeDrawer);

        /* ---------- dialog ---------- */
        const dlg = $('#fosDlg');
        function showDialog(content) {
            put(dlg, content);
            if (!dlg.open) dlg.showModal();
        }
        function closeDialog() { if (dlg.open) dlg.close(); }
        dlg.addEventListener('click', (e) => {
            if (e.target.closest('[data-dlg-close]')) closeDialog();
            const go = e.target.closest('[data-go]');
            if (go) goTo(go.dataset.go);
            const rev = e.target.closest('[data-rev]');
            if (rev) revert(rev.dataset.rev);
            if (e.target.closest('[data-dl-anyway]')) doDownload();
            if (e.target.closest('[data-more]')) { changeLimit += 250; showChanges(); }
        });
        function goTo(key) {
            closeDialog();
            const [kind, fi, id] = key.split(':');
            const f = state.files[Number(fi)];
            if (f !== file()) setFile(f);
            const v = f.v();
            if (kind === 'd') { const d = v.dwellerById.get(Number(id)); if (d) { setTab('dwellers'); openDrawer({ type: 'dweller', node: d.n }); } }
            if (kind === 'r') { const r = v.roomById.get(Number(id)); if (r) { setTab('map'); openDrawer({ type: 'room', node: r.n }); } }
        }

        /* ---------- Changes list ---------- */
        let changeLimit = 250;
        function changeRow(fi, c, i) {
            const C = window.FOSCore;
            const pathText = (n) => C.pathOf(n).join('.');
            const short = (s) => (s.length > 80 ? s.slice(0, 77) + '...' : s);
            if (c.kind === 'set') {
                return html`<div class="fos-chg"><span><b>${c.label}</b></span><button class="btn small" data-rev="${fi}:${i}">Revert</button><span class="mono">${pathText(c.node)}: ${short(c.from)} → ${short(c.to)}</span></div>`;
            }
            const value = C.serialise({ lead: '', v: c.entry.v, trail: '' });
            const where = c.own.up ? pathText(c.own) : '';
            return html`<div class="fos-chg"><span><b>${c.label}</b> <small>${c.kind === 'add' ? 'added' : 'removed'}</small></span><button class="btn small" data-rev="${fi}:${i}">Revert</button><span class="mono">${where}: ${short(value)}</span></div>`;
        }
        function showChanges() {
            const M = window.FOSModel;
            const groups = state.files.map((f, fi) => ({ f, fi, list: f.doc.changes() })).filter((g) => g.list.length);
            const total = groups.reduce((a, g) => a + g.list.length, 0);
            let left = changeLimit;
            const existing = state.files.filter((f) => f.base.length);
            showDialog(html`
                <h2>Changes <span class="muted fos-small">${num(total)}</span></h2>
                ${total ? '' : html`<p class="muted">You haven't changed anything yet.</p>`}
                ${groups.map((g) => {
                    const shown = g.list.slice(0, Math.max(0, left));
                    left -= shown.length;
                    const warns = M.editWarnings(g.f.doc);
                    return html`<h3>${g.f.name}</h3>${warns.length ? html`<ul>${warns.map((w) => html`<li>${w.text}</li>`)}</ul>` : ''}${shown.map((c, i) => changeRow(g.fi, c, i))}`;
                })}
                ${total > changeLimit ? html`<button class="btn small fos-more" data-more>Show more</button>` : ''}
                ${existing.length ? html`<p class="muted fos-note">${existing.map((f) => html`${f.name} came with ${f.base.length} mismatch${f.base.length === 1 ? '' : 'es'} the game accepts. They're left as they are. `)}</p>` : ''}
                <div class="actions"><button class="btn" data-dlg-close>Close</button></div>`);
        }
        function revert(key) {
            const [fi, i] = key.split(':').map(Number);
            const f = state.files[fi];
            const ch = f.doc.changes()[i];
            if (!ch) return;
            try { f.doc.revert(ch); } catch (e) { toast(e.code ? e.message : "That change couldn't be reverted.", 'err'); return; }
            f._v = null;
            renderChrome();
            renderView();
            renderDrawer();
            showChanges();
        }
        $('#fosChanges').addEventListener('click', () => { changeLimit = 250; showChanges(); });

        /* ---------- download ---------- */
        function problemsFor(f, fi) {
            const M = window.FOSModel;
            const added = M.newProblems(f.base, M.checkDoc(f.doc, f.kind));
            const warnings = M.editWarnings(f.doc);
            const link = (p) => (p.go ? (p.go.dweller !== undefined ? `d:${fi}:${p.go.dweller}` : `r:${fi}:${p.go.room}`) : '');
            return { errors: added.filter((p) => p.level === 'error').map((p) => ({ ...p, link: link(p), file: f.name })), warnings: [...warnings, ...added.filter((p) => p.level !== 'error')].map((p) => ({ ...p, file: f.name })) };
        }
        function startDownload() {
            const all = state.files.map((f, i) => (f.doc.isChanged() ? problemsFor(f, i) : { errors: [], warnings: [] }));
            const errors = all.flatMap((p) => p.errors), warnings = all.flatMap((p) => p.warnings);
            if (errors.length) {
                showDialog(html`<h2>Fix these before downloading</h2><p class="muted fos-small">The game could fail to load the save with these in it.</p>
                    <ul>${errors.map((p) => html`<li>${p.link ? html`<button class="fos-linkish" data-go="${p.link}">${p.text}</button>` : p.text} <span class="muted fos-small">${p.file}</span></li>`)}</ul>
                    <div class="actions"><button class="btn" data-dlg-close>Close</button></div>`);
                return;
            }
            if (warnings.length) {
                showDialog(html`<h2>Check these first</h2><p class="muted fos-small">These values are unusual. The game may still accept them.</p>
                    <ul>${warnings.map((p) => html`<li>${p.text} <span class="muted fos-small">${p.file}</span></li>`)}</ul>
                    <div class="actions"><button class="btn primary" data-dl-anyway>Download anyway</button><button class="btn" data-dlg-close>Close</button></div>`);
                return;
            }
            doDownload();
        }
        async function doDownload() {
            closeDialog();
            const C = window.FOSCore;
            const dl = $('#fosDownload');
            dl.disabled = true;
            dl.textContent = 'Preparing...';
            const ready = [];
            try {
                for (const f of changedFiles()) {
                    const text = f.doc.text();
                    const b64 = await C.encodeText(text);
                    // read our own output back before handing it over
                    const back = await C.decodeFile(new TextEncoder().encode(b64));
                    if (back.text !== text) throw new Error('mismatch');
                    ready.push({ f, b64, text });
                }
            } catch (e) {
                toast("Couldn't prepare the download. Nothing was saved.", 'err');
                renderBar();
                return;
            }
            for (const { f, b64, text } of ready) {
                // the link is never added to the page, so no analytics click listener can see the file name
                const a = document.createElement('a');
                const url = URL.createObjectURL(new Blob([b64], { type: 'application/octet-stream' }));
                a.href = url;
                a.download = f.name;
                a.click();
                setTimeout(() => URL.revokeObjectURL(url), 30000);
                f.doc.savedText = text;
                await new Promise((r) => setTimeout(r, 250));
            }
            $('#fosPutBack').hidden = false;
            renderBar();
            toast(`Downloaded ${ready.length} file${ready.length === 1 ? '' : 's'}.`, 'ok');
            $('#fosPutBack').scrollIntoView({ block: 'nearest' });
        }
        $('#fosDownload').addEventListener('click', startDownload);
        window.addEventListener('beforeunload', (e) => {
            if (state.files.some((f) => f.doc.hasUnsaved())) { e.preventDefault(); e.returnValue = ''; }
        });

        /* ---------- keyboard ---------- */
        document.addEventListener('keydown', (e) => {
            if ($('#fosApp').hidden) return;
            const typing = /^(input|textarea|select)$/i.test(e.target.tagName);
            if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey) { e.preventDefault(); $('#fosQ').focus(); return; }
            if (e.key === 'Escape' && !dlg.open && state.drawer && !(FOS.dragging && FOS.dragging())) { closeDrawer(); return; }
            if (!typing && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); undoRedo(e.shiftKey ? 'redo' : 'undo'); }
            if (!typing && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); undoRedo('redo'); }
        }, true);
    });
})();
