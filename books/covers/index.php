<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/books-covers.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://covers.openlibrary.org">
</head>
<body>
    <?php site_header(); ?>
    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Book Cover Search</h1>
        <p class="page-lede">Find a book, select the ones you want, then pick and download a cover for each.</p>

        <div class="card">
            <div class="cs-search">
                <label class="visually-hidden" for="searchInput">Book title</label>
                <input type="search" class="field" id="searchInput" placeholder="Book title  /" autocomplete="off">
                <button type="button" class="btn primary" id="searchBtn">Search</button>
            </div>
            <label class="cs-opt"><input type="checkbox" id="englishOnly" checked> Show only English editions</label>
            <div id="status" role="status"></div>
        </div>

        <div id="results" class="results"></div>

        <div id="selectionControls" class="selection-controls" hidden>
            <span class="selection-count"><b id="selectedCount">0</b> selected</span>
            <button type="button" id="clearSelectionBtn" class="btn">Clear</button>
            <button type="button" id="browseCoversBtn" class="btn primary">Browse covers</button>
        </div>

        <dialog id="coverModal" class="cover-dialog" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Loading…</h2>
                <button type="button" class="btn small" id="closeModal" aria-label="Close">✕</button>
            </div>
            <p class="modal-progress" id="modalProgress"></p>
            <label class="cs-opt modal-filters"><input type="checkbox" id="englishCoversOnly" checked> Show only English edition covers</label>
            <div id="coversContainer"></div>
            <div class="modal-nav">
                <button type="button" class="btn" id="prevBookBtn">← Previous book</button>
                <button type="button" class="btn" id="nextBookBtn">Next book →</button>
            </div>
        </dialog>

        <p class="attribution">Book data and covers from <a href="https://openlibrary.org" target="_blank" rel="noopener">Open Library</a>.</p>
    </main>
    <script>
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const englishOnly = document.getElementById('englishOnly');
        const statusDiv = document.getElementById('status');
        const resultsDiv = document.getElementById('results');
        const selectionControls = document.getElementById('selectionControls');
        const selectedCountSpan = document.getElementById('selectedCount');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const browseCoversBtn = document.getElementById('browseCoversBtn');
        const coverModal = document.getElementById('coverModal');
        const closeModal = document.getElementById('closeModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalProgress = document.getElementById('modalProgress');
        const coversContainer = document.getElementById('coversContainer');
        const prevBookBtn = document.getElementById('prevBookBtn');
        const nextBookBtn = document.getElementById('nextBookBtn');
        const englishCoversOnly = document.getElementById('englishCoversOnly');

        let selectedBooks = [];
        let currentBookIndex = 0;
        let allBooksData = [];

        // Search on button click
        searchBtn.addEventListener('click', performSearch);

        // Search on Enter key
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        // "/" focuses the search (Esc closing the dialog is built in)
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && !coverModal.open && !/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName)) {
                e.preventDefault();
                searchInput.focus();
            }
        });

        // English covers filter change
        englishCoversOnly.addEventListener('change', () => {
            if (coverModal.open) {
                loadBookCovers();
            }
        });

        // Clear selection
        clearSelectionBtn.addEventListener('click', () => {
            selectedBooks = [];
            updateSelectionUI();
            document.querySelectorAll('.book-card').forEach(card => {
                card.classList.remove('selected');
                            card.setAttribute('aria-pressed', 'false');
            });
        });

        // Browse covers
        browseCoversBtn.addEventListener('click', () => {
            if (selectedBooks.length > 0) {
                currentBookIndex = 0;
                openCoverBrowser();
            }
        });

        // Close modal
        closeModal.addEventListener('click', () => {
            coverModal.close();
        });

        // Navigation buttons
        prevBookBtn.addEventListener('click', () => {
            if (currentBookIndex > 0) {
                currentBookIndex--;
                loadBookCovers();
            }
        });

        nextBookBtn.addEventListener('click', () => {
            if (currentBookIndex < selectedBooks.length - 1) {
                currentBookIndex++;
                loadBookCovers();
            }
        });

        async function performSearch() {
            const query = searchInput.value.trim();
            
            if (!query) {
                showStatus('Please enter a book title', 'error');
                return;
            }

            showStatus('Searching for books...', 'loading');
            searchBtn.disabled = true;
            resultsDiv.innerHTML = '';
            selectedBooks = [];
            updateSelectionUI();

            try {
                let searchUrl = `https://openlibrary.org/search.json?title=${encodeURIComponent(query)}&limit=50`;
                
                if (englishOnly.checked) {
                    searchUrl += '&language=eng';
                }

                const response = await fetch(searchUrl);
                
                if (!response.ok) {
                    throw new Error('Search failed');
                }

                const data = await response.json();

                if (data.numFound === 0) {
                    showStatus('No books found. Try a different search term.', 'error');
                    return;
                }

                const booksWithCovers = data.docs.filter(book => book.cover_i);

                if (booksWithCovers.length === 0) {
                    showStatus(`Found ${data.numFound} books, but none have cover images available.`, 'error');
                    return;
                }

                allBooksData = booksWithCovers;
                showStatus(`Found ${booksWithCovers.length} books with covers. Click to select books, then browse their covers.`, 'success');
                displayBooks(booksWithCovers);

            } catch (error) {
                showStatus('An error occurred while searching. Please try again.', 'error');
                console.error('Search error:', error);
            } finally {
                searchBtn.disabled = false;
            }
        }

        function displayBooks(books) {
            resultsDiv.innerHTML = '';

            books.forEach((book, index) => {
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'book-card';
                card.setAttribute('aria-pressed', 'false');
                card.dataset.index = index;

                const coverUrl = book.cover_i 
                    ? `https://covers.openlibrary.org/b/id/${book.cover_i}-L.jpg`
                    : null;

                let coverElement;
                if (coverUrl) {
                    coverElement = `<img src="${coverUrl}" alt="" class="book-cover" width="180" height="270" loading="lazy" decoding="async">`;
                } else {
                    coverElement = `<div class="no-cover">No Cover Available</div>`;
                }

                const authors = book.author_name 
                    ? book.author_name.join(', ')
                    : 'Unknown Author';

                const year = book.first_publish_year || '';

                card.innerHTML = `
                    ${coverElement}
                    <span class="book-title">${UI.esc(book.title)}</span>
                    <span class="book-author">${UI.esc(authors)}</span>
                    ${year ? `<span class="book-year">First published: ${UI.esc(year)}</span>` : ''}
                `;

                card.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleBookSelection(index, card);
                });

                resultsDiv.appendChild(card);
            });
        }

        function toggleBookSelection(index, cardElement) {
            const bookIndex = selectedBooks.indexOf(index);
            
            if (bookIndex > -1) {
                selectedBooks.splice(bookIndex, 1);
                cardElement.classList.remove('selected');
                cardElement.setAttribute('aria-pressed', 'false');
            } else {
                selectedBooks.push(index);
                cardElement.classList.add('selected');
                cardElement.setAttribute('aria-pressed', 'true');
            }
            
            updateSelectionUI();
        }

        function updateSelectionUI() {
            selectedCountSpan.textContent = selectedBooks.length;
            
            selectionControls.hidden = selectedBooks.length === 0;
        }

        async function openCoverBrowser() {
            if (!coverModal.open) coverModal.showModal();
            await loadBookCovers();
        }

        async function loadBookCovers() {
            const bookIndex = selectedBooks[currentBookIndex];
            const book = allBooksData[bookIndex];
            
            modalTitle.textContent = book.title;
            modalProgress.textContent = `Book ${currentBookIndex + 1} of ${selectedBooks.length}`;
            coversContainer.innerHTML = '<p class="cs-msg">Loading covers…</p>';
            
            // Update navigation buttons
            prevBookBtn.disabled = currentBookIndex === 0;
            nextBookBtn.disabled = currentBookIndex === selectedBooks.length - 1;

            try {
                // Fetch all editions for this work
                const workKey = book.key;
                const editionsUrl = `https://openlibrary.org${workKey}/editions.json?limit=100`;
                
                const response = await fetch(editionsUrl);
                const data = await response.json();
                
                // Collect all series numbers we find for this work to determine the most common one
                const seriesNumberCounts = {};
                
                // First pass: collect all series numbers to find the most common
                if (data.entries) {
                    data.entries.forEach(edition => {
                        if (edition.series && edition.series.length > 0) {
                            const seriesText = edition.series.join(' ');
                            const numberMatch = seriesText.match(/#(\d+)|Book\s+(\d+)|Vol\.?\s*(\d+)|Volume\s+(\d+)/i);
                            if (numberMatch) {
                                const num = parseInt(numberMatch[1] || numberMatch[2] || numberMatch[3] || numberMatch[4]);
                                seriesNumberCounts[num] = (seriesNumberCounts[num] || 0) + 1;
                            }
                        }
                    });
                }
                
                // Determine the most common series number (if any)
                let mostLikelySeriesNumber = null;
                let maxCount = 0;
                for (const [num, count] of Object.entries(seriesNumberCounts)) {
                    if (count > maxCount) {
                        maxCount = count;
                        mostLikelySeriesNumber = parseInt(num);
                    }
                }
                
                // If no series number found in editions, try to extract from the main book title
                if (mostLikelySeriesNumber === null) {
                    mostLikelySeriesNumber = extractSeriesNumberFromTitle(book.title);
                }
                
                // Second pass: collect editions with cover IDs
                const editionsWithCovers = [];
                
                if (data.entries) {
                    data.entries.forEach(edition => {
                        const shouldInclude = !englishCoversOnly.checked || 
                            (edition.languages && edition.languages.some(lang => 
                                lang.key === '/languages/eng' || lang.key === 'eng'
                            ));
                        
                        if (shouldInclude && edition.covers && edition.covers.length > 0) {
                            edition.covers.forEach(coverId => {
                                if (coverId) {
                                    let year = null;
                                    if (edition.publish_date) {
                                        const yearMatch = edition.publish_date.match(/\d{4}/);
                                        if (yearMatch) {
                                            year = parseInt(yearMatch[0]);
                                        }
                                    }
                                    
                                    editionsWithCovers.push({
                                        coverId: coverId,
                                        year: year,
                                        seriesNumber: mostLikelySeriesNumber,
                                        title: edition.title || book.title
                                    });
                                }
                            });
                        }
                    });
                }

                // Also add the main cover from the work
                if (book.cover_i) {
                    const shouldIncludeMainCover = !englishCoversOnly.checked || 
                        (book.language && book.language.includes('eng'));
                    
                    if (shouldIncludeMainCover || !englishCoversOnly.checked) {
                        editionsWithCovers.push({
                            coverId: book.cover_i,
                            year: book.first_publish_year || null,
                            seriesNumber: mostLikelySeriesNumber,
                            title: book.title
                        });
                    }
                }

                if (editionsWithCovers.length === 0) {
                    coversContainer.innerHTML = '<p class="cs-msg">No covers found for this book with the current filters.</p>';
                    return;
                }

                // Remove duplicates based on coverId
                const uniqueEditions = [];
                const seenCoverIds = new Set();
                editionsWithCovers.forEach(edition => {
                    if (!seenCoverIds.has(edition.coverId)) {
                        seenCoverIds.add(edition.coverId);
                        uniqueEditions.push(edition);
                    }
                });

                // Sort by series number first (if available), then by year
                uniqueEditions.sort((a, b) => {
                    // If both have series numbers, sort by series number
                    if (a.seriesNumber !== null && b.seriesNumber !== null) {
                        return a.seriesNumber - b.seriesNumber;
                    }
                    // Items with series numbers come first
                    if (a.seriesNumber !== null) return -1;
                    if (b.seriesNumber !== null) return 1;
                    
                    // Fall back to year sorting
                    if (a.year === null && b.year === null) return 0;
                    if (a.year === null) return 1;
                    if (b.year === null) return -1;
                    return a.year - b.year;
                });

                // Display all covers with sequential numbering
                displayCovers(uniqueEditions, book.title);

            } catch (error) {
                console.error('Error loading covers:', error);
                coversContainer.innerHTML = '<p class="err">Could not load covers. Please try again.</p>';
            }
        }
        
        function extractSeriesNumberFromTitle(title) {
            if (!title) return null;
            
            // Common patterns for Harry Potter books
            const patterns = [
                /Philosopher'?s Stone/i,           // Book 1
                /Sorcerer'?s Stone/i,              // Book 1 (US)
                /Chamber of Secrets/i,             // Book 2
                /Prisoner of Azkaban/i,            // Book 3
                /Goblet of Fire/i,                 // Book 4
                /Order of the Phoenix/i,           // Book 5
                /Half-Blood Prince/i,              // Book 6
                /Deathly Hallows/i                 // Book 7
            ];
            
            for (let i = 0; i < patterns.length; i++) {
                if (patterns[i].test(title)) {
                    return i + 1;
                }
            }
            
            // Generic patterns like "Book 3", "#5", "Volume 2"
            const numberMatch = title.match(/#(\d+)|Book\s+(\d+)|Vol\.?\s*(\d+)|Volume\s+(\d+)/i);
            if (numberMatch) {
                return parseInt(numberMatch[1] || numberMatch[2] || numberMatch[3] || numberMatch[4]);
            }
            
            return null;
        }

        function displayCovers(editions, bookTitle) {
            coversContainer.innerHTML = '';
            
            const grid = document.createElement('div');
            grid.className = 'covers-grid';

            editions.forEach((edition, index) => {
                const coverItem = document.createElement('button');
                coverItem.type = 'button';
                coverItem.className = 'cover-item';
                coverItem.title = 'Download this cover';
                
                const coverUrl = `https://covers.openlibrary.org/b/id/${edition.coverId}-L.jpg`;
                
                coverItem.innerHTML = `
                    <img src="${coverUrl}" alt="Cover ${index + 1}" width="170" height="255" loading="lazy" decoding="async">
                `;

                coverItem.addEventListener('click', () => {
                    // Use series number if available, otherwise fall back to year or index
                    const numberToUse = edition.seriesNumber || edition.year || (index + 1);
                    downloadCover(coverUrl, edition.coverId, numberToUse, bookTitle);
                });

                grid.appendChild(coverItem);
            });

            coversContainer.appendChild(grid);
        }

        async function downloadCover(url, coverId, seriesNumber, bookTitle) {
            try {
                // Clean the book title to make it filename-safe
                const cleanTitle = bookTitle
                    .replace(/[<>:"/\\|?*]/g, '') // Remove invalid filename characters
                    .replace(/\s+/g, ' ') // Normalize spaces
                    .trim();
                
                // Use the actual series number if available, format with leading zero
                const paddedNumber = String(seriesNumber).padStart(2, '0');
                
                // Create filename: "01 Book Title.jpg"
                const filename = `${paddedNumber} ${cleanTitle}.jpg`;
                
                // Fetch the image as a blob
                const response = await fetch(url);
                const blob = await response.blob();
                
                // Create a temporary URL for the blob
                const blobUrl = window.URL.createObjectURL(blob);
                
                // Create a temporary link and trigger download
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Clean up the blob URL
                window.URL.revokeObjectURL(blobUrl);
                
                // Automatically go to next book after a short delay
                setTimeout(() => {
                    if (currentBookIndex < selectedBooks.length - 1) {
                        currentBookIndex++;
                        loadBookCovers();
                    } else {
                        // If this was the last book, close the modal
                        coverModal.close();
                        // Clear selection
                        selectedBooks = [];
                        updateSelectionUI();
                        document.querySelectorAll('.book-card').forEach(card => {
                            card.classList.remove('selected');
                            card.setAttribute('aria-pressed', 'false');
                        });
                    }
                }, 500);
                
            } catch (error) {
                console.error('Download error:', error);
                // If fetch fails, fall back to simple download
                const cleanTitle = bookTitle
                    .replace(/[<>:"/\\|?*]/g, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                const paddedNumber = String(seriesNumber).padStart(2, '0');
                const filename = `${paddedNumber} ${cleanTitle}.jpg`;
                
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Still auto-advance
                setTimeout(() => {
                    if (currentBookIndex < selectedBooks.length - 1) {
                        currentBookIndex++;
                        loadBookCovers();
                    } else {
                        coverModal.close();
                        selectedBooks = [];
                        updateSelectionUI();
                        document.querySelectorAll('.book-card').forEach(card => {
                            card.classList.remove('selected');
                            card.setAttribute('aria-pressed', 'false');
                        });
                    }
                }, 500);
            }
        }

        function showStatus(message, type) {
            statusDiv.textContent = message;
            statusDiv.className = { error: 'err', success: 'ok' }[type] || 'note';
            statusDiv.hidden = false;

            clearTimeout(showStatus.timer);
            if (type === 'success') {
                showStatus.timer = setTimeout(() => {
                    statusDiv.hidden = true;
                }, 5000);
            }
        }
    </script>
    <?php site_footer(); ?>
</body>
</html>