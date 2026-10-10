<?php

require_once __DIR__ . '/../assets/php/answer_matcher.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$questions = json_decode(file_get_contents(__DIR__ . '/../assets/json/quiz.json'), true);
$answers = json_decode(file_get_contents(__DIR__ . '/../assets/json/quiz_answers.json'), true);
expect(count($questions) === 14 && count($answers) === 14, 'all 14 original question and answer records remain');

$allCorrect = [
    ['year' => '382', 'era' => 'BC'],
    ['answer' => 'Cassandra Vablatsky'],
    ['answer' => 'Ukraniun Ironbelly'],
    ['answer' => 'Victoire Weasley'],
    ['number' => '45'],
    ['answer' => 'Terrance Higgs'],
    ['number' => '422nd'],
    ['answer' => '4th'],
    ['answer' => 'Elphias Doge'],
    ['bulgaria' => '160', 'ireland' => '170'],
    ['answer' => 'Piers Polkiss'],
    ['galleons' => '37', 'sickles' => '15', 'knuts' => '3'],
    ['answer' => 'A Book store and a Record shop'],
    ['answer' => 'Hazelnut'],
];
foreach ($answers as $index => $item) {
    expect(quizAnswerIsCorrect($index, $allCorrect[$index], $item['answer'], $questions[$index]), "question " . ($index + 1) . " accepts its original verified answer");
}

foreach ([
    [1, '  CASSANDRA   VABLÁTSKY! ', true],
    [1, 'Casandra Vablatsky', true],
    [1, 'Cassanddra Vablatsky', true],
    [1, 'Cassand ra Vablatsky', true],
    [1, 'Cassanrda Vablatsky', true],
    [1, 'Cassandra Bagshot', false],
    [1, 'Cassandra Vablatsk', true],
    [2, 'Ukrainian Ironbelly', true],
    [3, 'Victoire-Weasley', true],
    [3, 'Victor Weasley', false],
    [5, 'Terence Higgs', true],
    [5, 'Draco Malfoy', false],
    [10, 'Piers  Polkiss!', true],
    [10, 'Oliver Montague', false],
    [10, 'Piers', false],
] as [$index, $answer, $expected]) {
    expect(quizTextAnswerIsCorrect($answer, $index, $answers[$index]['answer'], $questions[$index]) === $expected, "text match for question " . ($index + 1) . ": {$answer}");
}

expect(!quizAnswerIsCorrect(0, ['year' => '382', 'era' => 'AD'], '382 BC', $questions[0]), 'BC/AD must match');
expect(quizAnswerIsCorrect(0, ['year' => '0382', 'era' => 'BC'], '382 BC', $questions[0]), 'numeric year accepts leading zero formatting');
expect(!quizAnswerIsCorrect(0, ['year' => '-382', 'era' => 'BC'], '382 BC', $questions[0]), 'negative year is rejected');
expect(!quizAnswerIsCorrect(4, ['number' => '46'], '45', $questions[4]), 'numeric answer is exact');
expect(quizAnswerIsCorrect(6, ['number' => '422ND'], '422nd', $questions[6]), 'ordinal suffix is accepted');
expect(!quizAnswerIsCorrect(6, ['number' => '421st'], '422nd', $questions[6]), 'wrong ordinal is rejected');
expect(!quizAnswerIsCorrect(6, ['number' => '422.5'], '422nd', $questions[6]), 'non-integer ordinal is rejected');
expect(quizAnswerIsCorrect(7, ['answer' => '4th'], '4th', $questions[7]), 'correct floor option is accepted');
expect(!quizAnswerIsCorrect(7, ['answer' => '3rd'], '4th', $questions[7]), 'wrong floor option is rejected');
expect(!quizAnswerIsCorrect(9, ['bulgaria' => '170', 'ireland' => '160'], 'Bulgaria 160 Ireland 170', $questions[9]), 'swapped team scores are rejected');
expect(quizAnswerIsCorrect(9, ['bulgaria' => '160', 'ireland' => '170'], 'Bulgaria 160 Ireland 170', $questions[9]), 'exact team scores are accepted');
expect(!quizAnswerIsCorrect(11, ['galleons' => '38', 'sickles' => '0', 'knuts' => '3'], '37 Galleons, 15 Sickles and 3 Knuts', $questions[11]), 'equivalent currency totals are rejected');
expect(!quizAnswerIsCorrect(11, ['galleons' => '-1', 'sickles' => '15', 'knuts' => '3'], '37 Galleons, 15 Sickles and 3 Knuts', $questions[11]), 'negative currency is rejected');
expect(!quizAnswerIsCorrect(11, ['galleons' => '37', 'sickles' => '17', 'knuts' => '3'], '37 Galleons, 15 Sickles and 3 Knuts', $questions[11]), 'invalid sickle denomination is rejected');
expect(!quizAnswerIsCorrect(11, ['galleons' => '37', 'knuts' => '3'], '37 Galleons, 15 Sickles and 3 Knuts', $questions[11]), 'omitted currency denomination is zero');
expect(quizAnswerIsCorrect(11, ['galleons' => '37', 'sickles' => '', 'knuts' => '3'], '37 Galleons, 15 Sickles and 3 Knuts', $questions[11]) === false, 'blank currency denomination is zero and does not match');
expect(quizOptionalIntegerValue(null) === 0 && quizOptionalIntegerValue('') === 0, 'omitted currency denominations normalize to zero');

foreach ([8, 12, 13] as $index) {
    expect(quizAnswerIsCorrect($index, ['answer' => $answers[$index]['answer']], $answers[$index]['answer'], $questions[$index]), "multiple choice question " . ($index + 1) . " accepts the verified choice");
    expect(!quizAnswerIsCorrect($index, ['answer' => $questions[$index]['one']], $answers[$index]['answer'], $questions[$index]), "multiple choice question " . ($index + 1) . " rejects a wrong choice");
}

$score = quizScoreAnswers($allCorrect, $answers, $questions);
expect($score['correct'] === 14 && $score['total'] === 14 && $score['percentage'] === 100, 'perfect score and completion count are preserved');
$allCorrect[4]['number'] = '46';
$score = quizScoreAnswers($allCorrect, $answers, $questions);
expect($score['correct'] === 13 && $score['total'] === 14 && $score['percentage'] === 93, 'one wrong answer changes the score exactly once');

echo "All Extra Hard quiz matcher tests passed.\n";
