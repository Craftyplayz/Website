<?php

$user_answers = $_POST['all_answers'];

$json = file_get_contents('../json/quiz_answers.json');
$answers = json_decode($json);
$correct = 0;
$wrong_answers = [];

foreach ($answers as $item) {
    if ($user_answers[$item->title] == $item->answer) {
        $correct++;
    } else {
        $wrong_answers[] = $item->title;
    }
    $total++;
}

$messages = array(
    "none" => "This is some text",
    "low" => "This is some text",
    "medium" => "This is some text",
    "high" => "This is some text",
    "perfect" => "This is some text"
);
$percentage = round(($correct * 100) / $total);

if ($percentage == 0) {
    $message = $messages['none'];
} elseif ($percentage >= 30) {
    $message = $messages['low'];
} elseif ($percentage >= 60) {
    $message = $messages['medium'];
} elseif ($percentage >= 90) {
    $message = $messages['high'];
} elseif ($percentage == 100) {
    $message = $messages['perfect'];
}

echo json_encode(array('percentage' => $percentage, 'correct' => $correct, 'total' => $total, 'message' => $message));
