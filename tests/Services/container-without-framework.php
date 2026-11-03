<?php

/*
 * The core's services where there is no framework (plan "omnibase agnostic",
 * step F5a; Marco's answer 3: the services stay available everywhere): run by
 * docker/bin/bare --strict for tests/BareTest.php, with Composer's autoloader
 * alone - no kernel, no container of Symfony's nor Laravel's, nothing of
 * Doctrine nor Twig - and bare reports every class and file of them loaded on
 * the way (none may be).
 *
 * omnisense/omnibridge's Omnibridge\Container is built from Base\Services::definitions(),
 * the list Symfony's container and Laravel's read, with what a host gives:
 * its parameters, its contracts in plain PHP (Views over closures, Urls,
 * Environment, the media router, the storages), Imagine and symfony/translation's
 * catalogues. Then the services are asked of it and used: the obfuscator with
 * its compressions, a thumbnail's address, a page's links, a word translated,
 * a thread's screens' builder, an e-mail composed through the views; and (step F5c,
 * item 3) the facade BaseService on the contracts, the entity hydrator on a unit of
 * work of plain PHP and the entities' shapes from a Description.
 */

use Base\Contract\MediaRouter;
use Base\Notifier\Mail\EmailComposer;
use Base\Screen\Builder\Stories;
use Base\Service\ArrayParameterBag;
use Base\Service\Flysystem;
use Base\Service\ImageRoute;
use Base\Service\MediaServiceInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\PaginatorInterface;
use Base\Service\Storages;
use Base\Service\TranslatorInterface;
use Base\Services;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Omnibridge\Container;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Urls;
use Omnibridge\Contract\Views;
use Psr\Container\ContainerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator as Catalogues;

/** The views of a host whose engine is PHP itself: each view a closure of its context. */
final class ClosureViews implements Views
{
    /** @param array<string, Closure(array<string, mixed>): string> $views */
    public function __construct(private readonly array $views)
    {
    }

    public function render(string $name, array $context = []): string
    {
        return ($this->views[$name] ?? throw new InvalidArgumentException('No view '.$name))($context);
    }

    public function exists(string $name): bool
    {
        return isset($this->views[$name]);
    }
}

final class PlainUrls implements Urls
{
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
        return '/'.ltrim($path, '/');
    }
}

final class PlainEnvironment implements Environment
{
    public function name(): string
    {
        return 'prod';
    }

    public function isDebug(): bool
    {
        return false;
    }

    public function secret(): string
    {
        return 'not-so-secret';
    }
}

final class PlainMediaRouter implements MediaRouter
{
    public function generate(string $name, array $parameters = [], int $referenceType = 1): string
    {
        [$path] = ImageRoute::ROUTES[$name] ?? throw new InvalidArgumentException($name);
        foreach ($parameters as $key => $value) {
            if (str_contains($path, '{'.$key.'}')) {
                $path = str_replace('{'.$key.'}', (string) $value, $path);
                unset($parameters[$key]);
            }
        }

        return $path.($parameters ? '?'.http_build_query($parameters) : '');
    }

    public function getRouteMatch(?string $routeUrl = null): ?array
    {
        return null;
    }

    public function getRouteName(?string $routeUrl = null): ?string
    {
        return null;
    }

    public function getAssetUrl(string $nameOrUrl, ?string $packageName = null): string
    {
        return '/'.ltrim($nameOrUrl, '/');
    }

    public function getUrl(?string $nameOrUrl = null, array $parameters = [], int $referenceType = 1): string
    {
        return (string) $nameOrUrl;
    }
}

/** An entity of plain PHP, described below. */
final class Poem
{
    public ?int $id = null;
    public ?string $title = null;
    public ?array $lines = null;
}

/** A unit of work of plain PHP that remembers what it was given as read. */
final class PlainUnitOfWork implements Omnibridge\Persistence\UnitOfWork, Omnibridge\Persistence\Snapshots
{
    /** @var array<int, array<string, mixed>> */
    public array $snapshots = [];

    public function persist(object $entity): void {}
    public function remove(object $entity): void {}
    public function flush(): void {}
    public function refresh(object $entity): void {}
    public function contains(object $entity): bool { return isset($this->snapshots[spl_object_id($entity)]); }
    public function reference(string $class, mixed $id): object { throw new LogicException('No references here.'); }
    public function transactional(callable $work): mixed { return $work($this); }
    public function isNew(object $entity): bool { return !$this->contains($entity); }
    public function identifier(object $entity): mixed { return $entity->id ?? null; }
    public function className(object $entity): string { return $entity::class; }
    public function changes(object $entity): array { return []; }
    public function recompute(object $entity): void {}
    public function original(object $entity): array { return $this->snapshots[spl_object_id($entity)] ?? []; }
}

$root = sys_get_temp_dir().'/omnibase-bare-services-'.getmypid();
@mkdir($root.'/source', 0777, true);
$source = $root.'/source/photo.png';
$picture = new Imagick();
$picture->newImage(400, 200, 'steelblue', 'png');
$picture->writeImage($source);

$operators = new class($root) implements ContainerInterface {
    private array $operators;

    public function __construct(string $root)
    {
        $this->operators = [
            'local.cache' => new Filesystem(new LocalFilesystemAdapter($root.'/cache')),
            'local.uploads' => new Filesystem(new LocalFilesystemAdapter($root.'/uploads')),
        ];
    }

    public function get(string $id): mixed
    {
        return $this->operators[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->operators[$id]);
    }
};

$values = [
    'kernel.secret' => 'not-so-secret',
    'base.images.timeout' => 60,
    'base.images.fallback' => false,
    'base.images.max_resolution' => Base\Imagine\Filter\Basic\Definition\UltraHighDefinitionFilter::class,
    'base.images.max_quality' => 0.8,
    'base.images.enable_webp' => true,
    'base.images.no_image' => [],
    'base.images.debug' => false,
    'base.uploader.storage' => 'local.uploads',
    'base.twig.editor.storage' => null,
    'base.obfuscator.uuid' => '6ba7b811-9dad-11d1-80b4-00c04fd430c8',
    'base.obfuscator.compression' => 'null',
    'base.obfuscator.max_length' => 0,
    'base.obfuscator.level' => -1,
    'base.obfuscator.encoding' => null,
    'base.obfuscator.cache_dir' => $root.'/obfuscator',
    'base.translator.politeness' => null,
    'base.paginator.page_size' => 10,
    'base.paginator.page_range' => 5,
    'base.paginator.page_parameter' => 'page',
    'base.paginator.default_template' => '@Base/paginator/default.html.twig',
    'base.notifier.technical_recipient.email' => 'Les Ringo Stars <postmaster@lesringostars.test>',
];

$catalogues = new Catalogues('fr');
$catalogues->addLoader('array', new ArrayLoader());
$catalogues->addResource('array', ['hello' => 'Bonjour %name%'], 'fr', 'messages');

$views = new ClosureViews([
    '@App/mail/welcome.html.twig' => static fn (array $c): string => '<title>Welcome '.$c['name'].'</title><p>'.$c['content'].'</p>',
    EmailComposer::BODY => static fn (array $c): string => '<body>'.$c['html'].'</body>',
    EmailComposer::FRAME => static fn (array $c): string => '<p>'.$c['content'].'</p>',
]);

$container = Container::fromDefinitions(Services::definitions(), $values, [
    ParameterBagInterface::class => new ArrayParameterBag($values),
    Views::class => $views,
    Urls::class => new PlainUrls(),
    Environment::class => new PlainEnvironment(),
    MediaRouter::class => new PlainMediaRouter(),
    Storages::class => new Flysystem($operators),
    'imagine.bitmap' => new Imagine\Imagick\Imagine(),
    'imagine.svg' => new Base\Imagine\Svg\Imagine(),
    Catalogues::class => $catalogues,
    Omnibridge\Persistence\UnitOfWork::class => $unitOfWork = new PlainUnitOfWork(),
    Base\Database\Mapping\EntityShapes::class => new Base\Database\Mapping\DescriptionShapes(new Omnibridge\Schema\Description([
        Poem::class => new Omnibridge\Schema\Entity(Poem::class, 'poem', identifier: ['id'], fields: [
            'id' => new Omnibridge\Schema\Field('id', 'id', 'integer', id: true),
            'title' => new Omnibridge\Schema\Field('title', 'title', 'string'),
            'lines' => new Omnibridge\Schema\Field('lines', 'lines', 'json', nullable: true),
        ]),
    ])),
]);

$obfuscator = $container->get(ObfuscatorInterface::class);
$media = $container->get(MediaServiceInterface::class);
$pagination = $container->get(PaginatorInterface::class)->paginate(range(1, 25), 2, 10);
$translator = $container->get(TranslatorInterface::class);
$email = $container->get(EmailComposer::class)->compose('Anne <anne@example.org>', '@App/mail/welcome.html.twig', ['name' => 'Anne'], 'Hello Anne.');

$base = $container->get('base.service');
$hydrator = $container->get(Base\Database\Entity\EntityHydratorInterface::class);
$poem = $hydrator->hydrate(Poem::class, ['id' => 3, 'title' => 'Le Pont Mirabeau']);
$unitOfWork->snapshots[spl_object_id($poem)] = ['title' => 'Alcools'];
$poem->title = 'Le Pont Mirabeau, changed';

$result = [
    'obfuscator' => [$obfuscator::class, count($obfuscator->getCompressions()), $obfuscator === $container->get('obfuscator')],
    'media' => [$media::class, (bool) preg_match('#^/images/([0-9a-f]{2}/){5}[0-9a-f]{22}/image\.png$#', $media->thumbnail($source, 100, 100, ['webp' => false]))],
    'paginator' => [count($pagination->getResult()), $pagination->getPath('home', 3)],
    'translator' => [$translator::class, $translator->trans('hello', ['%name%' => 'Anne'], 'messages', 'fr')],
    'stories' => $container->get(Stories::class)::class,
    'email' => [$email->getSubject(), (string) $email->getHtmlBody(), $email->getTextBody(), $email->getFrom()[0]->toString()],
    'base service' => [$base::class, $base === $container->get(Base\Service\BaseService::class), Base\Service\BaseService::getEnvironment(), $base->isDebug(), $base->getParameter('base.paginator.page_size')],
    'entity hydrator' => [$hydrator::class, $poem->title, $poem->lines, $hydrator->getOriginalEntity($poem)->title, $hydrator->getOriginalEntity($poem)->id],
];

exec('rm -rf '.escapeshellarg($root));

return $result;
