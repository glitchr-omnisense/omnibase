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
     * @var ?string
     */
    public /*?string*/ $identifier;

    /**
     * @var ?string
     */
    public /*?string*/ $password;
}

