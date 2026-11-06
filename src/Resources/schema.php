<?php

/*
 * The schema of omnibase's entities and the application's, for omnisense/omnibridge's plain-PHP
 * omnibridge:schema:create (plan "omnibase agnostic", step F7a): no framework, no persistence.
 *
 *     vendor/bin/omnibridge-schema-create --schema=vendor/omnisense/omnibase/src/Resources/schema.php [--url=mysql://user:password@host:3306/app] [--dump-sql]
 *
 * The project is the current folder, or OMNIBASE_PROJECT_DIR: its src/Entity beside the core's, its src/Enum.
 * An application with more (a bundle's entities, its own column types) writes its own file on this model, with
 * Base\Database\Schema\EntitySchema's options.
 */

use Base\Database\Schema\EntitySchema;

$projectDir = rtrim((string) (getenv('OMNIBASE_PROJECT_DIR') ?: getcwd()), '/');
$options = ['project_dir' => $projectDir];

return [
    'description' => EntitySchema::describe(EntitySchema::directories($projectDir), $options),
    'types' => EntitySchema::columnTypes($options),
];
