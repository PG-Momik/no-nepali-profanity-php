<?php

declare(strict_types=1);

namespace NoNepaliProfanity;

use InvalidArgumentException;
use Normalizer;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_slice;
use function count;
use function implode;
use function in_array;
use function is_string;
use function mb_str_split;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_split;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;
use function usort;

/**
 * Builds the match tables once for a set of options. Reuse it rather than passing options on every call.
 * A direct port of `js/src/index.ts`.
 */
final class ProfanityFilter
{
    private const STRICTNESS_LEVEL = ['lenient' => 0, 'standard' => 1, 'strict' => 2];

    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's'];

    private const MIN_COLLAPSE = 4;

    private const DEVANAGARI_RE = '~[\x{0900}-\x{097F}]~u';

    private const ZERO_WIDTH_RE = '~[\x{200B}-\x{200D}\x{2060}\x{FEFF}]~u';

    private const BANG_FOR_I_RE = '~(?<=[\p{L}\p{N}])!+(?=[\p{L}\p{N}])~u';

    private const TOKEN_RE = '~[\p{L}\p{M}*]+~u';

    private const WHITESPACE_RE = '~\A\s+\z~u';

    private const GRAPHEME_EXTEND_RE = '~[\p{M}\x{FE00}-\x{FE0F}\x{200C}\x{200D}]~u';

    /** Viramas of the Indic scripts, which join the consonants on either side into one visible character. */
    private const VIRAMA_CLASS = '[\x{094D}\x{09CD}\x{0A4D}\x{0ACD}\x{0B4D}\x{0BCD}\x{0C4D}\x{0CCD}\x{0D4D}\x{0DCA}]';

    /** @var array<string, true> */
    private array $latinExact = [];

    /** @var array<string, true> */
    private array $latinCollapsed = [];

    /** @var list<string> */
    private array $latinWords = [];

    /** @var list<string> */
    private array $latinStems = [];

    /** @var array<string, true> */
    private array $devWords = [];

    /** @var list<string> */
    private array $devStems = [];

    /** @var list<string> */
    private array $phrases = [];

    /**
     * @param array{languages?: list<string>, strictness?: string} $options
     */
    public function __construct(array $options = [])
    {
        $this->buildTables($options);
    }

    private static function isDevanagari(string $s): bool
    {
        return preg_match(self::DEVANAGARI_RE, $s) === 1;
    }

    /** Collapse repeated characters: "machikneee" -> "machikne". */
    private static function collapse(string $s): string
    {
        return preg_replace('~(.)\1+~u', '$1', $s) ?? $s;
    }

    /** Squeeze 3+ repeated chars to 2: "mooji" -> "mooji". */
    private static function squeeze(string $s): string
    {
        return preg_replace('~(.)\1{2,}~u', '$1$1', $s) ?? $s;
    }

    private static function normalizeChar(string $ch): string
    {
        if (preg_match(self::ZERO_WIDTH_RE, $ch) === 1) {
            return '';
        }
        if (self::isDevanagari($ch)) {
            // Decompose so a precomposed nukta letter (ऩ) loses its nukta too, and fold
            // chandrabindu into anusvara.
            $decomposed = Normalizer::normalize($ch, Normalizer::FORM_D);
            if ($decomposed === false) {
                $decomposed = $ch;
            }
            $decomposed = str_replace("\u{093C}", '', $decomposed);
            $decomposed = str_replace("\u{0901}", "\u{0902}", $decomposed);
            return $decomposed;
        }
        $folded = mb_strtolower(Normalizer::normalize($ch, Normalizer::FORM_KC) ?? $ch, 'UTF-8');
        $out = '';
        foreach (mb_str_split($folded, 1, 'UTF-8') as $c) {
            $out .= self::LEET[$c] ?? $c;
        }
        return $out;
    }

    /** Byte offset -> code point offset in a UTF-8 string. */
    private static function charOffset(string $text, int $byteOffset): int
    {
        if ($byteOffset === 0) {
            return 0;
        }
        return mb_strlen(substr($text, 0, $byteOffset), 'UTF-8');
    }

    /**
     * Normalized text, plus the span of the original text that each normalized code point came
     * from, so a match found in the normalized text can be traced back to what the user typed.
     *
     * @return array{text: string, starts: list<int>, ends: list<int>}
     */
    private static function normalize(string $input): array
    {
        $text = '';
        $starts = [];
        $ends = [];
        $offset = 0;
        foreach (mb_str_split($input, 1, 'UTF-8') as $ch) {
            $out = self::normalizeChar($ch);
            $text .= $out;
            $len = mb_strlen($out, 'UTF-8');
            for ($k = 0; $k < $len; $k++) {
                $starts[] = $offset;
                $ends[] = $offset + 1;
            }
            $offset += 1;
        }

        // Replace each run of "!" between letters with one "i" spanning the whole run.
        preg_match_all(self::BANG_FOR_I_RE, $text, $bang, PREG_OFFSET_CAPTURE);
        if ($bang[0] === []) {
            return ['text' => $text, 'starts' => $starts, 'ends' => $ends];
        }

        $result = '';
        $resultStarts = [];
        $resultEnds = [];
        $last = 0;
        foreach ($bang[0] as [$mtext, $byteI]) {
            $i = self::charOffset($text, $byteI);
            $len = mb_strlen($mtext, 'UTF-8');
            $result .= mb_substr($text, $last, $i - $last, 'UTF-8') . 'i';
            $resultStarts = array_merge($resultStarts, array_slice($starts, $last, $i - $last), [$starts[$i]]);
            $resultEnds = array_merge($resultEnds, array_slice($ends, $last, $i - $last), [$ends[$i + $len - 1]]);
            $last = $i + $len;
        }
        $result .= mb_substr($text, $last, null, 'UTF-8');
        $resultStarts = array_merge($resultStarts, array_slice($starts, $last));
        $resultEnds = array_merge($resultEnds, array_slice($ends, $last));
        return ['text' => $result, 'starts' => $resultStarts, 'ends' => $resultEnds];
    }

    private static function normalizeText(string $s): string
    {
        return self::normalize($s)['text'];
    }

    /** Turns a token into an anchored regex where each "*" stands for one hidden letter. */
    private static function wildcardRegex(string $s): string
    {
        $out = '';
        foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
            $out .= $ch === '*' ? '.' : preg_quote($ch, '~');
        }
        return '~^' . $out . '$~iu';
    }

    /**
     * @param array{languages?: list<string>, strictness?: string} $options
     */
    private function buildTables(array $options): void
    {
        $languages = $options['languages'] ?? Lexicon::LANGUAGES;
        foreach ($languages as $l) {
            if (!in_array($l, Lexicon::LANGUAGES, true)) {
                throw new InvalidArgumentException('Unknown language "' . $l . '". Use one of: ' . implode(', ', Lexicon::LANGUAGES) . '.');
            }
        }
        $strictness = $options['strictness'] ?? 'standard';
        $level = self::STRICTNESS_LEVEL[$strictness] ?? null;
        if ($level === null) {
            throw new InvalidArgumentException('Unknown strictness "' . $strictness . '". Use one of: ' . implode(', ', array_keys(self::STRICTNESS_LEVEL)) . '.');
        }

        $active = static function (array $entries, bool $devanagari) use ($languages, $level): array {
            $out = [];
            foreach ($entries as $entry) {
                if (in_array($entry['language'], $languages, true)
                    && self::STRICTNESS_LEVEL[$entry['strictness']] <= $level
                    && ($entry['language'] === 'devanagari') === $devanagari
                ) {
                    $out[] = self::normalizeText($entry['text']);
                }
            }
            return $out;
        };

        $latinWords = $active(Lexicon::WORDS, false);

        $latinExact = [];
        foreach ($latinWords as $w) {
            $latinExact[self::squeeze($w)] = true;
        }
        $latinCollapsed = [];
        foreach ($latinWords as $w) {
            $c = self::collapse($w);
            if (mb_strlen($c, 'UTF-8') >= self::MIN_COLLAPSE) {
                $latinCollapsed[$c] = true;
            }
        }

        $devWords = [];
        foreach ($active(Lexicon::WORDS, true) as $w) {
            $devWords[$w] = true;
        }

        $phrases = [];
        foreach (array_merge($active(Lexicon::PHRASES, false), $active(Lexicon::PHRASES, true)) as $p) {
            $body = implode('\\s+', array_map(
                static fn (string $part): string => preg_quote($part, '~'),
                preg_split('~\s+~u', trim($p)) ?: [],
            ));
            $phrases[] = '~(?:^|[^\p{L}\p{N}])(' . $body . ')(?![\p{L}\p{N}])~iu';
        }

        $this->latinExact = $latinExact;
        $this->latinCollapsed = $latinCollapsed;
        $this->latinWords = array_map(fn (string $w): string => self::squeeze($w), $latinWords);
        $this->latinStems = array_map(fn (string $w): string => self::collapse($w), $active(Lexicon::STEMS, false));
        $this->devWords = $devWords;
        $this->devStems = $active(Lexicon::STEMS, true);
        $this->phrases = $phrases;
    }

    private function wildcardTokenMatches(string $token): bool
    {
        if (!str_contains($token, '*')) {
            return false;
        }

        $cleanToken = trim($token, '*');
        if (preg_match('~\p{L}~u', $cleanToken) !== 1) {
            return false;
        }

        // "*" on both ends is markdown emphasis ("*sh*t*"). On one end only, it may also hide a first or last letter ("*ss").
        $emphasis = str_starts_with($token, '*') && str_ends_with($token, '*');
        $tokens = $emphasis || $cleanToken === $token ? [$cleanToken] : [$cleanToken, $token];
        $forms = [];
        foreach ($tokens as $t) {
            $forms[] = self::squeeze($t);
            $forms[] = self::collapse($t);
        }

        foreach ($forms as $f) {
            $regex = self::wildcardRegex($f);
            foreach ($this->latinWords as $w) {
                if (preg_match($regex, $w) === 1) {
                    return true;
                }
            }
            foreach ($this->latinStems as $stem) {
                if (mb_strlen($f, 'UTF-8') < mb_strlen($stem, 'UTF-8')) {
                    continue;
                }
                if (preg_match(self::wildcardRegex(mb_substr($f, 0, mb_strlen($stem, 'UTF-8'), 'UTF-8')), $stem) === 1) {
                    return true;
                }
            }
        }
        return false;
    }

    private function latinTokenMatches(string $token): bool
    {
        $candidates = [$token];
        foreach (Lexicon::LATIN_SUFFIXES as $s) {
            if (str_ends_with($token, $s) && mb_strlen($token, 'UTF-8') - mb_strlen($s, 'UTF-8') >= 3) {
                $candidates[] = mb_substr($token, 0, -mb_strlen($s, 'UTF-8'), 'UTF-8');
                break;
            }
        }

        foreach ($candidates as $t) {
            $squeezed = self::squeeze($t);
            $collapsed = self::collapse($t);
            if (isset($this->latinExact[$squeezed])) {
                return true;
            }
            if (mb_strlen($collapsed, 'UTF-8') >= self::MIN_COLLAPSE && isset($this->latinCollapsed[$collapsed])) {
                return true;
            }
            foreach ($this->latinStems as $stem) {
                if (str_starts_with($collapsed, $stem)) {
                    return true;
                }
            }
            if ($this->wildcardTokenMatches($t)) {
                return true;
            }
        }
        return false;
    }

    private function devanagariTokenMatches(string $token): bool
    {
        $candidates = [$token];
        foreach (Lexicon::DEVANAGARI_SUFFIXES as $s) {
            if (str_ends_with($token, $s) && mb_strlen($token, 'UTF-8') > mb_strlen($s, 'UTF-8') + 1) {
                $candidates[] = mb_substr($token, 0, -mb_strlen($s, 'UTF-8'), 'UTF-8');
                break;
            }
        }

        foreach ($candidates as $t) {
            if (isset($this->devWords[$t])) {
                return true;
            }
            foreach ($this->devStems as $stem) {
                if (str_starts_with($t, $stem)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * A token with the span of the original text it came from.
     *
     * @return list<array{value: string, start: int, end: int}>
     */
    private static function tokenSpans(array $n): array
    {
        $tokens = [];
        $run = [];

        // Three or more single letters in a row ("f.u.c.k", "f u c k") are read as one word.
        $flush = static function () use (&$tokens, &$run): void {
            if (count($run) >= 3) {
                $tokens[] = [
                    'value' => implode('', array_column($run, 'value')),
                    'start' => $run[0]['start'],
                    'end' => $run[count($run) - 1]['end'],
                ];
            }
            $run = [];
        };

        preg_match_all(self::TOKEN_RE, $n['text'], $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$mtext, $byteI]) {
            $i = self::charOffset($n['text'], $byteI);
            $t = [
                'value' => $mtext,
                'start' => $n['starts'][$i],
                'end' => $n['ends'][$i + mb_strlen($mtext, 'UTF-8') - 1],
            ];
            if (self::isDevanagari($t['value'])) {
                $flush();
                $tokens[] = $t;
            } elseif (mb_strlen($t['value'], 'UTF-8') === 1) {
                $run[] = $t;
            } else {
                $flush();
                $tokens[] = $t;
            }
        }
        $flush();
        return $tokens;
    }

    /** @return list<string> The raw tokens the matcher sees. Useful for debugging why a word is (or isn't) caught. */
    public static function tokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $out = [];
        foreach (self::tokenSpans(self::normalize($text)) as $t) {
            $out[] = $t['value'];
        }
        return $out;
    }

    /** @return list<ProfanityMatch> */
    private function scan(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $n = self::normalize($text);
        $found = [];

        foreach (self::tokenSpans($n) as $t) {
            $matched = self::isDevanagari($t['value'])
                ? $this->devanagariTokenMatches($t['value'])
                : $this->latinTokenMatches($t['value']);
            if ($matched) {
                $found[] = new ProfanityMatch(
                    text: mb_substr($text, $t['start'], $t['end'] - $t['start'], 'UTF-8'),
                    normalized: $t['value'],
                    start: $t['start'],
                    end: $t['end'],
                );
            }
        }

        foreach ($this->phrases as $re) {
            preg_match_all($re, $n['text'], $m, PREG_OFFSET_CAPTURE);
            foreach ($m[1] ?? [] as [$mtext, $byteI]) {
                $from = self::charOffset($n['text'], $byteI);
                $to = $from + mb_strlen($mtext, 'UTF-8') - 1;
                $found[] = new ProfanityMatch(
                    text: mb_substr($text, $n['starts'][$from], $n['ends'][$to] - $n['starts'][$from], 'UTF-8'),
                    normalized: $mtext,
                    start: $n['starts'][$from],
                    end: $n['ends'][$to],
                );
            }
        }

        return $found;
    }

    /** @return list<string> */
    private static function unique(array $matches): array
    {
        $seen = [];
        $out = [];
        foreach ($matches as $m) {
            if (!isset($seen[$m->normalized])) {
                $seen[$m->normalized] = true;
                $out[] = $m->normalized;
            }
        }
        return $out;
    }

    /** @return list<ProfanityMatch> sorted by start ascending, then end descending. */
    private static function byPosition(array $matches): array
    {
        usort($matches, static fn (ProfanityMatch $a, ProfanityMatch $b): int =>
            $a->start <=> $b->start ?: $b->end <=> $a->end
        );
        return $matches;
    }

    /** @return list<string> */
    private static function graphemes(string $s): array
    {
        $clusters = [];
        foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
            $last = count($clusters) - 1;
            if ($last >= 0 && (
                preg_match(self::GRAPHEME_EXTEND_RE, $ch) === 1
                || (preg_match('~^\p{L}$~u', $ch) === 1 && self::endsWithVirama($clusters[$last]))
            )) {
                $clusters[$last] .= $ch;
            } else {
                $clusters[] = $ch;
            }
        }
        return $clusters;
    }

    /**
     * Whether a consonant joins this cluster: it ends in a virama, perhaps followed by other marks or a ZWJ, so
     * a conjunct such as "ण्ड" masks as one visible character.
     */
    private static function endsWithVirama(string $cluster): bool
    {
        return preg_match('~' . self::VIRAMA_CLASS . '[\p{Mn}\x{200D}]*$~u', $cluster) === 1;
    }

    /**
     * @param list<ProfanityMatch> $matches
     * @param array{mask?: string, replace?: callable} $options
     *
     * @internal Used by ProfanityCheck so it can censor a scan result without scanning again.
     */
    public static function censorMatches(string $text, array $matches, array $options = []): string
    {
        $mask = array_key_exists('mask', $options) ? $options['mask'] : '*';
        $replace = array_key_exists('replace', $options) ? $options['replace'] : null;
        if (!is_string($mask) || $mask === '') {
            throw new InvalidArgumentException('mask must be a non-empty string.');
        }
        if ($replace !== null && !is_callable($replace)) {
            throw new InvalidArgumentException('replace must be callable.');
        }

        // Merge overlapping matches, such as the word "chaak" inside the phrase "chaak ko pwal", into one.
        $merged = [];
        foreach (self::byPosition($matches) as $m) {
            $prev = $merged[count($merged) - 1] ?? null;
            if ($prev !== null && $m->start < $prev->end) {
                if ($m->end > $prev->end) {
                    $prev->end = $m->end;
                    $prev->text = mb_substr($text, $prev->start, $prev->end - $prev->start, 'UTF-8');
                }
            } else {
                $merged[] = new ProfanityMatch(text: $m->text, normalized: $m->normalized, start: $m->start, end: $m->end);
            }
        }

        $out = '';
        $last = 0;
        foreach ($merged as $m) {
            if ($replace !== null) {
                $replacement = $replace($m);
            } else {
                $replacement = '';
                foreach (self::graphemes($m->text) as $g) {
                    $replacement .= preg_match(self::WHITESPACE_RE, $g) === 1 ? $g : $mask;
                }
            }
            $out .= mb_substr($text, $last, $m->start - $last, 'UTF-8') . $replacement;
            $last = $m->end;
        }
        return $out . mb_substr($text, $last, null, 'UTF-8');
    }

    public function check(string $text): ProfanityCheck
    {
        $matches = $this->scan($text);
        return new ProfanityCheck(
            text: $text,
            hasProfanity: $matches !== [],
            words: self::unique($matches),
            matches: self::byPosition($matches),
            rawMatches: $matches,
        );
    }

    public function containsProfanity(string $text): bool
    {
        return $this->scan($text) !== [];
    }

    /** @return list<string> Matching words, normalised + lowercased, deduplicated. Empty when clean. */
    public function findProfanity(string $text): array
    {
        return self::unique($this->scan($text));
    }

    /** @return list<ProfanityMatch> Every occurrence with its position in the original text, sorted by position. */
    public function findProfanityMatches(string $text): array
    {
        return self::byPosition($this->scan($text));
    }

    /** The text with each match masked. Options: mask (default "*"), replace(match). */
    public function censor(string $text, array $options = []): string
    {
        return self::censorMatches($text, $this->scan($text), $options);
    }
}