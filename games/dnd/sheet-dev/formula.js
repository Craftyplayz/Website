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

    g.hbCalc = calc;
    g.hbTerms = terms;
})(typeof window !== 'undefined' ? window : globalThis);
