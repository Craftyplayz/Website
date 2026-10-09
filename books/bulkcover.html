<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Series Cover Downloader</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .search-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }

        .search-container {
            position: relative;
            width: 100%;
        }

        #searchInput {
            width: 100%;
            padding: 15px 20px;
            font-size: 1.1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.3s;
        }

        #searchInput:focus {
            border-color: #667eea;
        }

        #suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .suggestion-item {
            padding: 12px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .suggestion-item:hover {
            background-color: #f8f9ff;
        }

        .suggestion-item img {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .suggestion-info {
            flex: 1;
        }

        .suggestion-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .suggestion-author {
            font-size: 0.9rem;
            color: #666;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .results-section {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .results-title {
            font-size: 1.5rem;
            color: #333;
        }

        .download-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .info-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #856404;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }

        select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }

        .book-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }

        .book-card.selected {
            border-color: #667eea;
            background-color: #f8f9ff;
        }

        .book-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .book-checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .book-cover {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 10px;
            background: #f0f0f0;
        }

        .book-number {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .book-title {
            font-size: 0.9rem;
            color: #333;
            font-weight: 500;
            line-height: 1.3;
            margin-top: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .no-cover {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #999;
            font-size: 0.85rem;
        }

        .message {
            text-align: center;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 8px;
            color: #666;
            margin: 20px 0;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }

            .results-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .download-controls {
                width: 100%;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📚 Book Series Cover Downloader</h1>
            <p class="subtitle">Search for a book and download all covers from its series</p>
        </header>

        <div class="search-section">
            <div class="search-container">
                <input 
                    type="text" 
                    id="searchInput" 
                    placeholder="Search for a book (e.g., Harry Potter, Dune, The Hunger Games...)"
                    autocomplete="off"
                >
                <div id="suggestions"></div>
            </div>
        </div>

        <div class="results-section" id="resultsSection">
            <div class="results-header">
                <h2 class="results-title" id="resultsTitle">Series Books</h2>
                <div class="download-controls">
                    <select id="sizeSelect">
                        <option value="1">Thumbnail</option>
                        <option value="3" selected>Medium</option>
                        <option value="5">Large</option>
                    </select>
                    <button class="btn btn-secondary" id="openTabsBtn">
                        Open in Tabs (<span id="tabsCount">0</span>)
                    </button>
                    <button class="btn btn-primary" id="downloadBtn">
                        Download ZIP (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>
            <div class="info-box">
                <strong>💡 Download Tips:</strong>
                Google may block automated downloads with CAPTCHAs. If ZIP download fails, use "Open in Tabs" to view all covers in separate tabs, then right-click to save each one.
            </div>
            <div id="booksGrid" class="books-grid"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script>
        // ============================================
        // CONFIGURATION - ENTER YOUR API KEY HERE
        // ============================================
        const GOOGLE_BOOKS_API_KEY = 'AIzaSyBOk3ZcZ3Em4H2atm6LYo6U54Ut939f2x4';
        // ============================================

        const API_BASE_URL = 'https://www.googleapis.com/books/v1/volumes';
        
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

        // Search input with debounce
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            if (query.length < 3) {
                suggestionsDiv.style.display = 'none';
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
                suggestionsDiv.style.display = 'none';
            }
        });

        // Fetch autocomplete suggestions
        async function fetchSuggestions(query) {
            try {
                suggestionsDiv.innerHTML = '<div class="loading">Searching...</div>';
                suggestionsDiv.style.display = 'block';

                // Search for books with proper filtering
                const url = `${API_BASE_URL}?q=${encodeURIComponent(query)}&maxResults=10&printType=books&key=${GOOGLE_BOOKS_API_KEY}`;
                const response = await fetch(url);
                const data = await response.json();

                if (!data.items || data.items.length === 0) {
                    suggestionsDiv.innerHTML = '<div class="loading">No results found</div>';
                    return;
                }

                // Filter to only show books (not magazines) with covers
                const filteredBooks = data.items.filter(item => {
                    const info = item.volumeInfo;
                    return info.imageLinks?.thumbnail; // Only show books with covers
                });

                if (filteredBooks.length === 0) {
                    suggestionsDiv.innerHTML = '<div class="loading">No books with covers found</div>';
                    return;
                }

                displaySuggestions(filteredBooks);
            } catch (error) {
                console.error('Error fetching suggestions:', error);
                suggestionsDiv.innerHTML = '<div class="loading">Error loading suggestions</div>';
            }
        }

        // Display autocomplete suggestions
        function displaySuggestions(books) {
            suggestionsDiv.innerHTML = '';
            
            books.forEach(book => {
                const info = book.volumeInfo;
                const item = document.createElement('div');
                item.className = 'suggestion-item';
                
                const thumbnail = info.imageLinks?.thumbnail || '';
                const title = info.title || 'Unknown Title';
                const authors = info.authors?.join(', ') || 'Unknown Author';
                
                item.innerHTML = `
                    ${thumbnail ? `<img src="${thumbnail}" alt="${title}">` : '<div style="width:40px;height:60px;background:#f0f0f0;border-radius:4px;"></div>'}
                    <div class="suggestion-info">
                        <div class="suggestion-title">${title}</div>
                        <div class="suggestion-author">${authors}</div>
                    </div>
                `;
                
                item.addEventListener('click', () => {
                    suggestionsDiv.style.display = 'none';
                    searchInput.value = title;
                    findSeriesBooks(book);
                });
                
                suggestionsDiv.appendChild(item);
            });
            
            suggestionsDiv.style.display = 'block';
        }

        // Find all books in the series
        async function findSeriesBooks(selectedBook) {
            try {
                booksGrid.innerHTML = '<div class="spinner"></div>';
                resultsSection.style.display = 'block';

                const info = selectedBook.volumeInfo;
                const title = info.title;
                const authors = info.authors?.[0] || '';

                // Extract series name from title
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
                    booksGrid.innerHTML = '<div class="message">No series books found. Try searching for another book.</div>';
                    return;
                }

                // Sort books
                books = sortSeriesBooks(books);
                currentSeriesBooks = books;
                
                displaySeriesBooks(books);
            } catch (error) {
                console.error('Error finding series:', error);
                booksGrid.innerHTML = '<div class="message">Error loading series books. Please try again.</div>';
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
                // Strategy 1: Use series parameter (most specific)
                `subject:"${seriesName}" inauthor:"${author}"`,
                // Strategy 2: Regular search with series in quotes
                `"${seriesName}" inauthor:"${author}"`,
                // Strategy 3: Fallback without quotes
                `${seriesName} ${author}`
            ];

            let allBooks = [];
            
            for (const query of searches) {
                const url = `${API_BASE_URL}?q=${encodeURIComponent(query)}&maxResults=40&printType=books&key=${GOOGLE_BOOKS_API_KEY}`;
                
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
            const url = `${API_BASE_URL}?q=inauthor:"${encodeURIComponent(author)}"&maxResults=40&printType=books&key=${GOOGLE_BOOKS_API_KEY}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (!data.items) return [];
            
            // Filter to only include books with covers
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
                // Sort by book number if available
                if (a.bookNumber !== null && b.bookNumber !== null) {
                    return a.bookNumber - b.bookNumber;
                }
                
                // Otherwise sort by publication date
                return (a.publishedDate || '').localeCompare(b.publishedDate || '');
            });
        }

        // Display series books
        function displaySeriesBooks(books) {
            booksGrid.innerHTML = '';
            
            books.forEach((book, index) => {
                const card = document.createElement('div');
                card.className = 'book-card selected';
                card.dataset.index = index;
                
                let coverImg;
                if (book.coverUrl) {
                    coverImg = `<img src="${book.coverUrl}" alt="${book.title}" class="book-cover" onerror="this.parentElement.querySelector('.book-cover').classList.add('no-cover'); this.style.display='none';">`;
                } else {
                    coverImg = `<div class="book-cover no-cover">No Cover</div>`;
                }
                
                card.innerHTML = `
                    <input type="checkbox" class="book-checkbox" checked data-index="${index}">
                    ${book.bookNumber ? `<div class="book-number">Book ${book.bookNumber}</div>` : ''}
                    ${coverImg}
                    <div class="book-title">${book.title}</div>
                `;
                
                booksGrid.appendChild(card);
            });

            updateSelectedCount();
            setupCheckboxListeners();
        }

        // Setup checkbox listeners
        function setupCheckboxListeners() {
            const checkboxes = document.querySelectorAll('.book-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    currentSeriesBooks[index].selected = e.target.checked;
                    
                    const card = e.target.closest('.book-card');
                    card.classList.toggle('selected', e.target.checked);
                    
                    updateSelectedCount();
                });
            });
        }

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
                alert('Please select at least one book');
                return;
            }

            if (selectedBooks.length > 10) {
                const confirm = window.confirm(`This will open ${selectedBooks.length} tabs. Your browser may block some pop-ups. Continue?`);
                if (!confirm) return;
            }

            selectedBooks.forEach(book => {
                const url = getCoverUrl(book.coverUrl, size);
                if (url) {
                    window.open(url, '_blank');
                }
            });

            alert(`Opened ${selectedBooks.length} covers in new tabs. If some didn't open, check your pop-up blocker settings.`);
        });

        // Download covers
        downloadBtn.addEventListener('click', async () => {
            const selectedBooks = currentSeriesBooks.filter(b => b.selected);
            const size = sizeSelect.value;
            
            if (selectedBooks.length === 0) {
                alert('Please select at least one book');
                return;
            }

            downloadBtn.disabled = true;
            const originalText = downloadBtn.innerHTML;
            downloadBtn.textContent = 'Preparing download...';

            try {
                if (selectedBooks.length === 1) {
                    // Single download - open in new tab (avoids CAPTCHA)
                    downloadSingleCover(selectedBooks[0], size);
                    downloadBtn.textContent = 'Download opened in new tab';
                } else {
                    // Multiple downloads as ZIP with delays to avoid rate limiting
                    await downloadAsZip(selectedBooks, size);
                }
            } catch (error) {
                console.error('Download error:', error);
                alert('Error downloading covers. Google may be blocking automated downloads. Try downloading fewer books or one at a time.');
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
                alert('No cover available for this book');
                return;
            }

            // Open image in new tab - user can right-click save
            // This avoids CAPTCHA issues with direct fetch
            window.open(url, '_blank');
        }

        // Download multiple covers as ZIP with delays
        async function downloadAsZip(books, size) {
            const zip = new JSZip();
            let successCount = 0;
            let failCount = 0;
            
            downloadBtn.textContent = `Downloading 0/${books.length}...`;
            
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
                    
                    const response = await fetch(url, {
                        method: 'GET',
                        mode: 'cors',
                    });
                    
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
                    
                    downloadBtn.textContent = `Downloading ${i + 1}/${books.length}...`;
                    
                } catch (error) {
                    console.error(`Error downloading cover for ${book.title}:`, error);
                    failCount++;
                }
            }

            if (successCount === 0) {
                alert('Failed to download any covers. Google is blocking the requests. Try:\n\n1. Selecting fewer books\n2. Downloading one at a time\n3. Waiting a few minutes before trying again');
                return;
            }

            downloadBtn.textContent = 'Creating ZIP...';
            
            const content = await zip.generateAsync({type: 'blob'});
            const link = document.createElement('a');
            link.href = URL.createObjectURL(content);
            link.download = 'book_series_covers.zip';
            link.click();
            
            URL.revokeObjectURL(link.href);
            
            if (failCount > 0) {
                alert(`Downloaded ${successCount} of ${books.length} covers.\n\n${failCount} covers failed (may be blocked by Google or unavailable).`);
            } else {
                downloadBtn.textContent = `Downloaded ${successCount} covers!`;
            }
        }

        // Get cover URL with size modification
        function getCoverUrl(baseUrl, size) {
            if (!baseUrl) return null;
            
            // Google Books URLs can come in different formats:
            // 1. With zoom parameter: ...&zoom=1
            // 2. Without zoom parameter
            // 3. Different image servers
            
            let url = baseUrl;
            
            // Replace http with https for security
            url = url.replace('http://', 'https://');
            
            // If URL has zoom parameter, replace it
            if (url.includes('zoom=')) {
                url = url.replace(/zoom=\d+/, `zoom=${size}`);
            } else {
                // Add zoom parameter if it doesn't exist
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
    </script>
</body>
</html>