// Fetches every API that needs a secret key (or blocks browser requests) and
// stores the trimmed result as static JSON under data/. Run by
// .github/workflows/update-data.yml on a schedule; keys come from repository secrets.
//   STEAM_KEY, STEAM_ID, RETRO_KEY, HYPIXEL_KEY
const fs = require('fs');
const path = require('path');

const out = path.join(__dirname, '..', 'data');
const { STEAM_KEY, STEAM_ID, RETRO_KEY, HYPIXEL_KEY } = process.env;
const sleep = ms => new Promise(r => setTimeout(r, ms));
let failures = 0;

async function get(url, label) {
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            const res = await fetch(url, { headers: { 'User-Agent': 'craftyplayz-website-data-job' } });
            if (res.ok) return await res.json();
            console.warn(`${label}: HTTP ${res.status}`);
            if (res.status === 429) await sleep(30000);
        } catch (e) {
            console.warn(`${label}: ${e.message}`);
        }
        await sleep(2000);
    }
    failures++;
    return null;
}

function save(file, data) {
    if (data == null) return; // keep the previous copy on failure
    const p = path.join(out, file);
    fs.mkdirSync(path.dirname(p), { recursive: true });
    fs.writeFileSync(p, JSON.stringify(data) + '\n');
}

const SKIP_APPS = [730, 682130, 469820, 1085750, 459820, 1463120];

async function steam() {
    if (!STEAM_KEY || !STEAM_ID) return console.log('steam: no credentials, skipped');
    const base = 'https://api.steampowered.com';
    const owned = await get(`${base}/IPlayerService/GetOwnedGames/v0001/?key=${STEAM_KEY}&steamid=${STEAM_ID}&format=json&include_appinfo=1`, 'steam owned');
    const recent = await get(`${base}/IPlayerService/GetRecentlyPlayedGames/v0001/?key=${STEAM_KEY}&steamid=${STEAM_ID}&format=json`, 'steam recent');
    if (!owned || !owned.response || !owned.response.games) return;
    const games = owned.response.games;
    for (const r of (recent && recent.response && recent.response.games) || []) {
        const g = games.find(x => x.appid === r.appid);
        if (g) g.playtime_2weeks = r.playtime_2weeks;
        else games.push(r);
    }
    games.sort((a, b) => b.playtime_forever - a.playtime_forever);

    const shown = games.filter(g => g.playtime_forever >= 60 && !SKIP_APPS.includes(g.appid));
    for (const g of shown) {
        const ach = await get(`${base}/ISteamUserStats/GetPlayerAchievements/v0001/?appid=${g.appid}&key=${STEAM_KEY}&steamid=${STEAM_ID}&format=json&l=en`, `steam achievements ${g.appid}`);
        const list = ach && ach.playerstats && ach.playerstats.achievements;
        if (list) {
            g.achieved = list.filter(a => a.achieved === 1).length;
            g.total_achievements = list.length;
            failures--; // a game without achievements is not an error
        } else if (ach == null) {
            failures--;
        }
        const detail = {};
        if (list) detail.achievements = list.map(a => ({ name: a.name, description: a.description, achieved: a.achieved, unlocktime: a.unlocktime }));
        const store = await get(`https://store.steampowered.com/api/appdetails?appids=${g.appid}&cc=gb`, `steam store ${g.appid}`);
        if (store && store[g.appid] && store[g.appid].success) {
            const d = store[g.appid].data;
            detail.info = {
                name: d.name, website: d.website, short_description: d.short_description,
                release_date: d.release_date, price: d.price_overview && d.price_overview.final,
                metacritic: d.metacritic
            };
        } else {
            failures--;
        }
        if (detail.info || detail.achievements) save(`steam/games/${g.appid}.json`, detail);
        await sleep(1500); // store API is rate limited
    }
    save('steam/owned.json', { updated: Date.now(), game_count: games.length, games });
}

async function retro() {
    if (!RETRO_KEY) return console.log('retro: no key, skipped');
    const u = 'https://retroachievements.org/API';
    const profile = await get(`${u}/API_GetUserProfile.php?u=CraftyPlayz&y=${RETRO_KEY}`, 'retro profile');
    const completion = await get(`${u}/API_GetUserCompletionProgress.php?u=CraftyPlayz&y=${RETRO_KEY}`, 'retro completion');
    let last = null;
    if (profile && profile.LastGameID) last = await get(`${u}/API_GetGame.php?g=${profile.LastGameID}&y=${RETRO_KEY}`, 'retro last game');
    if (profile && completion) {
        save('retro.json', {
            updated: Date.now(),
            richPresence: profile.RichPresenceMsg || 'No active rich presence',
            lastGame: last && last.Game,
            results: completion.Results
        });
    }
}

const HYPIXEL_PROFILES = [
    '22159541fde841e6a104593e1cb3a456',
    '39ac5666450b4635873573217a72f143',
    'dd5b7e595e7846a983d11f9cafa0cd42',
    'faccdbb77a1f4ed6a5d442dca531089b',
    '8fc496c53ce64a939b377d8d1af1f1cb'
];

async function hypixel() {
    save('hypixel/collections.json', await get('https://api.hypixel.net/v2/resources/skyblock/collections', 'hypixel resources'));
    const bazaar = await get('https://api.hypixel.net/v2/skyblock/bazaar', 'hypixel bazaar');
    const tear = bazaar && bazaar.products && bazaar.products.ENCHANTED_GHAST_TEAR;
    if (tear) save('hypixel/bazaar.json', { ENCHANTED_GHAST_TEAR: { pricePerUnit: tear.sell_summary[0] && tear.sell_summary[0].pricePerUnit } });
    if (!HYPIXEL_KEY) return console.log('hypixel: no key, skipped');

    const members = {};
    const status = {};
    for (const id of HYPIXEL_PROFILES) {
        const data = await get(`https://api.hypixel.net/v2/skyblock/profiles?key=${HYPIXEL_KEY}&uuid=${id}`, `hypixel profiles ${id}`);
        if (data && data.profiles) {
            members[id] = data.profiles.map(p => {
                const m = (p.members && p.members[id]) || {};
                return {
                    cute_name: p.cute_name,
                    player_id: m.player_id,
                    collection: m.collection || {},
                    crafted_generators: (m.player_data && m.player_data.crafted_generators) || [],
                    coin_purse: m.currencies && m.currencies.coin_purse
                };
            });
        }
        const st = await get(`https://api.hypixel.net/status?key=${HYPIXEL_KEY}&uuid=${id}`, `hypixel status ${id}`);
        if (st && st.session) status[id] = st.session.mode || null;
        await sleep(500);
    }
    if (Object.keys(members).length) save('hypixel/members.json', { updated: Date.now(), members, status });

    const ghast = await get(`https://api.hypixel.net/v2/skyblock/profile?key=${HYPIXEL_KEY}&profile=c468bc63-04f8-4dbd-ac21-3f4efb878cc1`, 'hypixel ghast profile');
    const purse = ghast && ghast.profile && ghast.profile.members['22159541fde841e6a104593e1cb3a456'];
    if (purse) save('hypixel/coin-purse.json', { coin_purse: (purse.currencies && purse.currencies.coin_purse) || 0 });
}

async function dnd() {
    // D&D Beyond does not allow browser (CORS) requests
    const data = await get('https://character-service.dndbeyond.com/character/v5/character/113247670', 'dndbeyond');
    if (data && data.data) {
        const c = data.data;
        save('dnd-character.json', { data: { name: c.name, race: { fullName: c.race.fullName }, classes: c.classes.map(k => ({ level: k.level, definition: { name: k.definition.name, description: k.definition.description } })) } });
    }
}

(async () => {
    const jobs = [steam, retro, hypixel, dnd];
    const only = process.argv[2];
    for (const job of jobs) if (!only || only === job.name) await job();
    console.log(failures > 0 ? `finished with ${failures} failed request(s)` : 'done');
})();
