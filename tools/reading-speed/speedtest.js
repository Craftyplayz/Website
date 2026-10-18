// Texts live in texts/<Language>/<Name>.json and are listed in texts/index.json
// (see texts/README.md for the file format).
const TEXTS_DIR = 'texts/';

const $id = (id) => document.getElementById(id);
const languageSelect = $id('language');
const passageSelect = $id('passage');

let manifest = {};
let current = null; // { name, wordCount, ... } of the loaded text
let startTime = 0;
let timerId;

function fileUrl(language, name) {
    return TEXTS_DIR + encodeURIComponent(language) + '/' + encodeURIComponent(name) + '.json';
}

function fillSelect(select, options) {
    select.innerHTML = '';
    for (const value of options) {
        const opt = document.createElement('option');
        opt.value = opt.textContent = value;
        select.appendChild(opt);
    }
}

function countWords(paragraphs) {
    return paragraphs.join(' ').split(/\s+/).filter(Boolean).length;
}

function formatTime(ms) {
    const tenths = Math.floor(ms / 100);
    const minutes = Math.floor(tenths / 600);
    const seconds = Math.floor(tenths / 10) % 60;
    return minutes + ':' + String(seconds).padStart(2, '0') + '.' + (tenths % 10);
}

async function loadManifest() {
    try {
        const res = await fetch(TEXTS_DIR + 'index.json', { cache: 'no-cache' });
        manifest = await res.json();
    } catch (e) {
        $id('info').textContent = 'Could not load texts/index.json';
        return;
    }
    fillSelect(languageSelect, Object.keys(manifest));

    // remember the last choice
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem('speedtest-choice')) || {}; } catch (e) {}
    if (manifest[saved.language]) languageSelect.value = saved.language;
    onLanguageChange(saved.passage);
}

function onLanguageChange(preferredPassage) {
    const passages = manifest[languageSelect.value] || [];
    fillSelect(passageSelect, passages);
    if (passages.includes(preferredPassage)) passageSelect.value = preferredPassage;
    loadPassage();
}

async function loadPassage() {
    const language = languageSelect.value;
    const name = passageSelect.value;
    stop();
    reset();
    current = null;
    $id('start').disabled = true;
    $id('reading').style.display = 'none';
    $id('text').innerHTML = '';
    if (!name) {
        $id('info').textContent = 'No texts for this language yet.';
        return;
    }
    $id('info').textContent = 'Loading...';
    try { localStorage.setItem('speedtest-choice', JSON.stringify({ language, passage: name })); } catch (e) {}

    let data;
    try {
        const res = await fetch(fileUrl(language, name), { cache: 'no-cache' });
        if (!res.ok) throw new Error(res.status);
        data = await res.json();
    } catch (e) {
        $id('info').textContent = 'Could not load "' + name + '" (' + e.message + '). Check the file name and JSON.';
        return;
    }
    // ignore a response for a selection the user already changed away from
    if (language !== languageSelect.value || name !== passageSelect.value) return;

    let paragraphs = data.paragraphs;
    if (!Array.isArray(paragraphs)) paragraphs = String(data.text || '').split(/\n\s*\n/);
    paragraphs = paragraphs.map((p) => p.trim()).filter(Boolean);

    current = {
        name,
        wordCount: Number(data.wordCount) || countWords(paragraphs),
    };

    const reading = $id('reading');
    reading.lang = data.lang || '';
    reading.dir = data.direction || 'auto';
    for (const p of paragraphs) {
        const el = document.createElement('p');
        el.textContent = p;
        $id('text').appendChild(el);
    }

    const details = [];
    if (data.author) details.push('by ' + data.author);
    if (data.source) details.push(data.source);
    details.push(current.wordCount + ' words');
    $id('info').textContent = details.join(' · ');
    $id('start').disabled = false;
}

function update() {
    const elapsed = performance.now() - startTime;
    const minutes = elapsed / 60000;
    $id('time').textContent = formatTime(elapsed);
    $id('speed').textContent = (minutes > 0 ? (current.wordCount / minutes).toFixed(0) : 0);
}

function reset() {
    $id('time').textContent = formatTime(0);
    $id('speed').textContent = '0';
}

function timer() {
    if (!current) return;
    $id('start').disabled = true;
    $id('stop').disabled = false;
    languageSelect.disabled = passageSelect.disabled = true;
    $id('reading').style.display = 'block';
    $id('text').scrollIntoView();
    startTime = performance.now();
    timerId = setInterval(update, 100);
}

function stop() {
    if (!timerId) return;
    clearInterval(timerId);
    timerId = null;
    update();
    $id('stop').disabled = true;
    $id('start').disabled = false;
    languageSelect.disabled = passageSelect.disabled = false;
    $id('start').scrollIntoView();
}

languageSelect.addEventListener('change', () => onLanguageChange());
passageSelect.addEventListener('change', loadPassage);
$id('start').addEventListener('click', timer);
$id('stop').addEventListener('click', stop);
loadManifest();

