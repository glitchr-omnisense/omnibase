<?php

namespace Tests\Base\Contract;

use Base\Contract\Runtime;
use Base\Entity\Thread;
use Base\Entity\User\Token;
use Base\Service\Model\Duration;
use Base\Service\Translator;
use Base\Translation\OverridingTranslator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A number of seconds in words, moved off BaseTrait's translator onto the
 * Translations contract (plan "omnibase agnostique", C4b), under Symfony:
 * Base\Service\Model\Duration through Runtime::translations() and
 * Translator::transTime() (which answers by it) give the strings the old
 * transTime() gave - copied here as it was -, in French and English, from a
 * second to years; a text rewritten in the back office is the one they print;
 * Token's and Thread's getters say what they said.
 */
final class DurationTest extends KernelTestCase
{
    private const SECONDS = [-5, 0, 1, 2, 59, 60, 61, 120, 3599, 3600, 3661, 7322, 86399, 86400, 90061, 172800, 2591999, 2592000, 2595661, 31103999, 31104000, 31190400, 62380861, 400000000];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheWordsAreTheTranslatorsOfBefore(): void
    {
        $translator = static::getContainer()->get('Base\Service\Translator');
        $spoken = 0;
        foreach (['fr', 'en'] as $locale) {
            $translator->setLocale($locale);
            foreach (self::SECONDS as $seconds) {
                $before = self::before($translator, $seconds);
                self::assertSame($before, Duration::spell($seconds, Runtime::translations()), $seconds.'s, '.$locale);
                self::assertSame($before, $translator->transTime($seconds), $seconds.'s, '.$locale.', transTime()');
                $spoken += (int) ('' !== $before);
            }
        }
        self::assertSame(2 * (count(self::SECONDS) - 2), $spoken);

        $translator->setLocale('fr');
        self::assertSame('1 jour 1 heure 1 minute 1 seconde', Duration::spell(90061, Runtime::translations()));
    }

    public function testATextRewrittenInTheBackOfficeIsTheOnePrinted(): void
    {
        $translator = static::getContainer()->get('Base\Service\Translator');
        $translator->setLocale('fr');
        // Between omnibase's translator and Symfony's; under an application's test environment the
        // profiler's DataCollectorTranslator may stand around it: followed down to it.
        $inner = (new \ReflectionProperty(Translator::class, 'translator'))->getValue($translator);
        for ($depth = 0; !$inner instanceof OverridingTranslator && $depth < 5 && property_exists($inner, 'translator'); ++$depth) {
            $inner = (new \ReflectionProperty($inner, 'translator'))->getValue($inner);
        }
        self::assertInstanceOf(OverridingTranslator::class, $inner, 'the back office\'s texts are between omnibase\'s translator and Symfony\'s');

        $map = new \ReflectionProperty(OverridingTranslator::class, 'map');
        $map->setValue($inner, ['messages|fr|base.hours' => '{0, plural, =0 {} one {heure réécrite} other {heures réécrites}}']);
        try {
            self::assertSame('2 heures réécrites 3 minutes', Duration::spell(7380, Runtime::translations()));
            self::assertSame('2 heures réécrites 3 minutes', $translator->transTime(7380));
        } finally {
            $inner->reset();
        }
        self::assertSame('2 heures 3 minutes', Duration::spell(7380, Runtime::translations()));
    }

    public function testTokensAndThreadsSayTheirTimeAsBefore(): void
    {
        $translator = static::getContainer()->get('Base\Service\Translator');
        $translator->setLocale('fr');

        $token = new Token('c4b-duration', 7322, 61);
        self::stable(fn () => [$token->getRemainingTimeStr(), self::before($translator, $token->getRemainingTime())]);
        self::stable(fn () => [$token->getThrottleTimeStr(), self::before($translator, $token->getThrottleTime())]);
        self::assertMatchesRegularExpression('/^2 heures 2 minutes/', $token->getRemainingTimeStr());

        $thread = new Thread();
        self::assertNull($thread->getPublishTimeStr());
        $thread->setPublishedAt(new \DateTime('+3 days 2 hours'));
        self::stable(fn () => [$thread->getPublishTimeStr(), self::before($translator, $thread->getPublishTime())]);
        self::assertMatchesRegularExpression('/^3 jours 2 heures/', $thread->getPublishTimeStr());
    }

    /** The two strings, once they were computed within the same second. */
    private static function stable(\Closure $pair): string
    {
        for ($i = 0; $i < 5; ++$i) {
            [$a, $b] = $pair();
            if ($a === $b) {
                return $a;
            }
        }
        self::fail('the two answers differ: '.$a.' / '.$b);
    }

    /** Translator::transTime() before C4b, as it was. */
    private static function before(Translator $translator, int $time): string
    {
        if ($time > 0) {
            $seconds = fmod($time, 60);
            $time = intdiv($time, 60);
            $minutes = fmod($time, 60);
            $time = intdiv($time, 60);
            $hours = fmod($time, 24);
            $time = intdiv($time, 24);
            $days = fmod($time, 30);
            $time = intdiv($time, 30);
            $months = fmod($time, 12);
            $years = intdiv($time, 12);

            $str =
                ($years ?: '').' '.$translator->trans('base.years', [$years]).' '.
                ($months ?: '').' '.$translator->trans('base.months', [$months]).' '.
                ($days ?: '').' '.$translator->trans('base.days', [$days]).' '.
                ($hours ?: '').' '.$translator->trans('base.hours', [$hours]).' '.
                ($minutes ?: '').' '.$translator->trans('base.minutes', [$minutes]).' '.
                ($seconds ?: '').' '.$translator->trans('base.seconds', [$seconds]);

            return trim($str);
        }

        return '';
    }
}
