<?php

namespace Base\Console\Task;

use Base\Database\UnitOfWorks;
use Omnibridge\Persistence\UnitOfWork;

/**
 * A console task on the entities of a class (plan "omnibase agnostic", step F5e, slice 3): icon:entities,
 * autocomplete:entities, entity:discriminator. Whether a class is an entity is the unit of work's shapes' answer
 * (Doctrine's ClassMetadataManipulator::isEntity() before), its rows its repository's.
 */
abstract class EntitiesTask
{
    public function __construct(protected readonly UnitOfWork $unitOfWork)
    {
    }

    protected function isEntity(string $class): bool
    {
        try {
            return class_exists($class) && UnitOfWorks::shapes($this->unitOfWork)->has($class);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The rows of an entity implementing $interface: all of them, or the one of $id; refused aloud otherwise.
     *
     * @param class-string $interface
     *
     * @return list<object>
     */
    protected function rows(string $class, string $interface, ?string $id): array
    {
        if (!$this->isEntity($class)) {
            throw new \Exception("Entity \"$class\" doesn't exists");
        }
        if (!class_implements_interface($class, $interface)) {
            throw new \Exception("Entity \"$class\" doesn't implement ".$interface);
        }

        $repository = UnitOfWorks::repositories($this->unitOfWork)->of($class);
        $entities = $id ? $repository->findBy(['id' => $id]) : $repository->findAll();
        if (empty($entities)) {
            throw new \Exception($id ? $class.' #'.$id.' not found.' : 'No '.$class.' found.');
        }

        return array_values($entities);
    }

    /**
     * The core's entities, then the application's, that implement $interface.
     *
     * @param class-string $interface
     *
     * @return list<string>
     */
    protected static function classes(string $source, string $interface): array
    {
        return array_values(array_filter(array_merge(Folders::classes(Folders::core().'/Entity'), Folders::classes($source.'/Entity')), static fn (string $class): bool => class_implements_interface($class, $interface)));
    }
}
