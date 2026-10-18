<?php

function quizNormalizeText(string $answer): string
{
    if (class_exists('Normalizer')) {
        $answer = Normalizer::normalize($answer, Normalizer::FORM_KD) ?: $answer;
    }
    if (function_exists('transliterator_transliterate')) {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $answer);
        $answer = is_string($transliterated) ? $transliterated : $answer;
    } elseif (function_exists('iconv')) {
        $answer = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $answer) ?: $answer;
    }
    $answer = function_exists('mb_strtolower') ? mb_strtolower($answer, 'UTF-8') : strtolower($answer);
    $answer = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $answer) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $answer) ?? '');
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

function quizFuzzyTextMatch(string $answer, string $variant): bool
{
    $answerWords = explode(' ', $answer);
    $variantWords = explode(' ', $variant);
    $totalLength = strlen(str_replace(' ', '', $variant));
    $totalThreshold = $totalLength >= 15 ? 2 : ($totalLength >= 8 ? 1 : 0);
    if ($totalThreshold === 0) {
        return false;
    }

    if (count($answerWords) !== count($variantWords)) {
        return quizDamerauLevenshtein($answer, $variant) <= 1;
    }

    $totalDistance = 0;
    foreach ($variantWords as $wordIndex => $variantWord) {
        $wordLength = strlen($variantWord);
        $wordThreshold = $wordLength >= 14 ? 2 : ($wordLength >= 7 ? 1 : 0);
        $wordDistance = quizDamerauLevenshtein($answerWords[$wordIndex], $variantWord);
        if ($wordDistance > $wordThreshold) {
            return false;
        }
        $totalDistance += $wordDistance;
    }
    return $totalDistance > 0 && $totalDistance <= $totalThreshold;
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
        if (quizFuzzyTextMatch($answer, $variant)) {
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
        if (!preg_match('/^(\d+)\s+(BC|AD)$/i', $correct, $expected)) {
            return false;
        }
        return quizIntegerValue($submitted['year'] ?? null) === quizIntegerValue($expected[1]) &&
            strtoupper((string) ($submitted['era'] ?? '')) === strtoupper($expected[2]);
    }
    if ($index === 4) {
        return quizIntegerValue($submitted['number'] ?? null) === quizIntegerValue($correct);
    }
    if ($index === 6) {
        if (!preg_match('/^(\d+)(?:st|nd|rd|th)?$/i', $correct, $expected) ||
            !isset($submitted['number']) || !is_string($submitted['number']) ||
            !preg_match('/^(\d+)(?:st|nd|rd|th)?$/i', trim($submitted['number']), $matches)) {
            return false;
        }
        return quizIntegerValue($matches[1]) === quizIntegerValue($expected[1]);
    }
    if ($index === 7 || in_array($index, [8, 12, 13], true)) {
        return isset($submitted['answer']) && is_string($submitted['answer']) &&
            hash_equals($correct, $submitted['answer']);
    }
    if ($index === 9) {
        if (!preg_match('/^Bulgaria\s+(\d+)\s+Ireland\s+(\d+)$/i', $correct, $expected)) {
            return false;
        }
        return quizIntegerValue($submitted['bulgaria'] ?? null) === quizIntegerValue($expected[1]) &&
            quizIntegerValue($submitted['ireland'] ?? null) === quizIntegerValue($expected[2]);
    }
    if ($index === 11) {
        if (!preg_match('/^(\d+)\s+Galleons,\s*(\d+)\s+Sickles\s+and\s+(\d+)\s+Knuts$/i', $correct, $expected)) {
            return false;
        }
        $galleons = quizOptionalIntegerValue($submitted['galleons'] ?? null);
        $sickles = quizOptionalIntegerValue($submitted['sickles'] ?? null);
        $knuts = quizOptionalIntegerValue($submitted['knuts'] ?? null);
        return $galleons === quizIntegerValue($expected[1]) &&
            $sickles === quizIntegerValue($expected[2]) &&
            $knuts === quizIntegerValue($expected[3]) &&
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
