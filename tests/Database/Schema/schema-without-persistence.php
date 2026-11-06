<?php

/*
 * The schema of omnibase's entities created with no persistence installed (plan "omnibase agnostic", step F7a): run by
 * docker/bin/bare --strict on the core installed alone (var/bare-alone: no Doctrine but collections, no framework) for
 * tests/BareTest.php. Base\Database\Schema\EntitySchema describes the core's entities from their declarations and
 * gives omnibase's column types; omnisense/omnibridge's SchemaCreator creates the tables in an SQLite database in
 * memory; SqliteSchema reads them back and compares them with the description.
 */

use Base\Database\Schema\EntitySchema;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Database\SqliteSchema;

$options = ['project_dir' => sys_get_temp_dir().'/omnibase-no-project'];
$description = EntitySchema::describe(EntitySchema::directories($options['project_dir']), $options);
$types = EntitySchema::columnTypes($options);

$pdo = new PDO('sqlite::memory:');
$statements = (new SchemaCreator($pdo, $types))->create($description);
$schema = SqliteSchema::read($pdo, [], $types);

return [
    'entities' => count($description->entities),
    'tables' => [count($description->tables), count($schema->tables)],
    'statements' => count($statements),
    'differences' => $schema->differences($description),
    'user' => array_keys($schema->tables['user']['columns'] ?? []) === array_keys($description->table('user')->columns),
    'enumerations and sets' => [isset($types->enumerations['thread_state']), isset($types->sets['user_role'])],
];
