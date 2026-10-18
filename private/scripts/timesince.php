<?php
$date = new DateTime('2024-12-21 21:47:00');
$now = new DateTime();
$interval = $now->diff($date);
echo 'Time since 9:47PM 21/12/2024: ' . $interval->format('%Y years, %m months, %d days, %h hours, %i minutes, %s seconds');
?>