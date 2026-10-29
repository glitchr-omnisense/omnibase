<?php

namespace Tests\Base\Field;

use Base\Field\Type\EditorType;
use Base\Field\Type\WysiwygType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * EditorType and WysiwygType default empty_data to null: written 'empty_data', null, they
 * declared options 0 and 1 and kept the hidden field's "" for an empty submission.
 */
final class EmptyDataTest extends KernelTestCase
{
    public function testAnEmptySubmissionIsNull(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();
        foreach ([EditorType::class, WysiwygType::class] as $type) {
            $config = static::getContainer()->get('form.factory')->create($type)->getConfig();
            self::assertNull($config->getOption('empty_data'), $type);
            self::assertFalse($config->hasOption(0), $type.': no option 0');
        }
    }
}
