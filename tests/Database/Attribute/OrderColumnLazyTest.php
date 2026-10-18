<?php

namespace Tests\Base\Database\Attribute;

use Base\Entity\Thread;
use Base\Entity\Thread\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * An #[OrderColumn] association is loaded when it is read, not when its
 * entity is (OrderColumn::postLoad() loaded every ordered association of
 * every entity loaded), and it still comes in the order saved: the
 * drag-and-drop case - reorder, flush, load again, read.
 *
 * Driven on omnibase's Thread and its taxa (#[OrderColumn(orderBy:
 * "taxonPositions")]), in the host application's kernel and database (the
 * harness: a fresh SQLite one).
 */
class OrderColumnLazyTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private array $created = [];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->clear();
            foreach (array_reverse($this->created) as [$class, $id]) {
                if ($entity = $this->em->find($class, $id)) {
                    $this->em->remove($entity);
                }
            }
            $this->em->flush();
        }
        parent::tearDown();
    }

    private function taxon(string $label): Taxon
    {
        $taxon = new Taxon($label.' '.bin2hex(random_bytes(3)));
        $this->em->persist($taxon);

        return $taxon;
    }

    /** @return array{int, int[]} the thread's id and its taxa's ids, a, b, c */
    private function threadWithThreeTaxa(): array
    {
        [$a, $b, $c] = [$this->taxon('A'), $this->taxon('B'), $this->taxon('C')];
        $this->em->flush();

        $thread = new Thread(null, null, 'Ordered '.bin2hex(random_bytes(3)));
        foreach ([$a, $b, $c] as $taxon) {
            $thread->addTaxon($taxon);
        }
        $this->em->persist($thread);
        $this->em->flush();

        // Removed in the reverse order: the thread, then its taxa.
        foreach ([$a, $b, $c] as $taxon) {
            $this->created[] = [Taxon::class, $taxon->getId()];
        }
        $this->created[] = [Thread::class, $thread->getId()];

        return [$thread->getId(), [$a->getId(), $b->getId(), $c->getId()]];
    }

    private function load(int $id): Thread
    {
        $this->em->clear();

        return $this->em->find(Thread::class, $id);
    }

    private function ids(iterable $taxa): array
    {
        $ids = [];
        foreach ($taxa as $taxon) {
            $ids[] = $taxon->getId();
        }

        return $ids;
    }

    public function testTheTaxaAreLoadedWhenRead(): void
    {
        [$id, $taxa] = $this->threadWithThreeTaxa();

        $thread = $this->load($id);
        $collection = $thread->getTaxa();
        $this->assertInstanceOf(PersistentCollection::class, $collection);
        $this->assertFalse($collection->isInitialized(), 'loading the thread loads none of its ordered associations');

        $this->assertSame($taxa, $this->ids($collection));
        $this->assertTrue($collection->isInitialized());
    }

    public function testAReorderSavedIsTheOrderReadAfterTheNextLoad(): void
    {
        [$id, [$a, $b, $c]] = $this->threadWithThreeTaxa();

        // As the back office's sortable field does: the same entries, another order.
        $thread = $this->load($id);
        $byId = [];
        foreach ($thread->getTaxa() as $taxon) {
            $byId[$taxon->getId()] = $taxon;
        }
        $thread->getTaxa()->clear();
        foreach ([$c, $a, $b] as $taxonId) {
            $thread->addTaxon($byId[$taxonId]);
        }
        $this->em->flush();

        $thread = $this->load($id);
        $this->assertFalse($thread->getTaxa()->isInitialized());
        $this->assertSame([$c, $a, $b], $this->ids($thread->getTaxa()), 'iterated');

        $thread = $this->load($id);
        $this->assertSame($c, $thread->getTaxa()->first()->getId(), 'first() on a collection not loaded yet');
        $this->assertSame([$c, $a, $b], $this->ids($thread->getTaxa()->toArray()));

        $thread = $this->load($id);
        $this->assertSame($a, $thread->getTaxa()->get(1)->getId(), 'get() by position');
        $this->assertSame([$c, $a, $b], array_map(fn ($t) => $t->getId(), $thread->getTaxa()->getValues()));

        // Saved again without the taxa read: the positions are written as they were.
        $thread = $this->load($id);
        $thread->setTitle('Renamed '.bin2hex(random_bytes(3)));
        $this->em->flush();
        $this->assertSame([$c, $a, $b], $this->ids($this->load($id)->getTaxa()));
    }

    public function testAnEntryAddedBeforeTheFirstReadComesLast(): void
    {
        [$id, [$a, $b, $c]] = $this->threadWithThreeTaxa();

        $thread = $this->load($id);
        $byId = [];
        foreach ($thread->getTaxa() as $taxon) {
            $byId[$taxon->getId()] = $taxon;
        }
        $thread->getTaxa()->clear();
        foreach ([$b, $c, $a] as $taxonId) {
            $thread->addTaxon($byId[$taxonId]);
        }
        $this->em->flush();

        $d = $this->taxon('D');
        $this->em->flush();
        $this->created[] = [Taxon::class, $d->getId()];
        $dId = $d->getId();

        $thread = $this->load($id);
        $d = $this->em->find(Taxon::class, $dId);
        $thread->getTaxa()->add($d); // before anything read it: Doctrine keeps it while it loads the rest
        $this->assertFalse($thread->getTaxa()->isInitialized());

        $this->assertSame([$b, $c, $a, $dId], $this->ids($thread->getTaxa()));
        $this->em->flush();

        $this->assertSame([$b, $c, $a, $dId], $this->ids($this->load($id)->getTaxa()));
    }
}
