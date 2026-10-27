// Homebrew sheet formulas: numbers, + - * / ( ), names from `vars` (case-insensitive) and min/max/floor/ceil/round/abs.
// A hand-written parser, so formulas from imported files can never run code.
(function (g) {
    'use strict';
    const FN = { min: Math.min, max: Math.max, floor: Math.floor, ceil: Math.ceil, round: Math.round, abs: Math.abs };

    // returns { v }; throws Error with a plain message on a bad formula
    function calc(src, vars) {
        const toks = String(src == null ? '' : src).match(/\d+(?:\.\d+)?|[A-Za-z_]\w*|\S/g) || [];
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
            return atom();
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

    g.hbCalc = calc;
})(typeof window !== 'undefined' ? window : globalThis);
