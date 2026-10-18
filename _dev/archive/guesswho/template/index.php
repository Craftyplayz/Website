<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/css/game.css">
    <title>Guess Who?</title>
</head>
<body>
    <div class="cards">
    </div>

    <div class="yourCard">
    </div>

<script>
    <?php
    // PHP: Scan the directory for images
    $imagesDir = './assets/imgs/people';
    $images = array_diff(scandir($imagesDir), array('..', '.'));
    $imagesJS = json_encode(array_values($images)); // Convert to JSON for JS
    ?>

    // JS: Get images from PHP
    var images = <?php echo $imagesJS; ?>;
    var cards = document.querySelector('.cards');
    var yourCard = document.querySelector('.yourCard');

    images.forEach((img) => {
        var person = document.createElement('div');
        person.innerHTML = `<img src="./assets/imgs/people/${img}"></img><br><h2>${img.split('.')[0]}</h2>`;
        person.classList.add('card');
        cards.appendChild(person);
    });


        var randomNumber = Math.floor(Math.random() * images.length);
        var personToGuess = document.createElement('div');
        personToGuess.innerHTML = `<img src="./assets/imgs/people/${images[randomNumber]}\"></img><br><h1>${images[randomNumber].split('.')[0]}</h1>`;
        yourCard.appendChild(personToGuess);

        document.querySelectorAll('.card').forEach((element) => {
            element.onmouseenter = function () {
                element.classList.add('hover');
            };
            element.onmouseleave = function () {
                element.classList.remove('hover');
            };
            element.onclick = function () {
                element.style.transition = 'all 0s';
                element.style.visibility = 'hidden';
            };
        });
    </script>
</body>
</html>
