<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\OrderColumnBackend;
use Base\Database\Common\Collections\OrderedArrayCollection;
use Doctrine\ORM\PersistentCollection;

/** #[OrderColumn]'s part on Doctrine (plan "omnibase agnostic", step F2c; moves to omnibridge/doctrine at F3). */
final class DoctrineOrderColumnBackend implements OrderColumnBackend
{
    private static ?\ReflectionProperty $collection = null;

    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function orderLazily(object $entity, mixed $collection, array $positions): bool
    {
        if (!$collection instanceof PersistentCollection || $collection->getOwner() !== $entity) {
            return false;
        }

        // The collection is not loaded here: what it wraps is swapped for
        // an OrderedArrayCollection holding the stored positions, which
        // Doctrine fills when the collection is first read (one query,
        // as for any lazy association) and which sorts itself on the
        // first read after that load (OrderedArrayCollection::loadedBy()).
        // It was loaded here, for every ordered association of every
        // entity loaded, read or not (28 on the home of lesringostars).
        // An association already loaded (fetch-joined, or refreshed)
        // keeps its entries, sorted by the positions read now.
        $ordered = new OrderedArrayCollection($collection->unwrap()->toArray(), $positions);
        $ordered->loadedBy($collection);

        self::$collection ??= new \ReflectionProperty(PersistentCollection::class, 'collection');
        self::$collection->setValue($collection, $ordered);

        return true;
    }
}
