<?php

declare(strict_types=1);

namespace NoNepaliProfanity;

use function sort;

/** Facade over cached {@see ProfanityFilter}, mirroring npm top-level functions. */
final class Profanity
{
    /** @var array<string, ProfanityFilter> */
    private static array $cache = [];

    public static function createFilter(array $options = []): ProfanityFilter
    {
        return new ProfanityFilter($options);
    }

    private static function filter(array $options = []): ProfanityFilter
    {
        $languages = $options['languages'] ?? Lexicon::LANGUAGES;
        sort($languages);
        $key = serialize([
            $options['strictness'] ?? '',
            $languages,
            $options['extraWords'] ?? [],
            $options['allowWords'] ?? [],
        ]);
        return self::$cache[$key] ??= new ProfanityFilter($options);
    }

    public static function check(string $text, array $options = []): ProfanityCheck
    {
        return self::filter($options)->check($text);
    }

    public static function containsProfanity(string $text, array $options = []): bool
    {
        return self::filter($options)->containsProfanity($text);
    }

    /** @return list<string> */
    public static function findProfanity(string $text, array $options = []): array
    {
        return self::filter($options)->findProfanity($text);
    }

    /** @return list<ProfanityMatch> */
    public static function findProfanityMatches(string $text, array $options = []): array
    {
        return self::filter($options)->findProfanityMatches($text);
    }

    public static function censor(string $text, array $options = []): string
    {
        $filterOptions = $options;
        unset($filterOptions['mask'], $filterOptions['replace']);
        return self::filter($filterOptions)->censor($text, $options);
    }

    /** @return list<string> */
    public static function tokenize(string $text): array
    {
        return ProfanityFilter::tokenize($text);
    }
}