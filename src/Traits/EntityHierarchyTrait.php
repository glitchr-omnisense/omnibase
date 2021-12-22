<?php

namespace Base\Traits;

trait EntityHierarchyTrait
{
    public function getHierarchy($id = -1, ?string $separator = null)
    {
        $hierarchyTree = $this->getHierarchyTree($separator);
        if ($id < 0) $id = $this->getHierarchyDepth($separator) - 1;

        return $hierarchyTree[$id] ?? null;
    }

    public function getHierarchyDepth(?string $separator = null)
    {
        $hierarchy = $this->getHierarchyTree($separator);
        return count($hierarchy);
    }

    public function getHierarchyTree(?string $separator = null)
    {
        if (!isset($this->getClassMetadata()->entityHierarchy))
            throw new \Exception("Missing @EntityHierarchy for class \"" . get_class($this) . "\"");

        $separator = $separator ?? $this->getClassMetadata()->entityHierarchySeparator ?? "/";
        return (is_array($this->getClassMetadata()->entityHierarchy) ?

            $this->getClassMetadata()->entityHierarchy :
            explode($separator, $this->getClassMetadata()->entityHierarchy)
        );
    }
}
