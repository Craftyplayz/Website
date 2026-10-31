// Homebrew sheet formulas: numbers, + - * / ( ), names from `vars` (case-insensitive), min/max/floor/ceil/round/abs,
// and [labels] after a value (`2 [Shield]`) that only name it for the breakdown.
// A hand-written parser, so formulas from imported files can never run code.
(function (g) {
    'use strict';
    const TOK = /\[[^\]]*\]|\d+(?:\.\d+)?|[A-Za-z_]\w*|\S/g;
    const FN = { min: Math.min, max: Math.max, floor: Math.floor, ceil: Math.ceil, round: Math.round, abs: Math.abs };

    // returns { v }; throws Error with a plain message on a bad formula
    function calc(src, vars) {
        const toks = String(src == null ? '' : src).match(TOK) || [];
        let i = 0;
        const peek = () => toks[i];
        const need = (t) => { if (toks[i++] !== t) throw Error('Missing ' + t); };
        function expr() {
            let v = term();
            while (peek() === '+' || peek() === '-') v = toks[i++] === '+' ? v + term() : v - term();
            return v;
        }
        function term() {
            let v = unary();
            while (peek() === '*' || peek() === '/') v = toks[i++] === '*' ? v * unary() : v / unary();
            return v;
        }
        function unary() {
            if (peek() === '-') { i++; return -unary(); }
            if (peek() === '+') { i++; return unary(); }
            const v = atom();
            while (peek() && peek()[0] === '[') i++;
            return v;
        }
        function atom() {
            const t = toks[i++];
            if (t === undefined) throw Error('The formula ends too early');
            if (t === '(') { const v = expr(); need(')'); return v; }
            if (/^\d/.test(t)) return +t;
            if (/^[A-Za-z_]/.test(t)) {
                const k = t.toLowerCase();
                if (FN[k] && peek() === '(') {
                    i++;
                    const args = [expr()];
                    while (peek() === ',') { i++; args.push(expr()); }
                    need(')');
                    return FN[k](...args);
                }
                if (Object.prototype.hasOwnProperty.call(vars, k)) return vars[k];
                throw Error('Unknown name ' + t);
            }
            throw Error('Unexpected ' + t);
        }
        if (!toks.length) return { v: 0 };
        const v = expr();
        if (i < toks.length) throw Error('Unexpected ' + toks[i]);
        if (!isFinite(v)) throw Error('The result is not a number');
        // D&D rounds down
        return { v: Math.floor(v) };
    }

    // split a formula at its top-level + and -: [{ sign: '+'|'-', src, label }] for the builder and breakdowns
    function terms(src) {
        const str = String(src == null ? '' : src), out = [];
        let depth = 0, start = 0, sign = '+', prev = null, label = '', m;
        const push = (end) => { const t = str.slice(start, end).replace(/\[[^\]]*\]/g, '').trim(); if (t) out.push({ sign, src: t, label }); };
        const re = new RegExp(TOK.source, 'g');
        while ((m = re.exec(str))) {
            const t = m[0];
            if (t[0] === '[') { if (!depth) label = t.slice(1, -1).trim(); continue; }
            if (t === '(') depth++;
            if (t === ')') depth--;
            if ((t === '+' || t === '-') && !depth && (prev === null || !/^[-+*/(,]$/.test(prev))) {
                if (prev !== null) push(m.index);
                sign = t; start = m.index + 1; label = ''; prev = t;
                continue;
            }
            prev = t;
        }
        push(str.length);
        return out;
    }

    // ---- coins: "-5g5s" spends 5 gp 5 sp, "+3pp 20c" (or no sign) adds; spending breaks bigger coins for change ----
    const CP = { pp: 1000, gp: 100, ep: 50, sp: 10, cp: 1 };
    const COIN_WORD = { p: 'pp', pp: 'pp', plat: 'pp', platinum: 'pp', g: 'gp', gp: 'gp', gold: 'gp', e: 'ep', ep: 'ep', electrum: 'ep',
        s: 'sp', sp: 'sp', silver: 'sp', c: 'cp', cp: 'cp', copper: 'cp' };
    // returns { purse, text } with the new coin counts, or throws Error with a plain message
    function coins(purse, src) {
        const s = String(src || '').trim().toLowerCase(), sign = s[0] === '-' ? -1 : 1;
        const body = s.replace(/^[+-]\s*/, '').replace(/,/g, ' ');
        const want = { pp: 0, gp: 0, ep: 0, sp: 0, cp: 0 };
        const rest = body.replace(/(\d+)\s*([a-z]+)/g, (m, n, w) => { if (!COIN_WORD[w]) throw new Error(`"${w}" isn't a coin. Use pp, gp, ep, sp or cp.`); want[COIN_WORD[w]] += +n; return ''; });
        if (rest.trim() || !Object.values(want).some(Boolean)) throw new Error('Type an amount like -5g5s to spend or +10gp to add.');
        const p = {}; for (const k in CP) p[k] = Math.max(0, Math.floor(+purse[k] || 0));
        const text = Object.keys(CP).filter((k) => want[k]).map((k) => want[k] + ' ' + k).join(' ');
        if (sign > 0) { for (const k in CP) p[k] += want[k]; return { purse: p, text: 'Added ' + text + '.' }; }
        const cost = Object.keys(CP).reduce((t, k) => t + want[k] * CP[k], 0), have = Object.keys(CP).reduce((t, k) => t + p[k] * CP[k], 0);
        if (cost > have) throw new Error(`Not enough coins: that's ${cost / 100} gp and you have ${have / 100} gp in total.`);
        // pay each coin type from that type first, then cover what's left
        let due = 0;
        for (const k in CP) { const n = Math.min(p[k], want[k]); p[k] -= n; due += (want[k] - n) * CP[k]; }
        const up = Object.keys(CP).reverse(), broke = [];
        while (due > 0) {
            // break the smallest coin that covers what's due, else hand over the biggest coin there is
            const k = up.find((c) => p[c] > 0 && CP[c] >= due) || Object.keys(CP).find((c) => p[c] > 0);
            p[k]--;
            if (CP[k] > due) {
                let change = CP[k] - due; broke.push(k);
                for (const c of ['gp', 'sp', 'cp']) { const n = Math.floor(change / CP[c]); p[c] += n; change -= n * CP[c]; }
                due = 0;
            } else due -= CP[k];
        }
        return { purse: p, text: 'Spent ' + text + '.' + (broke.length ? ` Broke ${broke.length > 1 ? broke.length + ' coins' : 'a ' + broke[0]} for change.` : '') };
    }

    g.hbCoins = coins;
    g.hbCalc = calc;
    g.hbTerms = terms;
})(typeof window !== 'undefined' ? window : globalThis);
