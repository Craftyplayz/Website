</div>
<?php
// The footer markup lives in /php/footer.html (shared with plain HTML pages).
// Year, breadcrumbs and cat fact are filled in by /php/layout.js.
readfile(__DIR__ . '/footer.html');
?>

<!-- some PHP pages use jQuery, keep loading it here -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://kit.fontawesome.com/af6ee4244b.js" crossorigin="anonymous"></script>