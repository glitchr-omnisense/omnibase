<?php

namespace Base\Repository\Thread;

use Base\Entity\Thread\Tag;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Repository\Thread\Tags;

/**
 * @method Tag|null find($id)
 * @method Tag|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 *
 * Tags, the interface a site's code is given (audit "lesringostars face au
 * master plan", step R15), answered through the finder.
 *
 * @implements Tags<Tag>
 */
class TagRepository extends ServiceEntityRepository implements Tags
{
    public function oneBySlug(string $slug): ?Tag
    {
        return $this->cacheOneBySlug($slug);
    }

    public function bySlugs(array $slugs): array
    {
        return $this->cacheBySlug($slugs)->getResult();
    }

    public function cacheByInsensitiveIdentifier($identifier, array $fields = []) { return $this->findByInsensitiveIdentifier($identifier, $fields, true); }
    public function findByInsensitiveIdentifier($identifier, array $fields = [], $cacheable = false)
    {
        if(empty($fields)) $fields[] = "slug";

        // LOWER(<field>) LIKE %identifier% on any of the fields: a finder of the language, one criterion per field.
        $identifier = "%".strtolower(preg_replace("/%$/", "", $identifier))."%";
        $finder = ($cacheable ? "cacheBy" : "findBy") . implode("Or", array_map(static fn (string $field) => "InsensitivePartial".ucfirst($field), $fields));

        return $this->{$finder}(...array_fill(0, \count($fields), $identifier));
    }
}
