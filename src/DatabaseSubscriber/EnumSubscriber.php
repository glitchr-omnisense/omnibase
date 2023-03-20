<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Type\EnumType;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

class EnumSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [ToolEvents::postGenerateSchema];
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

        /** @var \Doctrine\DBAL\Schema\Column $column */
        foreach ($columns as $column) {
            /**
             * @var EnumType
             */
            $enum = $column->getType();
            $column->setComment(trim(sprintf('%s (%s)', $column->getComment(), implode(',', $enum::getPermittedValues()))));
        }
    }
}
