<!DOCTYPE php>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Page</title>
    <!-- <meta http-equiv="refresh" content="5"> -->
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <style>
        .title {
            font-size: 30px;
            font-weight: bold;
            margin: 10px;
            text-align: center;
            color: white;
        }

        button {
            padding: 10px 20px;
            background-color: #636262;
            color: #ffffff;
            border: none;
            border-radius: 15px;
            font-size: 20px;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }
    </style>

    <body>
<div class="title">
  <h1>Page not found!</h1>
  <h2>The page you are looking for does not exist</h2>

  <!-- back button -->
  <button onclick="history.back()">Go Back</button>
</div>

<script>
  // Get the current URL
  var currentUrl = window.location.href;

  // Create a new element to display the URL
  var urlElement = document.createElement("p");
  urlElement.textContent = "Current URL: " + currentUrl;

  // Get the reference to the h2 element
  var h2Element = document.querySelector(".title h2");

  // Insert the URL element after the h2 element
  h2Element.insertAdjacentElement("afterend", urlElement);
</script>

        <?php
        include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
        ?>

    </body>

</html>