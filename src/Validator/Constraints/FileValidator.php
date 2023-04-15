<?php

namespace Base\Validator\Constraints;

use Base\Validator\Constraints\File as ConstraintsFile;
use Base\Validator\Constraints\FileSize;
use Base\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class FileValidator extends ConstraintValidator
{
    public function validate($entry, Constraint $constraint)
    {
        if (null === $entry || '' === $entry) {
            return;
        }

        if (!$entry instanceof File) {
            return;
        }

        if (!$constraint instanceof ConstraintsFile) {
            throw new UnexpectedTypeException($constraint, FileSize::class);
        }

        $mimeTypes = $constraint->getAllowedMimeTypes();
        $types = array_map(function ($mimeType) {
            $type = explode("/", $mimeType);
            return end($type);
        }, $mimeTypes);

        $compatibleMimeType = empty($mimeTypes);
        foreach ($mimeTypes as $mimeType) {
            $compatibleMimeType |= preg_match("/" . str_replace("/", "\/", $mimeType) . "/", $entry->getMimeType());
        }

        if (!$compatibleMimeType) {

            $constraint->message = $constraint->messageMimeType;
            $this->buildViolation($constraint, $entry)
                ->setParameter('{0}', count($mimeTypes))
                ->setParameter('{1}', implode(", ", $types))
                ->addViolation();

        } elseif ($entry->getSize() > 8*$constraint->getMaxSize()) {

            $constraint->message = $constraint->messageMaxSize;
            $this->buildViolation($constraint, $entry)
                ->setParameter('{1}', byte2str($entry->getSize()))
                ->setParameter('{0}', byte2str(8*$constraint->getMaxSize()))
                ->addViolation();
        }
    }
}
