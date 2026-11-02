<?php

namespace Tests\Base\Http;

use Base\EntityDispatcher\Event\UserEvent;
use Base\Form\Model\CommentModel;
use Base\Http\RequestsOf;
use Base\Notifier\Channel\BrowserPlusChannel;
use Base\Service\FormGuard;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Base\Service\SpamChecker;
use Base\Service\TranslatorInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Omnibridge\Contract\Requests;
use Omnibridge\Contract\Urls;
use Omnibridge\Event\NamedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\NoRecipient;

/**
 * The request through omnisense/omnibridge's Requests contract (plan "omnibase agnostic", step F5c):
 * what a service read of Symfony's RequestStack it reads of the contract, whichever host answers it.
 */
final class RequestsContractTest extends TestCase
{
    public function testARequestStackIsTheContractOfOmnibridgeSymfony(): void
    {
        self::assertNull(RequestsOf::of(null));
        $requests = self::requests();
        self::assertSame($requests, RequestsOf::of($requests));

        $stack = new RequestStack();
        $stack->push(Request::create('https://site.example/page?x=1', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_REFERER' => 'https://site.example/']));
        $adapted = RequestsOf::of($stack);
        self::assertInstanceOf(Requests::class, $adapted);
        self::assertSame('203.0.113.7', $adapted->ip());
        self::assertSame('https://site.example/', $adapted->header('Referer'));
        self::assertSame('https://site.example', $adapted->host());
        self::assertSame([], $adapted->flashes(), 'no session: nothing waits');

        $this->expectException(\InvalidArgumentException::class);
        RequestsOf::of(new \stdClass());
    }

    public function testTheSpamCheckerTellsWhatTheContractSays(): void
    {
        $settings = $this->createMock(SettingBagInterface::class);
        $parameters = $this->createMock(ParameterBagInterface::class);
        $parameters->method('get')->willReturn('fr');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('fr');
        $translator->method('getFallbackLocales')->willReturn(['fr']);
        $urls = $this->createMock(Urls::class);
        $urls->method('url')->with('/')->willReturn('https://site.example/');

        $checker = new SpamChecker(self::requests(), $settings, $parameters, $translator, new MockHttpClient(), false, new FormGuard('secret', []), $urls);
        $model = new CommentModel();
        $model->name = 'Anne Leroy';
        $model->content = 'Un commentaire.';
        $submission = $checker->submission($model);

        self::assertSame('203.0.113.7', $submission->ip);
        self::assertSame('Firefox', $submission->userAgent);
        self::assertSame('https://site.example/blog', $submission->referrer);
        self::assertSame('https://site.example/blog/un-billet', $submission->permalink);
        self::assertSame('https://site.example/', $checker->getUrl());
    }

    public function testABrowserNotificationIsFlashedOnceThroughTheContract(): void
    {
        $requests = self::requests();
        $channel = new BrowserPlusChannel($requests);
        $notification = (new Notification('Sujet'))->content('Bienvenue.')->importance('success');

        $channel->notify($notification, new NoRecipient());
        $channel->notify($notification, new NoRecipient());

        self::assertSame(['success' => ['Bienvenue.']], $requests->flashes());

        $outside = self::requests(false);
        (new BrowserPlusChannel($outside))->notify($notification, new NoRecipient());
        self::assertSame([], $outside->flashes(), 'outside a request nothing is flashed');
    }

    public function testAnEntityEventCarriesItsName(): void
    {
        $args = new LifecycleEventArgs(new \stdClass(), $this->createMock(ObjectManager::class));
        $event = new UserEvent($args, null);
        self::assertInstanceOf(NamedEvent::class, $event);
        self::assertSame(UserEvent::class, $event->eventName(), 'no name given: its class');
        self::assertSame(UserEvent::REGISTER, $event->setEventName(UserEvent::REGISTER)->eventName());
        self::assertNull($event->getRequest());
    }

    /** A request contract answered from memory, a request being answered or not. */
    private static function requests(bool $answering = true): Requests
    {
        return new class($answering) implements Requests {
            /** @var array<string, list<string>> */
            private array $flashes = [];
            /** @var array<string, mixed> */
            private array $session = [];

            public function __construct(private readonly bool $answering)
            {
            }

            public function has(): bool
            {
                return $this->answering;
            }

            public function ip(): ?string
            {
                return $this->answering ? '203.0.113.7' : null;
            }

            public function header(string $name): ?string
            {
                return $this->answering ? ['user-agent' => 'Firefox', 'referer' => 'https://site.example/blog'][strtolower($name)] ?? null : null;
            }

            public function uri(): ?string
            {
                return $this->answering ? 'https://site.example/blog/un-billet' : null;
            }

            public function host(): ?string
            {
                return $this->answering ? 'https://site.example' : null;
            }

            public function route(): ?string
            {
                return null;
            }

            public function attribute(string $name, mixed $default = null): mixed
            {
                return $default;
            }

            public function session(string $key, mixed $default = null): mixed
            {
                return $this->session[$key] ?? $default;
            }

            public function remember(string $key, mixed $value): void
            {
                $this->session[$key] = $value;
            }

            public function forget(string $key): void
            {
                unset($this->session[$key]);
            }

            public function flash(string $type, string $message): void
            {
                if ($this->answering) {
                    $this->flashes[$type][] = $message;
                }
            }

            public function flashes(): array
            {
                return $this->flashes;
            }

            public function request(): ?object
            {
                return null;
            }
        };
    }
}
