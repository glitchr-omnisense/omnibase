<?php

namespace Base\Console\Command;

use Base\Console\Command;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctrine:array:upgrade', aliases: [], description: 'This command allows to upgrade array field into json.')]
class DoctrineArrayUpgradeCommand extends Command
{
    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        EntityManagerInterface   $entityManager,
        ParameterBagInterface    $parameterBag
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
    }

    protected function configure(): void
    {
        $this->SetDescription('Converts array fields in MySQL tables to JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaManager = $this->getEntityManager()->GetConnection()->createSchemaManager();
        $tables = $schemaManager->listTables();
        foreach ($tables as $table) {
            $this->convertArrayFieldsToJson($table, $output);
        }

        $output->writeln('All array fields converted to JSON successfully.');

        return Command::SUCCESS;
    }

    private function convertArrayFieldsToJson(Table $table, OutputInterface $output)
    {
        $tableName = $table->getName();
        $columns = $table->getColumns();
        foreach ($columns as $column) {

            /** @var Column $column */
            $isArray = Type::getTypeRegistry()->lookupName($column->getType()) === 'array';
            $isArrayType = $column->getComment() == "(DC2Type:array)";
            $isJson = Type::getTypeRegistry()->lookupName($column->getType()) === 'json';
            $isJsonType = $column->getComment() == "(DC2Type:json)";
            if ($isJson || $isJsonType || $isArray || $isArrayType) {
                
                $columnName = $column->getName();
                try {
                    $this->convertArrayColumnToJson($tableName, $columnName, $output);
                    if($isArray) $this->alterColumnToJson($tableName, $columnName, $output);
                } catch (\Exception $e) {
                    // Handle the exception gracefully
                    $output->writeln("Error converting `$columnName` in `$tableName`: " . $e->getMessage());
                }
            }
        }
    }

    private function convertArrayColumnToJson(string $tableName, string $columnName, OutputInterface $output)
    {
        $connection = $this->getEntityManager()->getConnection();
        $query = $connection->createQueryBuilder()
            ->select('id', $columnName)
            ->from($tableName);

        $statement = $query->executeQuery();
        $rows = $statement->fetchAllAssociative();
        foreach ($rows as $row) {

            $value = $row[$columnName];
            if($value == "") $unserializedValue = [];
            else if (is_json($value)) continue;
            else if ($value !== null) {

                // Try to unserialize the value
                $unserializedValue = unserialize($value);
                if ($unserializedValue === false && $value !== 'b:0;') {
                    // If unserialization fails and the value is not 'b:0;', it's likely corrupt
                    $output->writeln("Skipping row in $tableName: Unable to unserialize value in $columnName");
                    continue;
                }
            }

            // Update the value to JSON format
            $jsonValue = json_encode($unserializedValue);
            $connection->executeStatement("UPDATE $tableName SET $columnName = :jsonValue WHERE id = :id", [
                'jsonValue' => $jsonValue,
                'id' => $row['id'],
            ]);
        }

        $output->writeln("Converted $columnName in $tableName to JSON format.");
    }


    private function alterColumnToJson(string $tableName, string $columnName, OutputInterface $output)
    {
        $connection = $this->getEntityManager()->getConnection();
        try {
            $connection->executeStatement("ALTER TABLE $tableName MODIFY $columnName JSON");
            $output->writeln("Altered $columnName in $tableName to JSON format.");
            $output->writeln("--> You can now change annotations/attributes column information in entities to `json`.");
        } catch (\Exception $e) {
            // Handle the exception gracefully
            $output->writeln("Error altering $columnName in $tableName: " . $e->getMessage());
        }
    }

}
