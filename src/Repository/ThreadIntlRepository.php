<?php

namespace Base\Repository;

use Base\Entity\Thread;
use Base\Entity\ThreadIntl;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Repository\ThreadTranslations;
use Base\Database\Repository\Finder\Group;

/**
 * @method ThreadIntl|null find($id)
 * @method ThreadIntl|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ThreadIntl[]    findAll()
 * @method ThreadIntl[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ThreadIntlRepository extends ServiceEntityRepository implements ThreadTranslations
{
    public function search(array $terms, array $states = [], Thread|int|string|null $parent = null): array
    {
        $terms = array_filter(
            array_intersect_key($terms, ["title" => true, "excerpt" => true, "content" => true]),
            fn($term) => is_string($term) && trim($term) !== ""
        );
        if (!$terms) {
            return [];
        }
        // (LOWER(title) LIKE %term% OR LOWER(excerpt) LIKE ... ), then the thread's states and parent.
        $names = $values = [];
        foreach ($terms as $field => $term) {
            $names[] = "InsensitivePartial" . ucfirst($field);
            $values[] = "%" . mb_strtolower(trim($term)) . "%";
        }

        $criteria = [Group::by(implode("Or", $names), ...$values)];
        if ($states) {
            $criteria["translatable.state"] = $states;
        }
        if ($parent) {
            $criteria["translatable.parent"] = $parent;
        }

        // The translated thread joined, always (TranslatableNotNull): its trash scope keeps the texts of a
        // deleted thread out.
        return $this->findByTranslatableNotNull($criteria)->getResult();
    }
}
