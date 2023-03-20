<?php

namespace Base\Twig;

use Base\Routing\RouterInterface;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use Base\Twig\Renderer\TagRendererInterface;
use Exception;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\LoaderInterface;

class Environment extends TwigEnvironment
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var LocalizerInterface
     */
    protected $localizer;

    /**
     * @var Environment
     */
    protected $environment;

    public function __construct(LoaderInterface $loader, array $options, RequestStack $requestStack, LocalizerInterface $localizer, RouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->requestStack   = $requestStack;
        $this->router         = $router;
        $this->parameterBag   = $parameterBag;
        $this->localizer = $localizer;

        parent::__construct($loader, $options);
    }

    public function getParameter(string $name = "")
    {
        if (!$name) {
            return $this->getGlobals();
        }

        return (array_key_exists($name, $this->getGlobals())) ? $this->getGlobals()[$name] : null;
    }

    public function addGlobal(string $name, $value): bool
    {
        try {
            parent::addGlobal($name, $value);
        } catch (LogicException $e) {
            return false;
        }

        return true;
    }

    public function hasParameter(string $name)
    {
        return $this->getGlobals()[$name] ?? null;
    }
    public function setParameter(string $name, $value)
    {
        return $this->addGlobal($name, $value);
    }
    public function addParameter(string $name, $newValue)
    {
        $value = $this->getParameter($name);
        if ($value == null) {
            $value = $newValue;
        } else {
            if (is_string($value)) {
                $value .= "\n" . $newValue;
            } elseif (is_array($value)) {
                $value += array_merge($value, $newValue);
            } elseif (is_numeric($value)) {
                $value += $newValue;
            } elseif (is_object($value) && is_object($newValue) && method_exists($value, '__add')) {
                $value += $newValue;
            } else {
                throw new Exception("Ambiguity for merging the two \"$name\" entities..");
            }
        }

        return $this->addGlobal($name, $value);
    }

    public function appendParameter($name, $value)
    {
        $parameter = $this->getGlobals()[$name] ?? null;
        if (is_string($parameter)) {
            $this->addGlobal($name, $parameter.$value);
        }
        if (is_array($parameter)) {
            $this->addGlobal($name, array_merge($parameter, $value));
        }
        throw new Exception("Unknown merging method for \"$name\"");
    }

    protected array $renderers = [];
    public function addRenderer(TagRendererInterface $renderer)
    {
        $this->renderers[] = $renderer;
    }
    public function getRenderer(string $className): ?TagRendererInterface
    {
        foreach ($this->renderers as $renderer) {
            if (is_instanceof($renderer, $className)) {
                return $renderer;
            }
        }

        return null;
    }

    public function getAsset(string $url): string
    {
        return $this->getRenderer(HtmlTagRenderer::class)?->getAsset($url);
    }

    public function render($name, array $context = []): string
    {
        $useCustomTwig = $this->parameterBag->get("base.parameter_bag.use_setting_bag") ?? false;
        if (!$useCustomTwig) {
            return parent::render($name, $context);
        }

        $contents = $this->getRenderer(HtmlTagRenderer::class)?->render($name, $context);
        if ($contents === null) {
            throw new RuntimeException(HtmlTagRenderer::class." renderer not found.");
        }

        return $contents;
    }
}
