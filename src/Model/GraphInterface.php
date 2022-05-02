<?php

namespace Base\Model;

use Doctrine\Common\Collections\Collection;

interface GraphInterface
{
    public function getParent();
    public function getChildren(): Collection;
    public function getConnexes(): Collection;
}
