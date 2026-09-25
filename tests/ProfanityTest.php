<?php

declare(strict_types=1);

namespace NoNepaliProfanity\Tests;

use InvalidArgumentException;
use NoNepaliProfanity\Profanity;
use NoNepaliProfanity\ProfanityFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfanityTest extends TestCase
{
    public static function catchCases(): iterable
    {
        return [
            'English' => ['what a bitch'],
            'an inflected English word (stem)' => ['fucking useless'],
            'Romanized Nepali' => ['muji teacher'],
            'Romanized Nepali with a postposition' => ['machikneko class'],
            'Devanagari Nepali' => ['यो मुजी हो'],
            'Devanagari with a postposition' => ['मुजीको कक्षा'],
            'Devanagari with a nukta or zero-width joiner' => ["मु\u{200D}जी"],
            'leetspeak' => ['sh1t lecturer'],
            'leetspeak with @' => ['@ss'],
            'leetspeak with $' => ['a$$'],
            'dodging with ! for i inside a word' => ['sh!t lecturer'],
            'dodging with a wildcard for a hidden letter' => ['f*ck this'],
            'dodging with a wildcard for the hidden i' => ['sh*t'],
            'markdown emphasis still reads the word' => ['*sh*t* is bad'],
            'stretched letters' => ['fuuuuck'],
            'letters spelled out with dots' => ['f.u.c.k'],
            'letters spelled out with spaces' => ['m u j i'],
            'upper case' => ['IDIOT'],
            'Hindi slang common in Nepal' => ['chutiya'],
            'Romanized invective' => ['murkha'],
            'Dodged word stays caught with a suffix' => ['gandako budi'],
            'exact word, not a long place name' => ['look at that gand'],
            'stem catches the -ne inflected form' => ['chodne manche'],
            'Devanagari invective' => ['मुर्ख'],
            'Devanagari with a doubled consonant' => ['थुक्क'],
            'Devanagari slang' => ['कमिना'],
            'Devanagari vulgar term' => ['लुंड'],
            'multi-word phrase' => ['chaak ko pwal'],
            'multi-word phrase with leetspeak' => ['p3sa g@rne taba'],
            'multi-word phrase ending a sentence' => ['thulo sasto manche!'],
            'review-supplied term' => ['chhakka lai hami mukhulla bhanchha'],
            'short spelling from words.csv' => ['mji'],
            'leet spelling from words.csv' => ['m00ji'],
            'English leet spelling from words.csv' => ['f4ck off'],
            'Devanagari spelling from words.csv' => ['राण्डी'],
            'Latin phrase from words.csv' => ['khatako choro'],
            'Devanagari phrase from words.csv' => ['राण्डीको बान'],
            'spelling variant with -ey' => ['yo khatey payment app kahiley chaley po'],
            'an English slur' => ['what a faggot'],
            'a short English slur' => ['fag'],
            'an English slur in leetspeak' => ['f@ggot'],
            'an English compound with a stem' => ['shitface'],
            'an English compound in leetspeak with !' => ['sh!tf@ce'],
            'a root inside a longer word' => ['dumbfuck'],
            'a root inside a joined phrase' => ['sonofabitch'],
            'x written for chh' => ['xakka'],
            'x written for chh, stretched' => ['xaaakka'],
            'x written for ch' => ['maxikne'],
            'a word split by punctuation' => ['sh.it happens'],
            'a word split by a hyphen' => ['fu-ck off'],
            'a word split with one letter on its own' => ['f-ck off'],
            'accented letters' => ['fück'],
            'a Cyrillic look-alike letter' => ["fu\u{0441}k"],
            '9 for g' => ['ni99er'],
            '8 for b' => ['8itch'],
            'dodging with a wildcard for the hidden first letter' => ['that *ss'],
            'dodging with a wildcard for the hidden last letter' => ['fuc* off'],
        ];
    }

    #[DataProvider('catchCases')]
    public function testCatches(string $text): void
    {
        self::assertTrue(Profanity::containsProfanity($text));
    }

    public static function cleanCases(): iterable
    {
        return [
            'class assignment' => ['The class assignment was as hard as expected'],
            'computing and data structures' => ['Computing and data structures'],
            'Scunthorpe problems' => ['Dickson explained Scunthorpe problems'],
            'Assam and Gandaki are places' => ['Assam and Gandaki are places'],
            'Kshitij Shrestha' => ['Kshitij Shrestha'],
            'Shitij Adhikari' => ['Shitij Adhikari'],
            'Putali Gurung' => ['Putali Gurung'],
            'पुतली गुरुङ' => ['पुतली गुरुङ'],
            'Randip Thapa' => ['Randip Thapa'],
            'Asha Sharma' => ['Asha Sharma'],
            'Machindra Karki' => ['Machindra Karki'],
            'Harimaya Tamang' => ['Harimaya Tamang'],
            'सीता कार्की' => ['सीता कार्की'],
            'क्षितिज श्रेष्ठ' => ['क्षितिज श्रेष्ठ'],
            'sentence-final bang' => ['Great teacher!'],
            'interior bang' => ["No way! That can't be right"],
            'trailing asterisk' => ['the starred items are on page 12*'],
            'markdown emphasis on a clean word' => ['feed ** me ** the list'],
            'markdown emphasis around "is"' => ['this *is* good'],
            'markdown emphasis around "and"' => ['*and* then'],
            'bold markdown around "hi"' => ['**hi** there'],
            'chicken biryani' => ['chicken biryani is good'],
            'the salaam greeting' => ['the salaam greeting sounded nice'],
            'Gandaki river' => ['Gandaki river is in Nepal'],
            'words separated so a phrase must not match' => ['sasto ra manche duitai ho'],
            'terms and conditions' => ['terms and conditions'],
            'the conductor' => ['the conductor'],
            'bhrastachar kanda' => ['bhrastachar kanda'],
            'Kandel sir' => ['Kandel sir'],
            'Lundberg' => ['Lundberg'],
            'fohor pani' => ['fohor pani'],
            'kano manche' => ['kano manche'],
            'lato keta' => ['lato keta'],
            'फोहोर पानी' => ['फोहोर पानी'],
            'लाटो केटा' => ['लाटो केटा'],
            // chh (छ) is kept apart from ch (च)
            'chhodnu parchha' => ['chhodnu parchha'],
            'xodnu parchha' => ['xodnu parchha'],
            'chhut paunu bhayo' => ['chhut paunu bhayo'],
            // Words and names on the allow list, or that only contain a listed word
            'Shital Shrestha' => ['Shital Shrestha'],
            'Shitijko ghar' => ['Shitijko ghar'],
            'Nigeria and Niger' => ['Nigeria and Niger'],
            'he sniggered' => ['he sniggered'],
            'Scunthorpe United' => ['Scunthorpe United'],
            'Harshita and Nishita' => ['Harshita and Nishita'],
            'shiitake mushrooms' => ['shiitake mushrooms'],
            'a niggardly tip' => ['a niggardly tip'],
            'Shiite and Sunni' => ['Shiite and Sunni'],
            'a cutwater and sweetwater' => ['a cutwater and sweetwater'],
            'the dog\'s muzzle' => ['the dog\'s muzzle'],
            'sticky goo' => ['sticky goo'],
            'a looser fit' => ['a looser fit'],
            'fagotto solo' => ['fagotto solo'],
            // Punctuation that isn't hiding a word
            'e.g. the i.e. case' => ['e.g. the i.e. case'],
            'shital.shrestha@example.com' => ['shital.shrestha@example.com'],
            'self-conscious and well-known' => ['self-conscious and well-known'],
            'don\'t go' => ['don\'t go'],
            // Ordinary words the Romanized spelling folds must not change
            'the sale is on' => ['the sale is on'],
            'good food' => ['good food'],
            'book a shoot' => ['book a shoot'],
            'the 2026 census' => ['the 2026 census'],
        ];
    }

    #[DataProvider('cleanCases')]
    public function testDoesNotFlag(string $text): void
    {
        self::assertSame([], Profanity::findProfanity($text));
    }

    public function testIsFalseForAnEmptyString(): void
    {
        self::assertFalse(Profanity::containsProfanity(''));
    }

    public function testTokenizeJoinsSpelledOutLettersAndKeepsDevanagariWhole(): void
    {
        self::assertSame(['fuck', 'this'], Profanity::tokenize('f.u.c.k this'));
        self::assertSame(['सीता', 'कार्की'], Profanity::tokenize('सीता कार्की'));
    }

    public function testTokenizeKeepsWildcardAndSentenceFinalBang(): void
    {
        self::assertSame(['f*ck', 'this'], Profanity::tokenize('f*ck this!'));
    }

    public function testLanguagesOptionChecksOnlyEnabledLanguages(): void
    {
        $romanized = ['languages' => ['romanized']];
        self::assertTrue(Profanity::containsProfanity('muji', $romanized));
        self::assertFalse(Profanity::containsProfanity('fuck', $romanized));
        self::assertFalse(Profanity::containsProfanity('मुजी', $romanized));
        self::assertTrue(Profanity::containsProfanity('sasto manche', $romanized));
    }

    public function testLanguagesOptionKeepsLanguagesSeparate(): void
    {
        self::assertSame(['fuck'], Profanity::findProfanity('fuck muji मुजी', ['languages' => ['english']]));
        self::assertSame(['मुजी'], Profanity::findProfanity('fuck muji मुजी', ['languages' => ['devanagari']]));
    }

    public function testLanguagesOptionChecksNothingWhenEmpty(): void
    {
        self::assertSame([], Profanity::findProfanity('fuck muji मुजी', ['languages' => []]));
    }

    public function testLanguagesOptionRejectsAnUnknownLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Profanity::createFilter(['languages' => ['hindi']]);
    }

    public function testStrictnessLenientSkipsMilderInsults(): void
    {
        $lenient = ['strictness' => 'lenient'];
        self::assertFalse(Profanity::containsProfanity('idiot', $lenient));
        self::assertFalse(Profanity::containsProfanity('murkha', $lenient));
        self::assertFalse(Profanity::containsProfanity('sasto manche', $lenient));
        self::assertTrue(Profanity::containsProfanity('muji', $lenient));
    }

    public function testStrictnessStandardIsTheDefault(): void
    {
        self::assertTrue(Profanity::containsProfanity('idiot'));
        self::assertTrue(Profanity::containsProfanity('idiot', ['strictness' => 'standard']));
        self::assertFalse(Profanity::containsProfanity('Randip Thapa'));
    }

    public function testStrictnessStrictAddsStemsAndWordsThatHitOrdinaryWords(): void
    {
        $strict = ['strictness' => 'strict'];
        self::assertSame(['randikoban'], Profanity::findProfanity('randikoban', $strict));
        self::assertSame(['damn'], Profanity::findProfanity('damn it', $strict));
        self::assertSame([], Profanity::findProfanity('damn it'));
    }

    public function testStrictnessStrictStillLeavesTheAllowListAlone(): void
    {
        $strict = ['strictness' => 'strict'];
        self::assertSame([], Profanity::findProfanity('Randip Thapa', $strict));
        self::assertSame([], Profanity::findProfanity('Randipko class', $strict));
        self::assertSame([], Profanity::findProfanity('terms and conditions', $strict));
        self::assertSame([], Profanity::findProfanity('a random conductor', $strict));
        self::assertSame([], Profanity::findProfanity('Kandel sir', $strict));
    }

    public function testExtraWordsAreFlaggedWithLeetspeakAndPostpositions(): void
    {
        $filter = Profanity::createFilter(['extraWords' => ['spammer', 'ठग']]);
        self::assertSame(['spammer'], $filter->findProfanity('sp4mmer'));
        self::assertSame(['spammerko'], $filter->findProfanity('spammerko kura'));
        self::assertSame(['ठगको'], $filter->findProfanity('ठगको'));
        self::assertSame([], Profanity::findProfanity('spammer ठग'));
    }

    public function testAllowWordsAreNeverFlagged(): void
    {
        self::assertSame([], Profanity::findProfanity('idiot', ['allowWords' => ['idiot']]));
        self::assertSame([], Profanity::findProfanity('mujiko', ['allowWords' => ['muji']]));
        self::assertSame([], Profanity::findProfanity('मुजीको', ['allowWords' => ['मुजी']]));
        self::assertSame(['muji'], Profanity::findProfanity('idiot muji', ['allowWords' => ['idiot']]));
    }

    public function testWordListsMustBeListsOfStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Profanity::createFilter(['allowWords' => [1]]);
    }

    public function testStrictnessRejectsAnUnknownValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Profanity::createFilter(['strictness' => 'max']);
    }

    public function testCreateFilterCombinesBothOptions(): void
    {
        $filter = Profanity::createFilter(['languages' => ['romanized'], 'strictness' => 'lenient']);
        self::assertTrue($filter->containsProfanity('muji'));
        self::assertFalse($filter->containsProfanity('murkha'));
        self::assertSame(['muji'], $filter->findProfanity('fuck muji'));
    }

    public function testCreateFilterIsExplicitlyInstantiable(): void
    {
        self::assertInstanceOf(ProfanityFilter::class, new ProfanityFilter());
    }

    public function testFindProfanityMatchesReturnsEveryOccurrenceWithPosition(): void
    {
        $expected = [
            ['text' => 'F.U.C.K', 'normalized' => 'fuck', 'start' => 0, 'end' => 7],
            ['text' => 'sh1t', 'normalized' => 'shit', 'start' => 13, 'end' => 17],
            ['text' => 'muji', 'normalized' => 'muji', 'start' => 19, 'end' => 23],
            ['text' => 'MUJI', 'normalized' => 'muji', 'start' => 25, 'end' => 29],
        ];
        $actual = array_map(
            static fn ($m): array => $m->toArray(),
            Profanity::findProfanityMatches('F.U.C.K this sh1t, muji. MUJI'),
        );
        self::assertSame($expected, $actual);
    }

    public function testFindProfanityMatchesReturnsAPhraseBeforeAWordItContains(): void
    {
        $expected = [
            ['text' => 'chaak ko pwal', 'normalized' => 'chaak ko pwal', 'start' => 0, 'end' => 13],
            ['text' => 'chaak', 'normalized' => 'chaak', 'start' => 0, 'end' => 5],
        ];
        $actual = array_map(
            static fn ($m): array => $m->toArray(),
            Profanity::findProfanityMatches('chaak ko pwal'),
        );
        self::assertSame($expected, $actual);
    }

    public function testFindProfanityMatchesIsEmptyForCleanText(): void
    {
        self::assertSame([], Profanity::findProfanityMatches('Great teacher!'));
        self::assertSame([], Profanity::findProfanityMatches(''));
    }

    public static function maskCases(): iterable
    {
        return [
            ['you muji', 'you ****'],
            ['F.U.C.K this Sh1t!', '******* this ****!'],
            ['sh!!t happens', '***** happens'],
            ['ＦＵＣＫ off', '**** off'],
            ['fuuuuck yeah', '******* yeah'],
            ['f*ck and *sh*t*', '**** and ******'],
            ['muji muji', '**** ****'],
            ['you 😀 muji 😀', 'you 😀 **** 😀'],
            ['sh.it happens', '***** happens'],
            ['sh!tf@ce', '********'],
        ];
    }

    #[DataProvider('maskCases')]
    public function testCensorMasks(string $text, string $expected): void
    {
        self::assertSame($expected, Profanity::censor($text));
    }

    public function testCensorMasksDevanagariByVisibleCharacter(): void
    {
        self::assertSame('*** कक्षा', Profanity::censor('मुजीको कक्षा'));
    }

    public function testCensorMasksADevanagariConjunctAsOneCharacter(): void
    {
        self::assertSame('**', Profanity::censor('गाण्ड'));
        self::assertSame('**', Profanity::censor('कुत्ता'));
    }

    public function testCensorKeepsSpacesInsideAPhrase(): void
    {
        self::assertSame('*****   ******', Profanity::censor('sasto   manche'));
    }

    public function testCensorMergesAWordWithThePhraseAroundIt(): void
    {
        self::assertSame('***** ** ****', Profanity::censor('chaak ko pwal'));
    }

    public function testCensorLeavesCleanTextAndNamesAlone(): void
    {
        self::assertSame('Great teacher!', Profanity::censor('Great teacher!'));
        self::assertSame('Shitij is great', Profanity::censor('Shitij is great'));
        self::assertSame('', Profanity::censor(''));
    }

    public function testCensorUsesACustomMaskCharacter(): void
    {
        self::assertSame('you ####', Profanity::censor('you muji', ['mask' => '#']));
    }

    public function testCensorUsesAReplaceFunctionOverTheMask(): void
    {
        self::assertSame(
            'you [censored] [censored]',
            Profanity::censor('you muji fuck', ['mask' => '#', 'replace' => fn () => '[censored]']),
        );
        self::assertSame(
            'you m***',
            Profanity::censor('you muji', ['replace' => fn ($m) => $m->text[0] . str_repeat('*', mb_strlen($m->text, 'UTF-8') - 1)]),
        );
    }

    public function testCensorRespectsTheFilterOptions(): void
    {
        self::assertSame('fuck ****', Profanity::censor('fuck muji', ['languages' => ['romanized']]));
        self::assertSame('you idiot', Profanity::censor('you idiot', ['strictness' => 'lenient']));
        self::assertSame('**** Randip', Profanity::createFilter(['strictness' => 'strict'])->censor('damn Randip'));
    }

    public function testCensorRejectsAnEmptyMask(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Profanity::censor('muji', ['mask' => '']);
    }

    public function testCheckReturnsEverythingFromOneScan(): void
    {
        $result = Profanity::check('you muji, F.U.C.K');
        self::assertSame('you muji, F.U.C.K', $result->text);
        self::assertTrue($result->hasProfanity);
        self::assertSame(['muji', 'fuck'], $result->words);
        self::assertSame(['muji', 'F.U.C.K'], array_map(static fn ($m) => $m->text, $result->matches));
        self::assertSame('you ****, *******', $result->censor());
        self::assertSame('you ####, #######', $result->censor(['mask' => '#']));
    }

    public function testCheckChainsStraightIntoCensor(): void
    {
        self::assertSame('you ****', Profanity::check('you muji')->censor());
        self::assertSame('Great teacher!', Profanity::check('Great teacher!')->censor());
    }

    public function testCheckReportsCleanText(): void
    {
        $result = Profanity::check('Great teacher!');
        self::assertFalse($result->hasProfanity);
        self::assertSame([], $result->words);
        self::assertSame([], $result->matches);
    }

    public function testCheckRespectsTheFilterOptions(): void
    {
        self::assertSame('fuck ****', Profanity::check('fuck muji', ['languages' => ['romanized']])->censor());
        self::assertFalse(Profanity::createFilter(['strictness' => 'lenient'])->check('you idiot')->hasProfanity);
    }
}