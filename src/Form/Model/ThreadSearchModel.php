<?php

namespace Base\Form\Model;

use Base\Form\Common\AbstractModel;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class ThreadSearchModel extends AbstractModel
{
    public $parent_id;

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
