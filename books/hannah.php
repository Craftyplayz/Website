<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book List</title>
    <link rel="stylesheet" href="/css/books.css">
    <script src="/books/sort.js"></script>
</head>
<!-- add search feature -->

<body>
    <!--#header--><!--#/header-->


    <body>
        <div class="title">
            <h1>Books I've read</h1>
        </div>
        <div class="jump">
            <input id="toggle" type="checkbox" checked>
            <h2>Sort by Series</h2>
            <ul>
                <li id="all"><a onclick="everything()">All</a></li>
                <li><a onclick="sort('na')">Standalone books</a></li>
                <li><a onclick="sort('mh')">Maple Hills</a></li>
            </ul>
        </div>
        <div class="gallery" id="read">
            <div class="gallery-item bookshelf" title="Playing the Field">
                <img src="/images/books/Standalone/pt.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Hook Line and Sinker">
                <img src="/images/books/BellingerSisters/bs2.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Out On a Limb">
                <img src="/images/books/Standalone/ol.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Villa with a View">
                <img src="/images/books/RomanceEscapes/re11.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Summer Seaside Kitchen">
                <img src="/images/books/Mure/mu1.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Before I Die">
                <img src="/images/books/Standalone/bd.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item mh bookshelf" title="Icebreaker" series="MapleHills">
                <img src="/images/books/MapleHills/mh1.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item mh bookshelf" title="Icebreaker" series="MapleHills">
                <img src="/images/books/MapleHills/mh2.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Check & Mate">
                <img src="/images/books/Standalone/cm.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Love Hypothesis">
                <img src="/images/books/Standalone/lh.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Love on the Brain">
                <img src="/images/books/Standalone/lb.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Holding the Reins">
                <img src="/images/books/SilverPinesRanch/sp1.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Cake Shop in the Garden">
                <img src="/images/books/Standalone/cs.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Secret Christmas Bookshop">
                <img src="/images/books/Standalone/sg.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Christmas Cottage">
                <img src="/images/books/Standalone/cc.jpg" class="gallery-image" />
            </div>
        </div>

        <div class="title">
            <h1>Current Book</h1>
            <h2>Currently Reading ______<br></h2>
        </div>
        <div class="gallery" id="reading">
        </div>

        <div class="title">
            <h1>Planned Reading List</h1>
            <h2>In no order. Click on a series to expand it</h2>
        </div>

        <div class="gallery" id="reading">
            <div class="gallery-item bookshelf" title="Playing the Field" series="lordoftherings">
                <img src="/images/books/LordOfTheRings/lr1.webp" class="gallery-image current" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Two Towers" series="lordoftherings">
                <img src="/images/books/LordOfTheRings/lr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Return of the King"
                series="lordoftherings">
                <img src="/images/books/LordOfTheRings/lr3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Hidden Oracle"
                series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp1.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Dark Prophecy"
                series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp2.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Burning Maze"
                series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp3.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tyrant's Tomb"
                series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp4.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tower of Nero"
                series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp5.jpeg" class="gallery-image" />
            </div>






            Hook, Line, and Sinker
            Out on a Limb
            A Villa with a View
            The Summer Seaside Kitchen
            Before I Die
            Icebreaker
            Wildfire
            The Love Hypothesis
            Love on the Brain
            Check & Mate
            Holding the Reins
            The Cake Shop in the Garden
            The Secret Christmas Bookshop
            The Christmas Cottage
            Anna and the French Kiss
            Summer at the Santorini Bookshop
            The Bookshop by the Loch
            A Scottish Highland Hideaway
            Hopeless
            A Winter in New York
        </div>

        <footer>
            <p>Last updated:
                <?php echo date('jS F Y', filemtime(__FILE__)); ?>
            </p>
        </footer>
        <!--#footer--><!--#/footer-->
    </body>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Function to hide all but the first item in each series
            function hideAllButFirst() {
                const seriesItems = {};
                const galleryItems = document.querySelectorAll('.gallery-item');

                galleryItems.forEach(item => {
                    const series = item.getAttribute('series');
                    if (series) {
                        if (!seriesItems[series]) {
                            seriesItems[series] = [];
                        }
                        seriesItems[series].push(item);

                        // Initially hide all but the first item in each series
                        if (seriesItems[series].length > 1) {
                            item.style.display = 'none';
                        }
                    } else {
                        // If no data-series, ensure the item is shown
                        item.style.display = 'block';
                    }
                });

                // Add click event to toggle the display of the series
                for (let series in seriesItems) {
                    seriesItems[series].forEach(item => {
                        item.addEventListener('click', function () {
                            const isExpanded = seriesItems[series][1].style.display === 'block';
                            seriesItems[series].forEach(seriesItem => {
                                seriesItem.style.display = isExpanded ? 'none' : 'block';
                            });
                            // Ensure the first item is always visible
                            seriesItems[series][0].style.display = 'block';
                        });
                    });
                }
            }

            hideAllButFirst();
        });

    </script>
    <script>
        document.querySelectorAll('.gallery-item').forEach(item => {
            // Check if the data-book attribute exists
            if (item.hasAttribute('data-book')) {
                const bookValue = item.getAttribute('data-book');
                const bookBadge = document.createElement('div');
                bookBadge.style.position = 'absolute';
                bookBadge.style.top = '3px';
                bookBadge.style.left = '3px';
                bookBadge.style.width = '20px';
                bookBadge.style.height = '20px';
                bookBadge.style.backgroundColor = '#21db4d';
                bookBadge.style.color = 'white';
                bookBadge.style.borderRadius = '50%';
                bookBadge.style.display = 'flex';
                bookBadge.style.alignItems = 'center';
                bookBadge.style.justifyContent = 'center';
                bookBadge.style.fontSize = '12px';
                bookBadge.textContent = bookValue;
                bookBadge.title = 'Times read';
                item.style.position = 'relative';
                item.appendChild(bookBadge);
            }

            // Check if the data-audio attribute exists
            if (item.hasAttribute('data-audio')) {
                const audioValue = item.getAttribute('data-audio');
                const audioBadge = document.createElement('div');
                audioBadge.style.position = 'absolute';
                audioBadge.style.top = '3px';
                audioBadge.style.left = '26px'; // Offset the audio badge from the book badge
                audioBadge.style.width = '20px';
                audioBadge.style.height = '20px';
                audioBadge.style.backgroundColor = 'red';
                audioBadge.style.color = 'white';
                audioBadge.style.borderRadius = '50%';
                audioBadge.style.display = 'flex';
                audioBadge.style.alignItems = 'center';
                audioBadge.style.justifyContent = 'center';
                audioBadge.style.fontSize = '12px';
                audioBadge.textContent = audioValue;
                audioBadge.title = 'Times listened to';
                item.style.position = 'relative';
                item.appendChild(audioBadge);
            }
        });

    </script>

</html>