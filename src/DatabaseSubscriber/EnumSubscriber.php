<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Type\EnumType;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Events;

class EnumSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            "postGenerateSchema"
        ];
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs)
    {
        $columns = [];

        foreach ($eventArgs->getSchema()->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                if ($column->getType() instanceof EnumType) {
                    $columns[] = $column;
                }
            }
        }

        /** @var Column $column */
        foreach ($columns as $column) {
            
            $enum = $column->getType()->lookupName($column->getType());
            $column->setComment(trim(sprintf('(DC2Type:%s)', $enum)));
        }
    }
}