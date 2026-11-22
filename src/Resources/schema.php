<?php

/*
 * The schema of omnibase's entities, the installed packages' and the application's, for omnisense/omnibridge's
 * plain-PHP omnibridge:schema:create (plan "omnibase agnostic", step F7a): no framework, no persistence.
 *
 *     vendor/bin/omnibridge-schema-create --schema=vendor/omnisense/omnibase/src/Resources/schema.php [--url=mysql://user:password@host:3306/app] [--dump-sql]
 *
 * The project is the current folder, or OMNIBASE_PROJECT_DIR: its src/Entity beside the core's and those of the
 * Composer packages that require the core (Base\Discovery::packages(), since 7.2), its src/Enum and theirs. An
 * application with more (folders a package adds by configuration, its own column types) writes its own file on this
 * model, with Base\Database\Schema\EntitySchema's options.
 */

use Base\Database\Schema\EntitySchema;
use Base\Discovery;

$projectDir = rtrim((string) (getenv('OMNIBASE_PROJECT_DIR') ?: getcwd()), '/');
$packages = Discovery::packages(is_dir($projectDir.'/vendor') ? $projectDir.'/vendor' : null);
$options = ['project_dir' => $projectDir, 'packages' => $packages];

return [
    'description' => EntitySchema::describe(array_keys(Discovery::entities($projectDir, $packages)), $options),
    'types' => EntitySchema::columnTypes($options),
];
