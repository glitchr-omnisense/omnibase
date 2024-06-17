<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Type\EnumType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class EnumSubscriber
{
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

            
            $enum = $column->getType();
            $column->setComment(trim(sprintf('%s (%s)', $column->getComment(), implode(',', $enum::getPermittedValues()))));

            // $type = $column->getType() instanceof SetType ? "set" : "enum";
            // $column->setComment(trim(sprintf('(DC2Type:%s,%s)', $type, $column->getType()->getName())));
        }
    }
}
