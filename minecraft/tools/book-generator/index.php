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
        <h1 class="page-title">Book Page Generator</h1>
        <p class="page-lede">Fill in up to 14 lines (15 characters each). Every page highlights a different line.</p>

        <div class="card form-card wide">
            <form class="form" id="bookform">
                <div class="pair">
                    <label>Book title
                        <input class="field" id="bookTitle" placeholder="Untitled">
                    </label>
                    <label>Book author
                        <input class="field" id="bookAuthor" placeholder="Unknown">
                    </label>
                </div>
                <fieldset class="lines">
                    <legend>Lines</legend>
                    <?php for ($i = 1; $i <= 14; $i++): ?>
                        <input class="field" id="inp<?= $i ?>" placeholder="Input <?= $i ?>" aria-label="Line <?= $i ?>" maxlength="15">
                    <?php endfor; ?>
                </fieldset>
                <button class="btn primary" type="submit">Generate command</button>
            </form>
            <div class="out hide" id="out">
                <textarea class="field cmd-text" id="output" aria-label="Command" spellcheck="false"></textarea>
                <button class="btn" type="button" id="copy">Copy command</button>
            </div>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        function generate() {
            const values = [];
            for (let i = 1; i <= 14; i++) {
                values.push(document.getElementById("inp" + i).value || "");
            }

            const bookTitle = document.getElementById("bookTitle").value || "Untitled";
            const bookAuthor = document.getElementById("bookAuthor").value || "Unknown";

            const pages = [];

            for (let a = 0; a < 14; a++) {
                const components = [];

                for (let i = 0; i < 14; i++) {
                    if (i === a) {
                        components.push({
                            text: "> " + values[i],
                            bold: true,
                            color: "dark_green",
                        });
                    } else {
                        components.push({
                            text: values[i],
                            color: "black",
                            bold: false,
                            italic: false,
                            underlined: false,
                            strikethrough: false,
                            obfuscated: false,
                        });
                    }

                    if (i < 13) components.push("\n");
                }

                pages.push(JSON.stringify(components));
            }

            pages.push(
                JSON.stringify([
                    { text: "Below Nether roof", bold: true, color: "dark_green" },
                    "\n",
                    "\n",
                    "\n",
                    "\n",
                    { text: "Do NOT sign this book", bold: true, color: "red" },
                ])
            );

            const command = `give craftyplayz_ writable_book[written_book_content={
    pages:[${pages.join(",")}],
    title:"${bookTitle}",
    author:"${bookAuthor}"
}]`;

            document.getElementById("output").value = command;
        }

        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, copy } = UI;
            $('#bookform').addEventListener('submit', e => {
                e.preventDefault();
                generate();
                $('#out').classList.remove('hide');
            });
            $('#copy').addEventListener('click', e => copy($('#output').value, e.currentTarget));
        });
    </script>
</body>

</html>
