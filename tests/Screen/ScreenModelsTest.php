<?php

namespace Tests\Base\Screen;

use Base\Screen\Author;
use Base\Screen\Card;
use Base\Screen\Comment;
use Base\Screen\Discussion;
use Base\Screen\Frame;
use Base\Screen\Link;
use Base\Screen\Reply;
use Base\Screen\Shape;
use Base\Screen\Site;
use Base\Screen\Story;
use Base\Screen\StoryText;
use Base\Screen\Tag;
use Base\Screen\Viewer;
use PHPUnit\Framework\TestCase;

/**
 * The view models' props (plan "omnibase agnostique", C5) keep lesringostars'
 * names and order (its App\Screen\Frame, BlogPage, StoryPage, StoryText, which
 * Symfony, Laravel and React give alike), the additions agreed with the pilot
 * after them: a card's counts null where none is counted, `recent` computed
 * rather than compared in a template, a frame's own fields that a site's extra
 * may not override, a comment named by an id or a uuid.
 */
final class ScreenModelsTest extends TestCase
{
    public function testTheFrameHasLesringostarsFieldsThenTheViewerAndTheTheme(): void
    {
        $frame = new Frame('fr', new Site('Les Ringo Stars', 'Le Japon en famille', '/logo.png', '2026', 'Un blog'), ['home' => '/', 'locales' => ['fr' => '/fr']], new Viewer(3, 'louis', null), ['scheme' => 'dark'], [
            'destination' => ['id' => 1, 'slug' => 'japon'], 'destinations' => [], 'lastArticle' => null, 'tags' => [new Tag('sorties', 'Sorties', '/t', count: 4)],
        ], 'https://lesringostars.test');

        self::assertSame(['locale', 'site', 'destination', 'destinations', 'lastArticle', 'tags', 'links', 'viewer', 'theme'], array_keys($frame->toArray()));
        self::assertSame(['title', 'slogan', 'logo', 'age', 'description'], array_keys($frame->toArray()['site']));
        self::assertSame(['slug' => 'sorties', 'label' => 'Sorties', 'color' => null, 'icon' => null, 'url' => '/t', 'count' => 4], $frame->toArray()['tags'][0], 'a screen inside the extra gives its props');
        self::assertSame(['id' => 3, 'name' => 'louis', 'avatar' => null], $frame->toArray()['viewer']);
        self::assertArrayNotHasKey('host', $frame->toArray(), 'the host serves the absolute addresses, it is not a prop');
        self::assertSame(json_encode($frame->toArray()), json_encode($frame));
        self::assertNull((new Frame('en'))->toArray()['viewer'], 'nobody signed in');
        self::assertSame(['x' => 1, 'destination' => ['id' => 1, 'slug' => 'japon']], array_intersect_key($frame->with(['x' => 1])->toArray(), ['x' => 0, 'destination' => 0]));
    }

    public function testASitesExtraMayNotOverrideTheFramesOwnFields(): void
    {
        foreach (Frame::RESERVED as $key) {
            try {
                new Frame('fr', extra: [$key => 'x']);
                self::fail($key.' accepted');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($key, $e->getMessage());
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        (new Frame('fr'))->with(['viewer' => null]);
    }

    public function testACardHasLesringostarsFieldsAndItsCountsNullWhereNoneIsCounted(): void
    {
        $card = new Card('le-fuji', 'Le Fuji', '/japon/articles/le-fuji');
        self::assertSame(['slug', 'title', 'excerpt', 'hasContent', 'publishedAt', 'readingMinutes', 'authors', 'tags', 'likes', 'likeUrl', 'comments', 'url'], \array_slice(array_keys($card->toArray()), 0, 12));
        self::assertSame(['headline', 'cover', 'taxa', 'recent'], \array_slice(array_keys($card->toArray()), 12));
        self::assertSame([null, null, null], [$card->toArray()['likes'], $card->toArray()['likeUrl'], $card->toArray()['comments']]);
        $counted = new Card('le-fuji', 'Le Fuji', '/x', likes: 0, comments: 0);
        self::assertSame([0, 0], [$counted->toArray()['likes'], $counted->toArray()['comments']], 'zero counted stays zero');
    }

    public function testAStoryHasLesringostarsArticleFields(): void
    {
        $story = new Story(12, 'le-fuji', 'Le Fuji', '/x', 'STATE_PUBLISH', text: new StoryText('<p>a</p>', true, [['tag' => 'h2', 'slug' => 'a', 'title' => 'A']]), authors: [new Author('Marco', '/u/1', '/a.png')], commentable: true, comments: 3);
        self::assertSame(['id', 'slug', 'title', 'headline', 'excerpt', 'html', 'editor', 'toc', 'publishedAt', 'readingMinutes', 'authors', 'tags', 'audio', 'likes', 'likeUrl', 'commentable', 'comments', 'state'], \array_slice(array_keys($story->toArray()), 0, 18));
        self::assertSame(['<p>a</p>', true], [$story->toArray()['html'], $story->toArray()['editor']]);
        self::assertSame('<p>a</p>', $story->getHtml(), 'Twig reads story.html');
        self::assertTrue($story->isEditor());
        self::assertSame(['name' => 'Marco', 'profile' => '/u/1', 'avatar' => '/a.png'], $story->toArray()['authors'][0], 'no staff key where the screen marks none');
        self::assertSame(['html' => '', 'editor' => false, 'toc' => []], (new StoryText())->toArray());
        self::assertSame(['title' => 'A', 'url' => '/a', 'state' => 'STATE_PUBLISH'], (new Link('A', '/a', 'STATE_PUBLISH'))->toArray());
    }

    public function testCommentsHaveLesringostarsFieldsAndAnIdOrAUuid(): void
    {
        $reply = new Reply(null, 'b2c', '2026-04-10T20:15:00+00:00', [new Author('Louis')], 'Moi aussi', false);
        $comment = new Comment(7, null, null, '2026-04-10T18:05:00+00:00', [new Author('Inès', 'https://ines.example.org', staff: false)], 'Merci', null, [$reply]);
        self::assertSame(['uuid', 'slug', 'publishedAt', 'authors', 'content', 'followUrl', 'replies'], \array_slice(array_keys($comment->toArray()), 0, 7));
        self::assertSame(['uuid', 'publishedAt', 'authors', 'content', 'deleted'], \array_slice(array_keys($reply->toArray()), 0, 5));
        self::assertSame(['id' => 7, 'recent' => false], array_intersect_key($comment->toArray(), ['id' => 0, 'recent' => 0]));
        self::assertSame('b2c', $comment->toArray()['replies'][0]['uuid']);
        self::assertFalse($comment->toArray()['authors'][0]['staff']);

        foreach ([fn () => new Comment(null, null), fn () => new Comment(null, ''), fn () => new Reply(null, null)] as $anonymous) {
            try {
                $anonymous();
                self::fail('a comment without an id nor a uuid');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testADiscussionAndATagSayWhatTheyHave(): void
    {
        self::assertSame(['comments', 'hasMore', 'open', 'canReply', 'signIn', 'flashes'], array_keys((new Discussion())->toArray()));
        self::assertSame(['slug', 'label', 'color', 'icon', 'url'], array_keys((new Tag('a', 'A', '/a'))->toArray()), 'a tag of a story: lesringostars\' five fields');
        self::assertSame(['slug', 'label', 'color', 'icon', 'url', 'current'], array_keys((new Tag('a', 'A', '/a', current: false))->toArray()));
    }

    public function testAMomentIsIsoInUtc(): void
    {
        self::assertNull(Shape::moment(null));
        self::assertSame('2026-05-11T08:00:00+00:00', Shape::moment('2026-05-11 08:00:00'));
        self::assertSame('2026-05-11T08:00:00+00:00', Shape::moment(new \DateTimeImmutable('2026-05-11 17:00:00', new \DateTimeZone('Asia/Tokyo'))));
    }

    /** A link dates itself only where a page shows the date (a member's latest threads): the blog's links keep their three fields. */
    public function testALinkIsDatedOnlyWhereAPageShowsTheDate(): void
    {
        self::assertSame(['title', 'url', 'state'], array_keys((new Link('A', '/a', 'STATE_PUBLISH'))->toArray()));
        self::assertSame(['title' => 'A', 'url' => '/a', 'state' => 'STATE_PUBLISH', 'publishedAt' => '2026-04-08T18:00:00+00:00'], (new Link('A', '/a', 'STATE_PUBLISH', '2026-04-08T18:00:00+00:00'))->toArray());
    }

    /**
     * The account's screens (C5, its second part): the props of each page by
     * their names; nothing counted is null where a page has no count (a
     * profile's passkeys where passkeys are off); a page carrying tokens is
     * confidential; a notification's group is one of four days.
     */
    public function testTheAccountsScreensSayWhatTheyHave(): void
    {
        $member = new \Base\Screen\Account\Member(1, 'anne');
        self::assertSame(['id', 'name', 'avatar', 'avatarHtml', 'roles', 'online', 'active', 'registeredAt', 'lastSeenAt', 'threads', 'likes'], array_keys($member->toArray()));
        self::assertSame(['member', 'own', 'recent', 'account', 'editUrl', 'settingsUrl', 'logoutUrl'], array_keys((new \Base\Screen\Account\ProfilePage($member))->toArray()));
        self::assertSame(['twoFactor' => null, 'passkeys' => null, 'identityProvider' => null, 'locale' => null, 'timezone' => null], (new \Base\Screen\Account\AccountSummary())->toArray(), 'not available: null, never 0 nor false');
        $settings = new \Base\Screen\Account\SettingsPage();
        self::assertSame(['name', 'demo', 'twoFactor', 'passkeys', 'identifier', 'providers', 'sessions', 'language', 'timezone'], array_keys($settings->toArray()));
        self::assertSame([], array_intersect(array_keys($settings->toArray()), Frame::RESERVED), 'no key of the frame\'s');
        self::assertInstanceOf(\Base\Screen\Confidential::class, $settings);
        $notifications = new \Base\Screen\Account\NotificationsPage([], 0, false, '/n', 't', '/r', '/l', '/d', '/s', '/u');
        self::assertInstanceOf(\Base\Screen\Confidential::class, $notifications);
        self::assertSame([], array_intersect(array_keys($notifications->toArray()), Frame::RESERVED));
        self::assertNotInstanceOf(\Base\Screen\Confidential::class, new \Base\Screen\Account\ProfilePage($member));
        self::assertSame(['url' => '/x', 'token' => 'y'], (new \Base\Screen\Account\Action('/x', 'y'))->toArray());
        $this->expectException(\InvalidArgumentException::class);
        new \Base\Screen\Account\Notification(1, group: 'tomorrow');
    }
}
