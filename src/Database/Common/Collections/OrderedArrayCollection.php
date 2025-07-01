<?php

namespace Base\Database\Common\Collections;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;
use Traversable;

/**
 *
 */
class OrderedArrayCollection extends ArrayCollection
{
    /**  * @var array */
    protected array $positions; 
    // NB: position is not called in clear(), add(), remove() or removeElement(); it is computed only when in reorder()
    //     it would not remember in such case.
    
    public function __construct(Collection|array $array = [], array $positions = [])
    {
        if($array instanceof Collection) object_hydrate($this, $array);
        else parent::__construct($array);

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

    public function toArray()
    {
        $this->reorder();
        return parent::toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        $this->reorder();
        return parent::first();
    }

    protected function createFrom(array $elements)
    {
        $this->reorder();
        return parent::createFrom($elements);
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        $this->reorder();
        return parent::last();
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        $this->reorder();
        return parent::key();
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $this->reorder();
        return parent::next();
    }

    /**
     * {@inheritDoc}
     */
    public function current()
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
    public function get(string|int $key)
    {
        $this->reorder();
        return parent::get($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys()
    {
        $this->reorder();
        return parent::getKeys();
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        $this->reorder();
        return parent::getValues();
    }

    public function set(string|int $key, mixed $value)
    {
        $this->reorder();
        return parent::set($key, $value);
    }

    public function getIterator(): Traversable
    {
        $this->reorder();
        return parent::getIterator();
    }

    public function map(Closure $func)
    {
        $this->reorder();
        return parent::map($func);
    }

    public function reduce(Closure $func, $initial = null)
    {
        $this->reorder();
        return parent::reduce($func, $initial);
    }

    public function filter(Closure $p)
    {
        $this->reorder();
        return parent::filter($p);
    }

    public function findFirst(Closure $p)
    {
        $this->reorder();
        return parent::findFirst($p);
    }

    public function forAll(Closure $p)
    {
        $this->reorder();
        return parent::forAll($p);
    }

    public function partition(Closure $p)
    {
        $this->reorder();
        return parent::partition($p);   
    }

    public function slice(int $offset, int|null $length = null)
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
