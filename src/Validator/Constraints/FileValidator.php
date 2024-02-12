<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraints\File as ConstraintsFile;
use Base\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FileValidator extends ConstraintValidator
{
    /**
     * @param $value
     * @param Constraint $constraint
     * @return void
     */
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof File) {
            return;
        }

        if (!$constraint instanceof ConstraintsFile) {
            throw new UnexpectedTypeException($constraint, ConstraintsFile::class);
        }

        $mimeTypes = $constraint->getAllowedMimeTypes();
        $types = array_map(function ($mimeType) {
            $type = explode("/", $mimeType);
            return end($type);
        }, $mimeTypes);

        $compatibleMimeType = empty($mimeTypes);
        foreach ($mimeTypes as $mimeType) {
            $compatibleMimeType |= preg_match("/" . str_replace("/", "\/", $mimeType) . "/", $value->getMimeType());
        }

        if (!$compatibleMimeType) {
            $constraint->message = $constraint->messageMimeType;
            $this->buildViolation($constraint, $value)
                ->setParameter('{0}', count($mimeTypes))
                ->setParameter('{1}', implode(", ", $types))
                ->addViolation();
        } elseif ($value->getSize() > $constraint->getMaxSize()) {
            $constraint->message = $constraint->messageMaxSize;
            $this->buildViolation($constraint, $value)
                ->setParameter('{1}', byte2str($value->getSize()))
                ->setParameter('{0}', byte2str($constraint->getMaxSize()))
                ->addViolation();
        }
    }
}
