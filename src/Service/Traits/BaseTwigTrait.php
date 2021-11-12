<?php

namespace Base\Service\Traits;

use Base\Service\BaseService;
use Base\Twig\BaseTwigExtension;
use Symfony\Component\Config\Definition\Exception\Exception;
use Twig\Environment;

trait BaseTwigTrait {

    private $formFactory;
    public function getFormFactory() { return $this->formFactory; }

    /**
     *  Twig related methods
     */
    public function getParameterTwig(string $name = "")
    {
        if (!isset(BaseService::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        $globals = BaseService::$twig->getGlobals();
        if(!$name) return $globals;

        return (array_key_exists($name, $globals)) ? $globals[$name] : null;
    }

    public function addParameterTwig(string $name, $newValue)
    {
        if (!isset(BaseService::$twig))
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

        return BaseService::$twig->addGlobal($name, $value);
    }

    public function hasParameterTwig(string $name)
    {
        if (!isset(BaseService::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        return BaseService::$twig->getGlobals()[$name] ?? null;
    }

    public function setParameterTwig(string $name, $value)
    {
        if (!isset(BaseService::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        return BaseService::$twig->addGlobal($name, $value);
    }

    public function appendParameterTwig($name, $value)
    {
        if (!isset(BaseService::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded BaseService::__construct ?");

        $parameter = BaseService::$twig->getGlobals()[$name] ?? null;
        if(is_string($parameter)) BaseService::$twig->addGlobal($name, $parameter.$value);
        if( is_array($parameter)) BaseService::$twig->addGlobal($name, array_merge($parameter,$value));
        throw new Exception("Unknown merging method for \"$name\"");
    }

    /**
     * Handling resource files
     */
    public function isValidUrl($url): bool
    {
        $regex  = "((https?|ftp)\:\/\/)?"; // SCHEME
        $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
        $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})"; // Host or IP
        $regex .= "(\:[0-9]{2,5})?"; // Port
        $regex .= "(([a-z0-9+\$_-]\.?)+)*\/?"; // Path
        $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query
        $regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?"; // Anchor

        return preg_match("/^$regex$/i", $url); // `i` flag for case-insensitive
    }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);
        if($parseUrl["scheme"] ?? false)
            return $url;

        $path = $parseUrl["path"];
        if(!str_starts_with($path, "/"))
            $path = $this->rstack->getCurrentRequest()->getBasePath()."/".$path;

        return $path;
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

        $relationship = $this->getFileRelationship($contentOrArrayOrFile);
        if(!$relationship) {

            $content = $contentOrArrayOrFile;
        
        } else {

            // Compute options
            $relationship = $options["rel"] ?? $relationship;
            unset($options["rel"]);

            $attributes = "";
            foreach($options as $attribute => $value)
                $attributes .= " ".trim($attribute)."='".$value."'";

            // Convert into html tag
            switch($relationship) {

                case "javascript":
                    $content = "<script src='".$contentOrArrayOrFile."' ".trim($attributes)."></script>";
                    break;

                default:
                    $content = "<link rel='".$relationship."' href='".$contentOrArrayOrFile."' ".trim($attributes).">";
                    break;
            }
        }

        if(!array_key_exists($location, $this->htmlContent))
            $this->htmlContent[$location] = [];

        $this->htmlContent[$location][] = $content;

        return $this;
    }

    public function getFileRelationship(string $file)
    {
        $extension = pathinfo(parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION);
        if(empty($extension)) return null;
        
        switch($extension)
        {
            case "ico": 
                return "icon";
            
            case "css": 
                return "stylesheet";

            case "js": 
                return "javascript";

            default:
                return "preload";
        }
    }
}
