<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
    <?php
    $characterId = $_GET['id'] ?? '113247670';
    $urldnd = "https://character-service.dndbeyond.com/character/v5/character/{$characterId}";
    $filednd = "/extras/examples/dndcharacter.json";
    $dnd = file_get_contents($urldnd);
    $dnd = json_decode($dnd, true);
?>
<head>
    <?php page_head(); ?>
    <meta http-equiv="refresh" content="100000000">
    <link rel="stylesheet" href="/assets/css/pages/dnd.css">
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <div class="title">
        <h1><?php echo ($dnd["data"]["name"]);?> Work in progress!</h1>
    </div>

    <?php /* echo "<img src=" . $dnd["data"]["decorations"]["avatarUrl"] . " class='avatar'>"; */?>

    <div class="info">
        <p>Character Name: <?php echo ($dnd["data"]["name"]);?></p>
        <p>Character Race: <?php echo ($dnd["data"]["race"]["fullName"]);?></p>
        <p>Character Level: <?php echo ($dnd["data"]["classes"][0]["level"]);?></p>
        <p>Character Class: <?php echo $dnd['data']['classes'][0]['definition']['name']; ?></p>
        <?php echo $dnd['data']['classes'][0]['definition']['description']; ?>

    </div>
    </main>
    <?php site_footer(); ?>

<?php
?>

<!-- https://character-service.dndbeyond.com/character/v5/character/113247670 -->

</body>

</html>