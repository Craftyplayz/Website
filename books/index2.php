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
                <li><a onclick="sort('na')">Standalone books</a></li>
                <li><a onclick="sort('pj')">Percy Jackson</a></li>
                <li><a onclick="sort('ho')">Heros of Olympus</a></li>
                <!--                 <li><a onclick="sort('tp')">Trials of Apollo</a></li> -->
                <li><a onclick="sort('hp')">Harry Potter</a></li>
                <li><a onclick="sort('hg')">Hunger Games</a></li>
                <li><a onclick="sort('mc')">Minecraft books</a></li>
                <li><a onclick="sort('rp')">Ready Player One/Two</a></li>
<!--                 <li><a onclick="sort('ar')">Alex Rider</a></li> -->
                <li><a onclick="sort('lr')">Lord of the Rings/Hobbit</a></li>
            </ul>
        </div>
        <div class="gallery" id="read">
            <div class="gallery-item lr bookshelf" title="The Hobbit">
                <img src="/images/books/LordOfTheRings/ho1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Lost Hero">
                <img src="/images/books/HerosOfOlympus/hoo1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Son of Neptune">
                <img src="/images/books/HerosOfOlympus/hoo2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Mark of Athena">
                <img src="/images/books/HerosOfOlympus/hoo3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The House of Hades">
                <img src="/images/books/HerosOfOlympus/hoo4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Blood of Olympus">
                <img src="/images/books/HerosOfOlympus/hoo5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Lightning Thief">
                <img src="/images/books/PercyJackson/set2/pj1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Sea of Monsters">
                <img src="/images/books/PercyJackson/set2/pj2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Titans Curse">
                <img src="/images/books/PercyJackson/set2/pj3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Battle of the Labrynth">
                <img src="/images/books/PercyJackson/set2/pj4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Lasy Olympian">
                <img src="/images/books/PercyJackson/set2/pj5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item rp bookshelf" title="Ready Player One">
                <img src="/images/books/ReadyPlayer/rp1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item rp" title="Ready Player Two">
                <img src="/images/books/ReadyPlayer/rp2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games">
                <img src="/images/books/HungerGames/hg1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games Catching Fire">
                <img src="/images/books/HungerGames/hg2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games Mockingjay">
                <img src="/images/books/HungerGames/hg3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Philosophers Stone">
                <img src="/images/books/HarryPotter/set1/hp1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Chamber of Secrets">
                <img src="/images/books/HarryPotter/set1/hp2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Prisoner of Azkaban">
                <img src="/images/books/HarryPotter/set1/hp3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Goblet of Fire">
                <img src="/images/books/HarryPotter/set1/hp4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Order of the pheonix">
                <img src="/images/books/HarryPotter/set1/hp5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Half-Blood prince">
                <img src="/images/books/HarryPotter/set1/hp6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Deathly Hallows">
                <img src="/images/books/HarryPotter/set1/hp7.webp" class="gallery-image" />
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
            <div class="gallery-item mc bookshelf" title="Minecraft Beginner's Handbook">
                <img src="/images/books/Minecraft/mc4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item na bookshelf" title="Encyclopedia of Cats">
                <img src="/images/books/standalone/ec.webp" class="gallery-image" />
            </div>
        </div>

        <div class="title">
            <h1>Current Book</h1>
            <h2>Currently Reading the Hobbit & The Lord of the Rings<br></h2>
        </div>
        <div class="gallery" id="reading">
            <div class="gallery-item lr bookshelf" title="The Hobbit">
                <img src="/images/books/LordOfTheRings/ho1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Fellowship of the Ring">
                <img src="/images/books/LordOfTheRings/lr1.webp" class="gallery-image current" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Two Towers">
                <img src="/images/books/LordOfTheRings/lr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Return of the King">
                <img src="/images/books/LordOfTheRings/lr3.webp" class="gallery-image" />
            </div>
        </div>

        <div class="title">
            <h1>Planned Reading List</h1>
            <h2>In no order</h2>
        </div>

        <div class="gallery" id="reading">
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Fellowship of the Ring">
                <img src="/images/books/LordOfTheRings/lr1.webp" class="gallery-image current" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Two Towers">
                <img src="/images/books/LordOfTheRings/lr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Return of the King">
                <img src="/images/books/LordOfTheRings/lr3.webp" class="gallery-image" />
            </div>

            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Hidden Oracle">
                <img src="/images/books/TrialsOfApollo/tp1.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Dark Prophecy">
                <img src="/images/books/TrialsOfApollo/tp2.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Burning Maze">
                <img src="/images/books/TrialsOfApollo/tp3.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tyrant's Tomb">
                <img src="/images/books/TrialsOfApollo/tp4.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tower of Nero">
                <img src="/images/books/TrialsOfApollo/tp5.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Ender's Game">
                <img src="/images/books/standalone/eg.jpf" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Percy Jackson And Chalice of the Gods">
                <img src="/images/books/PercyJackson/pj6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Miss Peregrine's Home for Peculiar Children">
                <img src="/images/books/Standalone/pc.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Tha Ballard of Songbirds and Snakes">
                <img src="/images/books/HungerGames/hg0.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Skulduggery Pleasant">
                <img src="/images/books/SkulduggeryPleasant/sb1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: Playing with Fire">
                <img src="/images/books/SkulduggeryPleasant/sd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: The Faceless Ones">
                <img src="/images/books/SkulduggeryPleasant/sd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: Dark Days">
                <img src="/images/books/SkulduggeryPleasant/sd4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Heros of Olympus: The Demigod Diaries">
                <img src="/images/books/HerosOfOlympus/hoo6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Game of Thrones">
                <img src="/images/books/ASongOfIceAndFire/if1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="A Song of Ice and Fire: A Clash of Kings">
                <img src="/images/books/ASongOfIceAndFire/if2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Storm of Swords">
                <img src="/images/books/ASongOfIceAndFire/if3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Feast for Crows">
                <img src="/images/books/ASongOfIceAndFire/if4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Dance with Dragons">
                <img src="/images/books/ASongOfIceAndFire/if5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf">
                <img src="/images/books/MazeRunner/mr1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Maze Runner">
                <img src="/images/books/MazeRunner/mr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Maze Runner: The Death Cure">
                <img src="/images/books/MazeRunner/mr3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight">
                <img src="/images/books/Twlight/tw1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight: New Moon">
                <img src="/images/books/Twlight/tw2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight: Eclipse">
                <img src="/images/books/Twlight/tw3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight: Breaking Dawn">
                <img src="/images/books/Twlight/tw4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Twilight: Midnight Sun">
                <img src="/images/books/Twlight/tw5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Northern Lights">
                <img src="/images/books/HisDarkMaterials/hd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Subtle Knife">
                <img src="/images/books/HisDarkMaterials/hd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Amber Spyglass">
                <img src="/images/books/HisDarkMaterials/hd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Stormbreaker">
                <img src="/images/books/AlexRider/ar1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Point Blanc">
                <img src="/images/books/AlexRider/ar2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Skeleeton Key">
                <img src="/images/books/AlexRider/ar3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Eagle Strike">
                <img src="/images/books/AlexRider/ar4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Scorpia">
                <img src="/images/books/AlexRider/ar5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Ark Angel">
                <img src="/images/books/AlexRider/ar6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Snakehead">
                <img src="/images/books/AlexRider/ar7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Crocodile Tears">
                <img src="/images/books/AlexRider/ar8.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Scorpia Rising">
                <img src="/images/books/AlexRider/ar9.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Russian Roulette">
                <a href="https://youtu.be/dQw4w9WgXcQ"><img src="/images/books/AlexRider/ar10.webp"
                        class="gallery-image" /></a>
            </div>
            <div class="gallery-item" title="Never say Die">
                <img src="/images/books/AlexRider/ar11.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Secret Weapon">
                <img src="/images/books/AlexRider/ar12.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Chronicles of Narnia: The Magician's Nephew">
                <img src="/images/books/ChronicalsOfNarnia/cn1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Lion the Witch and the Wardrobe">
                <img src="/images/books/ChronicalsOfNarnia/cn2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Horse and His Boy">
                <img src="/images/books/ChronicalsOfNarnia/cn3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: Prince Caspian">
                <img src="/images/books/ChronicalsOfNarnia/cn4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Voyage of the Dawn Treader">
                <img src="/images/books/ChronicalsOfNarnia/cn5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Silver Chair">
                <img src="/images/books/ChronicalsOfNarnia/cn6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Last Battle">
                <img src="/images/books/ChronicalsOfNarnia/cn7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Hitchhiker's Guide to the Galaxy">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Restaurant at the End of the Universe">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Life the Universe and Everything">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="So long and thanks for all the fish">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Mostly Harmless">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Famous Five Series (21 books)">
                <img src="/images/books/FamousFive/ff1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Secret Seven Series (17 books)">
                <img src="/images/books/SecretSeven/ss1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Artemis Fowl">
                <img src="/images/books/ArtemisFowl/af1.jpj" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="How To Train Your Dragon">
                <img src="/images/books/HowtoTrainYourDragon/hd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to be a Pirate">
                <img src="/images/books/HowtoTrainYourDragon/hd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to speak Dragonese">
                <img src="/images/books/HowtoTrainYourDragon/hd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Cheat a Dragons Curse">
                <img src="/images/books/HowtoTrainYourDragon/hd4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Twist a Dragon's Tale">
                <img src="/images/books/HowtoTrainYourDragon/hd5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: A Hero's Guide to Deadly Dragons">
                <img src="/images/books/HowtoTrainYourDragon/hd6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Ride a Dragon's Storm">
                <img src="/images/books/HowtoTrainYourDragon/hd7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Break a Dragon's Heart">
                <img src="/images/books/HowtoTrainYourDragon/hd8.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Steal a Dragons Sword">
                <img src="/images/books/HowtoTrainYourDragon/hd9.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Sieze a Dragon's Jewel">
                <img src="/images/books/HowtoTrainYourDragon/hd10.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Betray a Dragon's Hero">
                <img src="/images/books/HowtoTrainYourDragon/hd11.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Fight a Dragon's Fury">
                <img src="/images/books/HowtoTrainYourDragon/hd12.webp" class="gallery-image" />
            </div>

            <!--         div class="gallery-item">
                <img src="/images/books/OneUp/ou1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/OneUp/ou2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item">
                <img src="/images/books/OneUp/ou3.webp" class="gallery-image" />
            </div> -->
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