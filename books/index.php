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
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <body>
        <div class="title">
            <h1>Books I've read</h1>
        </div>
        <div class="jump">
            <input id="toggle" type="checkbox" checked>
            <h2>Sort by Series</h2>
            <ul>
                <li id="all"><a onclick="everything()">All</a></li>
                <!--             <li><a onclick="sort('na')">Standalone books</a></li> -->
                <li><a onclick="sort('pj')">Percy Jackson Series</a></li>
                <li><a onclick="sort('hp')">Harry Potter Series</a></li>
                <li><a onclick="sort('hg')">Hunger Games Series</a></li>
                <li><a onclick="sort('mc')">Minecraft books</a></li>
                <li><a onclick="sort('rp')">Ready Player One/Two</a></li>
                <li><a onclick="sort('ho')">Heros of Olympus Series</a></li>
                <li><a onclick="sort('tp')">Trials of Apollo Series</a></li>
            </ul>
        </div>
        <div class="gallery" id="read">
            <div class="gallery-item tp bookshelf">
                <img src="/images/books/TrialsOfApollo/tp1.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf">
                <img src="/images/books/HerosOfOlympus/hoo1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf">
                <img src="/images/books/HerosOfOlympus/hoo2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf">
                <img src="/images/books/HerosOfOlympus/hoo3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf">
                <img src="/images/books/HerosOfOlympus/hoo4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf">
                <img src="/images/books/HerosOfOlympus/hoo5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf">
                <img src="/images/books/PercyJackson/set2/pj1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf">
                <img src="/images/books/PercyJackson/set2/pj2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf">
                <img src="/images/books/PercyJackson/set2/pj3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf">
                <img src="/images/books/PercyJackson/set2/pj4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf">
                <img src="/images/books/PercyJackson/set2/pj5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp">
                <img src="/images/books/HarryPotter/set1/hp1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp">
                <img src="/images/books/HarryPotter/set1/hp2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp">
                <img src="/images/books/HarryPotter/set1/hp3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp">
                <img src="/images/books/HarryPotter/set1/hp4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp">
                <img src="/images/books/HarryPotter/set1/hp5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp">
                <img src="/images/books/HarryPotter/set1/hp6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp">
                <img src="/images/books/HarryPotter/set1/hp7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg" title="The Hunger Games">
                <img src="/images/books/HungerGames/hg1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg" title="The Hunger Games Catching Fire">
                <img src="/images/books/HungerGames/hg2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg" title="The Hunger Games Mockingjay">
                <img src="/images/books/HungerGames/hg3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item rp" title="Ready Player One">
                <img src="/images/books/ReadyPlayer/rp1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item rp" title="Ready Player Two">
                <img src="/images/books/ReadyPlayer/rp2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item mc" title="Minecraft Combat Handbook">
                <img src="/images/books/Minecraft/mc1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item mc bookshelf" title="Minecraft Construction Handbook">
                <img src="/images/books/Minecraft/mc2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item mc" title="Minecraft Redstone Handbook">
                <img src="/images/books/Minecraft/mc3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item mc" title="Minecraft Beginner's Handbook">
                <img src="/images/books/Minecraft/mc4.webp" class="gallery-image" />
            </div>
        </div>

        <div class="title">
            <h1>Current Series</h1>
            <h2>Trials of Apollo<br>By Rick Riordan</h2>
        </div>
        <div class="gallery" id="reading">
            <div class="gallery-item tp bookshelf">
                <img src="/images/books/TrialsOfApollo/tp1.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf">
                <img src="/images/books/TrialsOfApollo/tp2.jpeg" class="gallery-image current" />
            </div>
            <div class="gallery-item tp bookshelf">
                <img src="/images/books/TrialsOfApollo/tp3.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf">
                <img src="/images/books/TrialsOfApollo/tp4.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf">
                <img src="/images/books/TrialsOfApollo/tp5.jpeg" class="gallery-image" />
            </div>
        </div>

        <div class="title">
            <h1>Planned Reading List</h1>
            <h2>In no order</h2>
        </div>

        <div class="gallery" id="reading">

            <div class="gallery-item pj" title="Percy Jackson And Chalice of the Gods">
                <img src="/images/books/PercyJackson/pj6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Miss Peregrine's Home for Peculiar Children">
                <img src="/images/books/Standalone/pc.webp" class="gallery-image" />
            </div>
            <!--         <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb1.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb2.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb3.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb4.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb5.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb6.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb7.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb8.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb9.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb10.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb11.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb12.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb13.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb14.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb15.webp" class="gallery-image" />
        </div>
        <div class="gallery-item">
            <img src="/images/books/BoyVsBeast/bb16.webp" class="gallery-image" />
        </div> -->
            <div class="gallery-item">
                <img src="/images/books/HungerGames/hg0.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/SkulduggeryPleasant/sb1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/SkulduggeryPleasant/sd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/SkulduggeryPleasant/sd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/SkulduggeryPleasant/sd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/SkulduggeryPleasant/sd4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/LordOfTheRings/ho1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/HerosOfOlympus/hoo6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/LordOfTheRings/lr1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/LordOfTheRings/lr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/LordOfTheRings/lr3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ASongOfIceAndFire/if1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ASongOfIceAndFire/if2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/ASongOfIceAndFire/if3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ASongOfIceAndFire/if4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/ASongOfIceAndFire/if5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/MazeRunner/mr1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/MazeRunner/mr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/MazeRunner/mr3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/Twlight/tw1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/Twlight/tw2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/Twlight/tw3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/Twlight/tw4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/Twlight/tw5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/HisDarkMaterials/hd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/HisDarkMaterials/hd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/HisDarkMaterials/hd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar8.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/AlexRider/ar9.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <a href="https://youtu.be/dQw4w9WgXcQ"><img src="/images/books/AlexRider/ar10.webp"
                        class="gallery-image" /></a>
            </div>
            <div class="gallery-item">
                <img src="/images/books/AlexRider/ar11.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/AlexRider/ar12.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ChronicalsOfNarnia/cn1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ChronicalsOfNarnia/cn2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ChronicalsOfNarnia/cn3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ChronicalsOfNarnia/cn4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ChronicalsOfNarnia/cn5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ChronicalsOfNarnia/cn6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/ChronicalsOfNarnia/cn7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/Standalone/cm.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Shrek">
                <img src="/images/books/Standalone/sh.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Horton Hears a Who">
                <img src="/images/books/standalone/hh.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/OneUp/ou1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/OneUp/ou2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/OneUp/ou3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Famous Five Series (21 books)">
                <img src="/images/books/FamousFive/ff1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Secret Seven Series (17 books)">
                <img src="/images/books/SecretSeven/ss1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Encyclopaedia of Cats">
                <img src="/images/books/standalone/ec.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Artemis Fowl">
                <img src="/images/books/ArtemisFowl/af1.jpj" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd8.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd9.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd10.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd11.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd12.webp" class="gallery-image" />
            </div>
        </div>

        <footer>
            <p>Last updated:
                <?php echo date('jS F Y', filemtime(__FILE__)); ?>
            </p>
        </footer>
        <?php
        include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
        ?>
    </body>

</html>