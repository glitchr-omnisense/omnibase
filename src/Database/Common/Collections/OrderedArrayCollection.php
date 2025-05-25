<?php

namespace Base\Database\Common\Collections;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;

/**
 *
 */
class OrderedArrayCollection extends ArrayCollection
{
    /**  * @var array */
    protected array $ordering;
    
    public function __construct(Collection|array $array = [], array $ordering = [])
    {
        if($array instanceof Collection) object_hydrate($this, $array);
        else parent::__construct($array);

        $this->ordering = $ordering;
    }

    /**
     * @return array
     */
    public function getOrdering()
    {
        return $this->ordering;
    }

    /**
     * @return $this
     */
    public function applyOrdering()
    {
        if (!is_identity($this->ordering)) {

            $elements = parent::toArray();
            uksort($elements, fn($a, $b) => $elements[$a]->getId() <=> $elements[$b]->getId());

            if (empty($elements)) {
                return $this;
            }

            if (count($elements) < count($this->ordering)) {
                $elements = array_pad($elements, count($this->ordering), null);
            }

            $values = array_values($elements);
            $elements = usort_key($values, $this->ordering);
            $elements = array_filter($elements, fn($e) => $e !== null);

            parent::clear();
            foreach ($elements as $element) {
                parent::add($element);
            }

            $this->ordering = array_keys(parent::toArray());
        }

        return $this;
    }

    public function toArray()
    {
        $this->applyOrdering();
        return parent::toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        $this->applyOrdering();
        return parent::first();
    }

    protected function createFrom(array $elements)
    {
        $this->applyOrdering();
        return parent::createFrom($elements);
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        $this->applyOrdering();
        return parent::last();
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        $this->applyOrdering();
        return parent::key();
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $this->applyOrdering();
        return parent::next();
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        $this->applyOrdering();
        return parent::current();
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string|int $key)
    {
        if(isset($this->ordering[$key])) {
            unset($this->ordering[$key]);
        }
        $this->ordering = array_values($this->ordering); // Reindex elements array
        parent::remove($key);
    }

    /**
     * {@inheritDoc}
     */
    public function removeElement(mixed $element): bool
    {
        $this->applyOrdering();
        $key = $this->indexOf($element);
        if ($key === false) {
            return false;
        }

        if (isset($this->ordering[$key])) {
            unset($this->ordering[$key]);
        }

        $this->ordering = array_values($this->ordering); // Reindex ordering array
        return parent::removeElement($element);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset)
    {
        $this->applyOrdering();
        return parent::offsetExists($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet(mixed $offset)
    {
        $this->applyOrdering();
        return parent::offsetGet($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet(mixed $offset, mixed $value)
    {
        $this->applyOrdering();
        return parent::offsetSet($offset, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset(mixed $offset)
    {
        $this->applyOrdering();
        return parent::offsetUnset($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function indexOf($element)
    {
        $this->applyOrdering();
        return parent::get($element);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string|int $key)
    {
        $this->applyOrdering();
        return parent::get($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys()
    {
        $this->applyOrdering();
        return parent::getKeys();
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        $this->applyOrdering();
        return parent::getValues();
    }

    public function set(string|int $key, mixed $value)
    {
        $this->applyOrdering();
        return parent::set($key, $value);
    }

    public function getIterator()
    {
        $this->applyOrdering();
        return parent::getIterator();
    }

    public function map(Closure $func)
    {
        $this->applyOrdering();
        return parent::map($func);
    }

    public function reduce(Closure $func, $initial = null)
    {
        $this->applyOrdering();
        return parent::reduce($func, $initial);
    }

    public function filter(Closure $p)
    {
        $this->applyOrdering();
        return parent::filter($p);
    }

    public function findFirst(Closure $p)
    {
        $this->applyOrdering();
        return parent::findFirst($p);
    }

    public function forAll(Closure $p)
    {
        $this->applyOrdering();
        return parent::forAll($p);
    }

    public function partition(Closure $p)
    {
        $this->applyOrdering();
        return parent::partition($p);   
    }

    public function slice(int $offset, int|null $length = null)
    {
        $this->applyOrdering();
        return parent::slice($offset, $length);
    }

    public function matching(Criteria $criteria)
    {
        $this->applyOrdering();
        return parent::matching($criteria);
    }
}
