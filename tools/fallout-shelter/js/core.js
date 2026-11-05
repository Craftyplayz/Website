// Fallout Shelter Save Editor: file format and lossless JSON. No DOM here, so _dev/fallout-shelter/selfcheck.js runs it in Node.
// Every edit goes through Doc.apply, which is also where Paid Entitlements are refused.
(function (root) {
    'use strict';

    const MAX_FILE_BYTES = 20 * 1024 * 1024;
    const MAX_DEPTH = 64; // real saves reach 10
    const KEY_HEX = 'A7CA9F3366D892C2F0BEF417341CA971B69AE9F7BACCCFFCF43C62D1D7D021F9';
    const IV_TEXT = 'tu89geji340t89u2';

    class FosError extends Error {
        constructor(code, message) { super(message); this.name = 'FosError'; this.code = code; }
    }

    /* ---------- lossless JSON ----------
       A scalar is { t: 'n' | 's' | 'b' | 'z', raw, up }. A container is { t: 'o' | 'a', items: [entry], inner, up }.
       An entry is { own, v, a, d } plus { k, kr, b, c } in objects, holding the exact whitespace around it:
       a [kr] b : c v d. Keys live in entry lists, so order, duplicates and __proto__ are all just data. */
    const NUM = /-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/y;

    function parse(text, maxDepth = MAX_DEPTH) {
        let p = 0;
        const n = text.length;
        const fail = (m) => { throw new FosError('json', m + ' at character ' + p); };
        const ws = () => {
            const s = p;
            while (p < n) { const c = text.charCodeAt(p); if (c === 32 || c === 10 || c === 13 || c === 9) p++; else break; }
            return p > s ? text.slice(s, p) : '';
        };
        const strTok = () => {
            const s = p++;
            for (; ;) {
                if (p >= n) fail('Unterminated string');
                const c = text.charCodeAt(p);
                if (c === 34) { p++; return text.slice(s, p); }
                if (c < 32) fail('Control character in a string');
                if (c !== 92) { p++; continue; }
                const e = text[p + 1];
                if (e === 'u') { if (!/^[0-9a-fA-F]{4}$/.test(text.substr(p + 2, 4))) fail('Bad \\u escape'); p += 6; }
                else if (e !== undefined && '"\\/bfnrt'.includes(e)) p += 2;
                else fail('Bad escape');
            }
        };
        const value = (depth) => {
            const c = text[p];
            if (c === '{' || c === '[') {
                if (depth >= maxDepth) throw new FosError('depth', 'Nested too deeply');
                const isObj = c === '{', close = isObj ? '}' : ']';
                const node = { t: isObj ? 'o' : 'a', items: [], inner: '', up: null };
                p++;
                const lead = ws();
                if (text[p] === close) { node.inner = lead; p++; return node; }
                let a = lead;
                for (; ;) {
                    const e = { own: node, v: null, a, d: '' };
                    if (isObj) {
                        if (text[p] !== '"') fail('Expected a key');
                        e.kr = strTok();
                        e.k = JSON.parse(e.kr);
                        e.b = ws();
                        if (text[p] !== ':') fail('Expected a colon');
                        p++;
                        e.c = ws();
                    }
                    e.v = value(depth + 1);
                    e.v.up = e;
                    e.d = ws();
                    node.items.push(e);
                    if (text[p] === ',') { p++; a = ws(); continue; }
                    if (text[p] === close) { p++; return node; }
                    fail('Expected a comma or closing bracket');
                }
            }
            if (c === '"') return { t: 's', raw: strTok(), up: null };
            if (text.startsWith('true', p)) { p += 4; return { t: 'b', raw: 'true', up: null }; }
            if (text.startsWith('false', p)) { p += 5; return { t: 'b', raw: 'false', up: null }; }
            if (text.startsWith('null', p)) { p += 4; return { t: 'z', raw: 'null', up: null }; }
            NUM.lastIndex = p;
            const m = NUM.exec(text);
            if (!m) fail('Unexpected value');
            p += m[0].length;
            return { t: 'n', raw: m[0], up: null };
        };
        const bom = text.charCodeAt(0) === 0xfeff ? (p = 1, '﻿') : '';
        const lead = bom + ws();
        if (p >= n) fail('Empty document');
        const v = value(0);
        const trail = ws();
        if (p < n) fail('Text after the end of the document');
        return { lead, v, trail };
    }

    function serialise(tree) {
        const out = [tree.lead];
        const walk = (v) => {
            if (v.t === 'o' || v.t === 'a') {
                const obj = v.t === 'o';
                out.push(obj ? '{' : '[');
                if (!v.items.length) out.push(v.inner);
                v.items.forEach((e, i) => {
                    if (i) out.push(',');
                    out.push(e.a);
                    if (obj) out.push(e.kr, e.b, ':', e.c);
                    walk(e.v);
                    out.push(e.d);
                });
                out.push(obj ? '}' : ']');
            } else out.push(v.raw);
        };
        walk(tree.v);
        out.push(tree.trail);
        return out.join('');
    }

    /* ---------- reading values ---------- */
    // child by key (the last duplicate wins, as it does in a dictionary) or array index
    function get(node, ...steps) {
        for (const s of steps) {
            if (!node) return null;
            if (typeof s === 'number') {
                if (node.t !== 'a') return null;
                const e = node.items[s];
                node = e ? e.v : null;
            } else {
                if (node.t !== 'o') return null;
                let hit = null;
                for (const e of node.items) if (e.k === s) hit = e;
                node = hit ? hit.v : null;
            }
        }
        return node || null;
    }
    // a JS number only when that's exact; very large integers stay as their text
    function isSafeNumber(raw) {
        if (/^-?\d+$/.test(raw)) return Number.isSafeInteger(Number(raw));
        return raw.replace(/[-.]/g, '').replace(/[eE].*$/, '').replace(/^0+/, '').length <= 15 && Number.isFinite(Number(raw));
    }
    function val(node) {
        if (!node) return undefined;
        switch (node.t) {
            case 'n': return isSafeNumber(node.raw) ? Number(node.raw) : node.raw;
            case 's': return JSON.parse(node.raw);
            case 'b': return node.raw === 'true';
            case 'z': return null;
            default: return undefined;
        }
    }
    const items = (node) => (node && (node.t === 'a' || node.t === 'o') ? node.items : []);

    /* ---------- editing values ---------- */
    // new raw text for a number field, in the format of the value it replaces
    function numberLike(oldRaw, input) {
        const s = String(input).trim();
        if (!/^[-+]?\d+(\.\d+)?([eE][-+]?\d+)?$/.test(s)) throw new FosError('value', 'Enter a number.');
        if (/[eE]/.test(oldRaw)) return String(Number(s));
        const dec = /\.(\d+)$/.exec(oldRaw);
        if (!dec) {
            if (/^[-+]?\d+$/.test(s)) return BigInt(s).toString(); // BigInt keeps very large integers exact
            const n = Number(s);
            if (Number.isSafeInteger(n)) return String(n); // 2.0, 1e3
            throw new FosError('value', 'This field holds whole numbers.');
        }
        const n = Number(s);
        if (!Number.isFinite(n) || Math.abs(n) >= 1e21) throw new FosError('value', 'That number is too large.');
        return n.toFixed(dec[1].length);
    }
    const stringRaw = (s) => JSON.stringify(String(s));

    /* ---------- Paid Entitlements ---------- */
    // Any key on the path matching this is locked, in any file and at any depth, in any letter case.
    const LOCKED_KEY = /premium|purchase|entitlement|receipt|transaction|^SeasonPassLunchboxClaims$/i;
    const NOT_ENTITLEMENT = new Set(['lastpremiumupselltime']); // when an advert was last shown, not a purchase

    const lockedKey = (k) => typeof k === 'string' && LOCKED_KEY.test(k) && !NOT_ENTITLEMENT.has(k.toLowerCase());
    const isLockedPath = (path) => path.some(lockedKey);

    function pathOf(node) {
        const path = [];
        for (let e = node.up; e; e = e.own.up) path.unshift(e.own.t === 'o' ? e.k : e.own.items.indexOf(e));
        return path;
    }
    function containsLocked(node) {
        for (const e of items(node)) {
            if (lockedKey(e.k) || containsLocked(e.v)) return true;
        }
        return false;
    }
    // locked by its own path, or by a parent's
    function isLockedNode(node) {
        for (let e = node.up; e; e = e.own.up) if (lockedKey(e.k)) return true;
        return false;
    }
    const LOCK_MESSAGE = "Paid content can't be edited.";

    /* ---------- a loaded file ---------- */
    class Doc {
        constructor(name, text, b64) {
            this.name = name;
            this.original = text;
            this.originalB64 = b64;
            this.tree = parse(text);
            this.history = []; // [{ label, recs }]
            this.cursor = 0;
            this.savedText = text; // what was last downloaded
        }
        get root() { return this.tree.v; }
        text() { return serialise(this.tree); }
        isChanged() { return this.text() !== this.original; }
        hasUnsaved() { return this.text() !== this.savedText; }

        // The one function that changes a tree. The lock check here covers forms, raw edits, bulk actions, undo and revert.
        apply(rec, undoing) {
            const op = undoing ? { set: 'set', ins: 'del', del: 'ins' }[rec.op] : rec.op;
            if (op === 'set') {
                if (isLockedNode(rec.node)) throw new FosError('locked', LOCK_MESSAGE);
                rec.node.raw = undoing ? rec.from : rec.to;
            } else if (op === 'ins') {
                if (isLockedNode(rec.own) || lockedKey(rec.entry.k) || containsLocked(rec.entry.v)) throw new FosError('locked', LOCK_MESSAGE);
                rec.entry.own = rec.own;
                rec.own.items.splice(Math.min(rec.index, rec.own.items.length), 0, rec.entry);
            } else {
                const at = rec.own.items.indexOf(rec.entry);
                if (at < 0) throw new FosError('value', 'That value has already gone.');
                if (isLockedNode(rec.own) || lockedKey(rec.entry.k) || containsLocked(rec.entry.v)) throw new FosError('locked', LOCK_MESSAGE);
                rec.own.items.splice(at, 1);
            }
        }

        // ops: { op: 'set', node, raw } | { op: 'ins', own, index, entry } | { op: 'del', entry }. All apply, or none do.
        commit(label, ops, opts = {}) {
            const recs = [];
            try {
                for (const o of ops) {
                    let rec;
                    if (o.op === 'set') {
                        if (o.raw === o.node.raw) continue;
                        if (!opts.internal) this.checkSet(o.node, o.raw);
                        rec = { op: 'set', node: o.node, from: o.node.raw, to: o.raw };
                    } else if (o.op === 'ins') {
                        rec = { op: 'ins', own: o.own, entry: o.entry, index: o.index === undefined ? o.own.items.length : o.index };
                    } else {
                        rec = { op: 'del', own: o.entry.own, entry: o.entry, index: o.entry.own.items.indexOf(o.entry) };
                    }
                    this.apply(rec, false);
                    recs.push(rec);
                }
            } catch (e) {
                for (const r of recs.reverse()) this.apply(r, true);
                throw e;
            }
            if (!recs.length) return false;
            this.history.length = this.cursor;
            this.history.push({ label, recs });
            this.cursor++;
            return true;
        }
        // a raw edit must be one valid scalar of the kind already there, so it can't smuggle in extra keys
        checkSet(node, raw) {
            if (node.t === 'o' || node.t === 'a') throw new FosError('value', 'Only single values can be edited.');
            let parsed;
            try { parsed = parse(raw).v; } catch (e) { throw new FosError('value', 'That isn\'t a valid value.'); }
            if (parsed.t === 'o' || parsed.t === 'a' || parsed.raw !== raw) throw new FosError('value', 'That isn\'t a valid value.');
            if (node.t === 'z' ? parsed.t === 'o' : parsed.t !== node.t) throw new FosError('value', 'This field keeps its type.');
            if (node.t === 'n' && !/[eE]/.test(node.raw)) {
                const places = (r) => (/\.(\d+)$/.exec(r) || [0, ''])[1].length;
                if (places(raw) !== places(node.raw) || /[eE]/.test(raw)) throw new FosError('value', 'This field keeps its number format.');
            }
        }
        canUndo() { return this.cursor > 0; }
        canRedo() { return this.cursor < this.history.length; }
        undo() {
            if (!this.canUndo()) return false;
            this.cursor--;
            const recs = this.history[this.cursor].recs;
            for (let i = recs.length - 1; i >= 0; i--) this.apply(recs[i], true);
            return true;
        }
        redo() {
            if (!this.canRedo()) return false;
            for (const r of this.history[this.cursor].recs) this.apply(r, false);
            this.cursor++;
            return true;
        }

        // net changes since the file was opened, old against new
        changes() {
            const sets = new Map(), ents = new Map();
            for (let i = 0; i < this.cursor; i++) {
                const h = this.history[i];
                for (const r of h.recs) {
                    if (r.op === 'set') {
                        const m = sets.get(r.node) || { kind: 'set', node: r.node, from: r.from, label: h.label };
                        m.to = r.to;
                        sets.set(r.node, m);
                    } else if (ents.has(r.entry)) ents.delete(r.entry); // added then removed, or removed then put back
                    else ents.set(r.entry, { kind: r.op === 'ins' ? 'add' : 'remove', entry: r.entry, own: r.own, index: r.index, label: h.label });
                }
            }
            return [...[...sets.values()].filter((m) => m.from !== m.to), ...ents.values()];
        }
        revert(change) {
            const op = change.kind === 'set' ? { op: 'set', node: change.node, raw: change.from }
                : change.kind === 'add' ? { op: 'del', entry: change.entry }
                    : { op: 'ins', own: change.own, index: change.index, entry: change.entry };
            return this.commit('Revert: ' + change.label, [op], { internal: true });
        }
    }

    // a new entry from JSON text, copying the whitespace of its neighbours
    function makeEntry(own, key, json) {
        const v = parse(json).v;
        const near = own.items[0];
        const e = { own, v, a: near ? near.a : '', d: near ? near.d : '' };
        if (own.t === 'o') { e.k = key; e.kr = JSON.stringify(key); e.b = near ? near.b : ''; e.c = near ? near.c : ''; }
        v.up = e;
        return e;
    }

    /* ---------- files ---------- */
    let cryptoKey = null;
    async function getKey() {
        if (!root.crypto || !root.crypto.subtle) throw new FosError('crypto', 'This browser can\'t decrypt saves on this connection. Open the page over https.');
        if (!cryptoKey) {
            const bytes = new Uint8Array(KEY_HEX.match(/../g).map((h) => parseInt(h, 16)));
            cryptoKey = await root.crypto.subtle.importKey('raw', bytes, 'AES-CBC', false, ['encrypt', 'decrypt']);
        }
        return cryptoKey;
    }
    const iv = () => new TextEncoder().encode(IV_TEXT);
    const ALGO = () => ({ name: 'AES-CBC', iv: iv() });

    function bytesToB64(u) {
        let s = '';
        for (let i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000));
        return btoa(s);
    }

    // bytes of a file to its text. Each failure has its own code: size, base64, blocks, padding, json, depth.
    async function decodeFile(bytes) {
        if (bytes.length > MAX_FILE_BYTES) throw new FosError('size', 'File is over 20 MB.');
        if (!bytes.length) throw new FosError('base64', 'File is empty.');
        let b64 = '';
        for (let i = 0; i < bytes.length; i += 0x8000) b64 += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        if (b64.length % 4 || !/^[A-Za-z0-9+/]+={0,2}$/.test(b64)) throw new FosError('base64', 'File isn\'t valid Base64.');
        const bin = atob(b64);
        const data = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) data[i] = bin.charCodeAt(i);
        if (!data.length || data.length % 16) throw new FosError('blocks', 'File has a length AES can\'t have.');
        const key = await getKey();
        let plain;
        try { plain = await root.crypto.subtle.decrypt(ALGO(), key, data); } catch (e) { throw new FosError('padding', 'File has bad padding.'); }
        let text;
        try { text = new TextDecoder('utf-8', { fatal: true, ignoreBOM: true }).decode(plain); } catch (e) { throw new FosError('json', 'File isn\'t valid text.'); }
        parse(text); // throws json or depth
        return { text, b64 };
    }
    async function encodeText(text) {
        const key = await getKey();
        return bytesToB64(new Uint8Array(await root.crypto.subtle.encrypt(ALGO(), key, new TextEncoder().encode(text))));
    }

    const api = {
        MAX_FILE_BYTES, MAX_DEPTH, LOCK_MESSAGE, FosError, Doc,
        parse, serialise, get, val, items, isSafeNumber, numberLike, stringRaw, makeEntry,
        pathOf, lockedKey, isLockedPath, isLockedNode, containsLocked, decodeFile, encodeText,
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.FOSCore = api;
})(typeof globalThis !== 'undefined' ? globalThis : this);
