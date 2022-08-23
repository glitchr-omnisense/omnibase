<?php

namespace Base\Form\Model;

use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class ThreadSearchModel
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
