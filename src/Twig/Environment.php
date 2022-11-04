<?php

namespace Base\Twig;

use Base\Routing\RouterInterface;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Loader\ChainLoader;
use Twig\Loader\LoaderInterface;
use Twig\Template;
use Twig\TemplateWrapper;

class Environment extends TwigEnvironment
{
    public function __construct(LoaderInterface $loader, array $options, RequestStack $requestStack, LocaleProviderInterface $localeProvider, RouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->requestStack   = $requestStack;
        $this->router         = $router;
        $this->parameterBag   = $parameterBag;
        $this->localeProvider = $localeProvider;

        parent::__construct($loader, $options);
    }

    public function render($name, array $context = []): string
    {
        //
        // Make sure to load localized twig template, if available.
        if(str_ends_with($name, ".twig")) {

            $basename = explode(".", $name);

            $extension = [];
            $extension[] = array_pop($basename);
            $extension[] = array_pop($basename);
            $extension = implode(".", array_reverse($extension));
            $basename  = implode(".", $basename); 

            $lang           = $this->localeProvider->getLang();
            $defaultLang    = $this->localeProvider->getDefaultLang();
            $locale         = str_replace("-", "_", $this->localeProvider->getLocale());
            $defaultLocale  = str_replace("-", "_", $this->localeProvider->getDefaultLocale());

            if($this->getLoader()->exists($basename.".".$locale.".".$extension))
                $name = $basename.".".$locale.".".$extension;
            if($this->getLoader()->exists($basename.".".$lang.".".$extension))
                $name = $basename.".".$lang.".".$extension;
            if($this->getLoader()->exists($basename.".".$defaultLocale.".".$extension))
                $name = $basename.".".$locale.".".$extension;
            if($this->getLoader()->exists($basename.".".$defaultLang.".".$extension))
                $name = $basename.".".$lang.".".$extension;
        }

        //
        // Load resources: additional stylesheets & javascripts
        if(str_ends_with($name, ".html.twig")) {

            $stylesheet = str_rstrip($name, ".html.twig").".css.twig";
            if($this->getLoader()->exists($stylesheet)) {
                $stylesheet = $this->load($stylesheet)->render($context);
                if($stylesheet) $this->addHtmlContent("stylesheets:after", "<style>".$stylesheet."</style>");
            }

            $formats = [];
            $breakpoints = $this->parameterBag->get("base.twig.breakpoints") ?? [];
            foreach($breakpoints as $breakpoint)
                $formats[$breakpoint["name"]] = $breakpoint["media"];

            foreach($formats as $format => $media) {

                $stylesheet = str_rstrip($name, ".html.twig").".".$format.".css.twig";
                if($this->getLoader()->exists($stylesheet)) {
                    $stylesheet = $this->load($stylesheet)->render($context);
                    if($stylesheet) $this->addHtmlContent("stylesheets:after", "<style media='".$media."'>".$stylesheet."</style>");
                }
            }

            $javascript = str_rstrip($name, ".html.twig").".js.twig";
            if($this->getLoader()->exists($javascript)) {

                $javascript = $this->load($javascript)->render($context);
                if($javascript) $this->addHtmlContent("javascripts:body", "<script>".$javascript."</script>");
            }
        }

        try { return $this->load($name)->render($context); }
        catch (LoaderError $e) { throw new RuntimeException("File not found `".$name."` in any of the provided templates", $e->getCode(), $e); }
    }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parse = parse_url($url);
        if($parse["scheme"] ?? false)
            return $url;

        $request = $this->requestStack->getCurrentRequest();

        $baseDir = $request ? $request->getBasePath() : $this->router->getBaseDir();
        $baseDir = $baseDir ."/";
        $path = trim($parse["path"]);
        if($path == "/") return $baseDir ? $baseDir : "/";
        else if(!str_starts_with($path, "/"))
            $path = $baseDir.$path;

        return $path ? $path : null;
    }

    public function getParameter(string $name = "")
    {
        $globals = $this->getGlobals();
        if(!$name) return $globals;

        return (array_key_exists($name, $globals)) ? $globals[$name] : null;
    }

    public function addParameter(string $name, $newValue)
    {
        $value = $this->getParameter($name);
        if ($value == null) $value = $newValue;
        else {

            if (is_string($value)) $value .= "\n" . $newValue;
            else if (is_array($value)) $value += array_merge($value, $newValue);
            else if (is_numeric($value)) $value += $newValue;
            else if (is_object($value) && is_object($newValue) && method_exists($value, '__add')) $value += $newValue;
            else throw new Exception("Ambiguity for merging the two \"$name\" entities..");
        }

        return $this->addGlobal($name, $value);
    }

    public function hasParameter(string $name)
    {
        return $this->getGlobals()[$name] ?? null;
    }

    public function setParameter(string $name, $value)
    {
        return $this->addGlobal($name, $value);
    }

    public function appendParameter($name, $value)
    {
        $parameter = $this->getGlobals()[$name] ?? null;
        if(is_string($parameter)) $this->addGlobal($name, $parameter.$value);
        if( is_array($parameter)) $this->addGlobal($name, array_merge($parameter,$value));
        throw new Exception("Unknown merging method for \"$name\"");
    }

    private $htmlContent = [];

    public function renderHtmlContent(string $location)
    {
        $htmlContent = $this->getHtmlContent($location);
        if(!empty($htmlContent))
            $this->removeHtmlContent($location);

        return $htmlContent;
    }

    public function getHtmlContent(string $location)
    {
        return trim(implode(PHP_EOL,array_unique($this->htmlContent[$location] ?? [])));
    }

    public function removeHtmlContent(string $location)
    {
        if(array_key_exists($location, $this->htmlContent))
            unset($this->htmlContent[$location]);

        return $this;
    }

    public function addHtmlContent(string $location, $contentOrArrayOrFile, array $options = [])
    {
        if(empty($contentOrArrayOrFile)) return $this;

        if(is_array($contentOrArrayOrFile)) {

            foreach($contentOrArrayOrFile as $content)
                $this->addHtmlContent($location, $content, $options);

            return $this;
        }

        $relationship = pathinfo_relationship($contentOrArrayOrFile);
        if(!$relationship) $content = $contentOrArrayOrFile;
        else {

            // Compute options
            $relationship = $options["rel"] ?? $relationship;
            array_values_remove($options, "rel");

            $attributes = html_attributes($options);

            // Convert into html tag
            switch($relationship) {

                case "javascript":
                    $content = "<script src='".$this->getAsset($contentOrArrayOrFile)."' ".$attributes."></script>";
                    break;

                case "icon":
                case "preload":
                case "stylesheet":
                default:
                    $content = "<link rel='".$relationship."' href='".$this->getAsset($contentOrArrayOrFile)."' ".$attributes.">";
                    break;
            }
        }

        if(!array_key_exists($location, $this->htmlContent))
            $this->htmlContent[$location] = [];

        $this->htmlContent[$location][] = $content;
        return $this;
    }
}