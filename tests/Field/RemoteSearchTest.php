<?php

namespace Tests\Base\Field;

use Base\Enum\UserGender;
use Base\Field\Search\RemoteSearch;
use Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * A select's remote search (plan "omnibase agnostic", step F5d, slice 2): Base\Field\Search\RemoteSearch,
 * which AutocompleteController's routes now delegate to and omnibridge/laravel's SearchController calls the
 * same way. The payload RemoteSearch::data() makes with the visitor's token finds the records whose name
 * holds the term, an enumeration's values, the icons of an adapter; through Symfony's routes, a payload
 * with another token and an unknown payload are refused as before.
 */
final class RemoteSearchTest extends KernelTestCase
{

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        // The visitor's request: its session holds the token the payload carries.
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    private function search(): RemoteSearch
    {
        return static::getContainer()->get(RemoteSearch::class);
    }

    /** select2's method in this environment: GET in debug, POST otherwise. */
    private static function method(): string
    {
        return static::getContainer()->getParameter('kernel.debug') ? 'GET' : 'POST';
    }

    public function testTheRecordsWhoseNameHoldsTheTermAreFound(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $name = 'searched'.bin2hex(random_bytes(4));
        $user = new \App\Entity\User();
        if (PropertyAccess::createPropertyAccessor()->isWritable($user, 'username')) {
            PropertyAccess::createPropertyAccessor()->setValue($user, 'username', $name);
        }
        $user->setEmail($name.'@example.org');
        $user->setPlainPassword('test-'.$name);
        $em->persist($user);
        $em->flush();

        $search = $this->search();
        $answer = $search->records($search->data(\App\Entity\User::class), 'POST', [], ['term' => $name]);

        self::assertSame(200, $answer->status, json_encode($answer->body));
        self::assertArrayHasKey('pagination', $answer->body);
        self::assertContains((string) $user->getId(), array_map(static fn (array $entry): string => (string) $entry['id'], $answer->body['results']));
        $em->remove($user);
        $em->flush();
    }

    public function testAnEnumerationsValuesAreGiven(): void
    {
        $search = $this->search();
        $answer = $search->records($search->data(UserGender::class), 'POST', [], ['term' => '']);

        self::assertSame(200, $answer->status);
        self::assertNotEmpty($answer->body['results']);
        self::assertFalse($answer->body['pagination']);
    }

    public function testAnAdaptersIconsAreGivenPageByPage(): void
    {
        $search = $this->search();
        $answer = $search->icons(FontAwesomeAdapter::getName(), 200, $search->data(null), self::method(), ['term' => 'briefcase'], ['term' => 'briefcase']);

        self::assertSame(200, $answer->status, json_encode($answer->body));
        self::assertStringContainsString('briefcase', json_encode($answer->body['results']));
    }

    public function testAnotherTokenOrAnUnknownPayloadIsRefusedOnTheRoutes(): void
    {
        $obfuscator = static::getContainer()->get(\Base\Service\ObfuscatorInterface::class);
        $forged = $obfuscator->encode(['class' => \App\Entity\User::class, 'fields' => [], 'filters' => [], 'capitalize' => null, 'html' => false, 'avatar' => false, 'token_name' => RemoteSearch::TOKEN, 'token' => 'not-the-token'], true);
        $router = static::getContainer()->get('router');

        $response = $this->handle('POST', $router->generate('ux_autocomplete', ['data' => $forged]));
        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Invalid token', (string) $response->getContent());

        $response = $this->handle('POST', $router->generate('ux_autocomplete', ['data' => 'nothing-known']));
        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Unexpected request', (string) $response->getContent());

        $response = $this->handle(self::method(), $router->generate('ux_autocomplete_icons', ['provider' => FontAwesomeAdapter::getName(), 'pageSize' => 200, 'data' => $forged]));
        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Invalid request', (string) $response->getContent());
    }

    /** A request as select2 sends it, answered by the kernel. */
    private function handle(string $method, string $address): Response
    {
        $request = Request::create('https://localhost'.$address, $method);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return static::$kernel->handle($request);
    }

    public function testTheFiltersAreGuessedAsSelectTypeGuessesThem(): void
    {
        self::assertSame([], RemoteSearch::filters(['class' => 'A']));
        self::assertSame(['A'], RemoteSearch::filters(['class' => 'A', 'choice_filter' => null]));
        self::assertSame(['B'], RemoteSearch::filters(['class' => 'A', 'choice_filter' => ['B']]));
    }
}
