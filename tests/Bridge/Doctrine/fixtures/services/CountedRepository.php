<?php

namespace Tests\Base\Bridge\Doctrine\Fixtures\Services;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/** As a site's CommentRepository: its cache pool first, then the registry and the entity's class. */
class CountedRepository extends EntityRepository
{
    public function __construct(public readonly CacheItemPoolInterface $cache, ManagerRegistry $registry, ?string $entityName = null, public readonly ?\Countable $nothing = null)
    {
        $entityName ??= Counted::class;
        $manager = $registry->getManagerForClass($entityName);
        parent::__construct($manager, $manager->getClassMetadata($entityName));
    }
}
