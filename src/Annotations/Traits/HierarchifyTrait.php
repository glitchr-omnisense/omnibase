<?php

namespace Base\Annotations\Traits;

use Exception;

/**
 *
 */
trait HierarchifyTrait
{
    /**
     * @param $id
     * @param string|null $separator
     * @return mixed|string|null
     * @throws Exception
     */
    public function getHierarchy($id = -1, ?string $separator = null)
    {
        $hierarchyTree = $this->getHierarchyTree($separator);
        if ($id < 0) {
            $id = $this->getHierarchyDepth($separator) - 1;
        }

        return $hierarchyTree[$id] ?? null;
    }

    /**
     * @param string|null $separator
     * @return int|null
     * @throws Exception
     */
    public function getHierarchyDepth(?string $separator = null)
    {
        $hierarchy = $this->getHierarchyTree($separator);
        return count($hierarchy);
    }

    /**
     * @param string|null $separator
     * @return array|string[]
     * @throws Exception
     */
    public function getHierarchyTree(?string $separator = null)
    {
        $entityHierarchy = $this->getClassMetadataCompletor()->entityHierarchy;
        if (empty($entityHierarchy)) {
            throw new Exception("Missing @Hierarchify for class \"" . get_class($this) . "\"");
        }

        $entityHierarchySeparator = $this->getClassMetadataCompletor()->entityHierarchySeparator;
        $separator = $separator ?? $entityHierarchySeparator ?? "/";
        return (is_array($entityHierarchy) ? $entityHierarchy : explode($separator, $entityHierarchy));
    }
}
