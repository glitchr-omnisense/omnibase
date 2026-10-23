<?php

namespace Tests\Base\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The members' search (user_search): its template read `threads` and
 * `form_data.title` - a copy of the threads' search - while its controller
 * gave `users`, and its form had no field, so the page failed to render. Now:
 * the page renders its input; a term finds the members whose username holds
 * it (case aside), links to their profiles, and never looks into nor shows an
 * e-mail address; nothing found says so.
 */
final class UserSearchTest extends KernelTestCase
{
    /** @var array<string, string> */
    private array $cookies = [];

    /** @var list<object> */
    private array $created = [];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        if (!static::getContainer()->get('doctrine')->getManager()->getClassMetadata('App\\Entity\\User')->hasField('username')) {
            self::markTestSkipped('The application\'s user has no username: the search finds nobody by design.');
        }
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        foreach ($this->created as $user) {
            $em->remove($em->find(get_class($user), $user->getId()) ?? $user);
        }
        $em->flush();
        parent::tearDown();
    }

    private function member(string $username): object
    {
        $class = 'App\\Entity\\User';
        $user = new $class();
        PropertyAccess::createPropertyAccessor()->setValue($user, 'username', $username);
        $user->setEmail($username.'.secret-c4b@example.org');
        $user->setPlainPassword('c4b-'.bin2hex(random_bytes(6)));
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        return $this->created[] = $user;
    }

    private function browse(string $method, array $parameters = []): Response
    {
        $response = static::$kernel->handle(Request::create('/search/user', $method, $parameters, $this->cookies));
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }

    private function search(string $term): string
    {
        $page = $this->browse('GET');
        self::assertSame(200, $page->getStatusCode(), substr((string) $page->getContent(), 0, 2000));
        self::assertMatchesRegularExpression('/<input[^>]+name="([a-z_]+)\[username\]"/', (string) $page->getContent());
        preg_match('/name="([a-z_]+)\[username\]"/', (string) $page->getContent(), $name);
        $fields = ['username' => $term];
        if (preg_match('/name="'.preg_quote($name[1], '/').'\[(_csrf_token|_token)\]"[^>]*value="([^"]*)"/', (string) $page->getContent(), $token)) {
            $fields[$token[1]] = $token[2];
        }
        $response = $this->browse('POST', [$name[1] => $fields]);
        self::assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 2000));

        return (string) $response->getContent();
    }

    public function testThePageRendersAndFindsMembersByTheirUsername(): void
    {
        $ringo = $this->member('ringoC4b');
        $paul = $this->member('paulC4b');

        $found = $this->search('RINGOc4');
        self::assertStringContainsString('ringoC4b', $found);
        self::assertStringContainsString('/profile/'.$ringo->getId(), $found);
        self::assertStringNotContainsString('paulC4b', $found);
        self::assertStringNotContainsString('secret-c4b', $found, 'no e-mail address shown');

        $byMail = $this->search('secret-c4b');
        self::assertStringNotContainsString('ringoC4b', $byMail, 'never looked up by e-mail');
        self::assertStringNotContainsString('/profile/'.$paul->getId(), $byMail);
        self::assertStringNotContainsString('user-search-results', $byMail, 'nothing found');
        self::assertStringContainsString('user-search-results', $found);
    }
}
