<?php
// CLI self-check for lib.php: php -d zend.assertions=1 -d assert.exception=1 games/reaction-test/tests/validate_test.php
require __DIR__ . '/../lib.php';

function ok($b) { $r = rt_check($b); assert(is_array($r), json_encode($b) . ' should pass, got ' . json_encode($r)); return $r; }
function bad($b, $why) { assert(is_string(rt_check($b)), "should fail: $why"); }

$good = ['name' => '  Crafty  Playz ', 'score' => 250, 'tries' => [240, 250, 260, 245, 255]];
assert(ok($good)['name'] === 'Crafty Playz');
ok(['name' => 'Zoë', 'score' => 250.0, 'tries' => [240, 250, 260, 245, 255]]);

bad(null, 'not an object');
bad(['name' => null] + $good, 'no name');
bad(['name' => str_repeat('a', 21)] + $good, 'name too long');
bad(['name' => "a\x07b"] + $good, 'control char');
bad(['name' => "\xff\xfe"] + $good, 'invalid utf-8');
bad(['name' => '   '] + $good, 'blank name');
bad(['tries' => [240, 250, 260, 245]] + $good, 'four tries');
bad(['tries' => [240, 250, 260, 245, 79], 'score' => 215] + $good, 'try under 80');
bad(['tries' => [240, 250, 260, 245, '255']] + $good, 'string try');
bad(['score' => 251] + $good, 'average mismatch');
bad(['score' => '250'] + $good, 'string score');
bad(['score' => 99, 'tries' => [99, 99, 99, 99, 99]] + $good, 'under 100 average');
bad(['score' => 2001, 'tries' => [2001, 2001, 2001, 2001, 2001]] + $good, 'over 2000');
bad(['tries' => ['a' => 240, 'b' => 250, 'c' => 260, 'd' => 245, 'e' => 255]] + $good, 'keyed tries');
ok(['score' => 100, 'tries' => [100, 100, 100, 100, 100]] + $good);
echo "validate_test: all passed\n";
