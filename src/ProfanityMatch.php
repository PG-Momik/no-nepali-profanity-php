<?php

declare(strict_types=1);

namespace NoNepaliProfanity;

/**
 * One place where profanity was found in the original text.
 *
 * `start` and `end` are code point (Unicode character) offsets into the input, like the Python
 * port — not UTF-16 code units like the npm package.
 */
final class ProfanityMatch
{
    public function __construct(
        /** The matched text exactly as it appears in the input, e.g. "F.U.C.K". */
        public string $text,
        /** The normalized form that was matched, e.g. "fuck". The same value findProfanity returns. */
        public string $normalized,
        /** Start index in the input, in Unicode code points. */
        public int $start,
        /** End index in the input, exclusive. */
        public int $end,
    ) {
    }

    /** @return array{text: string, normalized: string, start: int, end: int} */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'normalized' => $this->normalized,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}