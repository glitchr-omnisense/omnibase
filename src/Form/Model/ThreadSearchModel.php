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
     * @var ?string
     */
    public /*?string*/ $generic;

    /**
     * @var ?string
     */
    public /*?string*/ $title;

    /**
     * @var ?string
     */
    public /*?string*/ $excerpt;

    /**
     * @var ?string
     */
    public /*?string*/ $content;
}
