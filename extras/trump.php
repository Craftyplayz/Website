<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Trump Quote</title>
    <meta http-equiv="refresh" content="-1">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>
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
  <img id="bouncing-image" style="width: 200px; height: 200px; " src="/images/trump-mugshot.webp" alt="Bouncing Image">
</div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>
<script></script
</html>