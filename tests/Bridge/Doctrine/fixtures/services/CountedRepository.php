<?php

namespace Tests\Base\Bridge\Doctrine\Fixtures\Services;

use Base\Database\Repository\Finders;
use Base\Database\Repository\ServiceEntityRepository;
use Psr\Cache\CacheItemPoolInterface;

/** As a site's CommentRepository: its cache pool first, then the finders and the entity's class. */
class CountedRepository extends ServiceEntityRepository
{
    public function __construct(public readonly CacheItemPoolInterface $cache, Finders $finders, ?string $entityName = null, public readonly ?\Countable $nothing = null)
    {
        parent::__construct($finders, $entityName ?? Counted::class);
    }
}
