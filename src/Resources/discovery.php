<?php

/*
 * What a host discovers among the core's services (Base\Discovery, plan "omnibase agnostic", step F5e slice 4c), as
 * data: the registries omnibridge/symfony's compiler passes held until slice 4c, each in its own class. omnibridge/symfony
 * compiles them in one generic pass (DiscoveryPass), omnibridge/laravel applies those it needs to what it binds, and
 * omnisense/omnibridge's Omnibridge\Container builds with the generic ones (autoconfigure, collect, fallbacks: the
 * format of Omnibridge\Container\Discovery). Classes are named as strings: reading this loads none but Aliases.
 * docs/20-architecture/services.md.
 */

use Base\Repository\Aliases;

return [
    // A service whose definition is autoconfigured and whose class is one of these gets the tags (Symfony's
    // registerForAutoconfiguration(), BaseExtension's and the Twig group's until slice 4c), in this order.
    'autoconfigure' => [
        // The tag renderers, handed to omnibase's Twig environment (collect, below); where Twig is installed.
        ['instanceof' => 'Base\Twig\Renderer\TagRendererInterface', 'tags' => ['twig.tag_renderer'], 'requires' => ['Twig\Environment']],
        ['instanceof' => 'Base\Service\Model\IconProvider\AbstractIconAdapter', 'tags' => ['base.service.icon']],
        ['instanceof' => 'Base\Database\Entity\EntityExtensionInterface', 'tags' => ['base.entity_extension']],
        ['instanceof' => 'Base\Attributes\AttributeInterface', 'tags' => ['base.attribute']],
        ['instanceof' => 'Base\Service\Model\IconProvider\IconAdapterInterface', 'tags' => ['base.icon_provider']],
        ['instanceof' => 'Base\Service\Model\Sharing\SharingAdapterInterface', 'tags' => ['base.service.sharing']],
        ['instanceof' => 'Base\Cache\Abstract\AbstractLocalCacheInterface', 'tags' => ['base.simple_cache']],
        ['instanceof' => 'Base\Service\Model\Currency\CurrencyApiInterface', 'tags' => ['currency.api']],
        // Collected by nothing (the compressions the obfuscator is handed are tagged obfuscator.compression in the
        // declaration of services): kept as it was registered, an application may read it.
        ['instanceof' => 'Base\Service\Model\Obfuscator\CompressionInterface', 'tags' => ['obfuscator.compressor']],
        ['instanceof' => 'Symfony\Component\Workflow\WorkflowInterface', 'tags' => ['workflow']],
        // The demonstration accounts a bundle or the application declares (docs/20-architecture/demo.md).
        ['instanceof' => 'Base\Demo\DemoAccountProviderInterface', 'tags' => ['base.demo_account_provider']],
        // An application's repository extending omnibridge/doctrine's (App\Repository\*): DoctrineBundle's repository
        // factory finds it in the container.
        ['instanceof' => 'Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository', 'tags' => ['doctrine.repository_service']],
        // The entity dispatchers: Doctrine listeners of these events, flushed after the flush that raised their events
        // (EntityDispatcherFlushSubscriber), reset between requests.
        ['instanceof' => 'Base\EntityDispatcher\EventDispatcherInterface', 'tags' => [
            ['name' => 'doctrine.event_listener', 'event' => 'preUpdate'],
            ['name' => 'doctrine.event_listener', 'event' => 'postUpdate'],
            ['name' => 'doctrine.event_listener', 'event' => 'prePersist'],
            ['name' => 'doctrine.event_listener', 'event' => 'postPersist'],
            ['name' => 'doctrine.event_listener', 'event' => 'preRemove'],
            ['name' => 'doctrine.event_listener', 'event' => 'postRemove'],
            ['name' => 'doctrine.event_listener', 'event' => 'postFlush'],
            'base.entity_dispatcher',
            ['name' => 'kernel.reset', 'method' => 'reset'],
        ]],
    ],

    // Each service a tag names (or each service whose id is a class that is one), in its order, handed to the
    // collecting service by that method - where the collecting service is defined.
    'collect' => [
        ['service' => 'Base\Attributes\AttributeReader', 'method' => 'addAttribute', 'tag' => 'base.attribute'],
        ['service' => 'Base\Database\Entity\EntityExtension', 'method' => 'addExtension', 'instanceof' => 'Base\Database\Entity\EntityExtensionInterface'],
        ['service' => 'Base\Service\IconProvider', 'method' => 'addAdapter', 'tag' => 'base.service.icon'],
        ['service' => 'Base\Service\Obfuscator', 'method' => 'addCompression', 'tag' => 'obfuscator.compression'],
        ['service' => 'Base\Service\Sharing', 'method' => 'addAdapter', 'tag' => 'base.service.sharing'],
        // An application's provider after the core's: the container's order (TradingTest).
        ['service' => 'Base\Service\Trading', 'method' => 'addProvider', 'tag' => 'currency.api'],
        ['service' => 'Base\Twig\Environment', 'method' => 'addRenderer', 'tag' => 'twig.tag_renderer'],
    ],

    // A service no one binds: the first candidate the host defines, else the default class. The admin's links (form
    // types and Twig helpers link back to the back office): omnibase/admin's generator where it is installed, none on
    // a front-end-only install - whatever the bundles' order. An application binding its own is never overridden.
    'fallbacks' => [
        'Base\Routing\AdminUrlGeneratorInterface' => ['candidates' => ['Base\Admin\Router\AdminUrlGenerator'], 'default' => 'Base\Routing\NullAdminUrlGenerator'],
        // The currency rates: Base\Service\Trading where florianv/swap is installed (its declaration requires it), else
        // NullTrading, which knows no rate - so the Twig filters, the money field and the facade, which ask
        // `trading_market`, are still built. The service id first, the contract then aliased to it.
        'trading_market' => ['candidates' => ['Base\Service\Trading'], 'default' => 'Base\Service\NullTrading'],
        'Base\Service\TradingInterface' => ['candidates' => ['trading_market'], 'default' => 'Base\Service\NullTrading'],
    ],

    // An alias moved to a better service where the application defines it: the rewritten texts
    // (Base\Translation\OverridingTranslator) in the shared Redis pool when it declares one, so that the web, the
    // worker and cron agree after an edit; otherwise the application's pool.
    'preferred' => [
        'base.text_override.cache' => ['prefer' => 'cache.redis', 'over' => 'cache.app'],
    ],

    // The contracts Base\Contract\Runtime reaches (plan "omnibase agnostique", C4): the host's (Omnibridge\Contract\*),
    // omnibase's own, the framework's clock, event dispatcher and mailer, the fields' payloads (F5d) - each by its
    // interface's name, the service the container binds to it. `service`: the locator's id, where a host builds one;
    // `services`: the contracts bound to another service than their name (the parameters: `parameter_bag`, the
    // settings stored in the database over the container's); `former`: the former names of moved contracts (none since
    // omnibase 4's final pass).
    'contracts' => [
        'service' => 'base.contracts',
        'contracts' => [
            'Omnibridge\Contract\Urls',
            'Omnibridge\Contract\Routes',
            'Omnibridge\Contract\Environment',
            'Base\Service\ParameterBagInterface',
            'Omnibridge\Contract\Translations',
            'Base\Contract\Nouns',
            'Omnibridge\Contract\Access',
            'Omnibridge\Contract\Sessions',
            'Omnibridge\Contract\Locales',
            'Omnibridge\Contract\Files',
            'Base\Contract\Pictures',
            'Base\Contract\Media',
            'Base\Contract\Texts',
            'Omnibridge\Contract\Tokens',
            'Omnibridge\Contract\Requests',
            'Omnibridge\Contract\Roles',
            'Omnibridge\Contract\Views',
            'Omnibridge\Contract\Validation',
            'Psr\Clock\ClockInterface',
            'Psr\EventDispatcher\EventDispatcherInterface',
            'Symfony\Component\Mailer\MailerInterface',
            // A select's remote search (F5d): SelectType hands it its payload, as FormView does elsewhere.
            'Base\Field\Search\RemoteSearch',
            // A dropzone's uploads (F5d, slice 4): FileType hands its payload, as the Upload behaviour does elsewhere.
            'Base\Field\Upload\Dropzone',
            // An editor's routes (F5d, slice 6): EditorType hands their payload and its pictures' addresses.
            'Base\Field\Editor\EditorJs',
            // The attribute reader, made when first asked (AttributeReader::getInstance(): Doctrine's SQL filters,
            // the attributes, the persistence's scopes), no longer at each kernel's boot.
            'Base\Attributes\AttributeReader',
        ],
        'services' => ['Base\Service\ParameterBagInterface' => 'parameter_bag'],
        'former' => [],
    ],

    // Arguments a service takes from another package's configuration, read once every package is configured, rather
    // than named in its definition: `parameter` (resolved when `resolve`), cast to `type`; `default` when the package
    // says nothing (no `default`: the argument is left as it is). FormGuard's default captcha and whether an
    // unreachable provider lets a form through are omnisense/omnishield's (omnishield.challenge.*); where
    // glitchr/ux-google's reCAPTCHA guards the sign-in, the sign-in asks no second captcha. `symfony`: where Symfony's
    // container has it (omnishield's bundle gives its validator the option, it sets no parameter).
    'arguments' => [
        ['service' => 'Base\Service\FormGuard', 'argument' => '$defaultChallenge', 'parameter' => 'omnishield.challenge.gateway', 'type' => 'string', 'default' => null],
        ['service' => 'Base\Service\FormGuard', 'argument' => '$acceptUnreachableChallenge', 'option' => 'omnishield.challenge.unreachable', 'type' => 'bool',
            'symfony' => ['definition' => ['Omnishield\Bridge\Symfony\Validator\PassesChallengeValidator', 3]]],
        ['service' => 'Omnibridge\Symfony\Omnibase\Security\SignInGuard', 'argument' => '$google', 'parameter' => 'google.recaptcha.enable', 'resolve' => true, 'type' => 'bool', 'default' => false],
    ],

    // The texts the application wrote itself (its own translations folder), for the level of politeness
    // (base.translator.politeness, docs/20-architecture/politeness.md): read while the services are built - once the
    // catalogues are merged nothing tells whose a text is - by `reader`, from the folder the parameter `directory`
    // names, given to the service as `argument`; nothing read when the parameter `when` is unset.
    'texts' => [
        'service' => 'Base\Service\Translator',
        'argument' => 4,
        'when' => 'base.translator.politeness',
        'directory' => 'translator.default_path',
        'reader' => ['Base\Translation\ApplicationTexts', 'read'],
    ],

    // omnibase's local storages (Base\Service\Flysystem reads the services tagged `tag` by their `storage` name), for a
    // host that has none of its own: the images' cache under var/cache, the uploads and the editor's files under
    // var/storage, each beside its public folder under public/ - as league/flysystem's local adapter defaults.
    'storages' => [
        'tag' => 'flysystem.storage',
        'local' => [
            'local.cache' => '%kernel.project_dir%/var/cache/%kernel.environment%/images',
            'local.cache.public' => '%kernel.project_dir%/public/images',
            'local.uploads' => '%kernel.project_dir%/var/storage/uploads',
            'local.uploads.public' => '%kernel.project_dir%/public/uploads',
            'local.wysiwyg' => '%kernel.project_dir%/var/storage/wysiwyg',
            'local.wysiwyg.public' => '%kernel.project_dir%/public/wysiwyg',
        ],
    ],

    // The enumerations (no Doctrine type since step F2a): the non-abstract classes extending `extends` in these folders
    // (%package%: the core's, %kernel.project_dir%: the application's), by their names (Base\Discovery::enumerations()).
    'enumerations' => [
        'extends' => 'Base\Database\Type\EnumType',
        'directories' => ['%package%/src/Enum', '%kernel.project_dir%/src/Enum'],
    ],

    // The core's repository interfaces and the repository each is bound to (an entity's repositoryClass may name an
    // interface, step F3): Base\Repository\Aliases.
    'repositories' => Aliases::INTERFACES,
];
