<?php

namespace Base\Annotations\Traits;

trait HierarchifyTrait
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
        $entityHierarchy = $this->getClassMetadataCompletor()->entityHierarchy;
        if (empty($entityHierarchy))
        throw new \Exception("Missing @Hierarchify for class \"" . get_class($this) . "\"");
        
        $entityHierarchySeparator = $this->getClassMetadataCompletor()->entityHierarchySeparator;
        $separator = $separator ?? $entityHierarchySeparator ?? "/";
        return (is_array($entityHierarchy) ? $entityHierarchy : explode($separator, $entityHierarchy));
    }
}
