<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/books-covers-bulk.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
</head>
<body>
    <?php site_header(); ?>
    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Bulk Cover Downloader</h1>
        <p class="page-lede">Search for a book, then download the covers of every book in its series.</p>

        <div class="bulk-search">
            <label class="visually-hidden" for="searchInput">Search for a book</label>
            <input type="search" class="field" id="searchInput" autocomplete="off"
                placeholder="Search for a book (e.g. Harry Potter, Dune, The Hunger Games)  /">
            <div class="suggest" id="suggestions" role="listbox" hidden></div>
        </div>

        <section class="card" id="resultsSection" hidden>
            <div class="bulk-head">
                <h2 id="resultsTitle">Series books</h2>
                <button type="button" class="btn" id="setsBtn">Find matching sets</button>
                <label class="visually-hidden" for="sizeSelect">Cover size</label>
                <select class="field" id="sizeSelect">
                    <option value="S">Small</option>
                    <option value="M">Medium</option>
                    <option value="L" selected>Large</option>
                </select>
                <button type="button" class="btn" id="openTabsBtn">Open in tabs <span id="tabsCount">0</span></button>
                <button type="button" class="btn primary" id="downloadBtn">Download ZIP <span id="selectedCount">0</span></button>
            </div>
            <p class="note bulk-tip">Covers come from Open Library. If the ZIP fails, use "Open in tabs" and save each cover from its tab.</p>
            <div id="setsPanel" class="sets" hidden></div>
            <div id="booksGrid" class="covers"></div>
        </section>

        <dialog id="coverPicker" class="picker" aria-labelledby="pickerTitle">
            <div class="picker-head">
                <h2 id="pickerTitle">Covers</h2>
                <button type="button" class="btn small" id="pickerClose" aria-label="Close">✕</button>
            </div>
            <div id="pickerGrid" class="picker-grid"></div>
        </dialog>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const { esc, toast } = UI;

        // Open Library search goes through api.php, which caches each answer for a day
        const search = (q, max) => `api.php?q=${encodeURIComponent(q)}&max=${max}`;

        let debounceTimer;
        let currentSeriesBooks = [];

        const searchInput = document.getElementById('searchInput');
        const suggestionsDiv = document.getElementById('suggestions');
        const resultsSection = document.getElementById('resultsSection');
        const booksGrid = document.getElementById('booksGrid');
        const downloadBtn = document.getElementById('downloadBtn');
        const openTabsBtn = document.getElementById('openTabsBtn');
        const sizeSelect = document.getElementById('sizeSelect');
        const selectedCountSpan = document.getElementById('selectedCount');
        const tabsCountSpan = document.getElementById('tabsCount');
        const resultsTitle = document.getElementById('resultsTitle');

        const message = (el, text) => { el.innerHTML = `<p class="msg">${esc(text)}</p>`; };

        // Search input with debounce
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();

            if (query.length < 3) {
                suggestionsDiv.hidden = true;
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchSuggestions(query);
            }, 300);
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.hidden = true;
            }
        });

        // "/" focuses the search, Esc closes the suggestions
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && !picker.open && !/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName)) {
                e.preventDefault();
                searchInput.focus();
            } else if (e.key === 'Escape' && !suggestionsDiv.hidden) {
                suggestionsDiv.hidden = true;
                searchInput.focus();
            }
        });

        // Fetch autocomplete suggestions
        async function fetchSuggestions(query) {
            try {
                message(suggestionsDiv, 'Searching…');
                suggestionsDiv.hidden = false;

                const docs = await olSearch(query);
                // Only show books with covers
                const withCovers = docs.filter(d => d.cover_i);

                if (withCovers.length === 0) {
                    message(suggestionsDiv, docs.length ? 'No books with covers found' : 'No results found');
                    return;
                }

                displaySuggestions(withCovers.slice(0, 10));
            } catch (error) {
                console.error('Error fetching suggestions:', error);
                message(suggestionsDiv, 'Could not load suggestions. Open Library may be busy; try again in a moment.');
            }
        }

        // Open Library search through api.php (cached for a day); throws if Open Library is down
        async function olSearch(q) {
            const response = await fetch(search(q, 40));
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return (await response.json()).docs || [];
        }

        // Display autocomplete suggestions
        function displaySuggestions(docs) {
            suggestionsDiv.innerHTML = '';

            docs.forEach(doc => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'sug';
                item.setAttribute('role', 'option');

                const title = doc.title || 'Unknown Title';
                const authors = doc.author_name?.join(', ') || 'Unknown Author';
                const series = doc.series_name?.[0] ? ` · ${doc.series_name[0]}${doc.series_position?.[0] ? ' #' + doc.series_position[0] : ''}` : '';

                item.innerHTML = `
                    <img src="${esc(getCoverUrl(coverBase(doc.cover_i), 'S'))}" alt="" width="40" height="60">
                    <span><b>${esc(title)}</b><small>${esc(authors + series)}</small></span>
                `;

                item.addEventListener('click', () => {
                    suggestionsDiv.hidden = true;
                    searchInput.value = title;
                    findSeriesBooks(doc);
                });

                suggestionsDiv.appendChild(item);
            });

            suggestionsDiv.hidden = false;
        }

        // Find all books in the series: Open Library's own series data when the book has it,
        // otherwise other books by the same author
        async function findSeriesBooks(doc) {
            try {
                message(booksGrid, 'Finding the rest of the series…');
                resultsSection.hidden = false;
                setsPanel.hidden = true;
                setsBtn.disabled = false;
                setsBtn.textContent = 'Find matching sets';

                const seriesName = doc.series_name?.[0];
                const author = doc.author_name?.[0] || '';
                let books = [];

                if (seriesName) {
                    resultsTitle.textContent = `Series: ${seriesName}`;
                    books = seriesBooks(await olSearch(`series_name:"${seriesName}"`), seriesName);
                }
                if (books.length < 2 && author) {
                    resultsTitle.textContent = 'Books by ' + author;
                    books = (await olSearch(`author:"${author}"`))
                        .filter(d => d.cover_i)
                        .map(d => formatBookData(d, null))
                        .sort((a, b) => (a.year || 9999) - (b.year || 9999));
                }

                if (books.length === 0) {
                    message(booksGrid, 'No series books found. Try searching for another book.');
                    currentSeriesBooks = [];
                    updateSelectedCount();
                    return;
                }

                currentSeriesBooks = books;
                displaySeriesBooks(books);
            } catch (error) {
                console.error('Error finding series:', error);
                message(booksGrid, 'Could not load the series. Open Library may be busy; please try again.');
            }
        }

        // One book per numbered position (box sets like "1-7" are skipped), in series order
        function seriesBooks(docs, seriesName) {
            const byPosition = new Map();
            for (const d of docs) {
                const i = (d.series_name || []).indexOf(seriesName);
                const pos = Number(d.series_position?.[i]);
                if (i < 0 || !d.cover_i || !Number.isFinite(pos) || byPosition.has(pos)) continue;
                byPosition.set(pos, formatBookData(d, pos));
            }
            return [...byPosition.values()].sort((a, b) => a.bookNumber - b.bookNumber);
        }

        const coverBase = (coverId) => `https://covers.openlibrary.org/b/id/${coverId}`;

        // Format book data
        function formatBookData(doc, bookNumber) {
            return {
                id: doc.key,
                title: doc.title || 'Unknown Title',
                authors: doc.author_name || [],
                year: doc.first_publish_year || null,
                bookNumber,
                coverUrl: coverBase(doc.cover_i),
                selected: true
            };
        }

        // Display series books
        function displaySeriesBooks(books) {
            booksGrid.innerHTML = books.map((book, index) => `
                <label class="bc">
                    <input type="checkbox" ${book.selected ? 'checked' : ''} data-index="${index}" aria-label="Include ${esc(book.title)}">
                    ${book.bookNumber ? `<span class="badge">Book ${book.bookNumber}</span>` : ''}
                    ${book.coverUrl
                        ? `<img src="${esc(getCoverUrl(book.coverUrl, 'M'))}" alt="" width="128" height="192" loading="lazy" decoding="async">`
                        : '<span class="no-cover">No cover</span>'}
                    <span class="t">${esc(book.title)}</span>
                    ${book.offSet ? '<span class="off">Not in this set: pick a cover</span>' : ''}
                    <button type="button" class="btn small pick" data-index="${index}">Change cover</button>
                </label>
            `).join('');

            updateSelectedCount();
        }

        // Open Library's cover server drops the odd request when many load at once, so a failed
        // image is retried once after 2s; if that fails too, giveUp() runs
        function retryImage(img, giveUp) {
            if (img.dataset.retried) return giveUp();
            img.dataset.retried = '1';
            setTimeout(() => { img.src = img.src.split('?')[0] + '?retry=1'; }, 2000);
        }

        // A cover that still fails becomes a "No cover" box
        booksGrid.addEventListener('error', (e) => {
            if (e.target.tagName !== 'IMG') return;
            retryImage(e.target, () => {
                const ph = document.createElement('span');
                ph.className = 'no-cover';
                ph.textContent = 'No cover';
                e.target.replaceWith(ph);
            });
        }, true);

        booksGrid.addEventListener('change', (e) => {
            const index = parseInt(e.target.dataset.index);
            if (isNaN(index)) return;
            currentSeriesBooks[index].selected = e.target.checked;
            updateSelectedCount();
        });

        /* ---------- set finder and cover picker ---------- */
        const setsBtn = document.getElementById('setsBtn');
        const setsPanel = document.getElementById('setsPanel');
        const picker = document.getElementById('coverPicker');
        const pickerTitle = document.getElementById('pickerTitle');
        const pickerGrid = document.getElementById('pickerGrid');
        let foundSets = [];

        // Every English edition of a book that has a cover (api.php, cached a week); fetched once per book
        function loadEditions(book) {
            // v= changes whenever api.php's editions format changes, so browsers don't reuse week-old answers
            return book.editionsLoading ??= fetch(`api.php?work=${encodeURIComponent(book.id)}&v=2`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(d => (book.editions = d.editions.filter(ed => sameBook(ed.t, book.title))))
                .catch(err => { book.editionsLoading = null; throw err; });
        }

        // Open Library often leaves an edition's language blank, so translations slip through
        // ("Harry Potter y la piedra filosofal"). Keep editions whose title shares more than half
        // of the book's longer words; UK/US variants ("Sorcerer's Stone") still pass.
        function sameBook(editionTitle, bookTitle) {
            const words = s => (s || '').toLowerCase().normalize('NFD').replace(/[^a-z0-9 ]/g, '').split(/\s+/).filter(w => w.length > 3);
            const want = words(bookTitle);
            if (!want.length || !editionTitle) return true;
            const have = new Set(words(editionTitle));
            return want.filter(w => have.has(w)).length > want.length / 2;
        }

        // Run fn over items, n at a time, so Open Library isn't hit with a whole series at once
        async function pool(items, n, fn) {
            let next = 0;
            await Promise.all(Array.from({ length: n }, async () => {
                while (next < items.length) await fn(items[next++]);
            }));
        }

        // "Bloomsbury Publishing", "Bloomsbury Children's Books" and "BLOOMSBURY" are one publisher
        function publisherKey(name) {
            const letters = s => s.replace(/[^a-z]/g, '');
            const lower = name.toLowerCase();
            return letters(lower.replace(/\b(publishing|publishers?|plc|ltd|limited|inc|llc|co|company|books?|children'?s|press|group|uk|usa?|the)\b/g, '')) || letters(lower);
        }

        // A matching set is one publisher's printing run: group every edition by publisher + year,
        // then keep the groups that cover at least half the books (and at least 2).
        function groupIntoSets(books) {
            const groups = new Map();
            books.forEach((book, i) => (book.editions || []).forEach(ed => {
                if (!ed.p || !ed.y || /audio|cd|mp3/i.test(ed.f)) return; // audiobook art is square
                const key = publisherKey(ed.p) + '|' + ed.y;
                if (!groups.has(key)) groups.set(key, { names: {}, year: ed.y, books: new Map() });
                const g = groups.get(key);
                g.names[ed.p] = (g.names[ed.p] || 0) + 1;
                if (!g.books.has(i)) g.books.set(i, new Map());
                const counts = g.books.get(i);
                counts.set(ed.c[0], (counts.get(ed.c[0]) || 0) + 1);
            }));

            const need = Math.max(2, Math.ceil(books.length / 2));
            const seen = new Set();
            return [...groups.values()]
                .filter(g => g.books.size >= need)
                .map(g => ({
                    label: Object.entries(g.names).sort((a, b) => b[1] - a[1])[0][0] + ' · ' + g.year,
                    year: g.year,
                    // per book, the cover most printings in this run used
                    covers: books.map((_, i) => g.books.has(i) ? [...g.books.get(i)].sort((a, b) => b[1] - a[1])[0][0] : null),
                }))
                .filter(s => !seen.has(s.covers.join()) && seen.add(s.covers.join()))
                .sort((a, b) => b.covers.filter(Boolean).length - a.covers.filter(Boolean).length || b.year - a.year)
                .slice(0, 12);
        }

        setsBtn.addEventListener('click', async () => {
            const books = currentSeriesBooks;
            if (books.length < 2) return toast('The set finder needs at least 2 books', 'err');
            setsBtn.disabled = true;
            let done = 0, failed = 0;
            setsBtn.textContent = `Reading editions 0/${books.length}…`;
            await pool(books, 3, async (book) => {
                try { await loadEditions(book); } catch { failed++; }
                setsBtn.textContent = `Reading editions ${++done}/${books.length}…`;
            });
            if (books !== currentSeriesBooks) return; // another series was loaded meanwhile
            setsBtn.disabled = false;
            setsBtn.textContent = 'Find matching sets';
            if (failed) toast(`Could not read the editions of ${failed} book${failed > 1 ? 's' : ''}; Open Library may be busy.`, 'err');

            foundSets = groupIntoSets(books);
            setsPanel.hidden = false;
            setsPanel.innerHTML = !foundSets.length
                ? '<p class="msg">No matching sets found. Use "Change cover" on each book to pick covers by hand.</p>'
                : `<h3>Matching sets <span class="n">${foundSets.length} found · click one to use it</span></h3>` + foundSets.map((s, k) => `
                    <button type="button" class="setrow" data-set="${k}">
                        <span class="setname"><b>${esc(s.label)}</b><small>${s.covers.filter(Boolean).length} of ${books.length} books</small></span>
                        <span class="strip">${s.covers.map(c => c
                            ? `<img src="${esc(getCoverUrl(coverBase(c), 'S'))}" alt="" width="28" height="42" loading="lazy" decoding="async">`
                            : '<span class="gap" title="Not in this set"></span>').join('')}</span>
                    </button>`).join('');
        });

        setsPanel.addEventListener('click', (e) => {
            const row = e.target.closest('.setrow');
            if (!row) return;
            const set = foundSets[+row.dataset.set];
            currentSeriesBooks.forEach((book, i) => {
                book.offSet = !set.covers[i];
                if (set.covers[i]) book.coverUrl = coverBase(set.covers[i]);
            });
            setsPanel.querySelectorAll('.setrow').forEach(r => r.classList.toggle('on', r === row));
            displaySeriesBooks(currentSeriesBooks);
            const missing = set.covers.filter(c => !c).length;
            toast(missing ? `Set applied. ${missing} book${missing > 1 ? 's are' : ' is'} not in it: use "Change cover" on ${missing > 1 ? 'them' : 'it'}.` : 'Set applied', missing ? 'note' : 'ok');
        });

        // "Change cover" on a card: every cover Open Library has for that book
        booksGrid.addEventListener('click', (e) => {
            const btn = e.target.closest('.pick');
            if (!btn) return;
            e.preventDefault(); // don't toggle the card's checkbox
            openPicker(+btn.dataset.index);
        });

        async function openPicker(index) {
            const book = currentSeriesBooks[index];
            picker.dataset.index = index;
            pickerTitle.textContent = `Covers for ${book.title}`;
            message(pickerGrid, 'Loading every edition…');
            if (!picker.open) picker.showModal();
            try {
                const editions = await loadEditions(book);
                // newest first, front cover of each edition, one tile per distinct cover
                const covers = new Map();
                [...editions].sort((a, b) => (b.y || 0) - (a.y || 0)).forEach(ed => {
                    if (!covers.has(ed.c[0])) covers.set(ed.c[0], [ed.p, ed.y, ed.f].filter(Boolean).join(' · '));
                });
                if (picker.dataset.index !== String(index)) return;
                pickerTitle.textContent = `Covers for ${book.title} (${covers.size})`;
                pickerGrid.innerHTML = covers.size ? [...covers].map(([id, label]) => `
                    <button type="button" class="pc${coverBase(id) === book.coverUrl ? ' on' : ''}" data-cover="${id}">
                        <img src="${esc(getCoverUrl(coverBase(id), 'M'))}" alt="" width="120" height="180" loading="lazy" decoding="async">
                        <small>${esc(label || 'Unknown edition')}</small>
                    </button>`).join('') : '<p class="msg">Open Library has no other English covers for this book.</p>';
            } catch {
                message(pickerGrid, 'Could not load the editions. Open Library may be busy; please try again.');
            }
        }

        pickerGrid.addEventListener('click', (e) => {
            const tile = e.target.closest('.pc');
            if (!tile) return;
            const book = currentSeriesBooks[+picker.dataset.index];
            book.coverUrl = coverBase(tile.dataset.cover);
            book.offSet = false;
            picker.close();
            displaySeriesBooks(currentSeriesBooks);
        });
        // covers Open Library lists but can't serve are dropped from the picker; in set strips they become gaps
        pickerGrid.addEventListener('error', (e) => {
            if (e.target.tagName === 'IMG') retryImage(e.target, () => e.target.closest('.pc')?.remove());
        }, true);
        setsPanel.addEventListener('error', (e) => {
            if (e.target.tagName === 'IMG') retryImage(e.target, () => e.target.replaceWith(Object.assign(document.createElement('span'), { className: 'gap' })));
        }, true);
        document.getElementById('pickerClose').addEventListener('click', () => picker.close());

        // Update selected count
        function updateSelectedCount() {
            const count = currentSeriesBooks.filter(b => b.selected).length;
            selectedCountSpan.textContent = count;
            tabsCountSpan.textContent = count;
            downloadBtn.disabled = count === 0;
            openTabsBtn.disabled = count === 0;
        }

        // Open covers in tabs (alternative to downloading)
        openTabsBtn.addEventListener('click', () => {
            const selectedBooks = currentSeriesBooks.filter(b => b.selected);
            const size = sizeSelect.value;

            if (selectedBooks.length === 0) {
                toast('Select at least one book', 'err');
                return;
            }

            if (selectedBooks.length > 10) {
                const ok = window.confirm(`This will open ${selectedBooks.length} tabs. Your browser may block some pop-ups. Continue?`);
                if (!ok) return;
            }

            selectedBooks.forEach(book => {
                const url = getCoverUrl(book.coverUrl, size);
                if (url) {
                    window.open(url, '_blank');
                }
            });

            toast(`Opened ${selectedBooks.length} covers in new tabs. If some didn't open, check your pop-up blocker.`);
        });

        // Download covers
        downloadBtn.addEventListener('click', async () => {
            const selectedBooks = currentSeriesBooks.filter(b => b.selected);
            const size = sizeSelect.value;

            if (selectedBooks.length === 0) {
                toast('Select at least one book', 'err');
                return;
            }

            downloadBtn.disabled = true;
            const originalText = downloadBtn.innerHTML;
            downloadBtn.textContent = 'Preparing download…';

            try {
                if (selectedBooks.length === 1) {
                    // Single download - open in new tab (avoids CAPTCHA)
                    downloadSingleCover(selectedBooks[0], size);
                    downloadBtn.textContent = 'Opened in a new tab';
                } else {
                    // Multiple downloads as ZIP with delays to avoid rate limiting
                    await downloadAsZip(selectedBooks, size);
                }
            } catch (error) {
                console.error('Download error:', error);
                toast('Could not download the covers. Try fewer books, or use Open in tabs.', 'err');
            }

            setTimeout(() => {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML = originalText;
            }, 2000);
        });

        // Download single cover - open in new tab to avoid CAPTCHA
        function downloadSingleCover(book, size) {
            const url = getCoverUrl(book.coverUrl, size);
            if (!url) {
                toast('No cover available for this book', 'err');
                return;
            }
            window.open(url, '_blank');
        }

        // JSZip loads on the first ZIP download only
        let zipLib;
        function loadJSZip() {
            return zipLib ??= new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
                s.onload = () => resolve(window.JSZip);
                s.onerror = () => { zipLib = null; reject(new Error('Could not load JSZip')); };
                document.head.append(s);
            });
        }

        // Download multiple covers as ZIP with delays
        async function downloadAsZip(books, size) {
            const JSZip = await loadJSZip();
            const zip = new JSZip();
            let successCount = 0;
            let failCount = 0;

            downloadBtn.textContent = `Downloading 0/${books.length}…`;

            for (let i = 0; i < books.length; i++) {
                const book = books[i];
                const url = getCoverUrl(book.coverUrl, size);

                if (!url) {
                    failCount++;
                    continue;
                }

                try {
                    // Add delay between requests to avoid rate limiting (500ms)
                    if (i > 0) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }

                    const response = await fetch(url, { method: 'GET', mode: 'cors' });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const blob = await response.blob();

                    // Check if we got an actual image (not an error page)
                    if (blob.type.startsWith('image/')) {
                        const filename = `${String(i + 1).padStart(2, '0')} - ${sanitizeFilename(book.title)}.jpg`;
                        zip.file(filename, blob);
                        successCount++;
                    } else {
                        failCount++;
                    }

                    downloadBtn.textContent = `Downloading ${i + 1}/${books.length}…`;

                } catch (error) {
                    console.error(`Error downloading cover for ${book.title}:`, error);
                    failCount++;
                }
            }

            if (successCount === 0) {
                toast('No covers downloaded. Open Library may be busy; try fewer books, or use Open in tabs.', 'err');
                return;
            }

            downloadBtn.textContent = 'Creating ZIP…';

            const content = await zip.generateAsync({ type: 'blob' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(content);
            link.download = 'book_series_covers.zip';
            link.click();

            URL.revokeObjectURL(link.href);

            if (failCount > 0) {
                toast(`Downloaded ${successCount} of ${books.length} covers. ${failCount} failed (unavailable on Open Library).`, 'err');
            } else {
                downloadBtn.textContent = `Downloaded ${successCount} covers`;
            }
        }

        // Open Library cover sizes: S, M or L
        function getCoverUrl(baseUrl, size) {
            return baseUrl ? `${baseUrl}-${size}.jpg` : null;
        }

        // Sanitize filename
        function sanitizeFilename(title) {
            return title
                .replace(/[<>:"/\\|?*]/g, '')
                .replace(/\s+/g, ' ')
                .trim()
                .substring(0, 100);
        }
    });
    </script>
    <?php site_footer(); ?>
</body>
</html>
