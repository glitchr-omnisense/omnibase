<?php

namespace Tests\Base\View;

use Base\Service\Model\PaginationInterface;
use Base\Service\TradingInterface;
use Base\Twig\Extension\ClassTwigExtension;
use Base\Twig\Extension\LinkTwigExtension;
use Base\Twig\Extension\PaginatorTwigExtension;
use Base\Twig\Extension\TradingTwigExtension;
use Base\View\Dates;
use Base\View\Formats;
use Base\View\Links;
use Base\View\Money;
use Base\View\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;

/**
 * The neutral view helpers (plan "omnibase agnostic", step F4: Base\View\*) answer what Twig
 * answered: the dates and amounts their own intl code formats are those of twig/intl-extra's
 * format_datetime() and format_currency() for the same input, and omnibase's Twig functions and
 * filters, now thin wrappers, print what the helpers give - the same names, so no template changes.
 * Where Twig is not installed the helpers give the same (BareTest::testTheViewHelpersWorkWithoutTwig()).
 */
final class ViewHelpersTest extends TestCase
{
    /** The |datetime filter's formatting: twig/intl-extra's formatDateTime() with no date part, lowered. */
    public function testDatesFormatAsTwigsIntlExtension(): void
    {
        if (!class_exists(IntlExtension::class)) {
            self::markTestSkipped('twig/intl-extra is not installed here: nothing to compare with.');
        }

        $intl = new IntlExtension();
        foreach (['UTC', 'Europe/Paris', 'Asia/Tokyo'] as $default) {
            $env = new Environment(new ArrayLoader());
            $env->getExtension(CoreExtension::class)->setTimezone($default);
            $dates = new Dates();
            foreach ([0, 1775844300, 1798761599, -86400] as $timestamp) {
                foreach (['YYYY-MM-dd HH:mm:ss', 'EEEE d MMMM y', 'HH:mm zzzz', ''] as $pattern) {
                    foreach (['fr', 'en_US', 'ja'] as $locale) {
                        foreach ([null, 'America/New_York', false] as $timezone) {
                            foreach (['medium', 'short', null] as $timeFormat) {
                                $twig = mb_strtolower($intl->formatDateTime($env, $timestamp, 'none', $timeFormat, $pattern, $timezone, 'gregorian', $locale));
                                $neutral = mb_strtolower($dates->format($timestamp, 'none', $timeFormat, $pattern, $timezone, 'gregorian', $locale, $default));
                                self::assertSame($twig, $neutral, sprintf('%d, "%s", %s, %s, %s, default %s', $timestamp, $pattern, $locale, var_export($timezone, true), var_export($timeFormat, true), $default));
                            }
                        }
                    }
                }
            }
            // |datetime itself: a moment lowered in that zone, a word lowered, nothing the pattern.
            self::assertSame(mb_strtolower($intl->formatDateTime($env, 1775844300, 'none', 'medium', 'EEEE d MMMM HH:mm', null, 'gregorian', 'fr')), $dates->datetime(1775844300, 'EEEE d MMMM HH:mm', locale: 'fr', default: $default));
            self::assertSame($dates->datetime(1775844300, 'HH:mm', locale: 'fr', default: $default), $dates->datetime((new \DateTime('@1775844300'))->format(\DATE_ATOM), 'HH:mm', locale: 'fr', default: $default));
            self::assertSame('présent', $dates->datetime('Présent', 'HH:mm', locale: 'fr'));
            self::assertSame('HH:mm', $dates->datetime(null, 'HH:mm', locale: 'fr'));
        }
    }

    /** The |format_currency filter's formatting: twig/intl-extra's formatCurrency(), attributes included. */
    public function testMoneyFormatsAsTwigsIntlExtension(): void
    {
        if (!class_exists(IntlExtension::class)) {
            self::markTestSkipped('twig/intl-extra is not installed here: nothing to compare with.');
        }

        $intl = new IntlExtension();
        foreach ([0, 12.5, 1234.567, -99.99, 1e7] as $amount) {
            foreach (['EUR', 'USD', 'JPY', 'CHF'] as $currency) {
                foreach (['fr_FR', 'en_US', 'de_CH', 'ja_JP'] as $locale) {
                    foreach ([[], ['fraction_digit' => 0], ['rounding_mode' => 'floor', 'fraction_digit' => 1], ['grouping_used' => false]] as $attrs) {
                        self::assertSame($intl->formatCurrency($amount, $currency, $attrs, $locale), Money::currency($amount, $currency, $attrs, $locale), sprintf('%s %s %s %s', $amount, $currency, $locale, json_encode($attrs)));
                    }
                }
            }
        }
    }

    /** omnibase's Twig functions and filters print what the helpers give. */
    public function testTheTwigExtensionsPrintWhatTheHelpersGive(): void
    {
        $translator = new class implements TranslatorInterface {
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id.($parameters ? '('.implode(',', $parameters).')' : '');
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
        $pagination = new class implements PaginationInterface {
            public function getPage() { return 4; }
            public function getPageSize() { return 10; }
            public function getPageRange() { return 2; }
            public function getTotalPages() { return 9; }
            public function getTemplate() { return 'sliding'; }
            public function getPath(string $name, int $page = 0, array $parameters = []) { return '/'.$name.'/'.$page.($parameters ? '?'.http_build_query($parameters) : ''); }
            public function getResult() { return []; }
            public function getTotalCount() { return 90; }
        };
        $trading = $this->createMock(TradingInterface::class);
        $trading->method('getRenderedCurrency')->willReturn(null);
        $trading->method('getFallback')->willReturn(null);
        $current = static fn ($url): bool => '/here' === $url;

        $twig = new Environment(new ArrayLoader([
            'sliding' => '{{ path }}|{{ path_parameters|json_encode }}|{{ rewind }}{{ separator }}{{ fastforward }}|{{ pagination.page }}',
            'page' => implode('#', [
                '{{ paginator(p, "blog", {tag: "x"}) }}',
                '{{ paginator_first(p, "blog") }}',
                '{{ paginator_previousOnes(p, "blog")|join(",") }}',
                '{{ paginator_current(p) }}',
                '{{ paginator_nextOnes(p, "blog", {}, "page.n")|join(",") }}',
                '{{ paginator_lastSeparator(p, "~") }}',
                '{{ paginator_fastforward(p, "blog") }}',
                '{{ "/here"|urlify("Here") }}',
                '{{ "/there"|linkify }}',
                '{{ get_short_class(o) }}',
                '{{ 1999|format_currency("EUR", {}, "fr_FR") }}',
            ]),
        ]), ['autoescape' => false]);
        $twig->addExtension(new PaginatorTwigExtension($translator));
        $twig->addExtension(new LinkTwigExtension($current));
        $twig->addExtension(new ClassTwigExtension());
        $twig->addExtension(new TradingTwigExtension($trading));

        $paginator = new Pagination($translator);
        $links = new Links($current);
        self::assertSame(implode('#', [
            'blog|{"tag":"x"}|«...»|4',
            $paginator->first($pagination, 'blog'),
            implode(',', $paginator->previousOnes($pagination, 'blog')),
            $paginator->current($pagination),
            implode(',', $paginator->nextOnes($pagination, 'blog', [], 'page.n')),
            $paginator->lastSeparator($pagination, '~'),
            $paginator->fastForward($pagination, 'blog'),
            $links->urlify('/here', 'Here'),
            $links->linkify('/there'),
            Formats::shortClass(new \ArrayObject()),
            (new Money($trading))->formatCurrency(1999, 'EUR', [], 'fr_FR'),
        ]), $twig->render('page', ['p' => $pagination, 'o' => new \ArrayObject()]));
        self::assertSame('<a href=\'/blog/1\'>messages.paginator.first</a>', $paginator->first($pagination, 'blog'));
        self::assertSame('<a href=\'/here\' class="highlight">Here</a>', $links->urlify('/here', 'Here'));
    }
}
