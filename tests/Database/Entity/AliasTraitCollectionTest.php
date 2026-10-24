<?php

namespace Tests\Base\Database\Entity;

use Base\Database\Attribute\Alias;
use Base\Database\Entity\Extension\AliasTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Exception\AccessException;

#[Alias(column: 'children', alias: 'replies')]
class AliasedThread
{
    use AliasTrait;

    /** @var list<object> */
    public array $children = [];
    /** @var list<object> */
    public array $owners = [];

    /** @var list<object>|null */
    #[Alias(column: 'children')]
    protected ?array $galleries = null;
    /** @var list<object>|null */
    #[Alias(column: 'owners')]
    protected ?array $authors = null;

    public function addChild(object $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    public function removeChild(object $child): self
    {
        $this->children = array_values(array_filter($this->children, static fn (object $each): bool => $each !== $child));

        return $this;
    }

    public function addOwner(object $owner): self
    {
        $this->owners[] = $owner;

        return $this;
    }
}

/**
 * add*() and remove*() of an alias's singular (step R29; seen by R28 in
 * lesringostars' AppFixtures, which failed at run time): an alias declared on
 * a property ($galleries of the children: addGallery()) and one declared on
 * the class under a plural ("replies": addReply()) reach their column's
 * add or remove.
 */
class AliasTraitCollectionTest extends TestCase
{
    public function testAPropertyAliasAddsToAndRemovesFromItsColumn(): void
    {
        $thread = new AliasedThread();
        $gallery = new \stdClass();
        $author = new \stdClass();

        $thread->addGallery($gallery);
        $thread->addAuthor($author);
        $this->assertSame([$gallery], $thread->children);
        $this->assertSame([$author], $thread->owners);

        $thread->removeGallery($gallery);
        $this->assertSame([], $thread->children);
    }

    public function testAClassAliasUnderAPluralAddsToItsColumn(): void
    {
        $thread = new AliasedThread();
        $reply = new \stdClass();

        $thread->addReply($reply);
        $this->assertSame([$reply], $thread->children);
    }

    public function testAnUnknownCollectionStillFails(): void
    {
        $this->expectException(AccessException::class);
        // No alias of that name: AliasTrait's __call() refuses it (called as such: PHPStan knows it is no method).
        (new AliasedThread())->__call('addPicture', [new \stdClass()]);
    }
}
