<?php

namespace Base\Traits;

trait EntityHierarchyTrait 
{
    protected static $entityHierarchy = null;
    protected static $entityHierarchySeparator = "/";

    public function getHierarchyById($id = -1, $separator = null)
    {
        $separator = $separator ?? self::$entityHierarchySeparator;
        if ($id < 0) return self::$entityHierarchy ?? null;

        $sections = $this->getHierarchy($separator);
        return $sections[$id] ?? null;
    }

    public function getHierarchyDepth($separator = null)
    {
        $separator = $separator ?? self::$entityHierarchySeparator;
        $sections = $this->getHierarchy($separator);
        return count($sections);
    }

    public function getHierarchy($separator = null)
    {
        $separator = $separator ?? self::$entityHierarchySeparator;
        if (self::$entityHierarchy === null)
            throw new \Exception("Missing section for class \"" . get_class($this) . "\"");

        $sections = (is_array(self::$entityHierarchy) ? self::$entityHierarchy : explode($separator, self::$entityHierarchy));
        return $sections;
    }
}
