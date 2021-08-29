<?php

namespace Base\Service\Traits;

use Base\Twig\BaseTwigExtension;
use Symfony\Component\Config\Definition\Exception\Exception;
use Twig\Environment;

trait BaseTwigTrait {

    private $cssFile = [];
    private $cssBlock = [];

    private $jsFile = [];
    private $jsBlock = [];

    private $formFactory;
    public function getFormFactory()
    {
        return $this->formFactory;
    }

    /**
     *  Twig related methods
     */
    public function getParameterTwig(string $name)
    {
        if (!isset($this->twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        $globals = $this->twig->getGlobals();
        return (array_key_exists($name, $globals)) ? $globals[$name] : null;
    }

    public function addParameterTwig(string $name, $newValue)
    {
        if (!isset($this->twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        $value = $this->getParameterTwig($name);
        if ($value == null) $value = $newValue;
        else {

            if (is_string($value)) $value .= "\n" . $newValue;
            else if (is_array($value)) $value += array_merge($value, $newValue);
            else if (is_numeric($value)) $value += $newValue;
            else if (is_object($value) && is_object($newValue) && method_exists($value, '__add')) $value += $newValue;
            else throw new Exception("Ambiguity for merging the two \"$name\" entities..");
        }

        return $this->twig->addGlobal($name, $value);
    }

    public function stylesheets()
    {
        $cssHtml = "";

        $this->cssFile = array_unique($this->cssFile);
        foreach ($this->cssFile as $file)
            $cssHtml .= "<link rel=\"stylesheet\" href=\"$file\">";

        foreach ($this->cssBlock as $block)
            $cssHtml .= $block;

        return $cssHtml;
    }

    public function addStylesheet($stylesheet)
    {
        if ($this->isValidUrl($stylesheet))
            return $this->addStylesheetFile($stylesheet);

        return $this->addStylesheetCode($stylesheet);
    }

    public function addStylesheetFile($file)
    {
        if (!$this->isStylesheetFile($file)) throw new Exception("File $file is not a valid stylesheet extension");

        if (is_array($file)) $this->cssFile = $this->cssFile + $file;
        else $this->cssFile[] = $file;

        // Add information to EasyAdmin context (e.g. for instance if field uses select2)
        if ($this->adminContextProvider) {
            $adminContext = $this->adminContextProvider->getContext();
            if ($adminContext) $adminContext->getAssets()->addHtmlContentToHead("<link rel=\"stylesheet\" href=\"".$file."\">");
        }
        return $this;
    }
    public function addStylesheetCode($script)
    {

        $this->cssBlock[] = "<style>" . $script . "</style>";

        // Add information to EasyAdmin context (e.g. for instance if field uses select2)
        if ($this->adminContextProvider) {
            $adminContext = $this->adminContextProvider->getContext();
            if ($adminContext) $adminContext->getAssets()->addHtmlContentToHead(end($this->cssBlock));
        }

        return $this;
    }

    public function javascripts($location = "body")
    {

        $jsHtml = "";

        if (!isset($this->jsFile[$location])) $this->jsFile[$location] = [];
        $this->jsFile[$location] = array_unique($this->jsFile[$location]);
        foreach ($this->jsFile[$location] as $file) {

            $array = explode(" ", $file);
            $src   = $array[0];
            $type  = $array[1] ?? "";
            $jsHtml .= "<script src=\"".$src."\" ".$type."></script>" .PHP_EOL;
        }
        if (!isset($this->jsBlock[$location])) $this->jsBlock[$location] = [];
        foreach ($this->jsBlock[$location] as $block)
            $jsHtml .= $block . PHP_EOL;

        return $jsHtml;
    }

    public function isValidUrl($url): bool
    {

        $regex  = "((https?|ftp)\:\/\/)?"; // SCHEME
        $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
        $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})"; // Host or IP
        $regex .= "(\:[0-9]{2,5})?"; // Port
        $regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?"; // Path
        $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query
        $regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?"; // Anchor

        return preg_match("/^$regex$/i", $url); // `i` flag for case-insensitive
    }

    public function addResourceFile($files, $location = "head"): bool
    {

        if (is_array($files)) {

            $ret = true;
            foreach ($files as $file)
                $ret &= $this->addResourceFile($file, $location);

            return $ret;
        }

        if ($this->isJavascriptFile($files)) $this->addJavascriptFile($files, $location);
        elseif ($this->isStylesheetFile($files)) $this->addStylesheetFile($files);
        else throw new Exception("Unknown resource file provided: $files");

        return true;
    }

    public function isJavascriptFile(string $url): bool
    {
        $url = explode("?", $url)[0];
        return str_ends_with($url, "js");
    }

    public function isStylesheetFile(string $url): bool
    {
        return str_ends_with($url, "css");
    }

    public function addJavascript(string $javascript, $location = "body")
    {
        if ($this->isValidUrl($javascript))
            return $this->addJavascriptFile($javascript, $location);

        return $this->addJavascriptCode($javascript, $location);
    }

    public function addJavascriptFile(string $file, $location = "body")
    {
        if (!$file) return $this;

        if (!$this->isJavascriptFile($file)) throw new Exception("File $file is not a valid javascript file extension");
        if (!isset($this->jsFile[$location])) $this->jsFile[$location] = [];

        if (is_array($file)) $this->jsFile[$location] = $this->jsFile[$location] + $file;
        else $this->jsFile[$location][] = $file;

        // Add file to admin context (if necessary)
        if ($this->adminContextProvider) {
            $adminContext = $this->adminContextProvider->getContext();
            if ($adminContext) {

                if ($location == "head") $adminContext->getAssets()->addHtmlContentToHead("<script src=\"$file\"></script>" . PHP_EOL);
                else $adminContext->getAssets()->addHtmlContentToBody("<script src=\"$file\"></script>" . PHP_EOL);
            }
        }

        return $this;
    }
    public function addJavascriptCode(string $block, $location = "body")
    {
        if (!isset($this->jsBlock[$location])) $this->jsBlock[$location] = [];
        $this->jsBlock[$location][] = $block . PHP_EOL;

        // Add file to admin context (if necessary)
        if ($this->adminContextProvider) {

            $adminContext = $this->adminContextProvider->getContext();
            if ($adminContext) {

                if ($location == "head") $adminContext->getAssets()->addHtmlContentToHead(end($this->jsBlock[$location]));
                else $adminContext->getAssets()->addHtmlContentToBody(end($this->jsBlock[$location]));
            }
        }

        return $this;
    }

    public function setParameterTwig(string $name, $value)
    {
        if (!isset($this->twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        return $this->twig->addGlobal($name, $value);
    }

    public function issetTwig(string $name)
    {
        if (!isset($this->twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        return in_array($name, $this->twig->getGlobals());
    }
}