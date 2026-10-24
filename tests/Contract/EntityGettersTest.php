<?php

namespace Tests\Base\Contract;

use Base\Contract\Link;
use Base\Contract\Runtime;
use Base\Entity\Layout\Semantic;
use Base\Entity\Layout\ShortLink;
use Omnibridge\Contract\Locales;
use Base\Entity\Thread;
use Base\Entity\ThreadIntl;
use Base\Service\Localizer;
use Base\Service\Model\LocaleNormalizer;
use Base\Enum\ThreadState;
use Base\Enum\WorkflowState;
use Omnibridge\Exception\MissingContractException;
use Base\Service\BaseService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The entities' getters C3e moved off BaseTrait's services onto the contracts
 * (plan "omnibase agnostique"), under Symfony: each answers what the service
 * it used answers - the router, the authorization checker, the Localizer -
 * compared here with the old path, one by one, as ContractAdaptersTest
 * compares the adapters. tests/BareTest's
 * testTheEntitiesAnswerThroughTheContractsWithoutTheFramework runs the same
 * getters where there is no framework.
 */
final class EntityGettersTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get('security.token_storage')->setToken(null);
        parent::tearDown();
    }

    public function testLinksReferenceTypesAreTheRoutersConstants(): void
    {
        self::assertSame(UrlGeneratorInterface::ABSOLUTE_URL, Link::ABSOLUTE_URL);
        self::assertSame(UrlGeneratorInterface::ABSOLUTE_PATH, Link::ABSOLUTE_PATH);
        self::assertSame(UrlGeneratorInterface::RELATIVE_PATH, Link::RELATIVE_PATH);
        self::assertSame(UrlGeneratorInterface::NETWORK_PATH, Link::NETWORK_PATH);
    }

    /**
     * Semantic::__toLink() and getUrl(), ShortLink::__toLink() and Link::to() give the router's
     * strings for the four reference types (BaseTrait's getRouter() is the same advanced_router).
     */
    public function testLinksAreTheRoutersAddresses(): void
    {
        $router = static::getContainer()->get('advanced_router');
        $types = [Link::ABSOLUTE_URL, Link::ABSOLUTE_PATH, Link::RELATIVE_PATH, Link::NETWORK_PATH];
        $checked = 0;

        foreach ([['security_login', []], ['user_profile', []], ['short_redirect', ['slug' => 'x7Kq2']]] as [$route, $parameters]) {
            if (null === $router->getRouteCollection()->get($route)) {
                continue;
            }

            $semantic = (new \ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
            $semantic->setRouteName($route);
            $semantic->setRouteParameters($parameters);
            foreach ($types as $type) {
                self::assertSame($router->generate($route, $parameters, $type), $semantic->__toLink($type), $route.', type '.$type);
                self::assertSame($router->generate($route, $parameters, $type), Link::to($route, $parameters, $type), $route.', Link::to(), type '.$type);
                self::assertSame($semantic->getRouter()->generate($route, $parameters, $type), $semantic->__toLink($type), $route.', BaseTrait\'s router');
            }
            self::assertSame($router->generate($route, $parameters), $semantic->getUrl(), $route);
            self::assertSame($router->generate($route, $parameters), $semantic->__toLink(), $route);
            ++$checked;
        }
        self::assertGreaterThan(0, $checked, 'a route of the core was compared');

        // A route the router cannot make: null, as before.
        $semantic->setRouteName('no_such_route_c3e');
        self::assertNull($semantic->__toLink());

        if (null !== $router->getRouteCollection()->get('short_redirect')) {
            $short = (new \ReflectionClass(ShortLink::class))->newInstanceWithoutConstructor();
            self::assertNull($short->__toLink());
            $short->setSlug('x7Kq2');
            foreach ($types as $type) {
                self::assertSame($router->generate('short_redirect', ['slug' => 'x7Kq2'], $type), $short->__toLink([], $type), 'short link, type '.$type);
                self::assertSame($router->generate('short_redirect', ['utm' => 'mail', 'slug' => 'x7Kq2'], $type), $short->__toLink(['utm' => 'mail'], $type), 'short link with a parameter, type '.$type);
            }
            self::assertSame($router->generate('short_redirect', ['slug' => 'x7Kq2']), $short->__toLink());
        }
    }

    /** A registry without Urls is said aloud by Semantic, never taken for a missing route. */
    public function testALinkWithoutTheUrlsContractIsSaidAloud(): void
    {
        $semantic = (new \ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
        $semantic->setRouteName('security_login');
        $semantic->setRouteParameters([]);

        $container = Runtime::getContainer();
        Runtime::setContainer(null);
        try {
            $this->expectException(MissingContractException::class);
            $semantic->__toLink();
        } finally {
            Runtime::setContainer($container);
        }
    }

    /**
     * Thread::isVisible() and User::isGranted() ask the Access contract what they asked BaseService:
     * the same authorization checker, nobody signed in then an administrator, a published, a draft
     * and a secret thread. The old isVisible() also asked whether the owners held the token
     * storage's token, never true: its answers were BaseService::isGranted('ROLE_ADMIN') or
     * approved, published and not secret, compared here.
     */
    public function testVisibilityAndGrantsAreTheAuthorizationCheckers(): void
    {
        $container = static::getContainer();
        $checker = $container->get('security.authorization_checker');
        $userClass = 'App\\Entity\\User';
        $member = (new \ReflectionClass($userClass))->newInstanceWithoutConstructor();

        $threads = [];
        foreach ([ThreadState::PUBLISH, ThreadState::DRAFT, ThreadState::SECRET] as $state) {
            $thread = new Thread();
            $thread->setState($state);
            $thread->setWorkflow(WorkflowState::APPROVED);
            $threads[$state] = $thread;
        }

        $seen = [];
        foreach ([null, new InMemoryUser('c3e-admin', null, ['ROLE_ADMIN'])] as $user) {
            $container->get('security.token_storage')->setToken($user ? new UsernamePasswordToken($user, 'main', $user->getRoles()) : null);
            $label = $user ? 'an administrator' : 'nobody';

            foreach ($threads as $state => $thread) {
                $before = BaseService::getService()->isGranted('ROLE_ADMIN') || ($thread->isApproved() && $thread->isPublished() && !$thread->isSecret());
                self::assertSame($before, $thread->isVisible(), $state.', '.$label);
                $seen[$label][$state] = $thread->isVisible();
            }

            foreach (['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPERADMIN', 'IS_AUTHENTICATED'] as $role) {
                self::assertSame($checker->isGranted($role, $member), $member->isGranted($role), $role.', '.$label);
                self::assertSame(BaseService::getService()->isGranted($role, $member), $member->isGranted($role), $role.', '.$label.', BaseService');
            }
        }

        self::assertSame([ThreadState::PUBLISH => true, ThreadState::DRAFT => false, ThreadState::SECRET => false], $seen['nobody'], 'nobody sees the published thread only');
        self::assertSame([true, true, true], array_values($seen['an administrator']), 'an administrator sees them all');
        self::assertTrue($member->isGranted('ROLE_ADMIN'), 'the administrator is granted ROLE_ADMIN on a member');
    }

    /**
     * The rule the Localizer applied before C3e, copied as it was (normalizeLocale() with the
     * Localizer's statics): LocaleNormalizer must give the same strings, through the Localizer and
     * through the Locales contract.
     */
    private static function normalizedBefore(string $locale): string
    {
        if (5 == strlen($locale)) {
            return substr($locale, 0, 2).'-'.substr($locale, 3, 2);
        }
        $locales = Localizer::getLocales();
        $lang = $locale ? substr($locale, 0, 2) : null;
        if (null === $lang || !array_key_exists($lang, $locales)) {
            $lang = substr(Localizer::getDefaultLocale(), 0, 2);
        }
        $defaultCountry = Localizer::getDefaultLocale() ? substr(Localizer::getDefaultLocale(), 3, 2) : ('en' == $lang ? 'GB' : first($locales[$lang]) ?? null);
        $availableCountries = array_transforms(fn ($k, $l): array => null !== $l ? [substr($l, 0, 2), [substr($l, 3, 2)]] : null, Localizer::getAvailableLocales());
        $langCountries = $availableCountries[$lang] ?? [];
        $country = substr($locale, 3, 2) ?: null;
        $country = null !== $country && in_array($country, $langCountries) ? $country : ($langCountries[0] ?? $defaultCountry);

        return $lang.'-'.$country;
    }

    /**
     * The translatable entities' locales (TranslatableTrait::translate(), its proxies, addTranslation(),
     * TranslationTrait's getLocale() and setLocale()) come from the Locales contract and
     * LocaleNormalizer, where they came from BaseTrait's Localizer: every quantity translate() read
     * of the Localizer - the default and available locales, a locale normalized, its language - is
     * the same, for the page's locale and a dozen given; and the translation picked, the title read
     * by __call, follow the page's language as before.
     */
    public function testTranslationsFollowTheLocalesAsTheLocalizerGaveThem(): void
    {
        $localizer = static::getContainer()->get('localizer');
        $locales = Runtime::locales();
        $default = $locales->getDefaultLocale();
        $available = $locales->getAvailableLocales();

        self::assertSame($localizer::getDefaultLocale(), $default);
        self::assertSame(array_values($localizer::getAvailableLocales()), $available);

        foreach (['fr', 'en', 'ja', 'de', 'pt', 'xx', '', 'fr_FR', 'fr-FR', 'en-US', 'en-GB', 'en_gb', 'fr-CA', 'es-MX', 'zz-ZZ', 'de-DE-1996'] as $given) {
            $before = self::normalizedBefore($given);
            self::assertSame($before, LocaleNormalizer::here($given), '"'.$given.'" through the contract');
            self::assertSame($before, Localizer::normalizeLocale($given), '"'.$given.'" through the Localizer');
            self::assertSame($before, LocaleNormalizer::normalize($given, $default, $available), '"'.$given.'"');
            self::assertSame($localizer->getLocaleLang($before), LocaleNormalizer::lang($before, $default), '"'.$given.'", its language');
            self::assertSame(Localizer::__toLocaleLang($given), LocaleNormalizer::lang($given, $default), '"'.$given.'", its language as given');
        }

        $thread = new Thread();
        foreach ($available as $locale) {
            $thread->translate($locale)->setTitle('Title '.$locale);
        }
        self::assertSame($available, array_keys($thread->getTranslations()->toArray()), 'each written in its language');

        foreach (array_merge($available, ['ja-JP', 'de-DE']) as $page) {
            $localizer->setLocale($page);
            self::assertSame($localizer->getLocale(), $locales->getLocale());
            self::assertSame(self::normalizedBefore($localizer->getLocale()), LocaleNormalizer::here($locales->getLocale()), 'the page\'s locale');
            $expected = $thread->getTranslations()[$localizer->getLocale()] ?? null;
            foreach ($thread->getTranslations() as $key => $candidate) {
                $expected ??= substr($key, 0, 2) === substr($localizer->getLocale(), 0, 2) ? $candidate : null;
            }
            $expected ??= $thread->getTranslations()[$default];
            self::assertSame($expected, $thread->translate(), 'the page\'s, the same language\'s, else the default\'s: '.$page);
            self::assertSame($expected->getTitle(), $thread->getTitle(), 'getTitle() by __call: '.$page);
        }

        // A write on the page goes where a read goes: the page's language when written in (the harness has two).
        $other = $available[1] ?? $default;
        $localizer->setLocale($other);
        $thread->setTitle('Retitled');
        self::assertSame('Retitled', $thread->translate($other)->getTitle());
        if ($other !== $default) {
            self::assertSame('Title '.$default, $thread->translate($default)->getTitle());
        }

        $localizer->setLocale($default);
        self::assertSame($thread->translate($default), $thread->translate(-1), 'a negative locale is the default');
        self::assertSame($thread->translate(), $thread->translate(null));

        $intl = new ThreadIntl();
        foreach (['fr_FR', 'en', 'fr', 'ja'] as $given) {
            $intl->setLocale($given);
            self::assertSame(self::normalizedBefore($given), $intl->getLocale(), 'TranslationTrait::setLocale("'.$given.'")');
        }
    }
}
