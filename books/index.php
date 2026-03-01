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
            <h2><a href="https://reading.craftyplayz.com/books">Live stats from Kindle</a></h2>
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
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Lightning Thief" data-book="2" >
                <img src="/images/books/PercyJackson/set2/pj1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Sea of Monsters" data-book="2" >
                <img src="/images/books/PercyJackson/set2/pj2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Titans Curse" data-book="2" >
                <img src="/images/books/PercyJackson/set2/pj3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Battle of the Labrynth" data-book="2" >
                <img src="/images/books/PercyJackson/set2/pj4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Lasy Olympian" data-book="2" >
                <img src="/images/books/PercyJackson/set2/pj5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item rp bookshelf" title="Ready Player One" data-book="3">
                <img src="/images/books/ReadyPlayer/rp1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item rp" title="Ready Player Two">
                <img src="/images/books/ReadyPlayer/rp2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games" data-book="2">
                <img src="/images/books/HungerGames/hg1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games Catching Fire">
                <img src="/images/books/HungerGames/hg2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games Mockingjay">
                <img src="/images/books/HungerGames/hg3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Philosophers Stone" data-book="37" data-audio="74">
                <img src="/images/books/HarryPotter/set1/hp1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Chamber of Secrets" data-book="37" data-audio="74">
                <img src="/images/books/HarryPotter/set1/hp2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Prisoner of Azkaban" data-book="37" data-audio="74">
                <img src="/images/books/HarryPotter/set1/hp3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Goblet of Fire" data-book="37" data-audio="74">
                <img src="/images/books/HarryPotter/set1/hp4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Order of the pheonix" data-book="37" data-audio="71">
                <img src="/images/books/HarryPotter/set1/hp5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Half-Blood prince" data-book="37" data-audio="71">
                <img src="/images/books/HarryPotter/set1/hp6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Deathly Hallows" data-book="37" data-audio="71">
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
            <h2>In no order. Click on a series to expand it</h2>
        </div>

        <div class="gallery" id="reading">
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Fellowship of the Ring" series="lordoftherings">
                <img src="/images/books/LordOfTheRings/lr1.webp" class="gallery-image current" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Two Towers" series="lordoftherings">
                <img src="/images/books/LordOfTheRings/lr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Return of the King" series="lordoftherings">
                <img src="/images/books/LordOfTheRings/lr3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Hidden Oracle" series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp1.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Dark Prophecy" series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp2.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Burning Maze" series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp3.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tyrant's Tomb" series="trialsofapollo">
                <img src="/images/books/TrialsOfApollo/tp4.jpeg" class="gallery-image" />
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tower of Nero" series="trialsofapollo">
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
            <div class="gallery-item bookshelf" title="Skulduggery Pleasant" series="skulduggerypleasant">
                <img src="/images/books/SkulduggeryPleasant/sd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: Playing with Fire" series="skulduggerypleasant">
                <img src="/images/books/SkulduggeryPleasant/sd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: The Faceless Ones" series="skulduggerypleasant">
                <img src="/images/books/SkulduggeryPleasant/sd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: Dark Days" series="skulduggerypleasant">
                <img src="/images/books/SkulduggeryPleasant/sd4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Malificent Seven" series="skulduggerypleasant">
                <img src="/images/books/SkulduggeryPleasant/sb1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Heros of Olympus: The Demigod Diaries">
                <img src="/images/books/HerosOfOlympus/hoo6.webp" class="gallery-image" />
            </div>
            r<div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Game of Thrones" series="asongoficeandfire">
                <img src="/images/books/ASongOfIceAndFire/if1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="A Song of Ice and Fire: A Clash of Kings" series="asongoficeandfire">
                <img src="/images/books/ASongOfIceAndFire/if2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Storm of Swords" series="asongoficeandfire">
                <img src="/images/books/ASongOfIceAndFire/if3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Feast for Crows" series="asongoficeandfire">
                <img src="/images/books/ASongOfIceAndFire/if4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Dance with Dragons" series="asongoficeandfire">
                <img src="/images/books/ASongOfIceAndFire/if5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" series="mazerunner">
                <img src="/images/books/MazeRunner/mr1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Maze Runner" series="mazerunner">
                <img src="/images/books/MazeRunner/mr2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Maze Runner: The Death Cure" series="mazerunner">
                <img src="/images/books/MazeRunner/mr3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight" series="twilight">
                <img src="/images/books/Twlight/tw1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight: New Moon" series="twilight">
                <img src="/images/books/Twlight/tw2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight: Eclipse" series="twilight">
                <img src="/images/books/Twlight/tw3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Twilight: Breaking Dawn" series="twilight">
                <img src="/images/books/Twlight/tw4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Twilight: Midnight Sun" series="twilight">
                <img src="/images/books/Twlight/tw5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Northern Lights" series="hisdarkmaterials">
                <img src="/images/books/HisDarkMaterials/hd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Subtle Knife" series="hisdarkmaterials">
                <img src="/images/books/HisDarkMaterials/hd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Amber Spyglass" series="hisdarkmaterials">
                <img src="/images/books/HisDarkMaterials/hd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Stormbreaker" series="alexrider">
                <img src="/images/books/AlexRider/ar1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Point Blanc" series="alexrider">
                <img src="/images/books/AlexRider/ar2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Skeleeton Key" series="alexrider">
                <img src="/images/books/AlexRider/ar3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Eagle Strike" series="alexrider">
                <img src="/images/books/AlexRider/ar4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Scorpia" series="alexrider">
                <img src="/images/books/AlexRider/ar5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Ark Angel" series="alexrider">
                <img src="/images/books/AlexRider/ar6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Snakehead" series="alexrider">
                <img src="/images/books/AlexRider/ar7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Crocodile Tears" series="alexrider">
                <img src="/images/books/AlexRider/ar8.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Scorpia Rising" series="alexrider">
                <img src="/images/books/AlexRider/ar9.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Russian Roulette" series="alexrider">
                <a href="https://youtu.be/dQw4w9WgXcQ"><img src="/images/books/AlexRider/ar10.webp"
                        class="gallery-image" /></a>
            </div>
            <div class="gallery-item" title="Never say Die" series="alexrider">
                <img src="/images/books/AlexRider/ar11.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="Secret Weapon" series="alexrider">
                <img src="/images/books/AlexRider/ar12.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Chronicles of Narnia: The Magician's Nephew" series="narnia">
                <img src="/images/books/ChronicalsOfNarnia/cn1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Lion the Witch and the Wardrobe" series="narnia">
                <img src="/images/books/ChronicalsOfNarnia/cn2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Horse and His Boy" series="narnia">
                <img src="/images/books/ChronicalsOfNarnia/cn3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: Prince Caspian" series="narnia">
                <img src="/images/books/ChronicalsOfNarnia/cn4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Voyage of the Dawn Treader" series="narnia">
                <img src="/images/books/ChronicalsOfNarnia/cn5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Silver Chair" series="narnia">
                <img src="/images/books/ChronicalsOfNarnia/cn6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Last Battle" series="narnia">
                <img src="/images/books/ChronicalsOfNarnia/cn7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Hitchhiker's Guide to the Galaxy" series="hitchhikers">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="The Restaurant at the End of the Universe" series="hitchhikers">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Life the Universe and Everything" series="hitchhikers">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="So long and thanks for all the fish" series="hitchhikers">
                <img src="/images/books/HitchHikersGuideToTheGalaxy/hh4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Mostly Harmless" series="hitchhikers">
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
            <div class="gallery-item bookshelf" title="How To Train Your Dragon" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to be a Pirate" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to speak Dragonese" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Cheat a Dragons Curse" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Twist a Dragon's Tale" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: A Hero's Guide to Deadly Dragons" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Ride a Dragon's Storm" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Break a Dragon's Heart" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd8.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Steal a Dragons Sword" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd9.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Sieze a Dragon's Jewel" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd10.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Betray a Dragon's Hero" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd11.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Fight a Dragon's Fury" series="httyd">
                <img src="/images/books/HowtoTrainYourDragon/hd12.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Diary of a Wimpy Kid" series="wimpykid">
                <img src="/images/books/DiaryofaWimpyKid/dw1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Diary of a Wimpy Kid: Roderick Rules" series="wimpykid">
                <img src="/images/books/DiaryofaWimpyKid/dw2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="oneup">
                <img src="/images/books/OneUp/ou1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="oneup">
                <img src="/images/books/OneUp/ou2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="oneup">
                <img src="/images/books/OneUp/ou3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb1.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb2.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb3.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb4.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb5.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb6.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb7.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb8.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb9.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb10.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb11.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb12.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb13.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb14.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb15.webp" class="gallery-image" />
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/images/books/BoyVsBeast/bb16.webp" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" series="beastquest">
                <img src="/images/books/BeastQuest/bq1.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/images/books/BeastQuest/bq2.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/images/books/BeastQuest/bq3.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/images/books/BeastQuest/bq4.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/images/books/BeastQuest/bq5.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/images/books/BeastQuest/bq6.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Images you should not masturbate to">
                <img src="/images/books/Standalone/im.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="How to teach your cat Welsh">
                <img src="/images/books/Standalone/cw.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="One Hundred Secret Thoughts Cats Have About Humans">
                <img src="/images/books/Standalone/chr.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="One Hundred Ways for a Cat to find its inner Human">
                <img src="/images/books/Standalone/ik.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Down Under">
                <img src="/images/books/Standalone/du.jpg" class="gallery-image" />
            </div>
            <div class="gallery-item bookshelf" title="Fantastic Beasts and Where to Find Them">
                <img src="/images/books/HarryPotter/fb1.webp" class="gallery-image" />
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

    <script>
document.addEventListener("DOMContentLoaded", function() {
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
                item.addEventListener('click', function() {
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