<?php

namespace Tests\Base\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * round() keeps its decimals (it was declared : int and printed 3 for round(3.14159, 2), with
 * a deprecation), and |color_name names a colour, the nearest CSS named colour: it read a
 * "colors" resource bundle symfony/intl does not ship, a MissingResourceException each time.
 */
final class MathAndColourFunctionsTest extends KernelTestCase
{
    private function render(string $template): string
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();

        return static::getContainer()->get('twig')->createTemplate($template)->render();
    }

    public function testRoundKeepsItsDecimals(): void
    {
        self::assertSame('3.14|3|4', $this->render('{{ round(3.14159, 2) }}|{{ round(3.4) }}|{{ round(3.5) }}'));
    }

    public function testAColourIsNamed(): void
    {
        self::assertSame('red|rebeccapurple|white', $this->render("{{ '#ff0001'|color_name }}|{{ '#663399'|color_name }}|{{ '#FEFEFE'|color_name }}"));
    }
}
