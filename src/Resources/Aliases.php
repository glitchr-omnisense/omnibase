<?php

use Base\BaseBundle;

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        return false;
    }

    $items = array_diff(scandir($dirPath), array('.', '..'));

    foreach ($items as $item) {
        $path = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDir($path); // recursive call for subdirectory
        } else {
            unlink($path); // delete file
        }
    }

    return rmdir($dirPath); // remove empty directory now
}

function generateStubs() {

    // Catch all base classes
    $classMap = array_transforms(function($k, $v): ?array {
                
        $k = preg_replace('/^Base\\\\/', 'App\\', $v);
        return $k == $v ? null : [$k, $v];

    }, BaseBundle::getAllClasses(BaseBundle::getBundleDir()));

    // Create class aliases dynamically
    foreach ($classMap as $appClass => $baseClass) {

        if (!class_exists($appClass) && class_exists($baseClass)) {
            class_alias($baseClass, $appClass);
        }
    }

    // IDE stub generation (one-time)
    $stubDir = __DIR__ . '/../../var/stubs';    
    if (!is_dir($stubDir)) {
        mkdir($stubDir, 0777, true);
    }

    foreach ($classMap as $appClass => $baseClass) {
        
        // Replace namespace separator to directory separator
        $appClass = str_replace('\\', '/', $appClass);
        $stubPath = $stubDir . '/' . $appClass . '.php';
        if (file_exists($stubPath)) continue;

        // Generate the stub code
        $namespace = dirname($appClass);  // Get the namespace part
        $stubCode = "<?php\n\nnamespace " . str_replace("/", "\\", $namespace) . ";\n\n";
        $stubCode .= "if (!class_exists('" . $appClass . "')) {\n";
        $stubCode .= "    class " . basename($appClass) . " extends \\" . $baseClass . " {}\n";
        $stubCode .= "}\n";

        // Write the stub file if it doesn't exist
        @mkdir(dirname($stubPath), 0777, true);
        file_put_contents($stubPath, $stubCode);
    }
}