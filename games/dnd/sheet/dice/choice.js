// The dice chosen on the Dice Sets page, kept in this browser so the Homebrew Sheet rolls with them too.
// A choice names each piece by its module and key (paths relative to this folder), so it can be rebuilt anywhere:
// { set: { key, url } or { key, builtin: true } (a plain style of dice3d.js), sound: { key, url } or null (the set's own),
//   nat: { 1, 20: { key, url } }, trails: [{ key, url }] top layer first, anims: { 1, 20: { key, url } } }
const KEY = 'dice-choice';
export const readChoice = () => { try { return JSON.parse(localStorage.getItem(KEY)); } catch (e) { return null; } };
export const saveChoice = (c) => { try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) { } };

// modules load once; one that fails counts as missing, so the rest of the choice still works
const mods = {};
const mod = (u) => (mods[u] ||= import(new URL(u, import.meta.url).href).then((m) => m.default, () => null));

// several trails at once: each die gets every trail, and what a higher layer adds to the scene is drawn over the layers below it
export const layered = (list) => (ctx) => {
    const parts = list.map((t, i) => { const order = 10 + (list.length - i) * 10;
        const scene = Object.assign(Object.create(ctx.scene), {
            add: (...o) => { for (const obj of o) obj.traverse((c) => { c.renderOrder = order; }); return ctx.scene.add(...o); },
            remove: (...o) => ctx.scene.remove(...o) });
        return t.make({ ...ctx, scene }); });
    return { tick: (...a) => parts.forEach((q) => q?.tick?.(...a)), dispose: () => parts.forEach((q) => q?.dispose?.()) };
};
// a chosen nat animation, with the chosen nat sound (or the set's original one) played at its impact
export const withSound = (a, n) => ({ ...a, start(x) {
    a.start?.(x);
    const s = x.audio && (x.st.natSounds?.[n] || x.st.natOriginal?.[n]);
    if (s) { const t0 = x.audio.ac.currentTime; s({ ...x.audio, t0, hit: t0 + (a.impact ?? 0) }); }
} });

// the style to throw with: the set, with the chosen sound, nat sounds, trails and nat animations on it; engine is dice3d.js (for plain styles)
export async function styleFrom(c, engine) {
    const set = c.set.builtin ? { ...(engine.STYLES[c.set.key] || engine.STYLES.purple), key: c.set.key } : { ...((await mod(c.set.url)) || engine.STYLES.purple), key: 'set-' + c.set.key };
    const find = (ref, get) => (ref ? mod(ref.url).then((m) => m && get(m)) : null);
    const [snd, n1, n20, trails, a1, a20] = await Promise.all([
        find(c.sound, (l) => l.find?.((s) => s.key === c.sound.key)),
        find(c.nat?.[1], (m) => m.nat1?.find((s) => s.key === c.nat[1].key)),
        find(c.nat?.[20], (m) => m.nat20?.find((s) => s.key === c.nat[20].key)),
        Promise.all((c.trails || []).map((t) => find(t, (l) => l.find?.((x) => x.key === t.key)))),
        find(c.anims?.[1], (m) => (m.nat1?.key === c.anims[1].key ? m.nat1 : null)),
        find(c.anims?.[20], (m) => (m.nat20?.key === c.anims[20].key ? m.nat20 : null)),
    ]);
    const tr = trails.filter(Boolean);
    return { ...set,
        ...(snd && { sound: { ...set.sound, throw: snd.throw, hit: snd.hit } }),
        natSounds: { 1: n1 && ((x) => n1.play(x)), 20: n20 && ((x) => n20.play(x)) },
        ...(tr.length && { trail: tr.length === 1 ? tr[0].make : layered(tr) }),
        ...((a1 || a20) && { exits: { ...set.exits, ...(a1 && { 'd20:1': withSound(a1, 1) }), ...(a20 && { 'd20:20': withSound(a20, 20) }) } }) };
}
