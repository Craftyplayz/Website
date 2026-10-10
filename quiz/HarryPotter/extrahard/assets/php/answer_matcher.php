<?php

function quizNormalizeText(string $answer): string
{
    $answer = Normalizer::normalize($answer, Normalizer::FORM_KD) ?: $answer;
    $answer = transliterator_transliterate('Any-Latin; Latin-ASCII', $answer);
    $answer = mb_strtolower($answer, 'UTF-8');
    $answer = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $answer);
    return trim(preg_replace('/\s+/u', ' ', $answer));
}

function quizDamerauLevenshtein(string $left, string $right): int
{
    $leftLength = strlen($left);
    $rightLength = strlen($right);
    $distance = [];
    for ($i = 0; $i <= $leftLength; $i++) {
        $distance[$i][0] = $i;
    }
    for ($j = 0; $j <= $rightLength; $j++) {
        $distance[0][$j] = $j;
    }
    for ($i = 1; $i <= $leftLength; $i++) {
        for ($j = 1; $j <= $rightLength; $j++) {
            $cost = $left[$i - 1] === $right[$j - 1] ? 0 : 1;
            $distance[$i][$j] = min(
                $distance[$i - 1][$j] + 1,
                $distance[$i][$j - 1] + 1,
                $distance[$i - 1][$j - 1] + $cost
            );
            if ($i > 1 && $j > 1 && $left[$i - 1] === $right[$j - 2] && $left[$i - 2] === $right[$j - 1]) {
                $distance[$i][$j] = min($distance[$i][$j], $distance[$i - 2][$j - 2] + 1);
            }
        }
    }
    return $distance[$leftLength][$rightLength];
}

function quizTextAnswerIsCorrect(string $submitted, int $index, string $correct, array $question): bool
{
    $answer = quizNormalizeText($submitted);
    if ($answer === '') {
        return false;
    }

    $aliases = [
        2 => ['Ukrainian Ironbelly'],
        5 => ['Terence Higgs'],
    ];
    $variants = array_merge([$correct], $aliases[$index] ?? []);
    $normalizedVariants = array_map('quizNormalizeText', $variants);

    $optionNames = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight'];
    foreach ($optionNames as $optionName) {
        if (isset($question[$optionName])) {
            $option = quizNormalizeText($question[$optionName]);
            if (!in_array($option, $normalizedVariants, true) && $answer === $option) {
                return false;
            }
        }
    }

    foreach ($normalizedVariants as $variant) {
        if ($answer === $variant) {
            return true;
        }
        $length = strlen(str_replace(' ', '', $variant));
        $threshold = $length >= 15 ? 2 : ($length >= 8 ? 1 : 0);
        if ($threshold > 0 && quizDamerauLevenshtein($answer, $variant) <= $threshold) {
            return true;
        }
    }
    return false;
}

function quizIntegerValue($value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $value = (string) $value;
    if (!preg_match('/^\d+$/', $value)) {
        return null;
    }
    $value = ltrim($value, '0');
    if ($value === '') {
        return 0;
    }
    $integer = filter_var($value, FILTER_VALIDATE_INT);
    return $integer === false ? null : $integer;
}

function quizOptionalIntegerValue($value): ?int
{
    return $value === null || $value === '' ? 0 : quizIntegerValue($value);
}

function quizAnswerIsCorrect(int $index, array $submitted, string $correct, array $question): bool
{
    if (in_array($index, [1, 2, 3, 5, 10], true)) {
        return isset($submitted['answer']) && is_string($submitted['answer']) &&
            quizTextAnswerIsCorrect($submitted['answer'], $index, $correct, $question);
    }

    if ($index === 0) {
        return quizIntegerValue($submitted['year'] ?? null) === 382 &&
            ($submitted['era'] ?? null) === 'BC' && $correct === '382 BC';
    }
    if ($index === 4) {
        return quizIntegerValue($submitted['number'] ?? null) === 45;
    }
    if ($index === 6) {
        if (!isset($submitted['number']) || !is_string($submitted['number']) ||
            !preg_match('/^(\d+)(?:st|nd|rd|th)?$/i', trim($submitted['number']), $matches)) {
            return false;
        }
        return quizIntegerValue($matches[1]) === 422;
    }
    if ($index === 7 || in_array($index, [8, 12, 13], true)) {
        return isset($submitted['answer']) && is_string($submitted['answer']) &&
            hash_equals($correct, $submitted['answer']);
    }
    if ($index === 9) {
        return quizIntegerValue($submitted['bulgaria'] ?? null) === 160 &&
            quizIntegerValue($submitted['ireland'] ?? null) === 170;
    }
    if ($index === 11) {
        $galleons = quizOptionalIntegerValue($submitted['galleons'] ?? null);
        $sickles = quizOptionalIntegerValue($submitted['sickles'] ?? null);
        $knuts = quizOptionalIntegerValue($submitted['knuts'] ?? null);
        return $galleons === 37 && $sickles === 15 && $knuts === 3 &&
            $sickles <= 16 && $knuts <= 29;
    }
    return false;
}

function quizScoreAnswers(array $submittedAnswers, array $answers, array $questions): array
{
    $correct = 0;
    foreach ($answers as $index => $item) {
        $submitted = $submittedAnswers[$index] ?? null;
        if (is_array($submitted) && quizAnswerIsCorrect($index, $submitted, $item['answer'], $questions[$index])) {
            $correct++;
        }
    }

    $total = count($answers);
    $percentage = $total > 0 ? (int) round(($correct * 100) / $total) : 0;
    $message = $percentage === 100 ? 'Perfect Score!' :
        ($percentage >= 90 ? 'Nearly there!' :
            ($percentage >= 60 ? 'Not bad' :
                ($percentage >= 30 ? 'You should try the easier questions' :
                    'Perfect Score! (if you were trying to get everything wrong)')));
    return ['percentage' => $percentage, 'correct' => $correct, 'total' => $total, 'message' => $message];
}
