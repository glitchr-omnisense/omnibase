<?php

namespace Base\Form\Model;

use Base\Form\Common\AbstractModel;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class SecurityLoginModel extends AbstractModel
{
    /**
     * @var string
     */
    public $identifier;

    /**
     * @var string
     */
    public $password;
}
