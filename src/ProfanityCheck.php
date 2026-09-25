<?php

declare(strict_types=1);

namespace NoNepaliProfanity;

/** Scan result: inspect then censor without re-scanning. */
final class ProfanityCheck
{
    public function __construct(
        public readonly string $text,
        public readonly bool $hasProfanity,
        public readonly array $words,
        public readonly array $matches,
        private array $rawMatches,
    ) {
    }

    /** The text with every match masked. Options: mask (default "*"), replace(match). */
    public function censor(array $options = []): string
    {
        return ProfanityFilter::censorMatches($this->text, $this->rawMatches !== [] ? $this->rawMatches : $this->matches, $options);
    }
}