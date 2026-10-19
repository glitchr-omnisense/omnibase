<?php

namespace Base\Database\Mapping;

use Exception;
use ReflectionClass;

/**
 * omnibase's names for tables and columns: App\Entity\Thread\Tag is
 * threadTag, App\Entity\User\Profile userProfile, Thread::$tags the join
 * table thread_tags (thread_id, threadTag_id), a property its own name.
 *
 * The rules are omnibridge/doctrine's NamingStrategy (framework-free, the one
 * another framework reading the same database maps with), which this class
 * extends: one place says how a class becomes a table. What omnibase adds on
 * top is kept here, unchanged: a class may be given as an object, and every
 * attribute of an entity is instantiated and must serialize (the metadata
 * cache stores them) - a mistake said at once, with the attribute's name -,
 * but for the attributes of a package not installed, which PHP itself ignores
 * (audit "lesringostars face au master plan", R4: the core's entities read
 * under Laravel, without API Platform).
 */
class NamingStrategy extends \Omnibridge\Doctrine\NamingStrategy
{
    protected array $uniqueTableName = [];

    /**
     * @param string|object|null $className
     * @throws Exception
     */
    public function classToTableName($className): string
    {
        if (!$className) {
            return "";
        }

        $className = is_object($className) ? get_class($className) : $className;
        if (class_exists($className)) {
            foreach ((new ReflectionClass($className))->getAttributes() as $attribute) {
                // The attribute of a package not installed (API Platform's #[ApiResource] on User and
                // Thread, which the core only suggests): PHP itself ignores it until it is instantiated.
                if (!class_exists($attribute->getName())) {
                    continue;
                }
                $attribute = $attribute->newInstance();
                if (!is_serializable($attribute)) {
                    throw new Exception("Attribute \"" . get_class($attribute) . "\" failed to serialize. Please implement __serialize/__unserialize, or double-check properties.");
                }
            }
        }

        // A name over 64 characters: omnibridge's \LengthException, an \Exception as before, the same message.
        return parent::classToTableName($className);
    }
}
