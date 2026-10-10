<?php

require_once __DIR__ . '/answer_matcher.php';

$submittedAnswers = $_POST['all_answers'] ?? null;
$answers = json_decode(file_get_contents(__DIR__ . '/../json/quiz_answers.json'), true);
$questions = json_decode(file_get_contents(__DIR__ . '/../json/quiz.json'), true);

if (!is_array($submittedAnswers) || !is_array($answers) || !is_array($questions) ||
    count($submittedAnswers) !== count($answers) || count($answers) !== 14) {
    http_response_code(400);
    exit;
}

$result = quizScoreAnswers($submittedAnswers, $answers, $questions);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
