<?php

$user_answers = $_POST['all_answers'];

$json = file_get_contents('../json/quiz_answers.json');
$answers = json_decode($json);
$correct = 0;
$total = 0;
$wrong_answers = [];

foreach ($answers as $item) {
    if (($user_answers[$item->title] ?? null) == $item->answer) {
        $correct++;
    } else {
        $wrong_answers[] = $item->title;
    }
    $total++;
}

$messages = array(
    "none" => "Perfect Score! (if you were trying to get everything wrong)",
    "low" => "You should try the easier questions",
    "medium" => "Not bad",
    "high" => "Nearly there!",
    "perfect" => "Perfect Score!"
);
$percentage = round(($correct * 100) / $total);

if ($percentage == 0) {
    $message = $messages['none'];
} elseif ($percentage == 100) {
    $message = $messages['perfect'];
} elseif ($percentage >= 90) {
    $message = $messages['high'];
} elseif ($percentage >= 60) {
    $message = $messages['medium'];
} else {
    $message = $messages['low'];
}

echo json_encode(array('percentage' => $percentage, 'correct' => $correct, 'total' => $total, 'message' => $message));
