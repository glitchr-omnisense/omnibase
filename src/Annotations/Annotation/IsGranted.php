<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use function is_string;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Symfony\Component\ExpressionLanguage\Expression;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "METHOD"})
 */

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class IsGranted extends AbstractAnnotation
{
    /**
     * @param array|string $data
     * @param mixed|null $subject
     * @param string|null $message
     * @param int|null $statusCode
     */
    public function __construct(
        /**
         * Sets the first argument that will be passed to isGranted().
         */
        array|string|Expression $attributes,

        /**
         * Sets the second argument passed to isGranted().
         *
         * @var array<string|Expression>|string|Expression|null
         */
        array|string|Expression|null $subject = null,

        /**
         * The message of the exception - has a nice default if not set.
         */
        ?string $message = null,

        /**
         * If set, will throw HttpKernel's HttpException with the given $statusCode.
         * If null, Security\Core's AccessDeniedException will be used.
         */
        ?int $statusCode = null,
    ) {

        $attributes = is_string($attributes) ? [$attributes] : $attributes;
        if (!$attributes) {
            throw new MissingConstructorArgumentsException("Attribute parameter missing", 500);
        }

        $this->setSubject($subject);
        $this->setMessage($message);
        $this->setStatusCode($statusCode);
        $this->setAttributes($attributes);
    }
    
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
