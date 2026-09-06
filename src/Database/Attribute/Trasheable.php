<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Database\Entity\Extension\TrasheableTrait;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

/**
 * Marks an entity as soft-deletable: `$em->remove()` stamps the deletion field
 * instead of issuing a DELETE, and TrashFilter hides the row from every read.
 *
 * The behaviour itself lives in TrasheableSubscriber - this class only carries
 * the configuration and knows how to find itself on a class.
 *
 * ## Why resolve() exists
 *
 * ReflectionClass::getAttributes() is NOT inheriting, and AttributeReader
 * caches per exact class name, so `#[Trasheable]` declared on Thread is
 * invisible to Article, Gallery, ArticleCommentReply and every other subtype -
 * which is to say invisible to all of the content this bundle actually
 * manages. Property attributes do not have the problem (getProperties() walks
 * parents), which is why Versionable needs no equivalent.
 *
 * Making AttributeReader inherit class attributes wholesale is not an option:
 * DiscriminatorEntry, Cache and Hierarchify are all class-level and all mean
 * something different on a subclass. So the walk is done here, by the one
 * attribute that wants it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Trasheable extends AbstractAttribute implements EntityExtensionInterface
{
    /** Field carrying the deletion date. */
    public string $deletedAt;

    /**
     * How long a trashed entity survives before the purge command may destroy
     * it for good. Null means "use base.extension.empty_trash".
     */
    public ?string $expiry;

    /**
     * Whether the cascade-remove children of a trashed entity are kept.
     * Translations are cascade:remove, so leaving this off would empty an
     * article of its title and content the moment it hit the trash - and
     * restoring would hand back a blank row.
     */
    public bool $cascade;

    public function __construct(string $field = "deletedAt", ?string $expiry = null, bool $cascade = true)
    {
        $this->deletedAt = $field;
        $this->expiry = $expiry;
        $this->cascade = $cascade;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     * @throws Exception
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if ($object instanceof ClassMetadata) {
            if (!$this->deletedAt) {
                throw new Exception("Timing field for deletion missing, please provide a valid field \"" . $this->deletedAt . "\"");
            }

            if (!$object->hasField($this->deletedAt)) {
                throw new Exception("Field \"" . $this->deletedAt . "\" is missing in \"" . $object->getName() . "\", did you forget to import \"" . TrasheableTrait::class . "\" ?");
            }
        }

        return ($target == AttributeReader::TARGET_CLASS);
    }

    /**
     * Find the attribute on $entityOrClass or on any of its ancestors, nearest
     * declaration first. Doctrine proxies are normalised by the caller passing
     * a ClassMetadata (or its name) rather than get_class($proxy).
     */
    public static function resolve(object|string $entityOrClass): ?self
    {
        if ($entityOrClass instanceof ClassMetadata) {
            $className = $entityOrClass->getName();
        } elseif (is_object($entityOrClass)) {
            $className = get_class($entityOrClass);
        } else {
            $className = $entityOrClass;
        }

        if (!class_exists($className)) {
            return null;
        }

        $reader = AttributeReader::getInstance();
        foreach (array_merge([$className], array_values(class_parents($className) ?: [])) as $candidate) {
            $attributes = $reader->getClassAttributes($candidate, self::class);
            if (!empty($attributes)) {
                return end($attributes);
            }
        }

        return null;
    }

    public static function has(object|string $entityOrClass): bool
    {
        return self::resolve($entityOrClass) !== null;
    }
}
