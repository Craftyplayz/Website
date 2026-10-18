<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/books.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/books/sort.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>
    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Books I've Read</h1>
        <p class="page-lede">What I've read, what I'm reading now and what's next. <a href="https://reading.craftyplayz.com/books">Live stats from Kindle</a></p>
        <nav class="jump" aria-label="Sections">
            <a href="#read-card">Read</a><a href="#reading">Reading now</a><a href="#planned">Planned</a>
        </nav>

        <section class="card section" id="read-card">
            <h2>Read <span class="n" id="read-n"></span></h2>
            <div class="stabs" id="read-f" aria-label="Filter by series">
                <button type="button" class="on" data-f="">All</button>
                <button type="button" data-f="na">Standalone</button>
                <button type="button" data-f="pj">Percy Jackson</button>
                <button type="button" data-f="ho">Heroes of Olympus</button>
                <button type="button" data-f="hp">Harry Potter</button>
                <button type="button" data-f="hg">Hunger Games</button>
                <button type="button" data-f="mc">Minecraft</button>
                <button type="button" data-f="rp">Ready Player One/Two</button>
                <button type="button" data-f="lr">Lord of the Rings/Hobbit</button>
            </div>
            <p class="shelf-key"><span class="cnt rd">3</span> times read <span class="cnt au">3</span> times listened to</p>
            <div class="shelf" id="read">
            <div class="gallery-item lr bookshelf" title="The Hobbit">
                <img src="/media/books/covers/LordOfTheRings/ho1.webp" class="gallery-image" alt="The Hobbit" width="200" height="300" decoding="async">
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Lost Hero">
                <img src="/media/books/covers/HerosOfOlympus/hoo1.webp" class="gallery-image" alt="The Heros of Olympus: The Lost Hero" width="200" height="300" decoding="async">
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Son of Neptune">
                <img src="/media/books/covers/HerosOfOlympus/hoo2.webp" class="gallery-image" alt="The Heros of Olympus: The Son of Neptune" width="200" height="300" decoding="async">
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Mark of Athena">
                <img src="/media/books/covers/HerosOfOlympus/hoo3.webp" class="gallery-image" alt="The Heros of Olympus: The Mark of Athena" width="200" height="300" decoding="async">
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The House of Hades">
                <img src="/media/books/covers/HerosOfOlympus/hoo4.webp" class="gallery-image" alt="The Heros of Olympus: The House of Hades" width="200" height="300" decoding="async">
            </div>
            <div class="gallery-item ho bookshelf" title="The Heros of Olympus: The Blood of Olympus">
                <img src="/media/books/covers/HerosOfOlympus/hoo5.webp" class="gallery-image" alt="The Heros of Olympus: The Blood of Olympus" width="200" height="300" decoding="async">
            </div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Lightning Thief">
                <img src="/media/books/covers/PercyJackson/set2/pj1.webp" class="gallery-image" alt="Percy Jackson: The Lightning Thief" width="200" height="300" decoding="async">
            <span class="cnt rd" title="Times read">2</span></div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Sea of Monsters">
                <img src="/media/books/covers/PercyJackson/set2/pj2.webp" class="gallery-image" alt="Percy Jackson: The Sea of Monsters" width="200" height="300" decoding="async">
            <span class="cnt rd" title="Times read">2</span></div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Titans Curse">
                <img src="/media/books/covers/PercyJackson/set2/pj3.webp" class="gallery-image" alt="Percy Jackson: The Titans Curse" width="200" height="300" decoding="async">
            <span class="cnt rd" title="Times read">2</span></div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Battle of the Labrynth">
                <img src="/media/books/covers/PercyJackson/set2/pj4.webp" class="gallery-image" alt="Percy Jackson: The Battle of the Labrynth" width="200" height="300" decoding="async">
            <span class="cnt rd" title="Times read">2</span></div>
            <div class="gallery-item pj bookshelf" title="Percy Jackson: The Lasy Olympian">
                <img src="/media/books/covers/PercyJackson/set2/pj5.webp" class="gallery-image" alt="Percy Jackson: The Lasy Olympian" width="200" height="300" decoding="async">
            <span class="cnt rd" title="Times read">2</span></div>
            <div class="gallery-item rp bookshelf" title="Ready Player One">
                <img src="/media/books/covers/ReadyPlayer/rp1.webp" class="gallery-image" alt="Ready Player One" width="200" height="300" decoding="async">
            <span class="cnt rd" title="Times read">3</span></div>
            <div class="gallery-item rp" title="Ready Player Two">
                <img src="/media/books/covers/ReadyPlayer/rp2.webp" class="gallery-image" alt="Ready Player Two" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games">
                <img src="/media/books/covers/HungerGames/hg1.webp" class="gallery-image" alt="The Hunger Games" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">2</span></div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games Catching Fire">
                <img src="/media/books/covers/HungerGames/hg2.webp" class="gallery-image" alt="The Hunger Games Catching Fire" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item hg bookshelf" title="The Hunger Games Mockingjay">
                <img src="/media/books/covers/HungerGames/hg3.webp" class="gallery-image" alt="The Hunger Games Mockingjay" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Philosophers Stone">
                <img src="/media/books/covers/HarryPotter/set1/hp1.png" class="gallery-image" alt="Harry potter and the Philosophers Stone" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">37</span><span class="cnt au" title="Times listened to">74</span></div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Chamber of Secrets">
                <img src="/media/books/covers/HarryPotter/set1/hp2.png" class="gallery-image" alt="Harry potter and the Chamber of Secrets" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">37</span><span class="cnt au" title="Times listened to">74</span></div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Prisoner of Azkaban">
                <img src="/media/books/covers/HarryPotter/set1/hp3.png" class="gallery-image" alt="Harry potter and the Prisoner of Azkaban" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">37</span><span class="cnt au" title="Times listened to">74</span></div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Goblet of Fire">
                <img src="/media/books/covers/HarryPotter/set1/hp4.png" class="gallery-image" alt="Harry potter and the Goblet of Fire" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">37</span><span class="cnt au" title="Times listened to">74</span></div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Order of the pheonix">
                <img src="/media/books/covers/HarryPotter/set1/hp5.png" class="gallery-image" alt="Harry potter and the Order of the pheonix" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">37</span><span class="cnt au" title="Times listened to">71</span></div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Half-Blood prince">
                <img src="/media/books/covers/HarryPotter/set1/hp6.png" class="gallery-image" alt="Harry potter and the Half-Blood prince" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">37</span><span class="cnt au" title="Times listened to">71</span></div>
            <div class="gallery-item hp bookshelf" title="Harry potter and the Deathly Hallows">
                <img src="/media/books/covers/HarryPotter/set1/hp7.png" class="gallery-image" alt="Harry potter and the Deathly Hallows" width="200" height="300" loading="lazy" decoding="async">
            <span class="cnt rd" title="Times read">37</span><span class="cnt au" title="Times listened to">71</span></div>
            <div class="gallery-item mc" title="Minecraft Combat Handbook">
                <img src="/media/books/covers/Minecraft/mc1.webp" class="gallery-image" alt="Minecraft Combat Handbook" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item mc bookshelf" title="Minecraft Construction Handbook">
                <img src="/media/books/covers/Minecraft/mc2.webp" class="gallery-image" alt="Minecraft Construction Handbook" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item mc" title="Minecraft Redstone Handbook">
                <img src="/media/books/covers/Minecraft/mc3.webp" class="gallery-image" alt="Minecraft Redstone Handbook" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item mc bookshelf" title="Minecraft Beginner's Handbook">
                <img src="/media/books/covers/Minecraft/mc4.webp" class="gallery-image" alt="Minecraft Beginner's Handbook" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item na bookshelf" title="Encyclopedia of Cats">
                <img src="/media/books/covers/Standalone/ec.webp" class="gallery-image" alt="Encyclopedia of Cats" width="200" height="300" loading="lazy" decoding="async">
            </div>
            </div>
        </section>

        <section class="card section" id="reading">
            <h2>Reading now <span class="n">The Hobbit &amp; The Lord of the Rings</span></h2>
            <div class="shelf">
            <div class="gallery-item lr bookshelf" title="The Hobbit">
                <img src="/media/books/covers/LordOfTheRings/ho1.webp" class="gallery-image" alt="The Hobbit" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Fellowship of the Ring">
                <img src="/media/books/covers/LordOfTheRings/lr1.webp" class="gallery-image current" alt="The Lord of the Rings: The Fellowship of the Ring" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Two Towers">
                <img src="/media/books/covers/LordOfTheRings/lr2.webp" class="gallery-image" alt="The Lord of the Rings: The Two Towers" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Return of the King">
                <img src="/media/books/covers/LordOfTheRings/lr3.webp" class="gallery-image" alt="The Lord of the Rings: The Return of the King" width="200" height="300" loading="lazy" decoding="async">
            </div>
            </div>
        </section>

        <section class="card section" id="planned">
            <h2>Planned <span class="n">In no order · click a series to expand it</span></h2>
            <div class="shelf">
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Fellowship of the Ring" series="lordoftherings">
                <img src="/media/books/covers/LordOfTheRings/lr1.webp" class="gallery-image current" alt="The Lord of the Rings: The Fellowship of the Ring" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Two Towers" series="lordoftherings">
                <img src="/media/books/covers/LordOfTheRings/lr2.webp" class="gallery-image" alt="The Lord of the Rings: The Two Towers" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Lord of the Rings: The Return of the King" series="lordoftherings">
                <img src="/media/books/covers/LordOfTheRings/lr3.webp" class="gallery-image" alt="The Lord of the Rings: The Return of the King" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Hidden Oracle" series="trialsofapollo">
                <img src="/media/books/covers/TrialsOfApollo/tp1.jpg" class="gallery-image" alt="The Trials of Apollo: The Hidden Oracle" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Dark Prophecy" series="trialsofapollo">
                <img src="/media/books/covers/TrialsOfApollo/tp2.jpg" class="gallery-image" alt="The Trials of Apollo: The Dark Prophecy" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Burning Maze" series="trialsofapollo">
                <img src="/media/books/covers/TrialsOfApollo/tp3.jpg" class="gallery-image" alt="The Trials of Apollo: The Burning Maze" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tyrant's Tomb" series="trialsofapollo">
                <img src="/media/books/covers/TrialsOfApollo/tp4.jpg" class="gallery-image" alt="The Trials of Apollo: The Tyrant's Tomb" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item tp bookshelf" title="The Trials of Apollo: The Tower of Nero" series="trialsofapollo">
                <img src="/media/books/covers/TrialsOfApollo/tp5.jpg" class="gallery-image" alt="The Trials of Apollo: The Tower of Nero" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Ender's Game">
                <img src="/media/books/covers/Standalone/eg.jpg" class="gallery-image" alt="Ender's Game" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Percy Jackson And Chalice of the Gods">
                <img src="/media/books/covers/PercyJackson/pj6.webp" class="gallery-image" alt="Percy Jackson And Chalice of the Gods" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Miss Peregrine's Home for Peculiar Children">
                <img src="/media/books/covers/Standalone/pc.webp" class="gallery-image" alt="Miss Peregrine's Home for Peculiar Children" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Tha Ballard of Songbirds and Snakes">
                <img src="/media/books/covers/HungerGames/hg0.webp" class="gallery-image" alt="Tha Ballard of Songbirds and Snakes" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Skulduggery Pleasant" series="skulduggerypleasant">
                <img src="/media/books/covers/SkulduggeryPleasant/sd1.webp" class="gallery-image" alt="Skulduggery Pleasant" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: Playing with Fire" series="skulduggerypleasant">
                <img src="/media/books/covers/SkulduggeryPleasant/sd2.webp" class="gallery-image" alt="Skulduggery Pleasant: Playing with Fire" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: The Faceless Ones" series="skulduggerypleasant">
                <img src="/media/books/covers/SkulduggeryPleasant/sd3.webp" class="gallery-image" alt="Skulduggery Pleasant: The Faceless Ones" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Skulduggery Pleasant: Dark Days" series="skulduggerypleasant">
                <img src="/media/books/covers/SkulduggeryPleasant/sd4.webp" class="gallery-image" alt="Skulduggery Pleasant: Dark Days" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Malificent Seven" series="skulduggerypleasant">
                <img src="/media/books/covers/SkulduggeryPleasant/sb1.webp" class="gallery-image" alt="Malificent Seven" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Heros of Olympus: The Demigod Diaries">
                <img src="/media/books/covers/HerosOfOlympus/hoo6.webp" class="gallery-image" alt="Heros of Olympus: The Demigod Diaries" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Game of Thrones" series="asongoficeandfire">
                <img src="/media/books/covers/ASongOfIceAndFire/if1.webp" class="gallery-image" alt="A Song of Ice and Fire: A Game of Thrones" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="A Song of Ice and Fire: A Clash of Kings" series="asongoficeandfire">
                <img src="/media/books/covers/ASongOfIceAndFire/if2.webp" class="gallery-image" alt="A Song of Ice and Fire: A Clash of Kings" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Storm of Swords" series="asongoficeandfire">
                <img src="/media/books/covers/ASongOfIceAndFire/if3.webp" class="gallery-image" alt="A Song of Ice and Fire: A Storm of Swords" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Feast for Crows" series="asongoficeandfire">
                <img src="/media/books/covers/ASongOfIceAndFire/if4.webp" class="gallery-image" alt="A Song of Ice and Fire: A Feast for Crows" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="A Song of Ice and Fire: A Dance with Dragons" series="asongoficeandfire">
                <img src="/media/books/covers/ASongOfIceAndFire/if5.webp" class="gallery-image" alt="A Song of Ice and Fire: A Dance with Dragons" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" series="mazerunner">
                <img src="/media/books/covers/MazeRunner/mr1.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Maze Runner" series="mazerunner">
                <img src="/media/books/covers/MazeRunner/mr2.webp" class="gallery-image" alt="The Maze Runner" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Maze Runner: The Death Cure" series="mazerunner">
                <img src="/media/books/covers/MazeRunner/mr3.webp" class="gallery-image" alt="The Maze Runner: The Death Cure" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Twilight" series="twilight">
                <img src="/media/books/covers/Twlight/tw1.webp" class="gallery-image" alt="Twilight" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Twilight: New Moon" series="twilight">
                <img src="/media/books/covers/Twlight/tw2.webp" class="gallery-image" alt="Twilight: New Moon" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Twilight: Eclipse" series="twilight">
                <img src="/media/books/covers/Twlight/tw3.webp" class="gallery-image" alt="Twilight: Eclipse" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Twilight: Breaking Dawn" series="twilight">
                <img src="/media/books/covers/Twlight/tw4.webp" class="gallery-image" alt="Twilight: Breaking Dawn" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Twilight: Midnight Sun" series="twilight">
                <img src="/media/books/covers/Twlight/tw5.webp" class="gallery-image" alt="Twilight: Midnight Sun" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Northern Lights" series="hisdarkmaterials">
                <img src="/media/books/covers/HisDarkMaterials/hd1.webp" class="gallery-image" alt="The Northern Lights" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Subtle Knife" series="hisdarkmaterials">
                <img src="/media/books/covers/HisDarkMaterials/hd2.webp" class="gallery-image" alt="The Subtle Knife" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Amber Spyglass" series="hisdarkmaterials">
                <img src="/media/books/covers/HisDarkMaterials/hd3.webp" class="gallery-image" alt="The Amber Spyglass" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Stormbreaker" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar1.webp" class="gallery-image" alt="Stormbreaker" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Point Blanc" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar2.webp" class="gallery-image" alt="Point Blanc" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Skeleeton Key" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar3.webp" class="gallery-image" alt="Skeleeton Key" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Eagle Strike" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar4.webp" class="gallery-image" alt="Eagle Strike" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Scorpia" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar5.webp" class="gallery-image" alt="Scorpia" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Ark Angel" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar6.webp" class="gallery-image" alt="Ark Angel" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Snakehead" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar7.webp" class="gallery-image" alt="Snakehead" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Crocodile Tears" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar8.webp" class="gallery-image" alt="Crocodile Tears" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Scorpia Rising" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar9.webp" class="gallery-image" alt="Scorpia Rising" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Russian Roulette" series="alexrider">
                <a href="https://youtu.be/dQw4w9WgXcQ"><img src="/media/books/covers/AlexRider/ar10.webp"
                        class="gallery-image" alt="Russian Roulette" width="200" height="300" loading="lazy" decoding="async"></a>
            </div>
            <div class="gallery-item" title="Never say Die" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar11.webp" class="gallery-image" alt="Never say Die" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Secret Weapon" series="alexrider">
                <img src="/media/books/covers/AlexRider/ar12.webp" class="gallery-image" alt="Secret Weapon" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Chronicles of Narnia: The Magician's Nephew" series="narnia">
                <img src="/media/books/covers/ChronicalsOfNarnia/cn1.webp" class="gallery-image" alt="The Chronicles of Narnia: The Magician's Nephew" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Lion the Witch and the Wardrobe" series="narnia">
                <img src="/media/books/covers/ChronicalsOfNarnia/cn2.webp" class="gallery-image" alt="The Chronicles of Narnia: The Lion the Witch and the Wardrobe" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Horse and His Boy" series="narnia">
                <img src="/media/books/covers/ChronicalsOfNarnia/cn3.webp" class="gallery-image" alt="The Chronicles of Narnia: The Horse and His Boy" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: Prince Caspian" series="narnia">
                <img src="/media/books/covers/ChronicalsOfNarnia/cn4.webp" class="gallery-image" alt="The Chronicles of Narnia: Prince Caspian" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Voyage of the Dawn Treader" series="narnia">
                <img src="/media/books/covers/ChronicalsOfNarnia/cn5.webp" class="gallery-image" alt="The Chronicles of Narnia: The Voyage of the Dawn Treader" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Silver Chair" series="narnia">
                <img src="/media/books/covers/ChronicalsOfNarnia/cn6.webp" class="gallery-image" alt="The Chronicles of Narnia: The Silver Chair" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="The Chronicles of Narnia: The Last Battle" series="narnia">
                <img src="/media/books/covers/ChronicalsOfNarnia/cn7.webp" class="gallery-image" alt="The Chronicles of Narnia: The Last Battle" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Hitchhiker's Guide to the Galaxy" series="hitchhikers">
                <img src="/media/books/covers/HitchHikersGuideToTheGalaxy/hh1.webp" class="gallery-image" alt="Hitchhiker's Guide to the Galaxy" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="The Restaurant at the End of the Universe" series="hitchhikers">
                <img src="/media/books/covers/HitchHikersGuideToTheGalaxy/hh2.webp" class="gallery-image" alt="The Restaurant at the End of the Universe" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Life the Universe and Everything" series="hitchhikers">
                <img src="/media/books/covers/HitchHikersGuideToTheGalaxy/hh3.webp" class="gallery-image" alt="Life the Universe and Everything" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="So long and thanks for all the fish" series="hitchhikers">
                <img src="/media/books/covers/HitchHikersGuideToTheGalaxy/hh4.webp" class="gallery-image" alt="So long and thanks for all the fish" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Mostly Harmless" series="hitchhikers">
                <img src="/media/books/covers/HitchHikersGuideToTheGalaxy/hh5.webp" class="gallery-image" alt="Mostly Harmless" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Famous Five Series (21 books)">
                <img src="/media/books/covers/FamousFive/ff1.webp" class="gallery-image" alt="Famous Five Series (21 books)" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="Secret Seven Series (17 books)">
                <img src="/media/books/covers/SecretSeven/ss1.webp" class="gallery-image" alt="Secret Seven Series (17 books)" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Artemis Fowl">
                <img src="/media/books/covers/ArtemisFowl/af1.jpg" class="gallery-image" alt="Artemis Fowl" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="How To Train Your Dragon" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd1.webp" class="gallery-image" alt="How To Train Your Dragon" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to be a Pirate" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd2.webp" class="gallery-image" alt="How To Train Your Dragon: How to be a Pirate" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to speak Dragonese" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd3.webp" class="gallery-image" alt="How To Train Your Dragon: How to speak Dragonese" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Cheat a Dragons Curse" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd4.webp" class="gallery-image" alt="How To Train Your Dragon: How to Cheat a Dragons Curse" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Twist a Dragon's Tale" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd5.webp" class="gallery-image" alt="How To Train Your Dragon: How to Twist a Dragon's Tale" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: A Hero's Guide to Deadly Dragons" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd6.webp" class="gallery-image" alt="How To Train Your Dragon: A Hero's Guide to Deadly Dragons" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Ride a Dragon's Storm" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd7.webp" class="gallery-image" alt="How To Train Your Dragon: How to Ride a Dragon's Storm" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Break a Dragon's Heart" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd8.webp" class="gallery-image" alt="How To Train Your Dragon: How to Break a Dragon's Heart" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Steal a Dragons Sword" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd9.webp" class="gallery-image" alt="How To Train Your Dragon: How to Steal a Dragons Sword" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Sieze a Dragon's Jewel" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd10.webp" class="gallery-image" alt="How To Train Your Dragon: How to Sieze a Dragon's Jewel" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Betray a Dragon's Hero" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd11.webp" class="gallery-image" alt="How To Train Your Dragon: How to Betray a Dragon's Hero" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" title="How To Train Your Dragon: How to Fight a Dragon's Fury" series="httyd">
                <img src="/media/books/covers/HowtoTrainYourDragon/hd12.webp" class="gallery-image" alt="How To Train Your Dragon: How to Fight a Dragon's Fury" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Diary of a Wimpy Kid" series="wimpykid">
                <img src="/media/books/covers/DiaryofaWimpyKid/dw1.webp" class="gallery-image" alt="Diary of a Wimpy Kid" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Diary of a Wimpy Kid: Roderick Rules" series="wimpykid">
                <img src="/media/books/covers/DiaryofaWimpyKid/dw2.webp" class="gallery-image" alt="Diary of a Wimpy Kid: Roderick Rules" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="oneup">
                <img src="/media/books/covers/OneUp/ou1.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="oneup">
                <img src="/media/books/covers/OneUp/ou2.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="oneup">
                <img src="/media/books/covers/OneUp/ou3.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb1.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb2.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb3.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb4.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb5.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb6.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb7.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb8.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb9.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb10.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb11.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb12.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb13.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb14.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb15.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="boyvsbeast">
                <img src="/media/books/covers/BoyVsBeast/bb16.webp" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" series="beastquest">
                <img src="/media/books/covers/BeastQuest/bq1.jpg" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/media/books/covers/BeastQuest/bq2.jpg" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/media/books/covers/BeastQuest/bq3.jpg" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/media/books/covers/BeastQuest/bq4.jpg" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/media/books/covers/BeastQuest/bq5.jpg" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item" series="beastquest">
                <img src="/media/books/covers/BeastQuest/bq6.jpg" class="gallery-image" alt="Book cover" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Images you should not masturbate to">
                <img src="/media/books/covers/Standalone/im.jpg" class="gallery-image" alt="Images you should not masturbate to" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="How to teach your cat Welsh">
                <img src="/media/books/covers/Standalone/cw.jpg" class="gallery-image" alt="How to teach your cat Welsh" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="One Hundred Secret Thoughts Cats Have About Humans">
                <img src="/media/books/covers/Standalone/chr.jpg" class="gallery-image" alt="One Hundred Secret Thoughts Cats Have About Humans" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="One Hundred Ways for a Cat to find its inner Human">
                <img src="/media/books/covers/Standalone/ik.jpg" class="gallery-image" alt="One Hundred Ways for a Cat to find its inner Human" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Down Under">
                <img src="/media/books/covers/Standalone/du.jpg" class="gallery-image" alt="Down Under" width="200" height="300" loading="lazy" decoding="async">
            </div>
            <div class="gallery-item bookshelf" title="Fantastic Beasts and Where to Find Them">
                <img src="/media/books/covers/HarryPotter/fb1.webp" class="gallery-image" alt="Fantastic Beasts and Where to Find Them" width="200" height="300" loading="lazy" decoding="async">
            </div>

            </div>
        </section>

        <p class="updated">Last updated <?= date('jS F Y', filemtime(__FILE__)) ?></p>
    </main>
    <?php site_footer(); ?>
</body>

</html>
