<?php

namespace Base\Twig\Renderer\Adapter;

use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Twig\Environment;
use Base\Twig\Renderer\AbstractTagRenderer;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;

class HtmlTagRenderer extends AbstractTagRenderer
{
    public function __construct(Environment $twig, LocaleProviderInterface $localeProvider, ParameterBagInterface $parameterBag, RequestStack $requestStack)
    {
        parent::__construct($twig, $localeProvider);
        $this->parameterBag = $parameterBag;
        $this->requestStack = $requestStack;
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

    protected $htmlContent = [];
    public function renderHtmlContent(string $location)
    {
        $htmlContent = $this->getHtmlContent($location);
        if(!empty($htmlContent))
            $this->removeHtmlContent($location);

        return $htmlContent;
    }

    public function getHtmlContent(string $location) { return trim(implode(PHP_EOL,array_unique($this->htmlContent[$location] ?? []))); }
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

    public function render(string $name, array $context = []): string
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

            if($this->twig->getLoader()->exists($basename.".".$locale.".".$extension))
                $name = $basename.".".$locale.".".$extension;
            if($this->twig->getLoader()->exists($basename.".".$lang.".".$extension))
                $name = $basename.".".$lang.".".$extension;
            if($this->twig->getLoader()->exists($basename.".".$defaultLocale.".".$extension))
                $name = $basename.".".$locale.".".$extension;
            if($this->twig->getLoader()->exists($basename.".".$defaultLang.".".$extension))
                $name = $basename.".".$lang.".".$extension;
        }

        //
        // Load resources: additional stylesheets & javascripts
        if(str_ends_with($name, ".html.twig")) {

            $stylesheet = str_rstrip($name, ".html.twig").".css.twig";
            if($this->twig->getLoader()->exists($stylesheet)) {
                $stylesheet = $this->twig->load($stylesheet)->render($context);
                if($stylesheet) $this->addHtmlContent("stylesheets:after", "<style>".$stylesheet."</style>");
            }

            $formats = [];
            $breakpoints = $this->parameterBag->get("base.twig.breakpoints") ?? [];
            foreach($breakpoints as $breakpoint)
                $formats[$breakpoint["name"]] = $breakpoint["media"];

            foreach($formats as $format => $media) {

                $stylesheet = str_rstrip($name, ".html.twig").".".$format.".css.twig";
                if($this->twig->getLoader()->exists($stylesheet)) {
                    $stylesheet = $this->twig->load($stylesheet)->render($context);
                    if($stylesheet) $this->addHtmlContent("stylesheets:after", "<style media='".$media."'>".$stylesheet."</style>");
                }
            }

            $javascript = str_rstrip($name, ".html.twig").".js.twig";
            if($this->twig->getLoader()->exists($javascript)) {

                $javascript = $this->twig->load($javascript)->render($context);
                if($javascript) $this->addHtmlContent("javascripts:body", "<script>".$javascript."</script>");
            }
        }

        try { return $this->twig->load($name)->render($context); }
        catch (LoaderError $e) { throw new RuntimeException("File not found `".$name."` in any of the provided templates", $e->getCode(), $e); }
    }

    public function renderFallback(Response $response): Response
    {
        $content = $response->getContent();

        $noscripts   = $this->getHtmlContent("noscripts");
        $content = preg_replace('/<body\b[^>]*>/', "$0".$noscripts, $content, 1);

        $stylesheetsHead = $this->getHtmlContent("stylesheets:before");
        $content = preg_replace('/(head\b[^>]*>)(.*?)(<link|<style)/s', "$1$2".$stylesheetsHead."$3", $content, 1);
        $stylesheets = $this->getHtmlContent("stylesheets");
        $content = preg_replace('/<\/head\b[^>]*>/', $stylesheets."$0", $content, 1);
        $stylesheets = $this->getHtmlContent("stylesheets:after");
        $content = preg_replace('/<\/head\b[^>]*>/', $stylesheets."$0", $content, 1);

        $javascriptsHead = $this->getHtmlContent("javascripts:head");
        $content = preg_replace('/(head\b[^>]*>)(.*?)(<script)/s', "$1$2".$javascriptsHead."$3", $content, 1);
        $javascripts = $this->getHtmlContent("javascripts");
        $content = preg_replace('/<\/head\b[^>]*>/', $javascripts."$0", $content, 1);
        $javascriptsBody = $this->getHtmlContent("javascripts:body");
        $content = preg_replace('/<\/body\b[^>]*>/', "$0".$javascriptsBody, $content, 1);

        $response->setContent($content);

        return $response;
    }
}