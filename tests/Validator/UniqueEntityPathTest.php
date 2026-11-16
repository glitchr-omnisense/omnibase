<?php

namespace Tests\Base\Validator;

use Base\Validator\Constraints\UniqueEntity;
use Base\Validator\Constraints\UniqueEntityValidator;
use PHPUnit\Framework\TestCase;

/**
 * UniqueEntity's violation lands on the field (errorPath, else the first field), as symfony/doctrine-bridge's does:
 * the validator asks the builder atPath($errorPath) right after building the violation, with the invalid value, the
 * records found as the cause and UniqueEntity's code. The lookup itself needs Doctrine's metadata (the harness: a
 * site's form test shows the error on the field); the building step is checked here on the validator's code.
 */
final class UniqueEntityPathTest extends TestCase
{
    public function testTheViolationIsAttachedToTheErrorPath(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(UniqueEntityValidator::class))->getFileName());
        self::assertMatchesRegularExpression('/\\$errorPath = null !== \\$constraint->errorPath \\? \\$constraint->errorPath : \\$fields\\[0\\];/', $source, 'errorPath, else the first field');
        self::assertMatchesRegularExpression('/buildViolation\\(\\$constraint, \\$invalidValue\\)\\s*->atPath\\(\\$errorPath\\)\\s*->setInvalidValue\\(\\$invalidValue\\)\\s*->setCause\\(\\$result\\)/', $source);
        self::assertStringContainsString("::NOT_UNIQUE_ERROR'", $source);
        self::assertSame('23bd9dbf-6b9b-41cd-a99e-4844bcf3077f', UniqueEntity::NOT_UNIQUE_ERROR, 'UniqueEntity\'s code, the one symfony/doctrine-bridge gives');
        self::assertSame('target', (new UniqueEntity(['source', 'target'], errorPath: 'target'))->errorPath);
    }
}
