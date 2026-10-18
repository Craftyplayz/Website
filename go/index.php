<?php
// Short links: /go/<name> -> the address in data/links.json (rewrite rule in .htaccess)
$key = isset($_GET['k']) ? (string)$_GET['k'] : '';
$links = json_decode((string)@file_get_contents(__DIR__ . '/../data/links.json'), true);
if (is_array($links) && isset($links[$key])) {
    header('Location: ' . $links[$key], true, 302);
    exit;
}
http_response_code(404);
require __DIR__ . '/../404.php';
