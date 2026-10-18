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
                    <option value="1">Thumbnail</option>
                    <option value="3" selected>Medium</option>
                    <option value="5">Large</option>
                </select>
                <button type="button" class="btn" id="openTabsBtn">Open in tabs <span id="tabsCount">0</span></button>
                <button type="button" class="btn primary" id="downloadBtn">Download ZIP <span id="selectedCount">0</span></button>
            </div>
            <p class="note bulk-tip">Google may block automated downloads. If the ZIP fails, use "Open in tabs" and save each cover from its tab.</p>
            <div id="booksGrid" class="covers"></div>
        </section>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const { esc, toast } = UI;

        // Google Books search goes through api.php, which adds the key server-side and caches for a day
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

                const url = search(query, 10);
                const response = await fetch(url);
                const data = await response.json();

                if (!data.items || data.items.length === 0) {
                    message(suggestionsDiv, 'No results found');
                    return;
                }

                // Only show books with covers
                const filteredBooks = data.items.filter(item => item.volumeInfo.imageLinks?.thumbnail);

                if (filteredBooks.length === 0) {
                    message(suggestionsDiv, 'No books with covers found');
                    return;
                }

                displaySuggestions(filteredBooks);
            } catch (error) {
                console.error('Error fetching suggestions:', error);
                message(suggestionsDiv, 'Could not load suggestions');
            }
        }

        // Display autocomplete suggestions
        function displaySuggestions(books) {
            suggestionsDiv.innerHTML = '';

            books.forEach(book => {
                const info = book.volumeInfo;
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'sug';
                item.setAttribute('role', 'option');

                const thumbnail = (info.imageLinks?.thumbnail || '').replace('http://', 'https://');
                const title = info.title || 'Unknown Title';
                const authors = info.authors?.join(', ') || 'Unknown Author';

                item.innerHTML = `
                    ${thumbnail ? `<img src="${esc(thumbnail)}" alt="" width="40" height="60">` : '<span class="ph"></span>'}
                    <span><b>${esc(title)}</b><small>${esc(authors)}</small></span>
                `;

                item.addEventListener('click', () => {
                    suggestionsDiv.hidden = true;
                    searchInput.value = title;
                    findSeriesBooks(book);
                });

                suggestionsDiv.appendChild(item);
            });

            suggestionsDiv.hidden = false;
        }

        // Find all books in the series
        async function findSeriesBooks(selectedBook) {
            try {
                message(booksGrid, 'Finding the rest of the series…');
                resultsSection.hidden = false;

                const info = selectedBook.volumeInfo;
                const title = info.title;
                const authors = info.authors?.[0] || '';

                const seriesName = extractSeriesName(title);

                let books = [];

                if (seriesName) {
                    resultsTitle.textContent = `Series: ${seriesName}`;
                    books = await searchBySeriesName(seriesName, authors);
                } else {
                    resultsTitle.textContent = 'Books by ' + authors;
                    books = await searchByAuthor(authors);
                }

                if (books.length === 0) {
                    message(booksGrid, 'No series books found. Try searching for another book.');
                    currentSeriesBooks = [];
                    updateSelectedCount();
                    return;
                }

                books = sortSeriesBooks(books);
                currentSeriesBooks = books;

                displaySeriesBooks(books);
            } catch (error) {
                console.error('Error finding series:', error);
                message(booksGrid, 'Could not load the series. Please try again.');
            }
        }

        // Extract series name from title
        function extractSeriesName(title) {
            // Remove subtitle after colon first for cleaner matching
            const mainTitle = title.split(':')[0];

            // Pattern 1: "Series Name (Book 1)" or "Series Name #1" or "Series Name, Book 1"
            let match = mainTitle.match(/^(.+?)\s*[\(,#]\s*(?:Book|Vol|Volume|#)?\s*\d+/i);
            if (match) return match[1].trim();

            // Pattern 2: "Series Name Number" (e.g., "Dune 1", "Foundation 3")
            match = mainTitle.match(/^(.+?)\s+\d+$/);
            if (match) return match[1].trim();

            // Pattern 3: "The Series Name Series" (e.g., "The Hunger Games Series")
            match = mainTitle.match(/^(.+?)\s+Series$/i);
            if (match) return match[1].trim();

            // Pattern 4: "Series Name: Subtitle" (assume everything before colon is series)
            if (title.includes(':')) {
                const beforeColon = title.split(':')[0].trim();
                // Only use this if it's not too long (likely not just a title)
                if (beforeColon.length < 50) {
                    return beforeColon;
                }
            }

            return null;
        }

        // Search for books by series name
        async function searchBySeriesName(seriesName, author) {
            // Try multiple search strategies for best results
            const searches = [
                `subject:"${seriesName}" inauthor:"${author}"`,
                `"${seriesName}" inauthor:"${author}"`,
                `${seriesName} ${author}`
            ];

            let allBooks = [];

            for (const query of searches) {
                const url = search(query, 40);

                try {
                    const response = await fetch(url);
                    const data = await response.json();

                    if (data.items) {
                        allBooks = allBooks.concat(data.items);
                    }

                    // If we found good results, don't try other strategies
                    if (allBooks.length >= 5) break;
                } catch (error) {
                    console.error('Search error:', error);
                }
            }

            if (allBooks.length === 0) return [];

            // Remove duplicates by ID
            const uniqueBooks = [];
            const seenIds = new Set();

            for (const item of allBooks) {
                if (!seenIds.has(item.id)) {
                    seenIds.add(item.id);
                    uniqueBooks.push(item);
                }
            }

            // Filter books that likely belong to the series and have covers
            return uniqueBooks
                .filter(item => {
                    const title = item.volumeInfo.title?.toLowerCase() || '';
                    const hasSeriesName = title.includes(seriesName.toLowerCase());
                    const hasCover = item.volumeInfo.imageLinks?.thumbnail;
                    return hasSeriesName && hasCover;
                })
                .map(item => formatBookData(item));
        }

        // Search by author (fallback)
        async function searchByAuthor(author) {
            const url = search(`inauthor:"${author}"`, 40);

            const response = await fetch(url);
            const data = await response.json();

            if (!data.items) return [];

            return data.items
                .filter(item => item.volumeInfo.imageLinks?.thumbnail)
                .map(item => formatBookData(item));
        }

        // Format book data
        function formatBookData(item) {
            const info = item.volumeInfo;
            return {
                id: item.id,
                title: info.title || 'Unknown Title',
                authors: info.authors || [],
                publishedDate: info.publishedDate || '',
                bookNumber: extractBookNumber(info.title || ''),
                coverUrl: info.imageLinks?.thumbnail || '',
                isbn: info.industryIdentifiers?.[0]?.identifier || '',
                selected: true
            };
        }

        // Extract book number from title
        function extractBookNumber(title) {
            const patterns = [
                /(?:Book|Vol|Volume)\s*(\d+)/i,
                /#(\d+)/,
                /\((\d+)\)/,
                /:\s*(\d+)$/
            ];

            for (const pattern of patterns) {
                const match = title.match(pattern);
                if (match) return parseInt(match[1]);
            }

            return null;
        }

        // Sort series books
        function sortSeriesBooks(books) {
            return books.sort((a, b) => {
                if (a.bookNumber !== null && b.bookNumber !== null) {
                    return a.bookNumber - b.bookNumber;
                }
                return (a.publishedDate || '').localeCompare(b.publishedDate || '');
            });
        }

        // Display series books
        function displaySeriesBooks(books) {
            booksGrid.innerHTML = books.map((book, index) => `
                <label class="bc">
                    <input type="checkbox" checked data-index="${index}" aria-label="Include ${esc(book.title)}">
                    ${book.bookNumber ? `<span class="badge">Book ${book.bookNumber}</span>` : ''}
                    ${book.coverUrl
                        ? `<img src="${esc(getCoverUrl(book.coverUrl, 1))}" alt="" width="128" height="192" loading="lazy" decoding="async">`
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
                toast('Could not download the covers. Google may be blocking automated downloads. Try fewer books, or one at a time.', 'err');
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
                toast('No covers downloaded: Google is blocking the requests. Try fewer books, one at a time, or wait a few minutes.', 'err');
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
                toast(`Downloaded ${successCount} of ${books.length} covers. ${failCount} failed (blocked by Google or unavailable).`, 'err');
            } else {
                downloadBtn.textContent = `Downloaded ${successCount} covers`;
            }
        }

        // Get cover URL with size modification
        function getCoverUrl(baseUrl, size) {
            if (!baseUrl) return null;

            let url = baseUrl.replace('http://', 'https://');

            if (url.includes('zoom=')) {
                url = url.replace(/zoom=\d+/, `zoom=${size}`);
            } else {
                url = url.includes('?') ? `${url}&zoom=${size}` : `${url}?zoom=${size}`;
            }

            // Remove edge=curl parameter which can reduce quality
            url = url.replace(/&?edge=curl/g, '');

            return url;
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
