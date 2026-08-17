<?php

namespace Base\Database\Common\Collections;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ReadableCollection;
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
        // raw reflection property copy keyed by NAME between $array and
        // $this. That works for two instances of compatible classes, but
        // the one real caller here (OrderColumn::postLoad(), reordering a
        // freshly loaded association) passes a Doctrine PersistentCollection
        // - which stores its entries behind its OWN "collection" property,
        // never one named "elements" (ArrayCollection's own storage) - so
        // the copy silently matched nothing and this ended up empty right
        // after construction. toArray() is the real, class-agnostic way to
        // read any Collection's entries regardless of its internal storage
        // name; it also forces the PersistentCollection's own lazy
        // initialize() to run now, before the reflection swap in postLoad()
        // replaces its wrapped collection - so a later access no longer
        // re-triggers Doctrine's OWN (unordered) lazy load over top of the
        // reordered one, which is what silently discarded every drag-to-
        // reorder on the next page load despite the correct order being
        // saved (reported live: Tags, Authors, ... reverted to their
        // original order on reload even though the DB held the new one).
        parent::__construct($array instanceof Collection ? $array->toArray() : $array);
        $this->positions = $positions;
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
        if (empty($this->positions)) {
            return $this;
        }

        $elements = parent::toArray();
        $positionMap = array_flip($this->positions);

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

    public function matching(Criteria $criteria): ReadableCollection&Selectable
    {
        $this->reorder();
        return parent::matching($criteria);
    }
}
