<?php
// CLI self-check: php -d zend.assertions=1 -d assert.exception=1 tests/validate_test.php
require __DIR__ . '/../validate.php';
$ok  = ['name' => '  Crafty  ', 'score' => 300, 'wpm' => 60, 'duration' => 60];
$bad = fn (array $over) => is_string(td_validate($over + $ok));
assert(td_validate($ok) === ['Crafty', 300, 60, 60]);
assert($bad(['name' => '']));
assert($bad(['name' => str_repeat('a', 21)]));
assert($bad(['name' => "a\x00b"]));
assert($bad(['name' => 5]));
assert($bad(['wpm' => 251]));
assert($bad(['wpm' => 0]));
assert($bad(['score' => '300']));
assert($bad(['score' => 300.5]));
assert($bad(['score' => 0]));
assert($bad(['duration' => 4]));
assert($bad(['duration' => 3601]));
assert($bad(['score' => 999999])); // not reachable in 60 s at 60 wpm
assert(is_string(td_validate('x')));
echo "validate_test ok\n";

// Word list: 3 to 8 lowercase letters each, no duplicates
$words = json_decode(file_get_contents(__DIR__ . '/../words.json'), true);
assert(is_array($words) && count($words) >= 300);
foreach ($words as $w) {
    assert(is_string($w) && preg_match('/^[a-z]{3,8}$/', $w) === 1);
}
assert(count($words) === count(array_unique($words)));
echo "words ok (" . count($words) . ")\n";
