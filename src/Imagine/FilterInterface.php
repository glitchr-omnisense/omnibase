<?php

namespace Base\Imagine;

/**
 *
 */
interface FilterInterface extends \Imagine\Filter\FilterInterface
{
    /**
     * @return mixed
     */
    public function __toString();
}
