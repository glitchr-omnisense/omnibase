<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use function is_string;

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
    protected mixed $attributes;

    /**
     * Sets the second argument passed to isGranted().
     *
     * @var mixed
     */
    protected mixed $subject;

    /**
     * The message of the exception - has a nice default if not set.
     *
     * @var ?string
     */
    protected ?string $message;

    /**
     * If set, will throw Symfony\Component\HttpKernel\Exception\HttpException
     * with the given $statusCode.
     * If null, Symfony\Component\Security\Core\Exception\AccessDeniedException.
     * will be used.
     *
     * @var int|null
     */
    protected ?int $statusCode;

    /**
     * @param array|string $data
     * @param mixed|null $subject
     * @param string|null $message
     * @param int|null $statusCode
     */
    public function __construct(
        array|string $data = [],
        mixed        $subject = null,
        string       $message = null,
        ?int         $statusCode = null
    )
    {
        $values = [];
        if (is_string($data)) {
            $values['attributes'] = $data;
        } else {
            $values = $data;
        }

        if (!array_key_exists("value", $values)) {
            throw new MissingConstructorArgumentsException("Attribute parameter missing", 500);
        }

        $this->setSubject($values['subject'] ?? $subject);
        $this->setMessage($values['message'] ?? $message);
        $this->setStatusCode($values['statusCode'] ?? $statusCode);
        $this->setAttributes($values['value'] ?? null);
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return true;
    }

    /**
     * @param $attributes
     * @return void
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param $subject
     * @return void
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string|null
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param $message
     * @return void
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return int|null
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param $statusCode
     * @return void
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @param $value
     * @return void
     */
    public function setValue($value)
    {
        $this->setAttributes($value);
    }

    /**
     * @return string
     */
    public function getAliasName()
    {
        return 'is_granted';
    }

    /**
     * @return true
     */
    public function allowArray()
    {
        return true;
    }
}
