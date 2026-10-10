<?php
declare(strict_types=1);

namespace HPChapter;

final class Parser
{
    public const SCHEMA_VERSION = 1;
    public const TITLES = [
        'hp-1' => "Philosopher's Stone", 'hp-2' => 'Chamber of Secrets',
        'hp-3' => 'Prisoner of Azkaban', 'hp-4' => 'Goblet of Fire',
        'hp-5' => 'Order of the Phoenix', 'hp-6' => 'Half-Blood Prince',
        'hp-7' => 'Deathly Hallows',
    ];

    public static function normalize(string $text): string
    {
        // ECMAScript \s, rather than PHP's different Unicode whitespace set.
        return trim(preg_replace('/[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', ' ', $text) ?? '');
    }

    public static function eligible(string $text): bool
    {
        $length = strlen(mb_convert_encoding(self::normalize($text), 'UTF-16LE', 'UTF-8')) / 2;
        return $length >= 120 && $length <= 2500;
    }

    public static function resolve(string $base, string $reference): string
    {
        $path = preg_split('/[?#]/', $reference, 2)[0];
        if ($path === '') {
            return '';
        }
        $directory = str_starts_with($path, '/') ? '' : substr($base, 0, (int) (strrpos($base, '/') === false ? 0 : strrpos($base, '/')));
        return self::archivePath($directory . '/' . $path);
    }

    private static function archivePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                // decodeURIComponent leaves malformed escapes and malformed UTF-8 unchanged.
                $decoded = rawurldecode($part);
                $parts[] = preg_match('/%(?![0-9a-f]{2})/i', $part) || !mb_check_encoding($decoded, 'UTF-8') ? $part : $decoded;
            }
        }
        return implode('/', $parts);
    }

    private static function markup(string $source, bool $fallback = false): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $ok = $document->loadXML($source, LIBXML_NONET);
            if (!$ok && $fallback) {
                $document = new \DOMDocument();
                $ok = $document->loadHTML('<?xml encoding="UTF-8">' . $source, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            }
            if (!$ok) {
                throw new \RuntimeException('The EPUB contains malformed XML.');
            }
            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function elements(\DOMNode $node, string $name): array
    {
        $matches = [];
        foreach ($node->getElementsByTagName('*') as $element) {
            if (strtolower($element->localName) === $name) {
                $matches[] = $element;
            }
        }
        return $matches;
    }

    private static function title(array &$titles, string $path, string $text): void
    {
        if ($path !== '' && $text !== '') {
            $titles[$path] = $text;
            $titles[basename($path)] = $text;
        }
    }

    private static function navigation(\ZipArchive $zip, array $manifest, string $opf): array
    {
        $titles = [];
        $directory = str_contains($opf, '/') ? substr($opf, 0, strrpos($opf, '/') + 1) : '';
        foreach ($manifest as $item) {
            if (!in_array('nav', preg_split('/\s+/', $item['properties']), true)) {
                continue;
            }
            $path = self::resolve($opf, $item['href']);
            $source = $zip->getFromName($path);
            if ($source !== false) {
                try {
                    foreach (self::elements(self::markup($source, true), 'a') as $link) {
                        $href = $link->getAttribute('href');
                        if (preg_match('/^(?:[a-z][a-z\d+.-]*:|\/\/)/i', $href)) {
                            continue;
                        }
                        $resolved = self::resolve($path, $href);
                        $text = self::normalize($link->textContent);
                        self::title($titles, str_starts_with($resolved, $directory) ? substr($resolved, strlen($directory)) : $resolved, $text);
                        self::title($titles, $resolved, $text);
                    }
                } catch (\RuntimeException) {
                    // As in the browser, unusable EPUB 3 navigation falls back to NCX.
                }
            }
            break;
        }
        if ($titles) {
            return $titles;
        }
        foreach ($manifest as $item) {
            if ($item['mediaType'] !== 'application/x-dtbncx+xml') {
                continue;
            }
            $path = self::resolve($opf, $item['href']);
            $source = $zip->getFromName($path);
            if ($source !== false) {
                try {
                    foreach (self::elements(self::markup($source, true), 'navpoint') as $point) {
                        $content = self::elements($point, 'content')[0] ?? null;
                        $label = self::elements($point, 'navlabel')[0] ?? null;
                        $textNode = $label ? (self::elements($label, 'text')[0] ?? null) : null;
                        $href = $content?->getAttribute('src') ?? '';
                        $text = self::normalize($textNode?->textContent ?? '');
                        if ($href === '' || $text === '') {
                            continue;
                        }
                        $resolved = self::resolve($path, $href);
                        self::title($titles, str_starts_with($resolved, $directory) ? substr($resolved, strlen($directory)) : $resolved, $text);
                        self::title($titles, $resolved, $text);
                    }
                } catch (\RuntimeException) {
                }
            }
            break;
        }
        return $titles;
    }

    public static function parse(string $file, string $bookId): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('The EPUB archive is unavailable.');
        }
        try {
            $container = $zip->getFromName('META-INF/container.xml');
            if ($container === false) {
                throw new \RuntimeException('The EPUB has no META-INF/container.xml file.');
            }
            $root = self::elements(self::markup($container), 'rootfile')[0] ?? null;
            $opf = $root?->getAttribute('full-path') ?? '';
            $source = $opf === '' ? false : $zip->getFromName(self::archivePath($opf));
            if ($source === false) {
                throw new \RuntimeException('The EPUB package file is missing.');
            }
            $document = self::markup($source);
            $manifest = [];
            foreach (self::elements($document, 'item') as $item) {
                $id = $item->getAttribute('id');
                if ($id !== '') {
                    $manifest[$id] = [
                        'href' => $item->getAttribute('href'), 'mediaType' => $item->getAttribute('media-type'),
                        'properties' => $item->getAttribute('properties'),
                    ];
                }
            }
            $titles = self::navigation($zip, $manifest, $opf);
            $chapters = [];
            foreach (self::elements($document, 'itemref') as $index => $reference) {
                $itemId = $reference->getAttribute('idref');
                $item = $manifest[$itemId] ?? null;
                if (!$item || !str_contains($item['mediaType'], 'html')) {
                    continue;
                }
                $path = self::resolve($opf, $item['href']);
                $title = $titles[$item['href']] ?? $titles[basename($item['href'])] ?? $titles[$path] ?? $titles[basename($path)] ?? null;
                $source = $zip->getFromName($path);
                if (!$title || $source === false) {
                    continue;
                }
                try {
                    $chapterDocument = self::markup($source, true);
                } catch (\RuntimeException) {
                    continue;
                }
                $seen = [];
                $paragraphs = [];
                $chapterId = $bookId . ':' . ($itemId !== '' ? $itemId : $index);
                foreach (self::elements($chapterDocument, 'p') as $paragraphIndex => $paragraph) {
                    $text = self::normalize($paragraph->textContent);
                    if (!self::eligible($text) || isset($seen[$text])) {
                        continue;
                    }
                    $seen[$text] = true;
                    $paragraphs[] = ['id' => $chapterId . ':p' . ($paragraphIndex + 1), 'text' => $text];
                }
                if ($paragraphs) {
                    $chapters[] = ['id' => $chapterId, 'title' => $title, 'href' => $path, 'paragraphs' => $paragraphs];
                }
            }
            return ['id' => $bookId, 'chapters' => $chapters];
        } finally {
            $zip->close();
        }
    }

    public static function excerpt(string $text, int $limit = 150): ?string
    {
        // Prefer the prefix; scan subsequent word boundaries only if it is not meaningful.
        preg_match_all('/\p{L}[\p{L}\p{M}]*/u', $text, $words, PREG_OFFSET_CAPTURE);
        foreach ($words[0] as $word) {
            $tail = substr($text, $word[1]);
            $candidate = mb_substr($tail, 0, $limit, 'UTF-8');
            if (mb_strlen($tail, 'UTF-8') > $limit) {
                $candidate = preg_replace('/\s+\S*$/u', '', $candidate) ?? '';
                // A single oversized word has no safe boundary.
                if (!preg_match('/\s/u', mb_substr($tail, 0, $limit, 'UTF-8'))) {
                    continue;
                }
            }
            $candidate = trim($candidate);
            if (mb_strlen($candidate, 'UTF-8') >= 60 && preg_match_all('/\p{L}[\p{L}\p{M}]*/u', $candidate) >= 8) {
                return $candidate;
            }
        }
        return null;
    }
}
