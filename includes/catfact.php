<?php
// Fetches a fresh cat fact for the footer and saves it, so the next page can print it straight away.
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$ch = curl_init('https://meowfacts.herokuapp.com');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3]);
$body = curl_exec($ch);
$ok = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
curl_close($ch);

$d = $ok ? json_decode($body, true) : null;
$fact = is_array($d['data'] ?? null) ? trim(implode(' ', array_filter($d['data'], 'is_string'))) : '';

if ($fact !== '') file_put_contents(catfact_file(), $fact, LOCK_EX);
else $fact = catfact_stored(); // API down: keep showing the saved one

echo json_encode(['fact' => $fact]);
