// PokeRogue Save Editor: open a .prsv, edit starters, vouchers and eggs, download an importable .prsv
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, num, toast } = UI;
    const P = window.PRSV;
    const main = $('main.prg');
    const SPRITE = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/';
    const CRYPTO = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js';
    const CHUNK = 240;
    const IV_NAMES = ['HP', 'Attack', 'Defence', 'Sp. Atk', 'Sp. Def', 'Speed'];
    const VOUCHERS = ['Regular', 'Plus', 'Premium', 'Golden'];
    const TIERS = ['Common', 'Rare', 'Epic', 'Legendary'];

    let data = null, save = null, fileName = 'pokerogue.prsv', ids = [], shown = CHUNK, filter = 'all', openId = null;

    const loadScript = src => new Promise((ok, fail) => {
        const s = document.createElement('script');
        s.src = src; s.onload = ok; s.onerror = () => fail(new Error('load'));
        document.head.appendChild(s);
    });
    let cryptoReady = null;
    const needCrypto = () => cryptoReady || (cryptoReady = loadScript(CRYPTO).catch(e => { cryptoReady = null; throw e; }));

    const name = id => (data.species[id] || ['#' + id])[0];
    const sprite = (id, shiny) => SPRITE + (shiny ? 'shiny/' : '') + (data.species[id] || [0, id])[1] + '.png';
    const caught = id => Number(save.dexData[id]?.$ca) > 0;
    const shiny = id => P.has(save.dexData[id]?.$ca, P.DEX.SHINY);

    // ---------- open ----------
    async function open(file) {
        try {
            const [text] = await Promise.all([file.text(), data ? null : fetch(main.dataset.data).then(r => { if (!r.ok) throw new Error('data'); return r.json(); }).then(d => { data = d; })]);
            let json = text.trim();
            if (!json.startsWith('{')) {
                await needCrypto();
                json = CryptoJS.AES.decrypt(json, P.KEY).toString(CryptoJS.enc.Utf8);
            }
            const parsed = JSON.parse(json);
            if (!P.isSave(parsed)) throw new Error('notsave');
            save = parsed;
            fileName = file.name.replace(/\s*\(\d+\)(?=\.\w+$)/, '').replace(/\.(json|txt)$/i, '.prsv');
            ids = Object.keys(save.starterData).map(Number).sort((a, b) => a - b);
            $('#prgStart').hidden = true;
            $('#prgApp').hidden = false;
            $('#prgPutBack').hidden = true;
            closeDrawer();
            renderAll();
        } catch (e) {
            toast(e.message === 'notsave' ? "That file isn't a PokeRogue data save. Session saves aren't supported."
                : e.message === 'load' ? "Couldn't load the decryption library. Check your connection and try again."
                    : "Couldn't read that file. Export it again from PokeRogue's Manage Data menu.", 'err');
        }
    }

    // ---------- render ----------
    function renderAll() { renderStats(); renderGrid(); renderVouchers(); renderEggs(); if ($('.prg-raw').open) renderRaw(); }

    function renderStats() {
        const nCaught = ids.filter(caught).length;
        const candies = ids.reduce((s, id) => s + (Number(save.starterData[id].$x) || 0), 0);
        const hours = Math.round((save.gameStats?.playTime || 0) / 3600);
        const cards = [
            ['caught', `${num(nCaught)}<small> / ${num(ids.length)}</small>`, 'starters caught'],
            ['shiny', num(ids.filter(shiny).length), 'shiny starters'],
            ['candies', num(candies), 'candies'],
            ['eggs', num((save.eggs || []).length), 'eggs'],
            ['vouchers', num(Number(save.voucherCounts?.[0]) || 0), 'egg vouchers'],
            ['played', num(hours) + ' h', 'played'],
        ];
        $('#prgStats').innerHTML = cards.map(([k, b, s]) => `<button class="stat-card" type="button" data-stat="${k}" title="Edit ${s}"><b>${b}</b><span>${s}</span><small>Edit</small></button>`).join('');
    }

    // ---------- stat card editors (one dialog, content per card) ----------
    const EGG_WAVES = [10, 25, 50, 100];
    const MAX_EGGS = 99; // shortcut: assumed in-game egg cap, raise if PokeRogue allows more
    const field = (label, k, val, min, max) => `<label>${label} <input class="field" type="number" inputmode="numeric" min="${min}" max="${max}" name="${k}" value="${val}" required></label>`;
    const EDITORS = {
        caught: () => [`Catch every starter`, `<p>Every starter becomes caught with shiny, all abilities, passive, natures, egg moves, perfect IVs and the full cost reduction. Genders and forms stay as they are.</p>`, 'Unlock all'],
        shiny: () => [`Shiny starters`, `<p>Every caught starter gets its shiny unlocked.</p>`, 'Make all shiny'],
        candies: () => [`Candies`, field('Candies for every starter', 'n', 0, 0, 9999) + `<label class="check"><input type="checkbox" name="add" checked> Add to what each starter has (untick to replace)</label>`, 'Give candies'],
        eggs: () => {
            const opts = ids.slice().sort((a, b) => name(a).localeCompare(name(b))).map(id => `<option value="${id}">${esc(name(id))}</option>`).join('');
            const room = MAX_EGGS - (save.eggs || []).length;
            return [`Add eggs`, `<p class="muted prg-small">You have ${num((save.eggs || []).length)} eggs; up to ${num(Math.max(0, room))} more fit.</p>
                <label>Pokémon <select class="field" name="species">${opts}</select></label>
                <div class="cols"><label>Rarity <select class="field" name="tier">${TIERS.map((t, i) => `<option value="${i}">${t}</option>`).join('')}</select></label>
                ${field('How many', 'n', 1, 1, Math.max(1, room))}</div>
                <label class="check"><input type="checkbox" name="shiny"> Shiny</label>`, 'Add eggs'];
        },
        vouchers: () => [`Vouchers`, `<div class="cols">${VOUCHERS.map((v, i) => field(v, 'v' + i, Number(save.voucherCounts?.[i]) || 0, 0, 999)).join('')}</div>`, 'Save vouchers'],
        played: () => [`Play time`, field('Hours played', 'n', Math.round((save.gameStats?.playTime || 0) / 3600), 0, 99999), 'Save play time'],
    };
    const APPLY = {
        caught: () => { ids.forEach(id => P.unlock(save, id)); return `Unlocked ${num(ids.length)} Pokémon.`; },
        shiny: () => {
            const list = ids.filter(caught);
            list.forEach(id => { const d = save.dexData[id]; d.$ca = Number(d.$ca) | P.DEX.SHINY | P.DEX.VARIANT_1; d.$sa = Number(d.$sa) | d.$ca; });
            return `${num(list.length)} starters are now shiny.`;
        },
        candies: f => {
            const n = Number(f.n.value), add = f.add.checked;
            ids.forEach(id => { const st = save.starterData[id]; st.$x = Math.min(9999, (add ? Number(st.$x) || 0 : 0) + n); });
            return add ? `Added ${num(n)} candies to every starter.` : `Every starter has ${num(n)} candies.`;
        },
        eggs: f => {
            save.eggs = save.eggs || [];
            const tier = Number(f.tier.value), n = Math.min(Number(f.n.value), MAX_EGGS - save.eggs.length);
            for (let i = 0; i < n; i++) save.eggs.push({
                id: crypto.getRandomValues(new Uint32Array(1))[0] >>> 1, gachaType: 0, hatchWaves: EGG_WAVES[tier], timestamp: Date.now(),
                tier, sourceType: 0, variantTier: 0, isShiny: f.shiny.checked, species: Number(f.species.value),
                eggMoveIndex: Math.floor(Math.random() * 4), overrideHiddenAbility: false,
            });
            return n > 0 ? `Added ${num(n)} eggs.` : 'No room for more eggs.';
        },
        vouchers: f => { save.voucherCounts = save.voucherCounts || {}; VOUCHERS.forEach((v, i) => { save.voucherCounts[i] = Number(f['v' + i].value); }); return 'Vouchers saved.'; },
        played: f => { save.gameStats = save.gameStats || {}; save.gameStats.playTime = Number(f.n.value) * 3600; return 'Play time saved.'; },
    };
    let editing = null;
    $('#prgStats').addEventListener('click', e => {
        const b = e.target.closest('[data-stat]'); if (!b) return;
        editing = b.dataset.stat;
        const [h2, body, btn] = EDITORS[editing]();
        $('#prgEdit').innerHTML = `<h2>${h2}</h2><form class="form" method="dialog">${body}<div class="actions"><button class="btn primary" value="ok">${btn}</button><button class="btn" value="cancel" formnovalidate>Cancel</button></div></form>`;
        $('#prgEdit').showModal();
    });
    $('#prgEdit').addEventListener('submit', e => {
        if (e.submitter?.value !== 'ok') return;
        toast(APPLY[editing](e.target), 'ok');
        renderAll(); edited();
        if (openId) renderDetail(openId);
    });

    function visible() {
        const q = $('#prgQ').value.trim().toLowerCase();
        return ids.filter(id => (!q || name(id).toLowerCase().includes(q) || String(id) === q)
            && (filter === 'all' || (filter === 'caught' && caught(id)) || (filter === 'missing' && !caught(id)) || (filter === 'shiny' && shiny(id))));
    }

    function tile(id, i) {
        const st = save.starterData[id];
        const lazy = i > 23 ? ' loading="lazy"' : '';
        return `<button class="prg-tile${caught(id) ? '' : ' missing'}" data-id="${id}" type="button">
            <img src="${sprite(id, shiny(id))}" alt="" width="96" height="96" decoding="async"${lazy}>
            <b>${esc(name(id))}</b><small>${num(Number(st.$x) || 0)} candies${shiny(id) ? ' · <i class="prg-shiny">Shiny</i>' : ''}</small></button>`;
    }

    function renderGrid() {
        const list = visible();
        $('#prgGrid').innerHTML = list.slice(0, shown).map(tile).join('') || '<p class="muted">No Pokémon match.</p>';
        $('#prgMore').hidden = list.length <= shown;
        $('#prgCount').textContent = `${num(list.length)} shown`;
    }

    function updateTile(id) {
        const el = $(`#prgGrid [data-id="${id}"]`);
        if (el) el.outerHTML = tile(id, 0);
        renderStats();
    }

    function renderVouchers() {
        $('#prgVouchers').innerHTML = `<div class="cols">${VOUCHERS.map((v, i) =>
            `<label>${v} <input class="field" type="number" inputmode="numeric" min="0" max="999" data-v="${i}" value="${Number(save.voucherCounts?.[i]) || 0}"></label>`).join('')}</div>`;
    }

    function renderEggs() {
        const eggs = save.eggs || [];
        $('#prgEggN').textContent = eggs.length ? num(eggs.length) : '';
        $('#prgEggs').innerHTML = eggs.map((e, i) => {
            const known = e.species && data.species[e.species];
            return `<div class="rowi">${known ? `<img src="${sprite(e.species, e.isShiny)}" alt="" width="40" height="40" loading="lazy">` : '<span class="prg-egg-icon" aria-hidden="true"></span>'}
                <span class="grow">${known ? esc(name(e.species)) : 'Random'} <small class="muted">${TIERS[e.tier] || ''}${e.isShiny ? ' · shiny' : ''}</small></span>
                <label class="prg-wave">Waves <input class="field" type="number" inputmode="numeric" min="1" max="999" data-egg="${i}" value="${Number(e.hatchWaves) || 0}"></label></div>`;
        }).join('') || '<p class="muted">No eggs.</p>';
    }

    function renderRaw() { $('#prgRaw').textContent = JSON.stringify(save, null, 1); }

    // ---------- drawer ----------
    const box = (k, bit, val, label) => `<label class="check"><input type="checkbox" data-bit="${k}:${bit}"${P.has(val, bit) ? ' checked' : ''}> ${esc(label)}</label>`;
    const numIn = (k, val, label, max) => `<label>${label} <input class="field" type="number" inputmode="numeric" min="0" max="${max}" data-num="${k}" value="${Number(val) || 0}"></label>`;

    function renderDetail(id) {
        const dex = save.dexData[id], st = save.starterData[id], D = P.DEX;
        const egg = data.eggMoves[id] || ['Egg move 1', 'Egg move 2', 'Egg move 3', 'Egg move 4'];
        $('#prgDetail').innerHTML = `
            <div class="prg-head"><img src="${sprite(id, shiny(id))}" alt="" width="96" height="96">
                <div class="grow"><h2>${esc(name(id))}</h2><span class="muted mono">#${id}</span></div></div>
            <div class="actions"><button class="btn small" data-act="unlock" type="button">Unlock everything</button></div>
            <form class="form" onsubmit="return false">
            <fieldset><legend>Caught</legend><div class="prg-checks">
                ${box('ca', D.NON_SHINY, dex.$ca, 'Normal')}${box('ca', D.SHINY, dex.$ca, 'Shiny')}
                ${box('ca', D.VARIANT_2, dex.$ca, 'Rare shiny')}${box('ca', D.VARIANT_3, dex.$ca, 'Epic shiny')}
                ${box('ca', D.MALE, dex.$ca, 'Male')}${box('ca', D.FEMALE, dex.$ca, 'Female')}
                ${P.forms(dex).map(n => box('ca', 128 * 2 ** n, dex.$ca, n ? `Form ${n + 1}` : 'Default form')).join('')}
            </div></fieldset>
            <div class="cols">${numIn('x', st.$x, 'Candies', 9999)}${numIn('f', st.$f, 'Friendship', 9999)}</div>
            <div class="cols">
                <label>Cost reduction <select class="field" data-num="vr">${[0, 1, 2].map(v => `<option${Number(st.$vr) === v ? ' selected' : ''}>${v}</option>`).join('')}</select></label>
                ${numIn('wc', st.$wc, 'Classic wins', 9999)}</div>
            <fieldset><legend>Abilities</legend><div class="prg-checks">
                ${box('a', 1, st.$a, 'Ability 1')}${box('a', 2, st.$a, 'Ability 2')}${box('a', 4, st.$a, 'Hidden ability')}
                ${box('pa', 1, st.$pa, 'Passive unlocked')}${box('pa', 2, st.$pa, 'Passive on')}
            </div></fieldset>
            <fieldset><legend>Egg moves</legend><div class="prg-checks">${egg.map((m, i) => box('em', 1 << i, st.$em, m)).join('')}</div></fieldset>
            <fieldset><legend>IVs</legend><div class="prg-ivs">${IV_NAMES.map((n, i) => numIn('i' + i, dex.$i?.[i], n, 31)).join('')}</div></fieldset>
            <fieldset><legend>Natures</legend><div class="prg-checks prg-natures">${data.natures.map((n, i) => box('na', 2 ** (i + 1), dex.$na, n)).join('')}</div></fieldset>
            <div class="cols">${numIn('s', dex.$s, 'Seen', 99999)}${numIn('c', dex.$c, 'Caught', 99999)}${numIn('hc', dex.$hc, 'Hatched', 99999)}</div>
            </form>`;
    }

    function openDrawer(id) {
        openId = id;
        renderDetail(id);
        const d = $('#prgDrawer');
        d.classList.add('open'); d.setAttribute('aria-hidden', 'false'); d.focus();
    }
    function closeDrawer() {
        const d = $('#prgDrawer');
        if (!d.classList.contains('open')) return;
        d.classList.remove('open'); d.setAttribute('aria-hidden', 'true');
        const t = $(`#prgGrid [data-id="${openId}"]`);
        openId = null;
        if (t) t.focus();
    }

    function edited() { $('#prgPutBack').hidden = true; }

    $('#prgDetail').addEventListener('change', e => {
        const t = e.target, id = openId, dex = save.dexData[id], st = save.starterData[id];
        if (t.dataset.bit) {
            const [k, bit] = t.dataset.bit.split(':');
            const obj = k === 'ca' || k === 'na' ? dex : st;
            obj['$' + k] = P.set(obj['$' + k], Number(bit), t.checked);
            if (k === 'ca') dex.$sa = Number(dex.$sa) | dex.$ca;
        } else if (t.dataset.num) {
            const k = t.dataset.num, max = Number(t.max) || 2;
            const v = Math.max(0, Math.min(max, Math.floor(Number(t.value)) || 0));
            t.value = v;
            if (k[0] === 'i') { dex.$i = dex.$i || [0, 0, 0, 0, 0, 0]; dex.$i[Number(k[1])] = v; }
            else if (k === 's' || k === 'c' || k === 'hc') dex['$' + k] = v;
            else st['$' + k] = v;
        } else return;
        edited();
        updateTile(id);
    });
    $('#prgDetail').addEventListener('click', e => {
        if (e.target.closest('[data-act="unlock"]')) { P.unlock(save, openId); renderDetail(openId); updateTile(openId); edited(); }
    });

    // ---------- events ----------
    const pick = () => $('#prgFile').click();
    $('#prgPick').addEventListener('click', pick);
    $('#prgOpen').addEventListener('click', pick);
    $('#prgFile').addEventListener('change', e => { if (e.target.files[0]) open(e.target.files[0]); e.target.value = ''; });
    const drop = $('#prgDrop');
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('over'));
    drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); if (e.dataTransfer.files[0]) open(e.dataTransfer.files[0]); });

    $('#prgGrid').addEventListener('click', e => { const t = e.target.closest('[data-id]'); if (t) openDrawer(Number(t.dataset.id)); });
    $('#prgQ').addEventListener('input', UI.debounce(() => { shown = CHUNK; renderGrid(); }));
    $('#prgFilter').addEventListener('click', e => {
        const b = e.target.closest('[data-f]'); if (!b) return;
        filter = b.dataset.f; shown = CHUNK;
        $('#prgFilter').querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
        renderGrid();
    });
    $('#prgMore').addEventListener('click', () => { shown += CHUNK; renderGrid(); });
    $('#prgClose').addEventListener('click', closeDrawer);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !$('#prgEdit').open) closeDrawer();
        if (e.key === '/' && save && !/INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) { e.preventDefault(); $('#prgQ').focus(); }
    });
    // missing sprite: hide it so the name shows, never a broken-image icon
    main.addEventListener('error', e => { if (e.target.tagName === 'IMG') e.target.style.visibility = 'hidden'; }, true);

    $('#prgVouchers').addEventListener('change', e => {
        const t = e.target; if (!t.dataset.v) return;
        save.voucherCounts = save.voucherCounts || {};
        t.value = Math.max(0, Math.min(999, Math.floor(Number(t.value)) || 0));
        save.voucherCounts[t.dataset.v] = Number(t.value);
        renderStats(); edited();
    });
    $('#prgEggs').addEventListener('change', e => {
        const t = e.target; if (!t.dataset.egg) return;
        t.value = Math.max(1, Math.min(999, Math.floor(Number(t.value)) || 1));
        save.eggs[Number(t.dataset.egg)].hatchWaves = Number(t.value);
        edited();
    });
    $('.prg-raw').addEventListener('toggle', e => { if (e.target.open) renderRaw(); });

    $('#prgDownload').addEventListener('click', async () => {
        const b = $('#prgDownload');
        b.disabled = true; b.textContent = 'Saving...';
        try {
            await needCrypto();
            const blob = new Blob([CryptoJS.AES.encrypt(JSON.stringify(save), P.KEY).toString()], { type: 'application/octet-stream' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob); a.download = fileName;
            a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            $('#prgPutBack').hidden = false;
        } catch (e) {
            toast("Couldn't load the encryption library. Check your connection and try again.", 'err');
        }
        b.disabled = false; b.textContent = 'Download save';
    });
});
