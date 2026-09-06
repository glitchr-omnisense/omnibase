<?php

namespace Base\Service;

use Base\Database\Attribute\Versionable;
use Base\Entity\Extension\Revision;
use Base\Repository\Extension\RevisionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads back what VersionableSubscriber wrote.
 *
 * Two shapes of the same data:
 *
 *   history()      the revisions of an entity, newest first - one line per
 *                  save, which is what the entity-level history reads
 *   fieldHistory() the same revisions pivoted per field - which is what the
 *                  per-field badge in the form label reads
 *
 * A form render asks for the same entity once per locale tab, so the lookup is
 * memoised for the life of the request: without it an article edited in three
 * languages would issue the same query four times to draw its badges.
 */
class VersionManager
{
    /** @var array<string, Revision[]> */
    protected array $memo = [];

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected int $maxRevisions = 5,
    ) {
    }

    protected function repository(): RevisionRepository
    {
        return $this->entityManager->getRepository(Revision::class);
    }

    /**
     * Revisions anchored on $entity - passing a translation gives its parent's
     * history, which is where its own changes were recorded.
     *
     * @return Revision[]
     */
    public function history(object $entity): array
    {
        [$target, $_] = Versionable::anchor($entity);
        if ($target === null || !method_exists($target, "getId") || $target->getId() === null) {
            return [];
        }

        $className = $this->entityManager->getClassMetadata($target::class)->getName();
        $key = $className . "#" . $target->getId();

        return $this->memo[$key] ??= $this->repository()->history($className, $target->getId(), $this->maxRevisions);
    }

    /**
     * The history of every versioned field of $entity, pivoted per property
     * and expressed in terms the form can render without knowing about
     * revisions at all.
     *
     * Only the fields declared on the class passed in are returned, under
     * their bare property name: a ThreadIntl gets title/headline/content, not
     * "fr-FR.title", so a form child can be matched by its own name.
     *
     * @return array<string, list<array{id: int, key: string, value: mixed, preview: string, at: ?\DateTimeInterface, by: ?string, hash: string, restorable: bool}>>
     */
    public function fieldHistory(object $entity): array
    {
        $versioned = Versionable::resolve($entity::class);
        if (empty($versioned)) {
            return [];
        }

        [$target, $prefix] = Versionable::anchor($entity);
        if ($target === null) {
            return [];
        }

        $fields = [];
        foreach ($this->history($entity) as $revision) {
            $entityData = $revision->getEntityData();

            foreach ($versioned as $property => $versionable) {
                $key = $prefix . $property;
                if (!array_key_exists($key, $entityData)) {
                    continue;
                }

                $value = $entityData[$key][0] ?? null;

                $fields[$property][] = [
                    "id" => $revision->getId(),
                    "key" => $key,
                    "value" => $value,
                    "preview" => $this->preview($value),
                    "at" => $revision->getCreatedAt(),
                    "by" => $revision->getInitiator()?->getUsername(),
                    "impersonator" => $revision->getImpersonator()?->getUsername(),
                    "hash" => $revision->getHashShort(),
                    "restorable" => $versionable->restorable,
                ];
            }
        }

        return $fields;
    }

    /**
     * A short, human line standing for a stored value in the dropdown.
     *
     * Deliberately lossy: this is the thing you read to decide whether that is
     * the version you want, not the thing that gets restored - restoring uses
     * the raw value.
     */
    public function preview(mixed $value, int $length = 80): string
    {
        if ($value === null || $value === "") {
            return "—";
        }

        if (is_bool($value)) {
            return $value ? "true" : "false";
        }

        if (is_array($value)) {
            // Associations normalise to {id, label}; a plain list (keywords)
            // stays as it is.
            if (array_key_exists("label", $value) || array_key_exists("id", $value)) {
                return (string) ($value["label"] ?? $value["id"] ?? "—");
            }

            $parts = array_map(fn($item) => $this->preview($item, 24), $value);
            return $parts ? implode(", ", $parts) : "—";
        }

        $value = (string) $value;

        // EditorJS payloads are unreadable raw, and they are exactly the
        // fields whose history matters most, so pull the text out of them.
        $decoded = json_decode($value, true);
        if (is_array($decoded) && isset($decoded["blocks"]) && is_array($decoded["blocks"])) {
            $texts = [];
            foreach ($decoded["blocks"] as $block) {
                $text = $block["data"]["text"] ?? $block["data"]["caption"] ?? null;
                if (is_string($text) && trim($text) !== "") {
                    $texts[] = $text;
                }
            }
            $value = implode(" ", $texts);
        }

        $value = trim(preg_replace('/\s+/u', " ", strip_tags($value)) ?? "");
        if ($value === "") {
            return "—";
        }

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . "…" : $value;
    }
}
