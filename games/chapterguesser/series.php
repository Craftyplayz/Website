<?php
// Lists the book series for the Chapter Oracle as JSON.
// Every folder in books/ is a series; every .epub inside it is a book,
// in natural filename order (book1, book2, … book10).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = __DIR__ . '/books';
$series = [];

foreach (scandir($root) ?: [] as $folder) {
    $dir = $root . '/' . $folder;
    if ($folder[0] === '.' || !is_dir($dir)) {
        continue;
    }
    $books = [];
    foreach (scandir($dir) ?: [] as $file) {
        if ($file[0] !== '.' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'epub' && is_file($dir . '/' . $file)) {
            $books[] = $file;
        }
    }
    if (!$books) {
        continue;
    }
    natcasesort($books);
    $series[] = [
        'id'    => $folder,
        'name'  => trim(preg_replace('/[-_]+/', ' ', $folder)),
        'books' => array_values($books),
    ];
}

usort($series, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
echo json_encode($series, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
