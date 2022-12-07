<?php

namespace Base\Subscriber;

use Base\Annotations\Annotation\IsGranted;
use Base\Annotations\AnnotationReader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class IsGrantedSubscriber implements EventSubscriberInterface
{
    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @var AnnotationReader
     */
    private $annotationReader;

    public function __construct(AnnotationReader $annotationReader, AuthorizationCheckerInterface $authorizationChecker = null)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->annotationReader = $annotationReader;
    }

    public function onKernelControllerArguments(KernelEvent $event)
    {
        $request = $event->getRequest();

        $controller = $request->attributes->get("_controller") ;
        list($class, $method) = is_array($controller) ? $controller : explode("::", $controller ?? "");
        if(!class_exists($class)) return;

        $configurations = array_merge(
            $this->annotationReader->getClassAnnotations ($class, IsGranted::class),
            $this->annotationReader->getMethodAnnotations($class, IsGranted::class)[$method] ?? []
        );

        if (null === $this->authorizationChecker) {
            throw new \LogicException('To use the @IsGranted tag, you need to install symfony/security-bundle and configure your security system.');
        }

        $arguments = $request->attributes->get("_route_parameters");
        foreach ($configurations as $configuration) {

            $subjectRef = $configuration->getSubject();
            $subject = null;

            if ($subjectRef) {
                if (\is_array($subjectRef)) {
                    foreach ($subjectRef as $ref) {
                        if (!\array_key_exists($ref, $arguments)) {
                            throw $this->createMissingSubjectException($ref);
                        }

                        $subject[$ref] = $arguments[$ref];
                    }
                } else {
                    if (!\array_key_exists($subjectRef, $arguments)) {
                        throw $this->createMissingSubjectException($subjectRef);
                    }

                    $subject = $arguments[$subjectRef];
                }
            }

            if (!$this->authorizationChecker->isGranted($configuration->getAttributes(), $subject)) {
                $argsString = $this->getIsGrantedString($configuration);

                $message = $configuration->getMessage() ?: sprintf('Access Denied by controller annotation @IsGranted(%s)', $argsString);

                if ($statusCode = $configuration->getStatusCode()) {
                    throw new HttpException($statusCode, $message);
                }

                $accessDeniedException = new AccessDeniedException($message);
                $accessDeniedException->setAttributes($configuration->getAttributes());
                $accessDeniedException->setSubject($subject);

                throw $accessDeniedException;
            }
        }
    }

    private function createMissingSubjectException(string $subject)
    {
        return new \RuntimeException(sprintf('Could not find the subject "%s" for the @IsGranted annotation. Try adding a "$%s" argument to your controller method.', $subject, $subject));
    }

    private function getIsGrantedString(IsGranted $isGranted)
    {
        $attributes = array_map(function ($attribute) {
            return sprintf('"%s"', $attribute);
        }, (array) $isGranted->getAttributes());
        if (1 === \count($attributes)) {
            $argsString = reset($attributes);
        } else {
            $argsString = sprintf('[%s]', implode(', ', $attributes));
        }

        if (null !== $isGranted->getSubject()) {
            $argsString = sprintf('%s, %s', $argsString, $isGranted->getSubject());
        }

        return $argsString;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'];
    }
}