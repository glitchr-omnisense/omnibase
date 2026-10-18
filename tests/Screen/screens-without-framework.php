<?php

/*
 * The screens' view models and their builder where there is no framework
 * (plan "omnibase agnostique", C5): run by docker/bin/bare for
 * tests/BareTest.php with Composer's autoloader alone - no kernel, no
 * container, no bundle - and bare reports every class and file of the
 * framework loaded on the way (none may be). The contracts a screen needs are
 * plain PHP here, as another framework binds them (Locales for the entities'
 * titles, Texts, Pictures, a clock); then omnibase's own Thread, Tag and
 * Comment become a card, a story, a link and comments, and a frame wraps
 * them, as a controller hands them to a view bridge. Returns their props.
 */

use Base\Contract\Locales;
use Base\Contract\Pictures;
use Base\Contract\Runtime;
use Base\Contract\Texts;
use Base\Entity\Thread;
use Base\Entity\Thread\Comment as CommentEntity;
use Base\Enum\CommentState;
use Base\Screen\Builder\Stories;
use Base\Screen\Discussion;
use Base\Screen\Frame;
use Base\Screen\Site;
use Base\Screen\View;
use Base\Screen\Viewer;
use Base\Service\Model\ClassAliases;
use Psr\Clock\ClockInterface;

// The class convention (App\Entity\X is Base\Entity\X where the application has none): the entities' types name App\Entity.
ClassAliases::register();

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
        return ['fr-FR'];
    }
});
Runtime::set(ClockInterface::class, new class implements ClockInterface {
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-05-11 12:00:00', new DateTimeZone('UTC'));
    }
});

$texts = new class implements Texts {
    public function isEditor(?string $text): bool
    {
        return str_starts_with((string) $text, '{');
    }

    public function render(?string $text, array $options = []): string
    {
        return '<h2><a class="markdown-anchor" id="depart" href="#depart">Départ</a></h2><p>'.htmlspecialchars((string) $text).'</p>';
    }

    public function toc(?string $text, ?int $maxLevel = null): array
    {
        return [['tag' => 'h2', 'slug' => 'depart', 'title' => 'Départ']];
    }
};
$pictures = new class implements Pictures {
    public function picture(?string $image, ?int $width = null, ?int $height = null): ?string
    {
        return null === $image || '' === $image ? null : $image.'?w='.$width;
    }
};

$thread = new Thread(null, null, 'La sortie au musée', 'la-sortie-au-musee');
$thread->setExcerpt('Une journée entière.');
$thread->setHeadline('Au musée des beaux-arts');
$thread->setContent('Nous sommes partis tôt.');
$thread->setPublishedAt(new DateTime('2026-05-11 08:00:00', new DateTimeZone('UTC')));
// The application's tag (the alias of Base\Entity\Thread\Tag): what the thread's types name.
$tagClass = 'App\\Entity\\Thread\\Tag';
$thread->addTag(new $tagClass('Sorties', 'sorties'));
$older = new Thread(null, null, 'La rentrée', 'la-rentree');

$stories = new Stories($texts, $pictures);
$url = static fn (string $slug): string => '/chroniques/'.$slug;
$tagUrl = static fn (object $tag): string => '/chroniques?sujet='.$tag->getSlug();

$comment = (new CommentEntity($thread))->setName('Inès')->setWebsite('https://ines.example.org')->setContent('Merci !');
$comment->setState(CommentState::APPROVED);
(new ReflectionProperty(CommentEntity::class, 'id'))->setValue($comment, 7);
(new ReflectionProperty(CommentEntity::class, 'createdAt'))->setValue($comment, new DateTime('2026-04-10 18:05:00', new DateTimeZone('UTC')));

$card = $stories->card($thread, $url('la-sortie-au-musee'), $tagUrl, cover: '/uploads/couverture.jpg', coverWidth: 800, readingMinutes: 3);
$story = $stories->story($thread, $url('la-sortie-au-musee'), $tagUrl, readingMinutes: 3, commentable: true);
$frame = new Frame('fr', new Site('Le blog', age: '2026'), ['home' => '/'], new Viewer(4, 'louis'), ['scheme' => 'light'], ['destination' => ['slug' => 'japon']], 'https://example.org');
$view = new View('@Blog/client/post.html.twig', new Discussion($stories->comments([$comment]), open: true), 'Post');

try {
    new Frame('fr', extra: ['links' => []]);
    $reserved = 'accepted';
} catch (InvalidArgumentException) {
    $reserved = 'refused';
}

$answers = [
    'card' => $card->toArray(),
    'story' => array_intersect_key($story->toArray(), array_flip(['id', 'slug', 'title', 'headline', 'html', 'editor', 'toc', 'state', 'commentable', 'likes', 'comments', 'recent'])),
    'link' => $stories->link($older, $url('la-rentree'))?->toArray(),
    'props' => array_keys($frame->toArray() + $view->screen->toArray()),
    'comment' => $view->screen->toArray()['comments'][0],
    'reserved' => $reserved,
    'json' => json_encode($frame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
];

Runtime::reset();

return $answers;
