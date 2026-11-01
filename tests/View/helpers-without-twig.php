<?php

/*
 * The neutral view helpers (plan "omnibase agnostic", step F4: Base\View\*) where Twig is not
 * installed: run by tests/BareTest.php through docker/bin/bare --strict, with Composer's autoloader
 * of var/bare/vendor alone (the core installed without the framework, and without Twig since F4).
 * What it returns is compared with helpers-without-twig.expected.php; ViewHelpersTest shows, where
 * Twig is installed, that the same helpers answer what Twig's own formatters and omnibase's Twig
 * functions answer.
 */

use Base\Enum\UserRole;
use Base\Service\Model\PaginationInterface;
use Base\View\Dates;
use Base\View\DeployVersion;
use Base\View\Enums;
use Base\View\Formats;
use Base\View\Links;
use Base\View\Menus;
use Base\View\Money;
use Base\View\Pagination;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Translations;
use Symfony\Contracts\Translation\TranslatorInterface;

$translator = new class implements TranslatorInterface {
    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return strtr((string) $id, ['messages.paginator.' => '']).($parameters ? '('.implode(',', $parameters).')' : '');
    }

    public function getLocale(): string
    {
        return 'fr';
    }
};

$pagination = new class implements PaginationInterface {
    public function getPage() { return 3; }
    public function getPageSize() { return 10; }
    public function getPageRange() { return 1; }
    public function getTotalPages() { return 5; }
    public function getTemplate() { return '@Base/paginator/sliding.html.twig'; }
    public function getPath(string $name, int $page = 0, array $parameters = []) { return '/'.$name.'?page='.$page; }
    public function getResult() { return []; }
    public function getTotalCount() { return 50; }
};

$translations = new class implements Translations {
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $id;
    }

    public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
    {
        return false;
    }
};

$locales = new class implements Locales {
    public function getLocale(): string { return 'fr-FR'; }
    public function getDefaultLocale(): string { return 'en-GB'; }
    public function getAvailableLocales(): array { return ['en-GB', 'fr-FR']; }
};

$dates = new Dates($translator, 'Europe/Paris');
$paginator = new Pagination($translator);
$moment = 1775844300; // 2026-04-10T18:05:00+00:00
$deploy = sys_get_temp_dir().'/omnibase-deploy-'.getmypid();
@mkdir($deploy);

$result = [
    'twig loaded' => array_values(array_filter(get_declared_classes(), static fn (string $class): bool => str_starts_with($class, 'Twig\\'))),
    'formats' => [
        'highlight' => Formats::highlight('Le petit chat dort', 'chat'),
        'highlight, gate' => Formats::highlight('un deux trois quatre cinq six', 'quatre', 1),
        'mailto' => Formats::mailto('ringo@example.org', 'Ringo'),
        'mailparse email' => Formats::mailparseEmail('Ringo Starr <ringo@example.org>'),
        'mailparse name' => Formats::mailparseName('Ringo Starr <ringo@example.org>'),
        'truncate' => Formats::truncate('The Beatles at the Cavern Club', 12),
        'pickup' => Formats::pickup(['only' => 1], 3),
        'color name' => Formats::colorName('#ff0000'),
        'preg split' => Formats::pregSplit('a, b,c', '/,\s*/'),
        'pad' => Formats::pad([1], 3, 0),
        'short class' => Formats::shortClass(new ArrayObject()),
        'static call' => Formats::staticCall(Formats::class, 'mailto', 'a@b.c', 'A'),
        'greater than' => Formats::greaterThan('2000-01-01', 60),
        'moment' => Dates::isMoment('2026-04-10T18:05:00+00:00'),
    ],
    'dates' => [
        'timestamp' => $dates->datetime($moment, 'dd MMMM YYYY, HH:mm'),
        'moment' => $dates->datetime('2026-04-10T18:05:00+00:00', 'EEEE d MMMM'),
        'utc' => $dates->datetime($moment, 'HH:mm', timezone: 'UTC'),
        'word' => $dates->datetime('Présent', 'dd/MM'),
        'none' => $dates->datetime(null, 'dd/MM'),
        'patterns' => $dates->datetime($moment, ['dd', 'MM']),
    ],
    'money' => [
        'euros' => Money::currency(1234.5, 'EUR', [], 'fr_FR'),
        'yen, no fraction' => Money::currency(1500, 'JPY', ['fraction_digit' => 0], 'en_US'),
    ],
    'pagination' => [
        'sliding control' => array_keys($paginator->slidingControl($pagination, 'blog')),
        'first' => $paginator->first($pagination, 'blog'),
        'previous ones' => $paginator->previousOnes($pagination, 'blog'),
        'current' => $paginator->current($pagination),
        'next ones' => $paginator->nextOnes($pagination, 'blog'),
        'last separator' => $paginator->lastSeparator($pagination, '...'),
        'fast forward' => $paginator->fastForward($pagination, 'blog'),
    ],
    'links' => [
        'urlify' => (new Links(static fn ($url): bool => '/blog' === $url))->urlify('/blog', 'Blog', ['class' => 'nav']),
        'linkify' => (new Links())->linkify('/about'),
    ],
    'enums' => [
        'by class' => get_class((new Enums($translations))->enum(UserRole::class)),
        'colorify' => (new Enums($translations))->colorify(null),
    ],
    'menus' => (new Menus($locales))->locale(),
    'deploy version' => (new DeployVersion($deploy))->version(),
];
@rmdir($deploy);

return $result;
