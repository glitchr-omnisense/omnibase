<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraints\FileMimeType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class FileMimeTypeValidator extends ConstraintValidator
{
    public function validate($entry, Constraint $constraint)
    {
        if (!$constraint instanceof FileMimeType)
            throw new UnexpectedTypeException($constraint, FileMimeType::class);

        if (null === $entry || '' === $entry)
            return;

        if (!$entry instanceof UploadedFile)
            return;

        $mimeTypes = $constraint->getAllowedMimeTypes();
        $types = array_map(function($mimeType) {
            $type = explode("/", $mimeType);
            return end($type);
        }, $mimeTypes);

        $compatibleMimeType = empty($mimeTypes);
        foreach ($mimeTypes as $mimeType)
            $compatibleMimeType |= preg_match("/" . str_replace("/", "\/", $mimeType) . "/", $entry->getMimeType());

        if (!$compatibleMimeType) {

            $this->context->buildViolation($constraint->message)
                ->setParameter('{0}', count($mimeTypes))
                ->setParameter('{1}', implode(", ", $types))
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}