<?php
declare(strict_types=1);

namespace HPChapter;

final class BetweenTheLines
{
    public const SCHEMA_VERSION = 1;
    // Deliberately closed vocabulary: these describe speech, not ordinary actions.
    public const SETTINGS = [
        'maximumQuestions' => 20, 'precedingSentences' => 3, 'followingSentences' => 1,
        'maximumPassageLength' => 2500, 'blank' => '______',
        'editDistanceThresholds' => [
            'short' => ['maximumLength' => 4, 'distance' => 0],
            'typical' => ['maximumLength' => 8, 'distance' => 1],
            'long' => ['maximumLength' => null, 'distance' => 2],
        ],
        'excludedDescriptors' => [
            'said', 'say', 'says', 'saying', 'asked', 'ask', 'asks', 'asking',
            'replied', 'reply', 'replies', 'replying', 'answered', 'answer', 'answers', 'answering',
            'told', 'tell', 'tells', 'telling', 'spoke', 'speak', 'speaks', 'speaking',
            'stated', 'state', 'states', 'stating', 'responded', 'respond', 'responds', 'responding',
            'added', 'add', 'adds', 'adding', 'continued', 'continue', 'continues', 'continuing',
            'explained', 'explain', 'explains', 'explaining', 'announced', 'announce', 'announces', 'announcing',
        ],
        'descriptors' => [
            'whispered' => ['whisper', 'whispers', 'whispering'],
            'muttered' => ['mutter', 'mutters', 'muttering'],
            'shouted' => ['shout', 'shouts', 'shouting'],
            'snarled' => ['snarl', 'snarls', 'snarling'],
            'bellowed' => ['bellow', 'bellows', 'bellowing'],
            'yelled' => ['yell', 'yells', 'yelling'],
            'screamed' => ['scream', 'screams', 'screaming'],
            'shrieked' => ['shriek', 'shrieks', 'shrieking'],
            'hissed' => ['hiss', 'hisses', 'hissing'],
            'murmured' => ['murmur', 'murmurs', 'murmuring'],
            'stammered' => ['stammer', 'stammers', 'stammering'],
            'stuttered' => ['stutter', 'stutters', 'stuttering'],
            'sputtered' => ['sputter', 'sputters', 'sputtering'],
            'croaked' => ['croak', 'croaks', 'croaking'],
            'drawled' => ['drawl', 'drawls', 'drawling'],
            'roared' => ['roar', 'roars', 'roaring'],
            'squeaked' => ['squeak', 'squeaks', 'squeaking'],
            'sobbed' => ['sob', 'sobs', 'sobbing'],
            'sniggered' => ['snigger', 'sniggers', 'sniggering'],
            'snapped' => ['snap', 'snaps', 'snapping'],
            'spluttered' => ['splutter', 'splutters', 'spluttering'],
            'retorted' => ['retort', 'retorts', 'retorting'],
            'growled' => ['growl', 'growls', 'growling'],
            'demanded' => ['demand', 'demands', 'demanding'],
        ],
    ];

    public static function version(string $bookId, string $sourceHash): string
    {
        return $bookId . ':btl' . self::SCHEMA_VERSION . ':p' . Parser::SCHEMA_VERSION . ':' . hash('sha256', json_encode(self::SETTINGS, JSON_THROW_ON_ERROR)) . ':' . $sourceHash;
    }

    public static function normalize(string $input): string
    {
        if (!mb_check_encoding($input, 'UTF-8')) {
            return '';
        }
        $input = \Normalizer::normalize($input, \Normalizer::FORM_KC);
        $input = preg_replace('/[\p{Z}\s]+/u', ' ', $input ?: '') ?? '';
        return mb_strtolower(preg_replace('/^[\p{P}\p{Z}\s]+|[\p{P}\p{Z}\s]+$/u', '', $input) ?? '', 'UTF-8');
    }

    private static function distance(string $left, string $right): int
    {
        $a = mb_str_split($left, 1, 'UTF-8');
        $b = mb_str_split($right, 1, 'UTF-8');
        $previous = range(0, count($b));
        foreach ($a as $i => $letter) {
            $row = [$i + 1];
            foreach ($b as $j => $other) {
                $row[] = min($row[$j] + 1, $previous[$j + 1] + 1, $previous[$j] + ($letter === $other ? 0 : 1));
            }
            $previous = $row;
        }
        return $previous[count($b)];
    }

    private static function descriptors(): array
    {
        return array_diff_key(self::SETTINGS['descriptors'], array_flip(self::SETTINGS['excludedDescriptors']));
    }

    private static function maximumDistance(int $length): int
    {
        foreach (self::SETTINGS['editDistanceThresholds'] as $threshold) {
            if ($threshold['maximumLength'] === null || $length <= $threshold['maximumLength']) {
                return $threshold['distance'];
            }
        }
        throw new \RuntimeException('Descriptor edit-distance settings must cover all word lengths.');
    }

    public static function matches(string $input, string $original): bool
    {
        $input = self::normalize($input);
        $answer = self::normalize($original);
        $descriptors = self::descriptors();
        if (!isset($descriptors[$answer]) || !preg_match('/^\p{L}+$/uD', $input)
            || in_array($input, self::SETTINGS['excludedDescriptors'], true)) {
            return false;
        }
        $forms = [$answer, ...$descriptors[$answer]];
        $best = min(array_map(static fn (string $form): int => self::distance($input, $form), $forms));
        // The shorter length prevents padding or a short guess from gaining a larger budget.
        $length = min(mb_strlen($answer, 'UTF-8'), mb_strlen($input, 'UTF-8'));
        $limit = self::maximumDistance($length);
        if ($best > $limit) {
            return false;
        }
        foreach ($descriptors as $descriptor => $variants) {
            if ($descriptor !== $answer) {
                foreach ([$descriptor, ...$variants] as $alternative) {
                    if (self::distance($input, $alternative) <= $best) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private static function subject(): string
    {
        // A pronoun or at most three capitalized name words; no arbitrary noun/action clause.
        return '(?!(?:If|When|While|Unless|Although|Because|Perhaps|Maybe|Not|No|The|A|An|Someone|Something)\b)(?:(?i:he|she|they|I|you|we)|\p{Lu}[\p{L}’\'-]*(?:\s+\p{Lu}[\p{L}’\'-]*){0,2})';
    }

    private static function quote(): string
    {
        return '(?:“[^“”]+”|‘[^‘’]*(?:’(?=\p{L})[^‘’]*)*’|"[^"]+")';
    }

    public static function sentences(string $paragraph): array
    {
        $tags = implode('|', [...array_keys(self::SETTINGS['descriptors']), ...self::SETTINGS['excludedDescriptors'], 'cried', 'exclaimed']);
        $subject = self::subject();
        $modifiers = '(?:(?:[\p{L}]+ly|very|quite|rather)\s+){0,3}';
        preg_match_all('/' . self::quote() . '/u', $paragraph, $quotes, PREG_OFFSET_CAPTURE);
        preg_match_all('/[.!?…]+(?:[”’"])?(?=\s|$)/u', $paragraph, $ends, PREG_OFFSET_CAPTURE);
        $sentences = [];
        $start = 0;
        foreach ($ends[0] as [$punctuation, $offset]) {
            $end = $offset + strlen($punctuation);
            $tail = substr($paragraph, $end);
            // A complete quoted utterance is atomic so context cannot detach a tag.
            $insideQuote = false;
            foreach ($quotes[0] as [$quote, $quoteOffset]) {
                if ($offset >= $quoteOffset && $end < $quoteOffset + strlen($quote)) {
                    $insideQuote = true;
                    break;
                }
            }
            if ($insideQuote) {
                continue;
            }
            // Closing speech punctuation belongs to its attribution, including inverted tags.
            if (preg_match('/[”’"]$/u', $punctuation)
                && preg_match('/^\s+(?:' . $subject . '\s+' . $modifiers . '(?:' . $tags . ')|(?:' . $tags . ')\s+' . $modifiers . $subject . ')\b/u', $tail)) {
                continue;
            }
            $text = trim(substr($paragraph, $start, $end - $start));
            // Ellipses, abbreviations and unfinished sentence fragments are kept intact.
            if ($punctuation === '…' || $punctuation === '...' || preg_match('/\b(?:Mr|Mrs|Ms|Dr|Prof|St)\.$/u', $text)) {
                continue;
            }
            if ($text !== '') {
                $sentences[] = ['text' => $text, 'offset' => $start + strspn(substr($paragraph, $start), ' ')];
            }
            $start = $end;
        }
        $tail = trim(substr($paragraph, $start));
        if ($tail !== '') {
            $sentences[] = ['text' => $tail, 'offset' => $start + strspn(substr($paragraph, $start), ' '), 'fragment' => true];
        }
        return $sentences;
    }

    /**
     * Extract only one adjacent, single-word configured verb with an explicit
     * pronoun or short capitalized name attribution. Skip adverbial/multi-word
     * tags, ambiguous speakers, nested reported speech, mixed attributions,
     * fragments, repeated answer forms and oversized contexts; never repair them.
     */
    public static function extract(array $book): array
    {
        $allowed = self::descriptors();
        $descriptors = implode('|', [...array_keys(self::SETTINGS['descriptors']), ...self::SETTINGS['excludedDescriptors'], 'cried', 'exclaimed']);
        $subject = self::subject();
        // Only immediately adjacent explicit speech attribution, with matching quote styles.
        $quote = self::quote();
        $patterns = [
            '/' . $quote . '\s+(?:(?<tag>' . $descriptors . ')\s+' . $subject . '|' . $subject . '\s+(?<tag2>' . $descriptors . '))(?=\s*[,.;!?]|$)/u',
            '/(?:^|(?<=[.!?])\s+)' . $subject . '\s+(?<tag>' . $descriptors . ')\s*,\s*' . $quote . '/u',
        ];
        $questions = [];
        foreach ($book['chapters'] as $chapter) {
            $units = [];
            foreach ($chapter['sourceParagraphs'] as $paragraph) {
                foreach (self::sentences($paragraph['text']) as $sentence) {
                    $units[] = $sentence + ['paragraph' => $paragraph];
                }
            }
            foreach ($units as $index => $unit) {
                if (isset($unit['fragment']) || (isset($units[$index + 1]) && isset($units[$index + 1]['fragment']))) {
                    continue;
                }
                $targets = [];
                preg_match_all('/' . $quote . '/u', $unit['paragraph']['text'], $topQuotes, PREG_OFFSET_CAPTURE);
                // Match against the full paragraph: a quote can start in a preceding sentence.
                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $unit['paragraph']['text'], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
                    foreach ($matches as $match) {
                        preg_match('/' . $quote . '/u', $match[0][0], $speech, PREG_OFFSET_CAPTURE);
                        $quoteStart = $match[0][1] + $speech[0][1];
                        if (!in_array([$speech[0][0], $quoteStart], $topQuotes[0], true)) {
                            continue;
                        }
                        $tag = $match['tag'][0] !== null ? $match['tag'] : $match['tag2'];
                        if ($match[0][1] + strspn($match[0][0], ' ') >= $unit['offset']
                            && $match[0][1] + strlen($match[0][0]) <= $unit['offset'] + strlen($unit['text'])
                            && $tag[1] >= $unit['offset'] && $tag[1] < $unit['offset'] + strlen($unit['text'])) {
                            $targets[$tag[1]] = $tag;
                        }
                    }
                }
                if (count($targets) !== 1) {
                    continue;
                }
                [$original, $offset] = array_values($targets)[0];
                if (!isset($allowed[$original])) {
                    continue;
                }
                $before = array_slice($units, max(0, $index - self::SETTINGS['precedingSentences']), min($index, self::SETTINGS['precedingSentences']));
                if (array_filter($before, static fn (array $sentence): bool => isset($sentence['fragment']))) {
                    continue;
                }
                $following = isset($units[$index + 1]) ? [$units[$index + 1]] : [];
                $context = [...$before, $unit, ...$following];
                $source = implode(' ', array_column($context, 'text'));
                $relative = $offset - $unit['offset'];
                $prefix = implode(' ', array_column($before, 'text'));
                $passageOffset = ($prefix === '' ? 0 : strlen($prefix) + 1) + $relative;
                if (mb_strlen($source, 'UTF-8') > self::SETTINGS['maximumPassageLength']) {
                    continue;
                }
                $passage = substr_replace($source, self::SETTINGS['blank'], $passageOffset, strlen($original));
                // No visible answer or accepted grammatical form anywhere else in context.
                $forms = [$original, ...self::SETTINGS['descriptors'][$original]];
                if (preg_match('/\b(?:' . implode('|', $forms) . ')\b/iu', $passage)) {
                    continue;
                }
                $questions[] = [
                    'id' => $unit['paragraph']['id'] . ':b' . $offset,
                    'passage' => $passage, 'descriptor' => $original,
                    'provenance' => [
                        'bookId' => $book['id'], 'chapterId' => $chapter['id'], 'chapterTitle' => $chapter['title'],
                        'href' => $chapter['href'], 'paragraphId' => $unit['paragraph']['id'],
                        'offset' => $offset, 'offsetUnit' => 'utf8-bytes', 'targetOriginal' => $original,
                        'sourceParagraph' => $unit['paragraph']['text'],
                        'targetSentence' => $unit['text'], 'sourceContext' => $source,
                        'passageOffset' => $passageOffset, 'precedingCount' => count($before), 'followingCount' => count($following),
                        'context' => array_map(static fn (array $sentence): array => [
                            'paragraphId' => $sentence['paragraph']['id'], 'offset' => $sentence['offset'], 'text' => $sentence['text'],
                        ], $context),
                    ],
                ];
            }
        }
        return $questions;
    }
}
