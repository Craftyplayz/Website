</div>
<footer>
    <?php
    $year = date("Y");
    echo "© $year CraftyPlayz";
    ?>
    <?php

    // This function will take $_SERVER['REQUEST_URI'] and build a breadcrumb based on the user's current path
    function breadcrumbs($separator = ' &raquo; ', $home = 'Home')
    {
        // This gets the REQUEST_URI (/path/to/file.php), splits the string (using '/') into an array, and then filters out any empty values
        $path = array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    
        // This will build our "base URL" ... Also accounts for HTTPS :)
        $base = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
    
        // Initialize a temporary array with our breadcrumbs. (starting with our home page, which I'm assuming will be the base URL)
        $breadcrumbs = array("<a style=\"color: white;\" href=\"$base\">$home</a>");
    
        // Find out the index for the last value in our path array
        $keys = array_keys($path);
        $last = end($keys);
    
        // Build the rest of the breadcrumbs
        $url = $base; // Initialize the URL variable
        foreach ($path as $x => $crumb) {
            // Our "title" is the text that will be displayed (strip out .php and turn '_' into a space)
            $title = ucwords(str_replace(array('.php', '_'), array('', ' '), $crumb));
    
            // Append the current crumb to the URL
            $url .= $crumb . '/';
    
            // If we are not on the last index, then display an <a> tag
            if ($x != $last)
                $breadcrumbs[] = "<a style=\"color: white;\" href=\"$url\">$title</a>";
            // Otherwise, just display the title
            else
                $breadcrumbs[] = $title;
        }
    
        // Build our temporary array (pieces of bread) into one big string :)
        return implode($separator, $breadcrumbs);
    }

    ?>
    <p>
        <?= breadcrumbs(' > ') ?>
    </p>
    
<p>
    <!-- Display a random cat fact -->
    <?php
    // Set the URL for the cat fact API
    $url = "https://meowfacts.herokuapp.com";

    // Initialize a cURL session
    $ch = curl_init();

    // Set the URL and option to return the response as a string
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the cURL session and get the response
    $response = curl_exec($ch);

    // Close the cURL session
    curl_close($ch);

    // Decode the JSON response
    $data = json_decode($response, true);

    // Loop through the cat facts and display them
    foreach($data['data'] as $fact) {
        echo "Random cat fact: " . $fact . "\n";
    }
    ?>
</p>

</footer>

<script>
    const navSlide = () => {
        const burger = document.querySelector('.hamburger');
        const nav = document.querySelector('.nav-links');
        burger.addEventListener('click', () => {
            nav.classList.toggle('nav-active');
        });
    }
    navSlide();
</script>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://kit.fontawesome.com/af6ee4244b.js" crossorigin="anonymous"></script>

<script>
    $(document).ready(function () {
        $('#toggleHeader').click(function () {
            $('#header').toggle();
        });
    });
</script>