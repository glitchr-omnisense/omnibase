<?php

namespace Base\Service\Collab;

use Doctrine\ORM\EntityManagerInterface;

/**
 * This class supplies room-key logic and version-hash logic for the
 * real-time collaboration feature. EditorType uses this class.
 * EditorController uses this class. The regular-field presence widget
 * uses this class. Because of this shared use, the room-key format and
 * the hash method exist in one place only.
 */
class CollabRoomResolver
{
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * The room key has this format: entity_fqcn:entity_id:field:locale.
     * Each field has one separate room. Example: an EditorJS field and a
     * select2 field on the same record use two different rooms. The
     * locale segment is always present. This method uses the placeholder
     * value "_" for a field with no locale. Translatable content is
     * specific to one locale. This method must not mix presence data or
     * conflict data between two locales.
     */
    public function buildRoom(string $fqcn, int|string $id, string $field, ?string $locale = null): string
    {
        return $fqcn . ":" . $id . ":" . $field . ":" . ($locale !== null && $locale !== "" ? $locale : "_");
    }

    /**
     * The root form data is not always the mapped entity. An embedded
     * form or a collection form can bind a separate DTO object or wrapper
     * object instead. In this case, this method returns null. This
     * method does not guess the correct entity. When this method returns
     * null, the caller must skip the collaboration setup for that field.
     */
    public function resolveEntity(mixed $data): ?object
    {
        if (!is_object($data)) {
            return null;
        }

        if ($this->entityManager->getMetadataFactory()->isTransient(get_class($data))) {
            return null;
        }

        return $data;
    }

    /**
     * This method creates a version stamp for a field's current value.
     * This system uses this stamp for optimistic-concurrency control.
     * This method uses a content hash. This method does not use a
     * timestamp column, because not every entity has a timestamp column.
     * This method avoids a database schema change for each entity.
     */
    public function hash(mixed $value): string
    {
        return hash("sha256", is_string($value) ? $value : (string) json_encode($value));
    }
}
