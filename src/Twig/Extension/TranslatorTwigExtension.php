<?php

namespace Base\Twig\Extension;

use Base\Service\Localizer;
use Base\Service\LocalizerInterface;
use Base\Service\Translator;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TranslatorTwigExtension extends AbstractExtension
{
    /**
     * @var Localizer
     */
    protected $localizer;
    
    public function __construct(LocalizerInterface $localizer) { $this->localizer = $localizer; }

    public function getName() { return 'lang_extension'; }

    public function getFilters() : array
    {
        return [
            new TwigFilter('trans',        [Translator::class, 'trans']),
            new TwigFilter('trans_quiet',  [Translator::class, 'transQuiet']),
            new TwigFilter('trans_exists', [Translator::class, 'transExists']),

            new TwigFilter('trans_time',         [Translator::class, 'transTime']),
            new TwigFilter('trans_enum',         [Translator::class, 'transEnum']),
            new TwigFilter('trans_enumExists',         [Translator::class, 'transEnumExists']),
            new TwigFilter('trans_entity',       [Translator::class, 'transEntity']),
            new TwigFilter('trans_entityExists',       [Translator::class, 'transEntityExists']),
        ];
    }
}
