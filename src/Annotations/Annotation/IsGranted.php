<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;

/**
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class IsGranted extends AbstractAnnotation
{
    /**
     * Sets the first argument that will be passed to isGranted().
     *
     * @var mixed
     */
    private $attributes;

    /**
     * Sets the second argument passed to isGranted().
     *
     * @var mixed
     */
    private $subject;

    /**
     * The message of the exception - has a nice default if not set.
     *
     * @var string
     */
    private $message;

    /**
     * If set, will throw Symfony\Component\HttpKernel\Exception\HttpException
     * with the given $statusCode.
     * If null, Symfony\Component\Security\Core\Exception\AccessDeniedException.
     * will be used.
     *
     * @var int|null
     */
    private $statusCode;

    /**
     * @param mixed        $subject
     * @param array|string $data
     */
    public function __construct(
        $data = [],
        $subject = null,
        string $message = null,
        ?int $statusCode = null
    ) {
        $values = [];
        if (\is_string($data)) {
            $values['attributes'] = $data;
        } else {
            $values = $data;
        }

        if(!array_key_exists("value", $values))
            throw new MissingConstructorArgumentsException("Attribute parameter missing", 500);

        $this->setSubject   ($values['subject'] ?? $subject);
        $this->setMessage   ($values['message'] ?? $message);
        $this->setMessage   ($values['statusCode'] ?? $statusCode);
        $this->setAttributes($values['value'] ?? null);
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool { return true; }
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    public function setValue($value)
    {
        $this->setAttributes($value);
    }

    public function getAliasName()
    {
        return 'is_granted';
    }

    public function allowArray()
    {
        return true;
    }
}