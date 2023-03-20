<?php

namespace Base\Database\Common\Collections;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;

class OrderedArrayCollection extends ArrayCollection
{
    /**  * @var array */
    protected $ordering;
    public function __construct(ArrayCollection|array $array = [], array $ordering = [])
    {
        parent::__construct($array instanceof ArrayCollection ? $array->toArray() : $array);

        $this->ordering = $ordering;
    }

    public function getOrdering()
    {
        return $this->ordering;
    }
    public function applyOrdering()
    {
        if (!is_identity($this->ordering)) {
            $elements = parent::toArray();
            uksort($elements, fn ($a, $b) => $elements[$a]->getId() <=> $elements[$b]->getId());

            if (empty($elements)) {
                return $this;
            }

            if (count($elements) < count($this->ordering)) {
                $elements = array_pad($elements, count($this->ordering), null);
            }

            $elements = usort_key(array_values($elements), $this->ordering);
            $elements = array_filter($elements, fn ($e) => $e !== null);

            parent::clear();
            foreach ($elements as $element) {
                parent::add($element);
            }

            $this->ordering = array_keys(parent::toArray());
        }

        return $this;
    }

    public function toArray(): array
    {
        $this->applyOrdering();
        return parent::toArray();
    }
    public function first(): mixed
    {
        $this->applyOrdering();
        return parent::first();
    }
    public function last(): mixed
    {
        $this->applyOrdering();
        return parent::last();
    }
    public function key(): int|string|null
    {
        $this->applyOrdering();
        return parent::key();
    }
    public function next(): mixed
    {
        $this->applyOrdering();
        return parent::next();
    }
    public function current(): mixed
    {
        $this->applyOrdering();
        return parent::current();
    }
    public function remove($key): mixed
    {
        $this->applyOrdering();
        return parent::remove($key);
    }
    public function removeElement($element): bool
    {
        $this->applyOrdering();
        return parent::removeElement($element);
    }
    public function offsetExists($offset): bool
    {
        $this->applyOrdering();
        return parent::offsetExists($offset);
    }
    public function offsetGet(mixed $offset): mixed
    {
        $this->applyOrdering();
        return parent::offsetGet($offset);
    }
    public function offsetSet($offset, $value): void
    {
        $this->applyOrdering();
        parent::offsetSet($offset, $value);
    }
    public function offsetUnset($offset): void
    {
        $this->applyOrdering();
        parent::offsetUnset($offset);
    }
    public function containsKey($key): bool
    {
        $this->applyOrdering();
        return parent::containsKey($key);
    }
    public function contains($element): bool
    {
        $this->applyOrdering();
        return parent::contains($element);
    }
    public function exists(Closure $p): bool
    {
        $this->applyOrdering();
        return parent::exists($p);
    }
    public function indexOf($element): int|string|bool
    {
        $this->applyOrdering();
        return parent::indexOf($element);
    }
    public function get($key): mixed
    {
        $this->applyOrdering();
        return parent::get($key);
    }
    public function getKeys(): array
    {
        $this->applyOrdering();
        return parent::getKeys();
    }
    public function getValues(): array
    {
        $this->applyOrdering();
        return parent::getValues();
    }
    public function set($key, $value): void
    {
        $this->applyOrdering();
        parent::set($key, $value);
    }
    public function count(): int
    {
        $this->applyOrdering();
        return parent::count();
    }
    public function isEmpty(): bool
    {
        $this->applyOrdering();
        return parent::isEmpty();
    }
    public function getIterator(): \Traversable
    {
        $this->applyOrdering();
        return parent::getIterator();
    }
    public function map(Closure $func): static
    {
        $this->applyOrdering();
        return parent::map($func);
    }
    public function filter(Closure $p): static
    {
        $this->applyOrdering();
        return parent::filter($p);
    }
    public function forAll(Closure $p): bool
    {
        $this->applyOrdering();
        return parent::forAll($p);
    }
    public function partition(Closure $p): array
    {
        $this->applyOrdering();
        return parent::partition($p);
    }
    public function __toString()
    {
        $this->applyOrdering();
        return parent::__toString();
    }
    public function clear(): void
    {
        $this->applyOrdering();
        parent::clear();
    }
    public function slice($offset, $length = null): array
    {
        $this->applyOrdering();
        return parent::slice($offset, $length);
    }
    public function matching(Criteria $criteria): Collection
    {
        $this->applyOrdering();
        return parent::matching($criteria);
    }
}
