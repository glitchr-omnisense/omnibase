<?php

namespace Base\Form\Model;

use Base\Form\Common\AbstractModel;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class SecurityLoginTokenModel extends AbstractModel
{
    /**
     * @var ?string
     */
    public /*?string*/ $email;
}
