<?php

namespace Tests\Base\Thread;

use Base\Database\Attribute\Hierarchify;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Thread\ThreadSearch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\Http\HttpTestTrait;
use Tests\Base\ReleasesWhatItHolds;

/**
 * The threads' search page (ThreadSearch::TEMPLATE, block main, as a site's layout prints it - the harness's layout
 * prints no block main, so its /search answered 200 where a site's answered 500):
 * - nothing searched yet, the form has no data: the page read each field's value from the form's (null) data,
 *   "Impossible to access an attribute ("generic") on a null variable" wherever Twig's variables are strict;
 * - a thread found whose class has no hierarchy (Base\Entity\Thread's #[Hierarchify(null)], a class without the
 *   attribute): its repository threw "Missing @Hierarchify" for the section the page groups it under; it has none;
 * - a hierarchy of several levels (#[Hierarchify(["store", "products"])]) was dropped to none.
 */
class ThreadSearchPageTest extends KernelTestCase
{
    use ReleasesWhatItHolds;
    use HttpTestTrait;

    /** @var list<Thread> */
    private array $threads = [];

    protected function setUp(): void
    {
        $this->bootHost();
        $request = Request::create('/search');
        $request->setLocale('en');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        foreach ($this->threads as $thread) {
            // Removed without the soft deletion (a trashed thread is a row still): its translations, then itself.
            $em->createQuery('DELETE FROM '.$thread::getTranslationEntityClass().' i WHERE i.translatable = :thread')->setParameter('thread', $thread->getId())->execute();
            $em->createQuery('DELETE FROM '.Thread::class.' t WHERE t.id = :thread')->setParameter('thread', $thread->getId())->execute();
        }
        $this->removeUsers();
        parent::tearDown();
        $this->releaseWhatItHolds();
    }

    public function testThePageWithNothingSearched(): void
    {
        $form = static::getContainer()->get('form.factory')->create('Omnibridge\Symfony\Omnibase\Form\Type\ThreadSearchType');
        self::assertNull($form->getData());

        $html = $this->page($form, null, []);
        self::assertStringContainsString('name="'.$form->getName().'[generic]"', $html);
        self::assertStringNotContainsString('name="'.$form->getName().'[generic]" value=', $html, 'no value');
    }

    public function testAThreadFoundWhoseClassHasNoHierarchy(): void
    {
        $title = 'Searched '.bin2hex(random_bytes(4));
        $thread = new Thread($this->createUser(), null, $title);
        $thread->setState(ThreadState::PUBLISH);
        $em = $this->entityManager();
        $em->persist($thread);
        $em->flush();
        $this->threads[] = $thread;

        $form = static::getContainer()->get('form.factory')->create('Omnibridge\Symfony\Omnibase\Form\Type\ThreadSearchType');
        $form->submit(['generic' => strtolower($title)]);
        [$model, $threads] = static::getContainer()->get(ThreadSearch::class)->find($form->getData());
        self::assertSame([$thread->getId()], array_map(static fn ($found) => $found->getId(), $threads));

        $html = $this->page($form, $model, $threads);
        self::assertStringContainsString($title, $html);
        self::assertStringContainsString('value="'.strtolower($title).'"', $html, 'the term searched, in its field');
    }

    public function testAHierarchyIsItsLevels(): void
    {
        self::assertSame(['store', 'products'], (new Hierarchify(['store', 'products']))->hierarchy);
        self::assertSame(['articles'], (new Hierarchify('articles'))->hierarchy);
        self::assertSame([], (new Hierarchify(null))->hierarchy);

        $repository = $this->entityManager()->getRepository(Thread::class);
        self::assertSame([], $repository->getHierarchyTree());
        self::assertSame(0, $repository->getHierarchyDepth());
        self::assertNull($repository->getHierarchy());
    }

    /** @param list<object> $threads */
    private function page(object $form, ?object $model, array $threads): string
    {
        return static::getContainer()->get('twig')->load(ThreadSearch::TEMPLATE)
            ->renderBlock('main', ['form' => $form->createView(), 'form_data' => $form->getData(), 'model' => $model, 'threads' => $threads]);
    }
}
