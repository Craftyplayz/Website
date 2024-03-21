<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quiz</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.7.4/css/bulma.min.css">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

      <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

  <section class="section">
    <div class="container">

      <div class="columns">
        <div class="column">
          <form id="quiz">
            <div id="content"></div><br>
            <button type="submit" class="button submit is-fullwidth">Submit</button>
          </form>
          <div class="card results">
            <div class="card-content">
              <div class='content'>
                <h3>Your results</h3>
                <nav class="level is-mobile">
                  <div class="level-item has-text-centered">
                    <div>
                      <p class="title" id="percent"></p>
                    </div>
                  </div>
                  <div class="level-item has-text-centered">
                    <div>
                      <p class="title" id="score">123</p>
                    </div>
                  </div>
                </nav>
                <h5 id="message"></h5>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
  <script src="assets/js/scripts.js"></script>
</body>

</html>
