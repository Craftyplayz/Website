<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-forms.css'],
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
        <h1 class="page-title">Minecraft Fake Transaction Generator</h1>
        <p class="page-lede">Add your own items and prices, then generate a book of fake receipts for Minecraft item sales.</p>

        <div class="card form-card wide">
            <form class="form" id="itemform">
                <h2>Items <span class="n" id="itemcount"></span></h2>
                <div class="rows hide" id="custom-items-container"></div>
                <div class="pair add">
                    <label>Item name
                        <input class="field" type="text" id="item-name">
                    </label>
                    <label>Price
                        <input class="field" type="number" id="item-price" step="any">
                    </label>
                    <button class="btn" type="submit">Add item</button>
                </div>
            </form>
            <form class="form" id="bookform">
                <h2>Book</h2>
                <div class="pair">
                    <label>Book title
                        <input class="field" type="text" id="book-title" placeholder="Receipts">
                    </label>
                    <label>Book author
                        <input class="field" type="text" id="book-author" placeholder="Transaction Generator">
                    </label>
                </div>
                <button class="btn primary" type="submit">Generate receipts</button>
            </form>
            <div class="out hide" id="out">
                <textarea class="field cmd-text" id="minecraft-command" aria-label="Command" readonly></textarea>
                <button class="btn" type="button" id="copy">Copy command</button>
                <details>
                    <summary id="receipts-summary"></summary>
                    <div class="receipts" id="receipts-container"></div>
                </details>
            </div>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        let items = [];

        const purchasers = [];
        fetch('/minecraft/tools/transaction-generator/names.csv')
            .then(response => response.text())
            .then(text => text.split('\n').map(line => {
                const name = line.split(',')[0];
                if (name === name.toUpperCase()) {
                    return null;
                }
                return name;
            }))
            .then(names => {
                purchasers.push(...names.filter(name => name !== null));
            });

        function getRandomInt(min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        }

        function getRandomDate() {
            const year = 1958;
            const month = getRandomInt(1, 12);
            const day = getRandomInt(1, 28);
            return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        }

        function addCustomItem() {
            const itemName = document.getElementById("item-name").value;
            const itemPrice = parseFloat(document.getElementById("item-price").value);

            if (itemName && !isNaN(itemPrice)) {
                items.push({ name: itemName, price: itemPrice });
                displayCustomItems();
                document.getElementById("item-name").value = '';
                document.getElementById("item-price").value = '';
                document.getElementById("item-name").focus();
            } else {
                UI.toast("Please enter both a valid item name and price.", 'err');
            }
        }

        function displayCustomItems() {
            const customItemsContainer = document.getElementById("custom-items-container");
            customItemsContainer.innerHTML = '';
            items.forEach(item => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'rowi';
                itemDiv.innerHTML = `<span class="grow">${UI.esc(item.name)}</span><small>$${item.price}</small>`;
                customItemsContainer.appendChild(itemDiv);
            });
            customItemsContainer.classList.toggle('hide', !items.length);
            document.getElementById('itemcount').textContent = items.length ? items.length : '';
        }

        function generateReceipt() {
            const numItems = getRandomInt(1, 2);
            const boughtItems = [];
            let totalPrice = 0;

            for (let i = 0; i < numItems; i++) {
                const randomItem = items[getRandomInt(0, items.length - 1)];
                boughtItems.push(randomItem);
                totalPrice += randomItem.price;
            }

            return {
                purchaser: purchasers[getRandomInt(0, purchasers.length - 1)],
                date: getRandomDate(),
                items: boughtItems,
                totalPrice: totalPrice
            };
        }

        function generateReceipts() {
            const numReceipts = getRandomInt(50, 100);
            const receipts = [];

            for (let i = 0; i < numReceipts; i++) {
                receipts.push(generateReceipt());
            }

            return receipts;
        }

        function generateAndDisplayReceipts() {
            if (!items.length) return UI.toast('Add at least one item first.', 'err');
            const esc = UI.esc;
            // Get the book title and author from the textboxes
            const bookTitle = document.getElementById('book-title').value || 'Receipts';
            const bookAuthor = document.getElementById('book-author').value || 'Transaction Generator';

            const receipts = generateReceipts();
            const receiptsContainer = document.getElementById('receipts-container');
            const bookPages = [];
            receiptsContainer.innerHTML = ''; // Clear previous receipts

            receipts.forEach((receipt, index) => {
                // Display the receipt
                const receiptDiv = document.createElement('div');
                receiptDiv.className = 'receipt';
                receiptDiv.innerHTML = `
                    <b>Receipt #${index + 1}</b>
                    <div class="kv">
                        <span>Purchaser</span><span>${esc(receipt.purchaser)}</span>
                        <span>Date</span><span>${receipt.date}</span>
                        ${receipt.items.map(item => `<span>Item</span><span>${esc(item.name)} - $${item.price}</span>`).join('')}
                        <span>Total</span><span>$${receipt.totalPrice}</span>
                    </div>
                `;
                receiptsContainer.appendChild(receiptDiv);

                // Format the items for the Minecraft command
                const itemsFormatted = receipt.items.map((item, i) => {
                    // Add ", after the \\\\n for the last item
                    const endComma = (i === receipt.items.length - 1) ? '",' : '';
                    return `Item: ${item.name} - Price: $${item.price}\\\\n${endComma}`;
                }).join('');

                // Format the receipt content for the Minecraft command
                const pageContent = [
                    `Receipt #${index + 1}\\\\n",`,
                    `{"text":"Purchaser:","bold":true}," ${receipt.purchaser}\\\\n",`,
                    `{"text":"Date:","bold":true}," ${receipt.date}\\\\n`,
                    itemsFormatted,  // Use the updated item formatting
                    `{"text":"Total Price:","bold":true}," $${receipt.totalPrice}`
                ].join('');

                // Add the page content to bookPages array
                bookPages.push(`'[["${pageContent}"]]'`);
            });

            // Start of the Minecraft command
            let minecraftCommand = `give @a written_book[written_book_content={pages:[`;

            // Add all pages to the command
            minecraftCommand += bookPages.join(',');

            // End of the command with title and author
            minecraftCommand += `],title:"${bookTitle}",author:"${bookAuthor}"}]`;

            // Display the Minecraft command
            document.getElementById('minecraft-command').value = minecraftCommand;
            document.getElementById('receipts-summary').textContent = `Show all ${receipts.length} receipts`;
            document.getElementById('out').classList.remove('hide');
        }

        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, copy } = UI;
            $('#itemform').addEventListener('submit', e => { e.preventDefault(); addCustomItem(); });
            $('#bookform').addEventListener('submit', e => { e.preventDefault(); generateAndDisplayReceipts(); });
            $('#copy').addEventListener('click', e => copy($('#minecraft-command').value, e.currentTarget));
        });
    </script>
</body>

</html>
