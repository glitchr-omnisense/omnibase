<?php

namespace Tests\Base;

use PHPUnit\Framework\TestCase;

/**
 * Each file of src/ declares the class its path names (PSR-4), and only it:
 * Composer's autoloader finds a class by its path, so a file declaring
 * another name is never loaded for its own and, loaded for the class it
 * names, declares one already declared elsewhere.
 * src/EntitySubscriber/HookExtensionSubscriber.php declared a second
 * Omnibridge\Doctrine\Omnibase\EntitySubscriber\ExtensionSubscriber, an older copy nothing loaded.
 */
class SourceFilesTest extends TestCase
{
    public function testEachFileDeclaresTheClassItsPathNames(): void
    {
        $src = \dirname(__DIR__).'/src';
        $wrong = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $expected = $file->getBasename('.php');
            foreach (self::declaredNames((string) file_get_contents($file->getPathname())) as $name) {
                if ($name !== $expected) {
                    $wrong[] = substr($file->getPathname(), \strlen($src) + 1).' declares '.$name;
                }
            }
        }

        $this->assertSame([], $wrong);
    }

    public function testTheDeadCopyOfExtensionSubscriberIsGone(): void
    {
        $this->assertFileDoesNotExist(\dirname(__DIR__).'/src/EntitySubscriber/HookExtensionSubscriber.php');
        // In omnibridge/doctrine since the plan "omnibase agnostic"'s step F3, its former name aliased by the core.
        $file = (string) (new \ReflectionClass(\Omnibridge\Doctrine\Omnibase\EntitySubscriber\ExtensionSubscriber::class))->getFileName();
        $this->assertSame(['ExtensionSubscriber'], self::declaredNames((string) file_get_contents($file)));
    }

    /** @return list<string> the classes, interfaces, traits and enums the code declares (not anonymous classes, not ::class) */
    private static function declaredNames(string $code): array
    {
        $tokens = array_values(array_filter(token_get_all($code), static fn ($token) => !\is_array($token) || !\in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)));
        $names = [];
        foreach ($tokens as $i => $token) {
            if (!\is_array($token) || !\in_array($token[0], [\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM], true)) {
                continue;
            }
            $previous = $tokens[$i - 1] ?? null;
            if (\is_array($previous) && \in_array($previous[0], [\T_DOUBLE_COLON, \T_NEW], true)) {
                continue;
            }
            $next = $tokens[$i + 1] ?? null;
            if (\is_array($next) && \T_STRING === $next[0]) {
                $names[] = $next[1];
            }
        }

        return $names;
    }
}
