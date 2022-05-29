<?php

namespace Base\Form\Data\Thread;

use Base\Entity\Thread;

use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class SearchData
{
    /**
     * @var string
     */
    public $generic;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $excerpt;

    /**
     * @var string
     */
    public $content;
}
