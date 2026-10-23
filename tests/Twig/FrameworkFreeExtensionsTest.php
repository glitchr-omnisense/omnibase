<?php

namespace Tests\Base\Twig;

use Base\Contract\Texts;
use Base\Enum\ThreadState;
use Base\Routing\AdminRouteNames;
use Base\Service\Icons;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Twig\Extension\EnumTwigExtension;
use Base\Twig\Extension\IconTwigExtension;
use Base\Twig\Extension\LinkTwigExtension;
use Base\Twig\Extension\NotificationTwigExtension;
use Base\Twig\Extension\PictureTwigExtension;
use Base\Twig\Extension\TextsTwigExtension;
use Base\Twig\Variable\AdminLinks;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Twig\HelperExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * omnibase's Twig extensions that no longer need Symfony (audit "lesringostars
 * face au master plan", R24): built on contracts and given what a host has,
 * each prints in a bare Twig what the Symfony site prints - the code
 * omnibridge/laravel registers - and under Symfony the functions and filters
 * are theirs, no longer MediaTwigExtension's, FunctionTwigExtension's,
 * TranslatorTwigExtension's nor HtmlTwigExtension's.
 */
final class FrameworkFreeExtensionsTest extends KernelTestCase
{
    /** @param array<string, string> $catalogue the "enums" domain's ids and words */
    private static function translations(array $catalogue): Translations
    {
        return new class($catalogue) implements Translations {
            /** @param array<string, string> $catalogue */
            public function __construct(private readonly array $catalogue)
            {
            }

            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $this->catalogue[$domain.':'.$id] ?? $id;
            }

            public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
            {
                return isset($this->catalogue[$domain.':'.$id]);
            }
        };
    }

    private static function twig(string $template, object ...$extensions): Environment
    {
        $twig = new Environment(new ArrayLoader(['page' => $template]));
        foreach ($extensions as $extension) {
            $twig->addExtension($extension);
        }

        return $twig;
    }

    public function testEnumerationsTheirColoursAndWords(): void
    {
        $extension = new EnumTwigExtension(self::translations(['enums:thread_state.state_draft._singular' => 'brouillon']));
        $twig = self::twig(
            '{{ (enum(class)|colorify)[draft]|first }}|{{ enum(class)|colorify(draft)|first }}|{{ draft|trans_enum(class) }}|{{ publish|trans_enum(class) }}|{{ draft|trans_enumExists(class) ? "yes" : "no" }}|{{ "none"|trans_enum("no_such_type") }}',
            $extension,
        );

        self::assertSame('#b02a37|#b02a37|Brouillon|thread_state.state_publish._singular|yes|none', $twig->render('page', ['class' => ThreadState::class, 'draft' => ThreadState::DRAFT, 'publish' => ThreadState::PUBLISH]));
        self::assertInstanceOf(ThreadState::class, $extension->enum(ThreadState::class));
        self::assertNull($extension->colorify(null));
    }

    public function testIconsByTheirAdapterAndARoutesOwn(): void
    {
        $adapter = new class implements IconAdapterInterface {
            public function supports(IconizeInterface|string|null $icon): bool { return \is_string($icon) && str_starts_with($icon, 'fa-'); }
            /** @param array<string, mixed> $attributes */
            public function iconify(IconizeInterface|string $icon, array $attributes): string { return "<i class='fa-fw ".$icon."'></i>"; }
            public static function getName(): string { return 'fa'; }
            /** @return array<string, string> */
            public static function getOptions(): array { return ['class' => 'fa-fw']; }
            public function getVersion(): string { return '6'; }
            /** @return list<string> */
            public function getAssets(): array { return []; }
            /** @return array<string, string> */
            public function getChoices(string $term = '') { return []; }
        };
        $icons = new Icons([$adapter], static fn ($route): ?array => 'app_article' === $route ? ['fa-solid fa-book'] : null);
        $twig = self::twig('{{ "fa-solid fa-pencil"|iconify|first|raw }}|{{ "app_article"|iconify|first|raw }}|{{ "nothing"|iconify|first|raw }}', new IconTwigExtension($icons));

        self::assertSame("<i class='fa-fw fa-solid fa-pencil'></i>|<i class='fa-fw fa-solid fa-book'></i>|<i class='fa-fw fa-solid fa-question-circle'></i>", $twig->render('page'));
    }

    public function testLinksAndTheTextsContract(): void
    {
        $texts = new class implements Texts {
            public function isEditor(?string $text): bool { return false; }
            public function render(?string $text, array $options = []): string { return null === $text ? '' : '<p>'.$text.(($options['headings'] ?? false) ? '#' : '').'</p>'; }
            public function toc(?string $text, ?int $maxLevel = null): array { return [['tag' => 'h2', 'slug' => 'a', 'title' => 'A']]; }
        };
        $twig = self::twig(
            '{{ url|urlify("edit", {target: "_blank"}) }}|{{ none|urlify }}|{{ text|wysiwyg({headings: true})|raw }}|{{ text|wysiwyg }}|{{ (text|wysiwyg_toc)[0].slug }}',
            new LinkTwigExtension(static fn ($url): bool => '/here' === $url),
            new TextsTwigExtension($texts),
        );

        self::assertSame("<a href='/admin/articles/1/edit' target=\"_blank\">edit</a>||<p>Fuji#</p>|&lt;p&gt;Fuji&lt;/p&gt;|a", $twig->render('page', ['url' => '/admin/articles/1/edit', 'none' => null, 'text' => 'Fuji']), '|wysiwyg is escaped without |raw, as under Symfony');
        self::assertSame("<a href='/here' class=\"highlight\">/here</a>", self::twig('{{ "/here"|urlify }}', new LinkTwigExtension(static fn ($url): bool => '/here' === $url))->render('page'));
    }

    public function testTheAdminsNamesAndARecordsEditPage(): void
    {
        self::assertSame('admin_crud_articles_edit', AdminRouteNames::forEntity('App\Entity\Article\Article', 'edit'));
        self::assertSame('admin_crud_articles_comments_edit', AdminRouteNames::forEntity('App\Entity\Article\Comment', 'edit'));
        self::assertSame('admin_crud_articles_comment_replies_edit', AdminRouteNames::forEntity('Proxies\__CG__\App\Entity\Article\CommentReply', 'edit'));
        self::assertSame('admin_crud_users_index', AdminRouteNames::forEntity('Base\Entity\User'));
        self::assertNull(AdminRouteNames::forEntity('App\Model\Article'));
        if (class_exists(\Base\Admin\Router\AdminRouteRegistry::class)) {
            foreach (['App\Controller\Backoffice\Crud\Article\CommentReplyCrudController', 'Base\Admin\Controller\Crud\UserCrudController', 'App\Controller\Backoffice\Crud\Layout\Widget\PageCrudController'] as $controller) {
                self::assertSame(\Base\Admin\Router\AdminRouteRegistry::slugify($controller), AdminRouteNames::slugify($controller), $controller);
            }
        }

        $routes = new class implements Routes {
            public function pattern(string $name): ?string { return 'admin_crud_comments_edit' === $name ? '/admin/comments/{entityId}/edit' : null; }
            public function match(string $url): ?string { return null; }
            public function icons(string $name): ?array { return null; }
        };
        $urls = new class implements Urls {
            public function path(string $name, array $parameters = []): string { return '/'.$name.'/'.$parameters['entityId']; }
            public function url(string $name, array $parameters = []): string { return 'https://site'.$this->path($name, $parameters); }
            public function asset(string $path): string { return $path; }
        };
        $links = new AdminLinks($routes, $urls);

        self::assertSame('/admin_crud_comments_edit/7', $links->crudify(new \Tests\Base\Twig\Entity\Comment(7)));
        self::assertNull($links->crudify(new \Tests\Base\Twig\Entity\Comment()), 'not stored yet');
        self::assertNull($links->crudify(new \Tests\Base\Twig\Entity\Tag()), 'no route of that name');
        self::assertNull($links->crudify(null));
    }

    public function testTheBellOnTheContracts(): void
    {
        $access = new class implements Access {
            public function isGranted(mixed $attribute, mixed $subject = null): bool { return false; }
            public function getUser(): ?object { return null; }
        };
        $tokens = new class implements Tokens {
            public function csrf(string $intent): string { return 'token-of-'.$intent; }
            public function isValid(string $intent, ?string $token): bool { return false; }
        };
        $notifications = $this->createMock(\Base\Repository\User\Notifications::class);
        $notifications->expects(self::never())->method('countUnreadFor');
        $twig = self::twig('{{ notifications_unread() }}|{{ notifications_latest()|length }}|{{ notifications_token() }}|{{ push_available() ? "yes" : "no" }}|{{ push_vapid_key() }}', new NotificationTwigExtension($access, $notifications, null, $tokens));

        self::assertSame('0|0|token-of-notifications|no|', $twig->render('page'), 'a visitor: nothing, the token all the same, no push without WebPushService');
    }

    /** Under Symfony the functions and filters are the framework-free extensions', registered by the core. */
    public function testSymfonysAreTheseExtensions(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
        $twig = static::getContainer()->get('twig');
        $owner = static function (callable|array|string|null $callable): ?string {
            if (\is_array($callable)) {
                return \is_object($callable[0]) ? $callable[0]::class : $callable[0];
            }

            return $callable instanceof \Closure ? (new \ReflectionFunction($callable))->getClosureScopeClass()?->getName() : null;
        };

        foreach ([
            'colorify' => EnumTwigExtension::class, 'trans_enum' => EnumTwigExtension::class,
            'thumbnail' => PictureTwigExtension::class, 'crop' => PictureTwigExtension::class,
            'soundify' => \Base\Service\MediaService::class, 'mimetype' => \Base\Service\FileService::class,
            'urlify' => LinkTwigExtension::class, 'iconify' => Icons::class,
            'wysiwyg' => TextsTwigExtension::class, 'wysiwyg_toc' => TextsTwigExtension::class,
            'at' => HelperExtension::class, 'emoji_only' => HelperExtension::class,
        ] as $filter => $class) {
            self::assertSame($class, $owner($twig->getFilter($filter)?->getCallable()), $filter);
        }
        foreach (['enum' => EnumTwigExtension::class, 'image' => PictureTwigExtension::class, 'notifications_token' => NotificationTwigExtension::class] as $function => $class) {
            self::assertSame($class, $owner($twig->getFunction($function)?->getCallable()), $function);
        }
    }
}
