<?php

namespace Tests\Base\Fixtures\Breadcrumb;

use Base\Attributes\Attribute\Iconize;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reflected by Breadcrumb::compute() via a real AttributeReader::getReflClass()
 * call — a plain class (not wired into any container) is enough since
 * compute() only needs PHP attribute reflection on the matched controller
 * action, not an instantiated controller.
 */
class FixtureController
{
    #[Route('/foo', name: 'app_foo')]
    #[Iconize('fa-solid fa-house')]
    public function fooAction(): void
    {
    }

    #[Route('/foo/{id}', name: 'app_foo_show')]
    #[Iconize('fa-solid fa-file')]
    public function showAction(): void
    {
    }
}
