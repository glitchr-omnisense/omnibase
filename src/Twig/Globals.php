<?php

namespace Base\Twig;

use Base\Service\BaseService;
use Base\Twig\AppVariable;
use Base\Twig\Variable\RandomVariable;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class Globals extends AbstractExtension implements GlobalsInterface
{
    private BaseService $base;
    private AppVariable $app;
    private RandomVariable $random;

    public function __construct(
        BaseService $base,
        AppVariable $app,
        RandomVariable $random
    ) {
        $this->base = $base;
        $this->app = $app;
        $this->random = $random;
    }

    public function getGlobals(): array
    {
        return [
            'server' => $_SERVER,
            'base'   => $this->base,
            'app'    => $this->app,
            'random' => $this->random,
        ];
    }
}