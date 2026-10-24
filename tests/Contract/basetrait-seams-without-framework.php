<?php

/*
 * What omnibase's classes read through BaseTrait's services before step C4b
 * (plan "omnibase agnostique"), answered through the contracts where there is
 * no framework: run by docker/bin/bare for tests/BareTest.php, with
 * Composer's autoloader alone - no kernel, no container, no bundle, no
 * BaseService - and bare reports every class and file of the framework loaded
 * on the way (none may be).
 *
 * Plain PHP implementations of the contracts are set by hand, as another
 * framework sets its own at boot, and the real classes of the core answer:
 * - the Nouns contract (Base\Service\Model\EntityNouns over a Translations
 *   with exists()): what an entity is called, a bundle's entity by its
 *   namespace; Thread's and Widget's __toString() without a title.
 * - Duration over the Translations contract: a number of seconds in words,
 *   Token's time left and throttle, Thread's time before it is published.
 * - the Media contract (and Urls): Image's and ImageCrop's links to the image
 *   route.
 * - the Routes contract (and Urls): Semantic's and Widget\Route's declared
 *   path, route and icons, Widget\Route's links.
 * - the Environment contract and the parameters (an ArrayParameterBag): User's
 *   delays and tester's check, a Token built (its throttling, its salt), a
 *   secured entity's vault; BaseTrait's getParameterBag() and getEnvironment(),
 *   BaseService's isDebug(), isDevelopment(), getSalt() and, with Access,
 *   isGranted() (C9) - on a BaseService never constructed; HotParameters, the
 *   settings laid over the parameters (C9).
 * - the Sessions and Locales contracts: User's logout() and its locale.
 * Returns what they answered.
 */

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment;
use Base\Contract\Link;
use Omnibridge\Contract\Locales;
use Base\Contract\Media;
use Base\Contract\Nouns;
use Omnibridge\Contract\Routes;
use Base\Contract\Runtime;
use Omnibridge\Contract\Sessions;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Base\Entity\Layout\Image;
use Base\Entity\Layout\ImageCrop;
use Base\Entity\Layout\Semantic;
use Base\Entity\Layout\SettingIntl;
use Base\Entity\Layout\Widget\Route as RouteWidget;
use Base\Entity\Layout\Widget;
use Base\Entity\Thread;
use Base\Entity\User;
use Base\Entity\User\Token;
use Omnibridge\Exception\MissingContractException;
use Base\Service\ArrayParameterBag;
use Base\Service\BaseService;
use Base\Service\HotParameters;
use Base\Service\Model\ClassAliases;
use Base\Service\Model\Duration;
use Base\Service\Model\EntityNouns;
use Base\Service\ParameterBagInterface;
use Base\Service\Translator;
use Omnibridge\Contract\Translations as OmnibridgeTranslations;

// The Base\ / App\ convention as the core resolves it without a kernel (User's states are App\Enum\UserState).
ClassAliases::register();

$answers = [];

// The catalogues of a host: domain => key => text, ICU's {0} replaced plainly.
$translations = new class implements Translations {
    public array $catalogue = [
        'entities' => [
            'thread._singular' => 'article',
            'thread._plural' => 'articles',
            'thread._plural._feminine' => 'publications',
            'thread._properties.published_at._singular' => 'publié le',
            'layout.widget._singular' => 'bloc',
            'marketplace.order._singular' => 'commande',
            'marketplace.order._singular._polite' => 'votre commande',
        ],
        'messages' => [],
    ];

    /** base.days and its kin: nothing for none, the singular for one, the plural otherwise (ICU's plural, plainly). */
    public array $units = ['years' => ['an', 'ans'], 'months' => ['mois', 'mois'], 'days' => ['jour', 'jours'], 'hours' => ['heure', 'heures'], 'minutes' => ['minute', 'minutes'], 'seconds' => ['seconde', 'secondes']];

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if (str_starts_with($id, 'base.') && isset($this->units[substr($id, 5)])) {
            $count = (int) ($parameters[0] ?? 0);

            return 0 === $count ? '' : $this->units[substr($id, 5)][1 === $count ? 0 : 1];
        }

        $text = $this->catalogue[$domain ?? 'messages'][$id] ?? $id;
        foreach ($parameters as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return $text;
    }

    public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
    {
        return isset($this->catalogue[$domain ?? 'messages'][$id]);
    }
};

// The site's languages: the translatable entities (Thread, Widget) need them to hold a title.
Runtime::set(Locales::class, new class implements Locales {
    public function getLocale(): string
    {
        return 'fr-FR';
    }

    public function getDefaultLocale(): string
    {
        return 'fr-FR';
    }

    public function getAvailableLocales(): array
    {
        return ['fr-FR', 'en-GB'];
    }
});

// Nouns: before anything is set, said aloud.
try {
    (string) new Thread();
} catch (MissingContractException $e) {
    $answers['thread, no Nouns'] = 'MissingContractException';
}

$nouns = new EntityNouns($translations, EntityNouns::namespacesOf(['Base\\Marketplace\\MarketplaceBundle']));
Runtime::set(Nouns::class, $nouns);
$answers['key, thread'] = $nouns->key(Thread::class);
$answers['key, app'] = $nouns->key('App\\Entity\\Thread\\Tag');
$answers['key, bundle'] = $nouns->key('Base\\Marketplace\\Entity\\Order');
$answers['thread'] = $nouns->entity(Thread::class);
$answers['threads'] = $nouns->entity(new Thread(), null, Translator::NOUN_PLURAL);
$answers['threads, feminine'] = $nouns->entity(Thread::class, null, [Translator::NOUN_PLURAL, Translator::GENDERNESS_FEMININE]);
$answers['thread, published at'] = $nouns->entity(Thread::class, 'publishedAt');
$answers['order'] = $nouns->entity('Base\\Marketplace\\Entity\\Order');
$answers['order, polite'] = $nouns->entity('Base\\Marketplace\\Entity\\Order', null, [Translator::NOUN_SINGULAR, Translator::POLITENESS_POLITE]);
$answers['ghost'] = $nouns->entity('App\\Entity\\Ghost', null, Translator::NOUN_PLURAL);
$answers['exists, order'] = $nouns->entityExists('Base\\Marketplace\\Entity\\Order');
$answers['exists, ghost'] = $nouns->entityExists('App\\Entity\\Ghost');
$answers['thread, toString'] = (string) new Thread();
$answers['thread with a slug, toString'] = (string) new Thread(null, null, null, 'abbey-road');
$answers['widget, toString'] = (string) new Widget();

// Duration: through Translations (omnibridge/twig's, which Runtime::translations() gives).
Runtime::set(OmnibridgeTranslations::class, $translations);
$answers['duration, none'] = Duration::spell(0, Runtime::translations());
$answers['duration, a day and a second'] = preg_replace('/ +/', ' ', Duration::spell(86401, Runtime::translations()));
$answers['duration, two hours two minutes two seconds'] = preg_replace('/ +/', ' ', Duration::spell(7322, Runtime::translations()));
$token = (new ReflectionClass(Token::class))->newInstanceWithoutConstructor();
$token->setExpireAt(new DateTime('+2 days 1 minute 30 seconds'));
$token->setAllowAt(new DateTime('+10 minutes 30 seconds'));
$answers['token, time left'] = preg_replace(['/ +/', '/ \\d+ secondes?$/'], [' ', ''], $token->getRemainingTimeStr());
$answers['token, throttle'] = preg_replace(['/ +/', '/ \\d+ secondes?$/'], [' ', ''], $token->getThrottleTimeStr());
$thread = new Thread();
$answers['thread, not to be published'] = $thread->getPublishTimeStr();
$thread->setPublishedAt(new DateTime('+3 days 2 hours 30 seconds'));
$answers['thread, to be published'] = preg_replace(['/ +/', '/ \\d+ secondes?$/'], [' ', ''], $thread->getPublishTimeStr());

// Media and Urls: an image's link to the image route, a crop's.
Runtime::set(Urls::class, new class implements Urls {
    public function path(string $name, array $parameters = []): string
    {
        return '/'.$name.($parameters ? '?'.http_build_query($parameters) : '');
    }

    public function url(string $name, array $parameters = []): string
    {
        return 'https://lesringostars.test'.$this->path($name, $parameters);
    }

    public function asset(string $path): string
    {
        return $path;
    }
});
Runtime::set(Media::class, new class implements Media {
    public function extension(?string $path): ?string
    {
        return $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: null : null;
    }

    public function obfuscate(?string $path, array $config = [], array $filters = []): ?string
    {
        return null === $path ? null : $path.'|'.($config['identifier'] ?? '-').'|'.(($config['webp'] ?? true) ? 'webp' : 'as-is').'|'.count($filters).' filter(s)';
    }

    public function link(string $route, array $parameters, ?string $path, array $config = []): ?string
    {
        return null === $path ? null : '/'.$route.'/'.$this->obfuscate($path, $config).(isset($parameters['extension']) ? '.'.$parameters['extension'] : '');
    }
});
$image = new class extends Image {
    public function __construct()
    {
    }

    public function getSource()
    {
        return '/uploads/abbey-road.JPG';
    }

    public function getNaturalWidth(): ?int
    {
        return 640;
    }

    public function getNaturalHeight(): ?int
    {
        return 480;
    }
};
$answers['image, link'] = $image->__toLink();
$answers['image, thumbnail'] = $image->__toLink(['thumbnail' => [120, 80]]);
$answers['image, its own extension'] = $image->__toLink(['webp' => false]);
$answers['image, url'] = $image->__toLink(['crop' => 'square', 'extension' => 'png'], Link::ABSOLUTE_URL);
$crop = new ImageCrop($image);
$crop->setSlug('square');
$answers['crop, link'] = $crop->__toLink();
$answers['crop, its own extension'] = $crop->__toLink(['extension' => true, 'webp' => false]);

// Routes: a route's declared path, the route of an address, a route's icons.
Runtime::set(Routes::class, new class implements Routes {
    private array $routes = ['article' => '/articles/{slug}', 'blog' => '/blog'];

    public function pattern(string $name): ?string
    {
        return $this->routes[$name] ?? null;
    }

    public function match(string $url): ?string
    {
        return '/blog' === $url ? 'blog' : (str_starts_with($url, '/article') ? 'article' : null);
    }

    public function icons(string $name): ?array
    {
        return 'blog' === $name ? ['fa-solid fa-newspaper'] : null;
    }
});
$semantic = (new ReflectionClass(Semantic::class))->newInstanceWithoutConstructor();
$semantic->setRouteName('article');
$semantic->setRouteParameters(['slug' => 'abbey-road']);
$answers['semantic, path'] = $semantic->getPath();
$answers['semantic, route'] = $semantic->getRoute();
$answers['semantic, icons'] = $semantic->getRouteIcons();
$menu = new RouteWidget('Le blog', 'blog');
$answers['route widget, path'] = $menu->getPath();
$answers['route widget, route'] = $menu->getRoute();
$answers['route widget, icons'] = $menu->__iconize();
$answers['route widget, link'] = $menu->__toLink(['page' => 2]);
$answers['route widget, url'] = (new RouteWidget('Le blog', 'blog'))->generate(Link::ABSOLUTE_URL);

// Environment and parameters: what the application runs as, and its parameters by a dotted path.
Runtime::set(Environment::class, new class implements Environment {
    public function name(): string
    {
        return 'prod';
    }

    public function isDebug(): bool
    {
        return true;
    }

    public function secret(): string
    {
        return 'not-so-secret';
    }
});
Runtime::set(ParameterBagInterface::class, new ArrayParameterBag([
    'base.user.active_delay' => 300,
    'base.user.online_delay' => 120,
    'base.user.token_default_throttling' => 60,
    'base.notifier.test_recipients' => ['@lesringostars\\.test$'],
]));
$answers['user, active delay'] = User::getActiveDelay();
$answers['user, online delay'] = User::getOnlineDelay();
$member = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
$answers['user, a tester'] = $member->setEmail('paul@lesringostars.test')->isTester();
$answers['user, not a tester'] = $member->setEmail('paul@example.org')->isTester();
$token = new Token('verify-email', 3600);
$answers['token, throttling'] = (new ReflectionProperty(Token::class, 'throttle'))->getValue($token);
$answers['token, salted with the secret'] = (new ReflectionProperty(Token::class, 'hashIds'))->getValue($token)->encodeHex('c4b0') === (new Hashids\Hashids('not-so-secret'))->encodeHex('c4b0');
$setting = new SettingIntl();
$answers['vault, secured'] = $setting->setSecure(true)->getVault();
$answers['vault, not secured'] = $setting->setSecure(false)->getVault();
// BaseTrait's accessors and BaseService's methods that a contract answers (C9), with no container:
// the parameters, the environment's name, debug, the secret, and Access - on a BaseService never constructed.
Runtime::set(Access::class, new class implements Access {
    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $attribute === 'ROLE_ADMIN';
    }

    public function getUser(): ?object
    {
        return null;
    }
});
$answers['basetrait, a parameter'] = BaseService::getParameterBag('base.user.active_delay');
$answers['basetrait, the parameters'] = BaseService::getParameterBag() instanceof ArrayParameterBag;
$answers['basetrait, the environment'] = BaseService::getEnvironment();
// The settings laid over the parameters (C9): HotParameters over the ArrayParameterBag, a false setting winning.
$hot = new HotParameters(new ArrayParameterBag(['base.parameter_bag.use_hot_bag' => true, 'base.user.login_with_token' => true, 'base.user.active_delay' => 300]));
$hot->add(['base.user.login_with_token' => false, 'base.user.active_delay' => null]);
$off = new HotParameters(new ArrayParameterBag(['base.user.active_delay' => 300]));
$off->add(['base.user.active_delay' => 60]);
$answers['hot parameters, a false setting, a null one, off'] = [$hot->get('base.user.login_with_token'), $hot->get('base.user.active_delay'), $off->get('base.user.active_delay')];
$facade = (new ReflectionClass(BaseService::class))->newInstanceWithoutConstructor();
$answers['baseservice, debug, development, salt'] = [$facade->isDebug(), $facade->isDevelopment(), $facade->getSalt()];
$answers['baseservice, granted'] = [$facade->isGranted('ROLE_ADMIN'), $facade->isGranted('ROLE_SUPERADMIN')];

// Sessions and Locales: a user signed out or kicked, a user's locale.
Runtime::set(Sessions::class, new class implements Sessions {
    public ?object $visitor = null;

    public function signOut(object $user): bool
    {
        if ($user !== $this->visitor) {
            return false;
        }
        $this->visitor = null;

        return true;
    }
});
$visitor = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
$visitor->setIsKicked(false);
Runtime::sessions()->visitor = $visitor;
$visitor->logout();
$answers['user, the visitor signed out'] = [null === Runtime::sessions()->visitor, $visitor->isKicked()];
$absent = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
$absent->setIsKicked(false);
$absent->logout();
$answers['user, someone else kicked'] = $absent->isKicked();
$answers['user, the page\'s locale'] = $absent->getLocale();
$answers['user, its own locale'] = $absent->setLocale('en-GB')->getLocale();

Runtime::reset();

return $answers;
