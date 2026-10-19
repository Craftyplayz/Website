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
            <div id="booksGrid" class="covers"></div>
        </section>
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
            if (e.key === '/' && !/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName)) {
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
                    <input type="checkbox" checked data-index="${index}" aria-label="Include ${esc(book.title)}">
                    ${book.bookNumber ? `<span class="badge">Book ${book.bookNumber}</span>` : ''}
                    ${book.coverUrl
                        ? `<img src="${esc(getCoverUrl(book.coverUrl, 'M'))}" alt="" width="128" height="192" loading="lazy" decoding="async">`
                        : '<span class="no-cover">No cover</span>'}
                    <span class="t">${esc(book.title)}</span>
                </label>
            `).join('');

            updateSelectedCount();
        }

        // A cover that fails to load becomes a "No cover" box
        booksGrid.addEventListener('error', (e) => {
            if (e.target.tagName !== 'IMG') return;
            const ph = document.createElement('span');
            ph.className = 'no-cover';
            ph.textContent = 'No cover';
            e.target.replaceWith(ph);
        }, true);

        booksGrid.addEventListener('change', (e) => {
            const index = parseInt(e.target.dataset.index);
            if (isNaN(index)) return;
            currentSeriesBooks[index].selected = e.target.checked;
            updateSelectedCount();
        });

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
