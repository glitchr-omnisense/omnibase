<?php

namespace Base\Form\Model;

use Base\Form\Common\AbstractModel;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class SecurityRegistrationModel extends AbstractModel
{
    /**
     * @var string
     */
    public string $email;

    /**
     * @var string
     */
    public string $plainPassword;

    /**
     * @var bool
     */
    public bool $agreeTerms;
}
