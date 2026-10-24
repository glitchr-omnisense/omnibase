<?php

/*
 * The contracts of the Foundation used where there is no framework (plan
 * "omnibase agnostique", C4): run by docker/bin/bare for tests/BareTest.php,
 * with Composer's autoloader alone - no kernel, no container, no bundle - and
 * bare reports every class and file of the framework loaded on the way (none
 * may be). What another framework does at boot is done here by hand: plain
 * PHP implementations of the contracts, bound in a PSR-11 container by the
 * contracts' names (as Laravel binds them in its own), handed to
 * Base\Contract\Runtime. Then a user of the contracts, written as step C3e
 * writes the entities' getters (__toLink, isGranted, a title in the
 * visitor's language...), answers through them. Returns what it answered.
 */

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Files;
use Omnibridge\Contract\Locales;
use Base\Contract\Runtime;
use Omnibridge\Contract\Urls;
use Omnibridge\Exception\MissingContractException;
use Omnibridge\Contract\Translations;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

// A user of the contracts: an article's getters, as C3e will write them on omnibase's entities.
final class BareRuntimeArticle
{
    public function __construct(
        public string $slug,
        public string $title,
        public \DateTimeImmutable $publishedAt,
        public ?string $thumbnail = null,
    ) {
    }

    public function __toLink(): string
    {
        return Runtime::urls()->path('article', ['slug' => $this->slug]);
    }

    public function __toUrl(): string
    {
        return Runtime::urls()->url('article', ['slug' => $this->slug]);
    }

    public function isGranted(string $attribute): bool
    {
        return Runtime::access()->isGranted($attribute, $this);
    }

    public function getLabel(?int $comments = null): string
    {
        return Runtime::translations()->trans('article.label', ['title' => $this->title, 'comments' => $comments ?? 0], 'entities', Runtime::locales()->getLocale());
    }

    public function getDaysOld(): int
    {
        return (int) $this->publishedAt->diff(Runtime::clock()->now())->days;
    }

    public function getThumbnailUrl(): ?string
    {
        return null === $this->thumbnail ? null : Runtime::files()->publicUrl($this->thumbnail, 'local.uploads');
    }

    public function publish(): object
    {
        return Runtime::events()->dispatch(new ArrayObject(['published' => $this->slug]));
    }
}

// What a framework binds: each contract by its interface's name.
$catalogue = [
    'fr-FR' => ['article.label' => '« {title} », {comments, plural, =0 {sans commentaire} one {# commentaire} other {# commentaires}}'],
    'en-GB' => ['article.label' => '"{title}", {comments, plural, =0 {no comment} one {# comment} other {# comments}}'],
];
$locale = new class implements Locales {
    public string $current = 'fr-FR';

    public function getLocale(): string
    {
        return $this->current;
    }

    public function getDefaultLocale(): string
    {
        return 'fr-FR';
    }

    public function getAvailableLocales(): array
    {
        return ['fr-FR', 'en-GB'];
    }
};
$listened = new ArrayObject();
$services = [
    Urls::class => new class implements Urls {
        public function path(string $name, array $parameters = []): string
        {
            return '/'.$name.'s/'.rawurlencode((string) ($parameters['slug'] ?? ''));
        }

        public function url(string $name, array $parameters = []): string
        {
            return 'https://lesringostars.test'.$this->path($name, $parameters);
        }

        public function asset(string $path): string
        {
            return '/'.ltrim($path, '/');
        }
    },
    // ICU through PHP's intl alone: no translation component either.
    Translations::class => new class($catalogue, $locale) implements Translations {
        public function __construct(private array $catalogue, private Locales $locales)
        {
        }

        public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
        {
            $locale ??= $this->locales->getLocale();
            $message = $this->catalogue[$locale][$id] ?? $id;

            return (string) (MessageFormatter::formatMessage(str_replace('-', '_', $locale), $message, $parameters) ?: $message);
        }

        // omnibridge/twig's former Translations is omniscient/omnibridge's since A2, which asks exists() too.
        public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
        {
            return isset($this->catalogue[$locale ?? $this->locales->getLocale()][$id]);
        }
    },
    Access::class => new class implements Access {
        public ?object $user = null;

        public function isGranted(mixed $attribute, mixed $subject = null): bool
        {
            return 'VIEW' === $attribute || ('EDIT' === $attribute && 'alice' === ($this->user->name ?? null));
        }

        public function getUser(): ?object
        {
            return $this->user;
        }
    },
    Locales::class => $locale,
    Files::class => new class implements Files {
        public function publicPath(string $path, ?string $storage = null): ?string
        {
            return '/srv/app/public/uploads/'.$path;
        }

        public function publicUrl(string $path, ?string $storage = null): ?string
        {
            return '/uploads/'.$path;
        }

        public function localPath(string $path, ?string $storage = null): ?string
        {
            return '/srv/app/var/storage/uploads/'.$path;
        }

        public function isRemote(?string $storage = null): bool
        {
            return false;
        }
    },
    ClockInterface::class => new class implements ClockInterface {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-10-18 02:00:00', new DateTimeZone('UTC'));
        }
    },
    EventDispatcherInterface::class => new class($listened) implements EventDispatcherInterface {
        public function __construct(private ArrayObject $listened)
        {
        }

        public function dispatch(object $event): object
        {
            $this->listened[] = $event['published'] ?? get_class($event);

            return $event;
        }
    },
];

$answers = [];
try {
    Runtime::urls();
} catch (MissingContractException $e) {
    $answers['empty'] = 'MissingContractException';
}

Runtime::setContainer(new class($services) implements ContainerInterface {
    public function __construct(private array $services)
    {
    }

    public function get(string $id): mixed
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
});

$article = new BareRuntimeArticle('abbey-road', 'Abbey Road', new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('UTC')), 'covers/abbey-road.jpg');
$answers += [
    'link' => $article->__toLink(),
    'url' => $article->__toUrl(),
    'view' => $article->isGranted('VIEW'),
    'edit, nobody' => $article->isGranted('EDIT'),
    'label fr' => $article->getLabel(),
    'label fr, 2' => $article->getLabel(2),
    'days' => $article->getDaysOld(),
    'thumbnail' => $article->getThumbnailUrl(),
    'asset' => Runtime::urls()->asset('images/logo.svg'),
];
$services[Access::class]->user = (object) ['name' => 'alice'];
$answers['edit, alice'] = $article->isGranted('EDIT');
$answers['user'] = Runtime::access()->getUser()?->name;
$locale->current = 'en-GB';
$answers['label en, 1'] = $article->getLabel(1);
$answers['available'] = Runtime::locales()->getAvailableLocales();
$article->publish();
$answers['events'] = $listened->getArrayCopy();
$answers['mailer'] = Runtime::has(Symfony\Component\Mailer\MailerInterface::class);

Runtime::reset();

return $answers;
