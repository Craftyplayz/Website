<?php
// Score validation for Typing Defence. Returns [name, score, wpm, duration] or an error string.
// Must match typing-defence.js: level = 1 + floor(seconds / TD_LEVEL_SECS).
const TD_LEVEL_SECS = 20;
const TD_MAX_WPM    = 250;
const TD_MAX_TIME   = 3600;

function td_validate($body)
{
    if (!is_array($body)) {
        return 'Send the score as JSON.';
    }
    $name = is_string($body['name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $body['name']) ?? '') : '';
    if (!preg_match('/^[^\p{C}]{1,20}$/u', $name)) {
        return 'Names must be 1 to 20 characters.';
    }
    $score = $body['score'] ?? null;
    $wpm   = $body['wpm'] ?? null;
    $secs  = $body['duration'] ?? null;
    foreach ([$score, $wpm, $secs] as $v) {
        if (!is_int($v)) {
            return 'That score is not valid.';
        }
    }
    if ($wpm < 1 || $wpm > TD_MAX_WPM || $secs < 5 || $secs > TD_MAX_TIME || $score < 1) {
        return 'That score is not valid.';
    }
    // Most letters you could type at this speed, each worth at most the level reached
    $letters = ($wpm + 1) * 5 * $secs / 60 + 10;
    $max     = $letters * (1 + intdiv($secs, TD_LEVEL_SECS));
    if ($score > $max) {
        return 'That score is not valid.';
    }
    return [$name, $score, $wpm, $secs];
}
