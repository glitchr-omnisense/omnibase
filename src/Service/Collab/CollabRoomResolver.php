<?php

namespace Base\Service\Collab;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Shared room-key + version-hash logic for the real-time collaboration
 * feature (autosave conflict guard, and later presence/live sync), used by
 * EditorType, EditorController, and the regular-field presence widget alike
 * so the room-key format and version hashing only exist in one place.
 */
class CollabRoomResolver
{
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * entity_fqcn:entity_id:field:locale — one room per *field* (an EditorJS
     * field and a select2 field on the same record are independent rooms).
     * Locale is always present (placeholder "_") since translatable content
     * is per-locale and must not bleed presence/conflicts across locales.
     */
    public function buildRoom(string $fqcn, int|string $id, string $field, ?string $locale = null): string
    {
        return $fqcn . ":" . $id . ":" . $field . ":" . ($locale !== null && $locale !== "" ? $locale : "_");
    }

    /**
     * Root form data isn't always the mapped entity itself (embedded/
     * collection forms may bind a DTO/wrapper) — returns null rather than
     * guessing so callers can simply skip collab wiring for that field.
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
     * Optimistic-concurrency version stamp for a field's current value —
     * content hash rather than a timestamp column, since not every entity
     * has one and this avoids a schema change per entity.
     */
    public function hash(mixed $value): string
    {
        return hash("sha256", is_string($value) ? $value : (string) json_encode($value));
    }
}
