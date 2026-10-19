// Storage Sorter: compares a pasted sheet with every survival item, and turns display names into item IDs
// for /give. No DOM here, so _dev/scripts/storage-sorter.test.js can run it in Node.
(function (root) {
    // "Dirt Path[BE]", "Spectral Arrow‌ [JE]" (with a zero-width joiner) -> "dirt path", "spectral arrow"
    const norm = (s) => s.replace(/[​-‍﻿]/g, '').replace(/\s*\[(JE|BE)\]\s*$/i, '').trim().toLowerCase();

    // Map of normalised name -> how often it appears in the sheet. Every cell counts, so extra columns are harmless.
    function parseSheet(raw) {
        const counts = new Map();
        for (const line of raw.split('\n')) {
            for (const cell of line.split(/[\t,]+/)) {
                const key = norm(cell.replace(/^"|"$/g, ''));
                if (key) counts.set(key, (counts.get(key) || 0) + 1);
            }
        }
        return counts;
    }

    // everything in `all` (display names) that the sheet doesn't have, plus the sheet's duplicates
    function compare(all, raw) {
        const sheet = parseSheet(raw);
        const unsorted = all.filter((n) => !sheet.has(norm(n))).sort((a, b) => a.localeCompare(b));
        const duplicates = [...sheet].filter(([, n]) => n > 1).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
        const matched = all.length - unsorted.length;
        return { unsorted, duplicates, matched };
    }

    // display names whose ID isn't just the name in snake_case
    const ALIAS = {
        'banner pattern (creeper charge)': 'creeper_banner_pattern', 'banner pattern (flower charge)': 'flower_banner_pattern',
        'banner pattern (gust)': 'guster_banner_pattern', 'banner pattern (skull charge)': 'skull_banner_pattern',
        'banner pattern (snout)': 'piglin_banner_pattern', 'banner pattern (thing)': 'mojang_banner_pattern',
        'block of lapis lazuli': 'lapis_block', 'deepslate lapis lazuli ore': 'deepslate_lapis_ore', 'lapis lazuli ore': 'lapis_ore',
        'book and quill': 'writable_book', "bottle o' enchanting": 'experience_bottle', "dragon's breath": 'dragon_breath',
        'empty map': 'map', 'eye of ender': 'ender_eye', 'gold nautilus armor': 'golden_nautilus_armor', 'hay bale': 'hay_block',
        "jack o'lantern": 'jack_o_lantern', 'leather cap': 'leather_helmet', 'leather tunic': 'leather_chestplate',
        'leather pants': 'leather_leggings', 'lodestone compass': 'compass', 'nether quartz': 'quartz', 'ominous banner': 'white_banner',
        "rabbit's foot": 'rabbit_foot', 'redstone comparator': 'comparator', 'redstone dust': 'redstone', 'redstone repeater': 'repeater',
        'short grass (grass)': 'short_grass', 'slimeball': 'slime_ball', 'smooth quartz block': 'smooth_quartz', 'steak': 'cooked_beef',
        'turtle shell': 'turtle_helmet', 'vines': 'vine', 'waxed block of copper': 'waxed_copper_block', 'disc fragment': 'disc_fragment_5',
        'music disc (precipe)': 'music_disc_precipice', 'netherite upgrade': 'netherite_upgrade_smithing_template',
    };
    const FAMILY = [
        [/^firework rocket /, () => 'firework_rocket'],
        [/^firework star /, () => 'firework_star'],
        [/^goat horn /, () => 'goat_horn'],
        [/^tipped arrow /, () => 'tipped_arrow'],
        [/ (explorer|village|treasure) map$/, () => 'filled_map'],
        [/^music disc \((.*)\)$/, (m) => 'music_disc_' + slug(m[1])],
        [/^banner pattern \((.*)\)$/, (m) => slug(m[1]) + '_banner_pattern'],
        [/^(.*) armor trim$/, (m) => slug(m[1]) + '_armor_trim_smithing_template'],
        [/^bucket of (.*)$/, (m) => slug(m[1]) + '_bucket'],
        [/^minecart with (.*)$/, (m) => slug(m[1]) + '_minecart'],
        [/^(.*) (boat|raft) with chest$/, (m) => slug(m[1]) + '_chest_' + m[2]],
        [/^block of (.*)$/, (m) => slug(m[1]) + '_block'],
    ];
    const slug = (s) => s.replace(/'/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');

    // "Block of Iron" -> "minecraft:iron_block". `ids` (optional) is the set of known IDs, used to drop "Raw " from raw meat.
    function nameToId(name, ids) {
        const n = norm(name).replace(/^minecraft:/, '');
        if (ALIAS[n]) return 'minecraft:' + ALIAS[n];
        for (const [re, fn] of FAMILY) { const m = n.match(re); if (m) return 'minecraft:' + fn(m); }
        const s = slug(n);
        if (ids && !ids.has(s) && s.startsWith('raw_') && ids.has(s.slice(4))) return 'minecraft:' + s.slice(4);
        return 'minecraft:' + s;
    }

    // One /give per chest: each item gets one full stack in a shulker box, 27 per box, up to 27 boxes per chest,
    // and a new chest starts before a command would pass the command-block limit.
    function giveCommands(names, ids, stackSize, maxLength = 32500) {
        const slots = (list) => list.map((x, i) => `{slot:${i},item:${x}}`).join(',');
        const item = (name) => { const id = nameToId(name, ids); return `{id:"${id}",count:${stackSize(id)}}`; };
        const boxes = [];
        for (let i = 0; i < names.length; i += 27) boxes.push(`{id:"minecraft:shulker_box",count:1,components:{"minecraft:container":[${slots(names.slice(i, i + 27).map(item))}]}}`);
        const cmd = (list) => `/give @s minecraft:chest[container=[${slots(list)}]]`;
        const out = [];
        let cur = [];
        for (const box of boxes) {
            if (cur.length && (cur.length === 27 || cmd([...cur, box]).length > maxLength)) { out.push(cmd(cur)); cur = []; }
            cur.push(box);
        }
        if (cur.length) out.push(cmd(cur));
        return out;
    }

    const api = { norm, parseSheet, compare, nameToId, giveCommands };
    if (typeof module !== 'undefined') module.exports = api; else root.Sorter = api;
})(typeof window !== 'undefined' ? window : globalThis);
