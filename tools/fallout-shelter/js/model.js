// Fallout Shelter Save Editor: what the tree means. Rooms, Dwellers, Assignments, checks and the ops for safe edits. No DOM.
(function (root) {
    'use strict';
    const C = typeof module !== 'undefined' && module.exports ? require('./core.js') : root.FOSCore;
    const { get, val, items, numberLike, makeEntry } = C;

    const FOS_TESTED_VERSIONS = ['2.6.0'];

    // shortcut: names written from the game's menus until the catalogue (milestone 4) supplies them
    const ROOM_NAMES = {
        Entrance: 'Vault Door', FakeWasteland: 'Wasteland', Elevator: 'Elevator', Geothermal: 'Power Generator', Energy2: 'Nuclear Reactor',
        WaterPlant: 'Water Treatment', Water2: 'Water Purification', Cafeteria: 'Diner', Hydroponic: 'Garden', NukaCola: 'Nuka-Cola Bottler',
        Storage: 'Storage Room', MedBay: 'Medbay', ScienceLab: 'Science Lab', LivingQuarters: 'Living Quarters', Overseer: "Overseer's Office",
        Radio: 'Radio Studio', Gym: 'Weight Room', Armory: 'Armory', SuperRoom2: 'Fitness Room', Bar: 'Lounge', Classroom: 'Classroom',
        Dojo: 'Athletics Room', Casino: 'Game Room', WeaponFactory: 'Weapon Workshop', OutfitFactory: 'Outfit Workshop', DesignFactory: 'Theme Workshop',
        DecorationFactory: 'Decoration Workshop', BarberShop: 'Barbershop', UltraciteMining: 'Ultracite Mine', UltraciteWeaponFactory: 'Ultracite Workshop',
    };
    const STATE_BADGES = { RoomEmergency: 'Incident', RoomBurned: 'Burned', RoomRecovery: 'Recovering', RoomFixed: 'Repaired', Building: 'Building', RoomRushing: 'Rushing', RoomRushingWaiting: 'Rushing' };
    const SPECIAL = ['Strength', 'Perception', 'Endurance', 'Charisma', 'Intelligence', 'Agility', 'Luck']; // ESpecialStat: index 0 is None, 1 to 7 are these
    const RESOURCES = { Nuka: 'Caps', Food: 'Food', Energy: 'Power', Water: 'Water', StimPack: 'Stimpaks', RadAway: 'RadAway', Lunchbox: 'Lunchboxes', MrHandy: 'Mr. Handy boxes', PetCarrier: 'Pet carriers', CraftedOutfit: 'Crafted outfits', CraftedWeapon: 'Crafted weapons', NukaColaQuantum: 'Nuka-Cola Quantum', CraftedTheme: 'Crafted themes', DummyUltracite: 'Ultracite', PokerChip: 'Poker chips' };
    // ELunchBoxType. StarterPack and PredefinedPack come from purchases, so they are shown but can't be added or removed.
    const LUNCHBOX_TYPES = [[0, 'Lunchbox', true], [1, 'Mr. Handy box', true], [2, 'Pet carrier', true], [3, 'Starter pack box', false], [4, 'Nuka-Cola Quantum box', true], [5, 'Bought pack', false], [6, 'Victor box', true], [7, 'Curie box', true]];
    const THEMES = [[0, 'None'], [1, 'Christmas'], [2, 'Halloween'], [3, 'Thanksgiving'], [101, 'Brotherhood of Steel'], [102, 'Institute'], [103, 'Minutemen'], [104, 'Railroad'], [105, 'Anniversary party'], [106, 'Vault 33'], [107, 'Ultracite'], [108, 'Sunset Sarsaparilla'], [109, 'Enclave'], [110, 'New Vegas'], [111, 'New Vegas night'], [112, 'Lucky 38 penthouse'], [113, 'Concord']]; // ESpecialTheme
    const RARITIES = ['Common', 'Normal', 'Rare', 'Legendary'];
    const GRID_COLS = 26, GRID_ROWS = 25; // the largest seen on real saves; a save with more just draws more

    const roomName = (type) => (Object.prototype.hasOwnProperty.call(ROOM_NAMES, type) ? ROOM_NAMES[type] : String(type));
    const roomWidth = (type, merge) => (type === 'Elevator' ? 1 : merge * 3); // no overlaps with this on every real save tried
    const ROCK_WIDTH = 2; // rocks sit 2 apart at their tightest and never touch a Room at this width, on a real save
    const LIMIT = 500; // grid values from a hostile file can't make the page draw or walk millions of cells
    const gridInt = (x, max = LIMIT) => (Number.isInteger(x) ? Math.min(Math.max(x, 0), max) : 0);
    const ints = (node) => items(node).map((e) => val(e.v));

    /* ---------- which file is this ---------- */
    function kindOf(doc) {
        if (doc.root.t !== 'o') return 'unknown';
        if (get(doc.root, 'vault') && get(doc.root, 'dwellers')) return 'vault';
        if (get(doc.root, 'seasonsData') || get(doc.root, 'season')) return 'season';
        return 'unknown';
    }
    function versionWarning(doc) {
        const v = val(get(doc.root, 'appVersion'));
        return typeof v === 'string' && !FOS_TESTED_VERSIONS.includes(v.trim()) ? v.trim() : '';
    }

    /* ---------- a snapshot of a vault, rebuilt after each edit ---------- */
    function readVault(doc) {
        const r = doc.root, vault = get(r, 'vault');
        const rooms = items(get(vault, 'rooms')).map((e) => {
            const n = e.v, type = val(get(n, 'type')), merge = gridInt(val(get(n, 'mergeLevel')), 3) || 1;
            return {
                n, type, name: roomName(type), cls: val(get(n, 'class')), merge, level: val(get(n, 'level')),
                row: gridInt(val(get(n, 'row'))), col: gridInt(val(get(n, 'col'))), width: roomWidth(type, merge),
                id: val(get(n, 'deserializeID')), dwellerIds: ints(get(n, 'dwellers')), deadIds: ints(get(n, 'deadDwellers')),
                state: val(get(n, 'currentStateName')), power: val(get(n, 'power')), broken: val(get(n, 'broken')) === true,
                storage: get(n, 'storage'),
            };
        });
        const roomById = new Map(rooms.map((x) => [x.id, x]));
        const wastelandIds = new Set();
        for (const t of items(get(vault, 'wasteland', 'teams'))) for (const d of items(get(t.v, 'dwellers'))) wastelandIds.add(val(d.v));
        const roomOfDweller = new Map();
        for (const x of rooms) for (const id of x.dwellerIds) roomOfDweller.set(id, x);
        const dwellers = items(get(r, 'dwellers', 'dwellers')).map((e) => {
            const n = e.v, id = val(get(n, 'serializeId')), hp = val(get(n, 'health', 'healthValue'));
            return {
                n, id, name: val(get(n, 'name')) || '', lastName: val(get(n, 'lastName')) || '', level: val(get(n, 'experience', 'currentLevel')),
                rarity: val(get(n, 'rarity')), gender: val(get(n, 'gender')), hp, happiness: val(get(n, 'happiness', 'happinessValue')),
                dead: typeof hp === 'number' && hp <= 0, savedRoom: val(get(n, 'savedRoom')),
                inWasteland: wastelandIds.has(id), room: roomOfDweller.get(id) || null,
                special: SPECIAL.map((_, i) => val(get(n, 'stats', 'stats', i + 1, 'value'))),
            };
        });
        const dwellerById = new Map(dwellers.map((x) => [x.id, x]));
        const rocks = items(get(vault, 'rocks')).map((e) => ({ row: gridInt(val(get(e.v, 'r'))), col: gridInt(val(get(e.v, 'c'))) }));
        const cols = Math.max(GRID_COLS, ...rooms.map((x) => x.col + x.width), ...rocks.map((x) => x.col + ROCK_WIDTH));
        const gridRows = Math.max(GRID_ROWS, ...rooms.map((x) => x.row + 1), ...rocks.map((x) => x.row + 1));
        return { doc, rooms, roomById, dwellers, dwellerById, rocks, cols, rows: gridRows, wastelandIds };
    }
    const fullName = (d) => (d.name + ' ' + d.lastName).trim() || 'Dweller ' + d.id;

    /* ---------- Assignments ---------- */
    // Most rooms hold 2 Dwellers per section (6 in a merge 3 room on real saves). The door holds 2 guards whatever its width.
    function capacity(room) {
        if (room.type === 'Elevator' || room.type === 'FakeWasteland' || room.cls === 'Quest') return 0;
        return room.type === 'Entrance' ? 2 : 2 * room.merge;
    }
    function whyNotAssign(v, d, room) {
        if (d.dead) return `${fullName(d)} is dead, so can't be moved.`;
        if (d.inWasteland) return `${fullName(d)} is in the wasteland, so can't be moved.`;
        if (!room) return '';
        if (d.room === room) return `${fullName(d)} is already there.`;
        if (!capacity(room)) return `${room.name} can't have Dwellers assigned.`;
        if (room.dwellerIds.length >= capacity(room)) return `${room.name} is full, with ${room.dwellerIds.length} of ${capacity(room)}.`;
        return '';
    }
    // room null means no Assignment. `assigned` isn't touched: it's false for every Dweller on every real save.
    function moveOps(v, d, room) {
        const why = whyNotAssign(v, d, room);
        if (why) return { why };
        const ops = [];
        if (d.room) {
            const list = get(d.room.n, 'dwellers');
            for (const e of list.items) if (val(e.v) === d.id) ops.push({ op: 'del', entry: e });
        }
        if (room) {
            const list = get(room.n, 'dwellers');
            ops.push({ op: 'ins', own: list, entry: makeEntry(list, null, String(d.id)) });
        }
        const saved = get(d.n, 'savedRoom');
        if (saved) ops.push({ op: 'set', node: saved, raw: String(room ? room.id : -1) });
        return { ops };
    }

    /* ---------- Resources, Lunchboxes, Inventory ---------- */
    function lunchboxOps(doc, type, wanted) {
        const vault = get(doc.root, 'vault'), byType = get(vault, 'LunchBoxesByType'), decks = get(vault, 'LunchBoxDeckNames'), count = get(vault, 'LunchBoxesCount');
        const hold = items(byType).filter((e) => val(e.v) === type);
        const inStep = decks && items(decks).length === items(byType).length; // older saves have an empty deck list
        const ops = [];
        let total = items(byType).length;
        if (wanted > hold.length) {
            for (let i = hold.length; i < wanted; i++) {
                ops.push({ op: 'ins', own: byType, entry: makeEntry(byType, null, String(type)) });
                if (inStep) ops.push({ op: 'ins', own: decks, entry: makeEntry(decks, null, '"Normal"') });
                total++;
            }
        } else {
            for (const e of hold.slice(wanted)) {
                if (inStep) ops.push({ op: 'del', entry: decks.items[byType.items.indexOf(e)] });
                ops.push({ op: 'del', entry: e });
                total--;
            }
        }
        if (count) ops.push({ op: 'set', node: count, raw: String(total) });
        return ops;
    }
    function itemJson(type, id, extra) {
        const base = { id, type, hasBeenAssigned: false, hasRandonWeaponBeenAssigned: false }; // the misspelling is the game's
        if (extra) base.extraData = extra;
        return JSON.stringify(base);
    }
    function addItemOps(doc, type, id, extra) {
        const list = get(doc.root, 'vault', 'inventory', 'items');
        return [{ op: 'ins', own: list, entry: makeEntry(list, null, itemJson(type, id, extra)) }];
    }
    // every id the save itself knows about, so pickers can suggest them before the catalogue exists
    function knownIds(doc) {
        const out = { Weapon: new Set(), Outfit: new Set(), Pet: new Set(), Junk: new Set() };
        const r = doc.root, add = (t, id) => { if (Object.prototype.hasOwnProperty.call(out, t) && typeof id === 'string') out[t].add(id); };
        for (const e of items(get(r, 'vault', 'inventory', 'items'))) add(val(get(e.v, 'type')), val(get(e.v, 'id')));
        for (const e of items(get(r, 'dwellers', 'dwellers'))) {
            for (const [k, t] of [['equipedOutfit', 'Outfit'], ['equipedWeapon', 'Weapon'], ['equippedPet', 'Pet']]) add(t, val(get(e.v, k, 'id')));
        }
        for (const [k, t] of [['weapons', 'Weapon'], ['outfits', 'Outfit'], ['pets', 'Pet'], ['junk', 'Junk']]) for (const e of items(get(r, 'survivalW', k))) add(t, val(e.v));
        return out;
    }

    /* ---------- checks ----------
       Measured when a file opens, because real saves hold mismatches the game accepts. Only problems an edit adds can block a download. */
    function checkVault(v) {
        const out = [];
        const add = (level, key, text, go) => out.push({ level, key, text, go });
        const seenD = new Set(), seenR = new Set();
        for (const d of v.dwellers) {
            if (seenD.has(d.id)) add('error', 'dup-dweller:' + d.id, `Two Dwellers share the ID ${d.id}.`, { dweller: d.id });
            seenD.add(d.id);
        }
        for (const r of v.rooms) {
            if (seenR.has(r.id)) add('error', 'dup-room:' + r.id, `Two Rooms share the ID ${r.id}.`, { room: r.id });
            seenR.add(r.id);
        }
        const where = new Map();
        for (const r of v.rooms) {
            for (const id of r.dwellerIds) {
                if (!v.dwellerById.has(id)) add('error', `ghost:${r.id}:${id}`, `${r.name} lists a Dweller that doesn't exist (ID ${id}).`, { room: r.id });
                if (where.has(id)) add('error', `two-rooms:${id}`, `Dweller ${id} is listed in two Rooms.`, { room: r.id });
                where.set(id, r);
            }
            const cap = capacity(r);
            if (r.dwellerIds.length > cap && (cap || r.dwellerIds.length)) add('error', `full:${r.id}`, `${r.name} has ${r.dwellerIds.length} Dwellers and holds ${cap}.`, { room: r.id });
        }
        for (const d of v.dwellers) {
            const listed = where.get(d.id);
            if (d.savedRoom !== undefined && d.savedRoom !== -1 && !v.roomById.has(d.savedRoom)) add('error', `saved-gone:${d.id}`, `${fullName(d)} is assigned to a Room that doesn't exist.`, { dweller: d.id });
            // a Dweller on a wasteland team keeps a saved Room without being listed in it, on real saves
            if (listed && d.savedRoom !== undefined && d.savedRoom !== listed.id) add('error', `link:${d.id}`, `${fullName(d)} is listed in ${listed.name} but saved as elsewhere.`, { dweller: d.id });
            if (!listed && !d.inWasteland && d.savedRoom !== undefined && d.savedRoom !== -1) add('error', `link:${d.id}`, `${fullName(d)} has a saved Room but isn't in its list.`, { dweller: d.id });
            if (listed && d.inWasteland) add('error', `both:${d.id}`, `${fullName(d)} is in a Room and in the wasteland.`, { dweller: d.id });
        }
        const occ = new Map();
        const place = (row, col, what, key) => {
            const k = row + ':' + col;
            if (occ.has(k)) add('error', `overlap:${k}`, `${what} overlaps ${occ.get(k)} on row ${row}.`, key);
            else occ.set(k, what);
        };
        for (const r of v.rooms) for (let c = r.col; c < r.col + r.width; c++) place(r.row, c, r.name, { room: r.id });
        for (const s of v.rocks) for (let c = s.col; c < s.col + ROCK_WIDTH; c++) place(s.row, c, 'A rock', null);
        return out;
    }
    function checkDoc(doc, kind) {
        return kind === 'vault' ? checkVault(readVault(doc)) : [];
    }
    // value warnings on the edits themselves
    function editWarnings(doc) {
        const out = [];
        for (const c of doc.changes()) {
            if (c.kind !== 'set' || c.node.t !== 'n') continue;
            const n = Number(c.to);
            if (Number.isFinite(n) && n < 0 && Number(c.from) >= 0) out.push({ level: 'warn', key: 'neg:' + C.pathOf(c.node).join('.'), text: `${c.label} is now below zero.` });
        }
        return out;
    }
    function newProblems(base, now) {
        const had = new Set(base.map((p) => p.key));
        return now.filter((p) => !had.has(p.key));
    }

    const api = {
        ROOM_NAMES, STATE_BADGES, SPECIAL, RESOURCES, LUNCHBOX_TYPES, THEMES, RARITIES, FOS_TESTED_VERSIONS,
        roomName, roomWidth, kindOf, versionWarning, readVault, fullName, capacity, whyNotAssign, moveOps,
        lunchboxOps, addItemOps, knownIds, checkVault, checkDoc, editWarnings, newProblems,
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.FOSModel = api;
})(typeof globalThis !== 'undefined' ? globalThis : this);
