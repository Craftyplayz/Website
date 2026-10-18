<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">
    <div class="title">
        <h1>Random Donald Trump Quote</h1>
    </div>
    <style>
        #container {
  position: fixed;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
}

#bouncing-image {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation: bounce 2s infinite;
}

@keyframes bounce {
  0% {
    top: 10%;
    transform: translate(-50%, -50%) scale(1);
  }
  50% {
    top: 20%;
    transform: translate(-50%, -50%) scale(1);
  }
  100% {
    top: 10%;
    transform: translate(-50%, -50%) scale(1);
  }
}

        </style>

    <div class="title">
        <p>
            <?php
            $curl = curl_init('https://api.tronalddump.io/random/quote');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);
            curl_close($curl);

            $data = json_decode($response, true);
            echo $data['value'] . "\n<br>";
            $created_at = $data['created_at'];
            $formatted_date = date("jS F Y", strtotime($created_at));
            echo "Date: " . $formatted_date . "\n<br>Tags: ";
            foreach ($data['tags'] as $item) {
                echo $item;
            }
            ?>
        </p>
    </div>
    <div id="container">
  <img id="bouncing-image" style="width: 200px; height: 200px; " src="/media/memes/trump-mugshot.webp" alt="Bouncing Image">
</div>

    </main>
    <?php site_footer(); ?>

</body>
<script></script
</html>