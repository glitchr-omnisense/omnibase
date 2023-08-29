<?php

namespace Base\Repository\Thread;

use Base\Entity\Thread\Tag;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository
{
    public function cacheByInsensitiveIdentifier($identifier, array $fields = []) { return $this->findByInsensitiveIdentifier($identifier, $fields, true); }
    public function findByInsensitiveIdentifier($identifier, array $fields = [], $cacheable = false)
    {
        if(empty($fields)) $fields[] = "slug";

        $identifier = preg_replace("/%$/", "", $identifier);
        $qb = $this->createQueryBuilder('u')
            ->setCacheable($cacheable)
            ->setCacheRegion($this->getClassMetadata()->cache["region"] ?? null)
            ->setParameter('identifier', "%".strtolower($identifier)."%");

        foreach($fields as $field) {
            $qb->orWhere('LOWER(u.'.$field.') LIKE :identifier');
        }

        return $qb->getQuery();
    }
}
