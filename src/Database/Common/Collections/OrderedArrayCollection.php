<?php

namespace Base\Database\Common\Collections;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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
            dump("ORDERING", $this);
        }

        return $this;
    }

    public function __call(string $method, array $vars)
    {
        $this->applyOrdering();
        return parent::$method(...$vars);
    }
}
