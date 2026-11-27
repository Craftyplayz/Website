<?php
// Validation for Reaction Test scores. Pure functions, no output, so tests/ can load it.

// Returns ['name' => string, 'score' => int, 'tries' => int[5]] or an error message string.
function rt_check($body)
{
    if (!is_array($body)) {
        return 'Send the score as JSON.';
    }
    $name = is_string($body['name'] ?? null) ? $body['name'] : '';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (!preg_match('/^[^\p{C}]{1,20}$/u', $name)) {
        return 'Names must be 1 to 20 characters.';
    }

    $tries = $body['tries'] ?? null;
    if (!is_array($tries) || count($tries) !== 5 || array_keys($tries) !== range(0, 4)) {
        return 'Send all 5 tries.';
    }
    foreach ($tries as $t) {
        if (!is_int($t) || $t < 80 || $t > 10000) {
            return 'One of the tries is not valid.';
        }
    }

    $score = $body['score'] ?? null;
    if (is_float($score) && floor($score) === $score) {
        $score = (int) $score;
    }
    // Under 100 ms on average isn't human; over 2 s isn't worth ranking
    if (!is_int($score) || $score < 100 || $score > 2000) {
        return 'That score is not valid.';
    }
    if ($score !== (int) round(array_sum($tries) / 5)) {
        return 'The tries do not match the score.';
    }
    return ['name' => $name, 'score' => $score, 'tries' => $tries];
}
