<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amazon Cover Extractor</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cream: #faf8f3;
            --charcoal: #2d2d2d;
            --rust: #c85a3e;
            --forest: #4a5943;
            --gold: #d4a574;
        }

        body {
            font-family: 'Crimson Pro', serif;
            background: var(--cream);
            color: var(--charcoal);
            padding: 3rem 2rem;
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            margin-bottom: 3rem;
            border-bottom: 2px solid var(--charcoal);
            padding-bottom: 1.5rem;
        }

        h1 {
            font-size: 3.5rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--charcoal);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            font-family: 'DM Mono', monospace;
            font-size: 0.875rem;
            color: var(--forest);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .input-section {
            background: white;
            border: 2px solid var(--charcoal);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: 8px 8px 0 var(--gold);
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 0.75rem;
            color: var(--charcoal);
        }

        textarea {
            width: 100%;
            min-height: 200px;
            padding: 1rem;
            font-family: 'DM Mono', monospace;
            font-size: 0.875rem;
            border: 2px solid var(--charcoal);
            background: var(--cream);
            color: var(--charcoal);
            resize: vertical;
            transition: all 0.3s ease;
        }

        textarea:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px var(--gold);
        }

        button {
            margin-top: 1rem;
            padding: 1rem 2.5rem;
            font-family: 'DM Mono', monospace;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            background: var(--rust);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 4px 4px 0 var(--charcoal);
        }

        button:hover {
            background: var(--charcoal);
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0 var(--charcoal);
        }

        button:active {
            transform: translate(4px, 4px);
            box-shadow: 0 0 0 var(--charcoal);
        }

        .results-section {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .results-section.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .stats {
            font-family: 'DM Mono', monospace;
            font-size: 0.875rem;
            color: var(--forest);
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-left: 4px solid var(--gold);
        }

        .covers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .cover-item {
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
            text-align: center;
        }

        .cover-item:nth-child(1) { animation-delay: 0.1s; }
        .cover-item:nth-child(2) { animation-delay: 0.2s; }
        .cover-item:nth-child(3) { animation-delay: 0.3s; }
        .cover-item:nth-child(4) { animation-delay: 0.4s; }
        .cover-item:nth-child(5) { animation-delay: 0.5s; }
        .cover-item:nth-child(n+6) { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cover-item img {
            width: 100%;
            height: auto;
            border: 2px solid var(--charcoal);
            box-shadow: 6px 6px 0 var(--charcoal);
            transition: all 0.3s ease;
            background: white;
        }

        .cover-item img:hover {
            transform: translate(-4px, -4px);
            box-shadow: 10px 10px 0 var(--charcoal);
        }

        .isbn-label {
            font-family: 'DM Mono', monospace;
            font-size: 0.75rem;
            color: var(--forest);
            margin-top: 0.5rem;
            letter-spacing: 0.05em;
        }

        .error {
            color: var(--rust);
            font-weight: 600;
            padding: 1rem;
            background: rgba(200, 90, 62, 0.1);
            border-left: 4px solid var(--rust);
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 2rem 1rem;
            }

            h1 {
                font-size: 2.5rem;
            }

            .covers-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Amazon Cover Extractor</h1>
            <div class="subtitle">Extract High-Res Book Covers from Amazon Series</div>
        </header>

        <div class="input-section">
            <label for="htmlInput">Paste Amazon Book Series HTML Source:</label>
            <textarea 
                id="htmlInput" 
                placeholder="Paste the HTML source code from an Amazon book series page here..."
            ></textarea>
            <button onclick="extractAndDisplay()">Extract & Display Covers</button>
        </div>

        <div id="results" class="results-section">
            <div id="stats" class="stats"></div>
            <div id="covers" class="covers-grid"></div>
        </div>
    </div>

    <script>
        function extractAndDisplay() {
            const htmlInput = document.getElementById('htmlInput').value;
            const resultsSection = document.getElementById('results');
            const statsDiv = document.getElementById('stats');
            const coversDiv = document.getElementById('covers');
            
            // Clear previous results
            coversDiv.innerHTML = '';
            statsDiv.innerHTML = '';
            resultsSection.classList.remove('visible');
            
            if (!htmlInput.trim()) {
                coversDiv.innerHTML = '<div class="error">Please paste some HTML first!</div>';
                resultsSection.classList.add('visible');
                return;
            }
            
            // Extract Amazon image IDs from og:image meta tags
            const imageIds = new Set();
            
            // Find all og:image meta tags
            const ogImagePattern = /<meta\s+property=["']og:image["']\s+content=["']([^"']+)["']/gi;
            const matches = htmlInput.matchAll(ogImagePattern);
            
            for (const match of matches) {
                const ogImageUrl = match[1];
                
                // Extract image IDs from the complex Amazon URL
                // Look for patterns like "81xrAw9CfkL.jpg" in the URL
                const imageIdPattern = /([A-Z0-9]{11,13})\.jpg/gi;
                const idMatches = ogImageUrl.matchAll(imageIdPattern);
                
                for (const idMatch of idMatches) {
                    const imageId = idMatch[1];
                    // Skip if it looks like dimensions or other metadata
                    if (!imageId.startsWith('SX') && !imageId.startsWith('SY') && !imageId.startsWith('_')) {
                        imageIds.add(imageId);
                    }
                }
            }
            
            if (imageIds.size === 0) {
                coversDiv.innerHTML = '<div class="error">No Amazon image IDs found in the provided HTML.<br><br>Make sure you\'re pasting the HTML source from an Amazon book series page with og:image meta tags.</div>';
                resultsSection.classList.add('visible');
                return;
            }
            
            // Display stats
            const imageIdList = Array.from(imageIds);
            statsDiv.innerHTML = `Found ${imageIds.size} unique book cover${imageIds.size !== 1 ? 's' : ''}: ${imageIdList.join(', ')}`;
            
            // Create image elements for each image ID
            imageIds.forEach((imageId, index) => {
                const coverItem = document.createElement('div');
                coverItem.className = 'cover-item';
                
                const img = document.createElement('img');
                const coverUrl = `https://m.media-amazon.com/images/I/${imageId}._SL1500_.jpg`;
                img.src = coverUrl;
                img.alt = `Book cover ${imageId}`;
                img.loading = 'lazy';
                
                // Handle image load errors
                img.onerror = function() {
                    const errorSvg = `data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="300"%3E%3Crect fill="%23faf8f3" width="200" height="300"/%3E%3Ctext x="50%25" y="35%25" font-family="monospace" font-size="11" fill="%23c85a3e" text-anchor="middle" dominant-baseline="middle"%3EImage Load Failed%3C/text%3E%3Ctext x="50%25" y="45%25" font-family="monospace" font-size="8" fill="%234a5943" text-anchor="middle" dominant-baseline="middle"%3EID: ${imageId}%3C/text%3E%3Ctext x="50%25" y="55%25" font-family="monospace" font-size="6" fill="%234a5943" text-anchor="middle" dominant-baseline="middle"%3E${coverUrl.substring(0,45)}%3C/text%3E%3Ctext x="50%25" y="60%25" font-family="monospace" font-size="6" fill="%234a5943" text-anchor="middle" dominant-baseline="middle"%3E${coverUrl.substring(45)}%3C/text%3E%3C/svg%3E`;
                    this.src = errorSvg;
                };
                
                const label = document.createElement('div');
                label.className = 'isbn-label';
                label.textContent = imageId;
                
                coverItem.appendChild(img);
                coverItem.appendChild(label);
                coversDiv.appendChild(coverItem);
            });
            
            // Show results with animation
            setTimeout(() => {
                resultsSection.classList.add('visible');
            }, 100);
        }
        
        // Allow Enter key in textarea (Ctrl+Enter to submit)
        document.getElementById('htmlInput').addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                extractAndDisplay();
            }
        });
    </script>
</body>
</html>