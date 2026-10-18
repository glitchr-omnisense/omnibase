<?php

namespace Base\Database\Common\Collections;

use Closure;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Traversable;

class OrderedArrayCollection extends ArrayCollection
{
    /**  * @var array */
    protected array $positions;
    // NB: position is not called in clear(), add(), remove() or removeElement(); it is computed only when in reorder()
    //     it would not remember in such case.
    
    public function __construct(Collection|array $array = [], array $positions = [])
    {
        // Was object_hydrate($this, $array) for the Collection branch: a
        // raw reflection property copy keyed by NAME, which matched nothing
        // in a Doctrine PersistentCollection (its entries sit behind its own
        // "collection" property, not ArrayCollection's "elements"), so this
        // ended up empty. toArray() reads any Collection's entries.
        //
        // OrderColumn::postLoad() no longer hands it the PersistentCollection
        // (whose toArray() loaded the association there and then, for every
        // entity loaded): it passes the entries the association holds, none
        // until it is loaded, and loadedBy() the PersistentCollection, so the
        // positions wait for Doctrine's own load to have filled this one -
        // the order lost after a drag-and-drop (Tags, Authors... back in
        // their original order on the next page while the database held the
        // new one) was a reorder spent before that load.
        parent::__construct($array instanceof Collection ? $array->toArray() : $array);
        $this->positions = $positions;
    }

    /**
     * The lazy collection that fills this one (OrderColumn::postLoad(): the
     * PersistentCollection of the association, not loaded yet). Until it is
     * loaded the positions are kept: Doctrine reads this collection while it
     * loads it (the entries added before, then the snapshot), and sorting
     * then would spend the positions on what is not all there yet. Kept in a
     * WeakMap, not a property: this collection is serialized with its
     * entity (a session's user) as before, and nothing holds the lazy one.
     *
     * @return $this
     */
    public function loadedBy(AbstractLazyCollection $collection): static
    {
        self::$loadedBy ??= new \WeakMap();
        self::$loadedBy[$this] = \WeakReference::create($collection);

        return $this;
    }

    /** @var \WeakMap<self, \WeakReference<AbstractLazyCollection>>|null */
    private static ?\WeakMap $loadedBy = null;

    /**
     * Whether the entries are all there: no lazy collection fills this one,
     * or it has been loaded.
     */
    public function isLoaded(): bool
    {
        if (null === self::$loadedBy || !isset(self::$loadedBy[$this])) {
            return true;
        }

        $collection = self::$loadedBy[$this]->get();
        if (null !== $collection && !$collection->isInitialized()) {
            return false;
        }

        unset(self::$loadedBy[$this]);

        return true;
    }

    /**
     * @return array
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * @return $this
     */
    public function reorder()
    {
        if (empty($this->positions) || !$this->isLoaded()) {
            return $this;
        }

        $elements = parent::toArray();
        // Only ids can be flipped: positions recorded while the entries had
        // none yet (created with their parent in one flush) are nulls.
        $positionMap = array_flip(array_filter($this->positions, fn ($id) => \is_int($id) || \is_string($id)));

        // sort by the map (unknown ids go to the end)
        usort($elements, function($a, $b) use ($positionMap) {
            $posA = $positionMap[$a->getId()] ?? PHP_INT_MAX;
            $posB = $positionMap[$b->getId()] ?? PHP_INT_MAX;
            return $posA <=> $posB;
        });

        $this->clear();
        foreach ($elements as $element) {
            $this->add($element);
        }

        $this->positions = [];
        return $this;
    }

    public function toArray(): array
    {
        $this->reorder();
        return parent::toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function first(): mixed
    {
        $this->reorder();
        return parent::first();
    }

    protected function createFrom(array $elements): static 
    {
        $this->reorder();
        return parent::createFrom($elements);
    }

    /**
     * {@inheritDoc}
     */
    public function last(): mixed
    {
        $this->reorder();
        return parent::last();
    }

    /**
     * {@inheritDoc}
     */
    public function key(): int|string|null
    {
        $this->reorder();
        return parent::key();
    }

    /**
     * {@inheritDoc}
     */
    public function next(): mixed
    {
        $this->reorder();
        return parent::next();
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        $this->reorder();
        return parent::current();
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        $this->reorder();
        return parent::offsetExists($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        $this->reorder();
        return parent::offsetGet($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->reorder();
        parent::offsetSet($offset, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->reorder();
        parent::offsetUnset($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function indexOf($element): string|int|false
    {
        $this->reorder();
        return parent::indexOf($element);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string|int $key): mixed
    {
        $this->reorder();
        return parent::get($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys(): array
    {
        $this->reorder();
        return parent::getKeys();
    }

    /**
     * {@inheritDoc}
     */
    public function getValues(): array 
    {
        $this->reorder();
        return parent::getValues();
    }

    public function set(string|int $key, mixed $value): void
    {
        $this->reorder();
        parent::set($key, $value);
    }

    public function getIterator(): Traversable
    {
        $this->reorder();
        return parent::getIterator();
    }

    public function map(Closure $func): static 
    {
        $this->reorder();
        return parent::map($func);
    }

    public function reduce(Closure $func, $initial = null): mixed
    {
        $this->reorder();
        return parent::reduce($func, $initial);
    }

    public function filter(Closure $p): static
    {
        $this->reorder();
        return parent::filter($p);
    }

    public function findFirst(Closure $p): mixed
    {
        $this->reorder();
        return parent::findFirst($p);
    }

    public function forAll(Closure $p): bool
    {
        $this->reorder();
        return parent::forAll($p);
    }

    public function partition(Closure $p): array
    {
        $this->reorder();
        return parent::partition($p);   
    }

    public function slice(int $offset, int|null $length = null): array
    {
        $this->reorder();
        return parent::slice($offset, $length);
    }

    /**
     * Collection&Selectable satisfies both doctrine/collections majors:
     * 2.x declares ReadableCollection&Selectable here (Collection extends
     * ReadableCollection), 3.x narrowed ArrayCollection::matching() to
     * Collection - and the old ReadableCollection&Selectable is not a
     * subtype of that, a compile error that took the whole kernel down.
     */
    public function matching(Criteria $criteria): Collection&Selectable
    {
        $this->reorder();
        return parent::matching($criteria);
    }
}
