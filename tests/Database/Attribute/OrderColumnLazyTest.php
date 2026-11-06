<?php

namespace Tests\Base\Database\Attribute;

use Base\Entity\Thread;
use Base\Entity\Thread\Taxon;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Database\Backend\BothBackends;

/**
 * An #[OrderColumn] association is loaded when it is read, not when its
 * entity is (OrderColumn::postLoad() loaded every ordered association of
 * every entity loaded), and it still comes in the order saved: the
 * drag-and-drop case - reorder, flush, load again, read.
 *
 * Driven on omnibase's Thread and its taxa (#[OrderColumn(orderBy:
 * "taxonPositions")]), in the host application's kernel and database (the
 * harness: a fresh SQLite one), on both persistences (BothBackends: Doctrine's PersistentCollection, omnibridge/
 * eloquent's LazyCollection - plan "omnibase agnostic", step F7c).
 */
class OrderColumnLazyTest extends KernelTestCase
{
    use BothBackends;

    private string $backend = 'doctrine';
    private array $created = [];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    protected function tearDown(): void
    {
        if ([] !== $this->created) {
            $this->clear($this->backend);
            $unitOfWork = $this->unitOfWork($this->backend);
            foreach (array_reverse($this->created) as [$class, $id]) {
                if ($entity = $this->find($this->backend, $class, $id)) {
                    $unitOfWork->remove($entity);
                }
            }
            $unitOfWork->flush();
            $this->created = [];
        }
        parent::tearDown();
    }

    private function taxon(string $label): Taxon
    {
        $taxon = new Taxon($label.' '.bin2hex(random_bytes(3)));
        $this->unitOfWork($this->backend)->persist($taxon);

        return $taxon;
    }

    /** @return array{int, int[]} the thread's id and its taxa's ids, a, b, c */
    private function threadWithThreeTaxa(): array
    {
        [$a, $b, $c] = [$this->taxon('A'), $this->taxon('B'), $this->taxon('C')];
        $this->unitOfWork($this->backend)->flush();

        $thread = new Thread(null, null, 'Ordered '.bin2hex(random_bytes(3)));
        foreach ([$a, $b, $c] as $taxon) {
            $thread->addTaxon($taxon);
        }
        $this->unitOfWork($this->backend)->persist($thread);
        $this->unitOfWork($this->backend)->flush();

        // Removed in the reverse order: the thread, then its taxa.
        foreach ([$a, $b, $c] as $taxon) {
            $this->created[] = [Taxon::class, $taxon->getId()];
        }
        $this->created[] = [Thread::class, $thread->getId()];

        return [$thread->getId(), [$a->getId(), $b->getId(), $c->getId()]];
    }

    private function load(int $id): Thread
    {
        $this->clear($this->backend);

        return $this->find($this->backend, Thread::class, $id);
    }

    private function ids(iterable $taxa): array
    {
        $ids = [];
        foreach ($taxa as $taxon) {
            $ids[] = $taxon->getId();
        }

        return $ids;
    }

    /** @dataProvider backends */
    public function testTheTaxaAreLoadedWhenRead(string $backend): void
    {
        $this->backend = $backend;
        [$id, $taxa] = $this->threadWithThreeTaxa();

        $thread = $this->load($id);
        $collection = $thread->getTaxa();
        $this->assertInstanceOf(AbstractLazyCollection::class, $collection);
        $this->assertFalse($collection->isInitialized(), 'loading the thread loads none of its ordered associations');

        $this->assertSame($taxa, $this->ids($collection));
        $this->assertTrue($collection->isInitialized());
    }

    /** @dataProvider backends */
    public function testAReorderSavedIsTheOrderReadAfterTheNextLoad(string $backend): void
    {
        $this->backend = $backend;
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
        $this->unitOfWork($backend)->flush();

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
        $this->unitOfWork($backend)->flush();
        $this->assertSame([$c, $a, $b], $this->ids($this->load($id)->getTaxa()));
    }

    /** @dataProvider backends */
    public function testAnEntryAddedBeforeTheFirstReadComesLast(string $backend): void
    {
        $this->backend = $backend;
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
        $this->unitOfWork($backend)->flush();

        $d = $this->taxon('D');
        $this->unitOfWork($backend)->flush();
        $this->created[] = [Taxon::class, $d->getId()];
        $dId = $d->getId();

        $thread = $this->load($id);
        $d = $this->find($backend, Taxon::class, $dId);
        $thread->getTaxa()->add($d); // before anything read it: the persistence keeps it while it loads the rest
        $this->assertFalse($thread->getTaxa()->isInitialized());

        $this->assertSame([$b, $c, $a, $dId], $this->ids($thread->getTaxa()));
        $this->unitOfWork($backend)->flush();

        $this->assertSame([$b, $c, $a, $dId], $this->ids($this->load($id)->getTaxa()));
    }
}
