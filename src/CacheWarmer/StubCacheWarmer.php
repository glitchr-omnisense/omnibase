<?php

namespace Base\CacheWarmer;

use Base\BaseBundle;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class StubClassCacheWarmer implements CacheWarmerInterface
{
    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array;
    {
        $stubDir = $cacheDir . '/stubs';
        if (!is_dir($stubDir)) {
            mkdir($stubDir, 0777, true);
        }

        $classMap = array_transforms(function($k, $v): ?array {
            $k = preg_replace('/^Base\\\\/', 'App\\', $v);
            return $k == $v ? null : [$k, $v];
        }, BaseBundle::getAllClasses(BaseBundle::getBundleDir()));

        foreach ($classMap as $appClass => $baseClass) {
            $appClassPath = str_replace('\\', '/', $appClass);
            $stubPath = $stubDir . '/' . $appClassPath . '.php';
            if (file_exists($stubPath)) continue;

            $namespace = dirname($appClass);
            $stubCode = "<?php\n\nnamespace " . $namespace . ";\n\n";
            $stubCode .= "if (!class_exists('$appClass')) {\n";
            $stubCode .= "    class " . basename($appClass) . " extends \\" . $baseClass . " {}\n";
            $stubCode .= "}\n";

            @mkdir(dirname($stubPath), 0777, true);
            file_put_contents($stubPath, $stubCode);
        }

        return [$stubDir];
    }
}