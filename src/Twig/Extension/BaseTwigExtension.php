<?php

namespace Base\Twig\Extension;

use Base\Service\BaseService;
use Base\Controller\BaseController;
use Base\Exception\NotFoundResourceException;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use Twig\Extra\Intl\IntlExtension;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\LoaderError;
use Twig\TwigFunction;

final class BaseTwigExtension extends AbstractExtension
{
    /**
     * @var AssertExtension
     */
    protected $assertExtension;

    /**
     * @var RoutingExtension
     */
    protected $routingExtension;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var string
     */
    protected string $projectDir;

    public function __construct(TranslatorInterface $translator, RoutingExtension $routingExtension, AssetExtension $assetExtension) {

        BaseController::$foundBaseTwigExtension = true;

        $this->translator       = $translator;
        $this->routingExtension = $routingExtension;
        $this->assetExtension   = $assetExtension;
        $this->intlExtension    = new IntlExtension();
        $this->mimeTypes        = new MimeTypes();
    }

    public function setBase(BaseService $baseService) 
    {
        $this->baseService = $baseService;
        $this->projectDir = $this->baseService->getProjectDir();

        return $this;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction("path",  [$this, "path"]),
            new TwigFunction('asset', [$this, 'asset'])
        ];
    }

    public function getFilters() : array
    {
        return [
            new TwigFilter('trans',         [$this, 'trans']),
            new TwigFilter('url',           [$this, 'url']),
            new TwigFilter('time',          [$this, 'time']),
            new TwigFilter('mimetype',      [$this, 'mimetype']),
            new TwigFilter('synopsis',      [$this, 'synopsis']),
            new TwigFilter('extension',     [$this, 'extension']),
            new TwigFilter('stringify',     [$this, 'stringify']),
            new TwigFilter('shorten',       [$this, 'shorten']),
            new TwigFilter('highlight',     [$this, 'highlight']),
            new TwigFilter('flatten_array', [$this, 'flattenArray']),
            new TwigFilter('filesize',      [$this, 'filesize']),
            new TwigFilter('lang',          [$this, 'lang']),
            new TwigFilter('country',       [$this, 'country']),
            new TwigFilter('fontAwesome',   [$this, 'fontAwesome']),
            new TwigFilter('imagify',       [$this, 'imagify']),
            new TwigFilter('image',         [$this, 'image'],       ['needs_environment' => true, 'needs_context' => true]),
            new TwigFilter('datetime',      [$this, 'datetime'],    ['needs_environment' => true]),
            new TwigFilter('lessThan',      [$this, 'lessThan']),
            new TwigFilter('greaterThan',   [$this, 'greaterThan'])
        ];
    }

    public function synopsis($class) { return class_synopsis($class); }

    public function fontAwesome(array|null|string $icon, array $attributes = []) 
    {
        if(!$icon) return $icon;
        if(is_array($icon)) {

            foreach($icon as $key => $_icon)
                $icon[$key] = $this->fontAwesome($_icon);

            return $icon;
        } 

        $ids = explode(" ", $icon);

        $isAwesome = false;
        foreach($ids as $id) {
            $isAwesome = in_array($id, ["fa", "far", "fab", "fas", "fad", "fal"]);
            if($isAwesome) break;
        }

        if($isAwesome) {

            $class = $attributes["class"] ?? "";
            $class = trim($class." ".$icon);
            if($attributes["class"] ?? false) unset($attributes["class"]);

            $htmlAttributes = "";
            foreach($attributes as $name => $attribute)
                $htmlAttributes .= $name."=\"".$attribute."\" ";

            return "<i ".trim($htmlAttributes)." class='".$class."'></i>";
        }
        
        return null;
    }

    public function imagify(array|null|string $src, array $attributes = []) 
    { 
        if(!$src) return $src;
        if(is_array($src)) {

            foreach($src as $key => $_src)
                $src[$key] = $this->fontAwesome($_src);

            return $src;
        }
        
        if (filter_var($src, FILTER_VALIDATE_URL) === FALSE) 
            return null;

        if($attributes["src"] ?? false) unset($attributes["src"]);

        $htmlAttributes = "";
        foreach($attributes as $name => $attribute)
            $htmlAttributes .= $name."=\"".$attribute."\" ";

        return "<img ".trim($htmlAttributes)." src='".$src."' />";
    }

    public function shorten(?string $str, int $length = 100, string $separator = " [..] "): ?string
    {
          return shorten_str($str, $length, $separator);
    }

    public function extension($mimetypeOrArray)
    {
        if(!$mimetypeOrArray) return [];
        if(is_array($mimetypeOrArray)) {

            $extensions = [];
            $extensionList = array_map(function($mimetype) { return $this->extension($mimetype); }, $mimetypeOrArray);
            foreach ( $extensionList as $extension )
                $extensions = array_merge($extensions,$extension);

            return array_unique($extensions);
        }

        return $this->mimeTypes->getExtensions($mimetypeOrArray);
    }

    public function mimetype($fileOrArray) {

        if(!$fileOrArray) return null;
        if(is_array($fileOrArray))
            return array_map(function($file) { return $this->mimetype($file); }, $fileOrArray);

        return $this->mimeTypes->guessMimeType($fileOrArray);
    }

    public function filesize($bytes): string
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = (int) floor(log($bytes) / log(1024));

        return (int) ($bytes / (1024 ** $factor)).@$size[$factor];
    }

    public function flattenArray($array): ?array { return array_flatten($array); }
    public function lang(string $locale):    string { return substr($locale, 0, 2); }
    public function country(string $locale): string { return substr($locale, 3, 2); }
    public function time(int $time):         string { return $this->translator->time($time); }

    public function image(Environment $env, array $context, $image): ?string
    {
        if( ( ($context["email"] ?? null) instanceof WrappedTemplatedEmail) ) {

            $email = $context["email"];
            return $email->image($image);
        }

        try { $path = $env->getLoader()->getSourceContext($image)->getPath(); }
        catch(LoaderError $e) {  // Image not found
            throw new NotFoundResourceException("Image \"$image\" not found.");
        }

        if (substr($path, 0, strlen($this->projectDir)) == $this->projectDir)
            $path = substr($path, strlen($this->projectDir));

        return $path;
    }

    public function datetime(Environment $env, $date, string $pattern = "YYYY-MM-dd HH:mm:ss", ?string $dateFormat = 'medium', ?string $timeFormat = 'medium', $timezone = null, string $calendar = 'gregorian', string $locale = null): string
    {
        if(is_string($date)) return $date;
        return $this->intlExtension->formatDateTime($env, $date, 'none', $timeFormat, $pattern, $timezone, $calendar, $locale);
    }

    public function url(string $url): string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);

        if(!array_key_exists("schema", $parseUrl)) {

            $path = $url;

            $https      = $_SERVER['HTTPS']       ?? $this->baseService->getSettings()->protocol();
            $serverName = $_SERVER['SERVER_NAME'] ?? $this->baseService->getSettings()->domain();
            $baseDir    = $_SERVER['BASE']    ?? $this->baseService->getSettings()->base_dir();
            $baseDir    = "/".trim($baseDir, "/");

            if(str_starts_with($path, "http://") || str_starts_with($path, "https://")) $domain = "";
            else $domain = ($https ? "https://" : "http://") . $serverName;

            if (!empty($domain)) $join = str_starts_with($path, "/") ? "" : $baseDir;
            else $join = "";

            $url = $domain . $join . $path;
        }

        return $url;
    }

    public function path(string $name, array $parameters = [], bool $relative = false): string
    {
        $baseDir = null;
        if($this->baseService->isCli()) {
            $baseDir    = $_SERVER['BASE']    ?? $this->baseService->getSettings()->base_dir();
            $baseDir    = "/".trim($baseDir, "/");
        }

        return $baseDir . $this->routingExtension->getPath($name, $parameters, $relative);
    }

    public function asset(string $path, string $packageName = null): string
    {
        return $this->assetExtension->getAssetUrl($path, $packageName);
    }

    public function lessThan($date, $diff): bool
    {
        if(is_string($date)) $date = new \DateTime($date);
        if($date instanceof \DateTime) $date = $date->getTimestamp();
        if(is_string($diff)) $diff = new \DateTime($diff);
        if($diff instanceof \DateTime) $diff = $diff->getTimestamp() - time();
      
        $deltaTime = time() - $date;

        return $deltaTime < $diff;
    }

    public function greaterThan($date, int $diff): bool
    {
        if(is_string($date)) $date = new \DateTime($date);
        if($date instanceof \DateTime) $date = $date->getTimestamp();
        if(is_string($diff)) $diff = new \DateTime($diff);
        if($diff instanceof \DateTime) $diff = $diff->getTimestamp() - time();

        $deltaTime = time() - $date;
        return $deltaTime > $diff;
    }

    public function truncate($string, $maxLength = 30, $replacement = '', $truncAtSpace = false): string
    {
        $maxLength -= strlen($replacement);
        $length = strlen($string);

        if($length <= $maxLength)
            return $string;

        if( $truncAtSpace && ($space_position = strrpos($string, ' ', $maxLength-$length)) )
            $maxLength = $space_position;

        return substr_replace($string, $replacement, $maxLength)."..";
    }

    public function highlight(?string $content, $pattern, $gate = 5)
    {
        // Empty entry
        if ($content == null) return null;
        if ($pattern == null) return null;

        $highlightContent = "";
        if( $gate < 0 ) {

            $highlightContent = preg_replace_callback(
                '/([^ ]*)(' . $pattern . ')([^ ]*)/im',
                function($matches) {

                    if(!isset($matches[2]) || empty($matches[2]))
                        return $matches[0];

                    return '<span class="highlightWord">'.
                                $matches[1].
                                '<span class="highlightPattern">'.$matches[2].'</span>'.
                                $matches[3].
                            '</span>';

                }, $content);

        } else if( preg_match_all('/((?:[^ ]+ ){0,' . $gate . '})([^ ]*)(' . $pattern . ')([^ ]*)((?: [^ ]+){0,' . $gate . '})/im',$content,$matches) ) {

            $priorPatternGate = $matches[1][0] ?? "";
            $priorPattern     = $matches[2][0] ?? "";
            $pattern          = $matches[3][0] ?? ""; //(Case insensitive)
            $afterPattern     = $matches[4][0] ?? "";
            $afterPatternGate = $matches[5][0] ?? "";

            $sentence = $priorPatternGate . $priorPattern . $pattern . $afterPattern . $afterPatternGate;

            if( !str_starts_with($content, $sentence) )
                $highlightContent .= "[..] ";

            $highlightContent .= "<span class='highlightGate'>";
            $highlightContent .= $priorPatternGate;
            $highlightContent .= "<span class='highlightWord'>";
            $highlightContent .= $priorPattern;
            $highlightContent .= "<span class='highlightPattern'>";
            $highlightContent .= $pattern;
            $highlightContent .= "</span>";
            $highlightContent .= $afterPattern;
            $highlightContent .= "</span>";
            $highlightContent .= $afterPatternGate;
            $highlightContent .= "</span>";

            if( !str_ends_with($content, $sentence) )
                $highlightContent .= " [..]";

        }

        return ( empty($highlightContent) ? null : $highlightContent );
    }

    public function trans(?string $id, array $parameters = array(), ?string $domain = null, ?string $locale = null, $recursive = true)
    {
        return $this->translator->trans($id, $parameters, $domain, $locale, $recursive);
    }

    public function stringify($value): string
    {
        if (null === $value) {
            return '';
        }

        if (\is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_array($value)) {
            return sprintf('Array (%d items)', \count($value));
        }

        if (\is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            if (method_exists($value, 'getId')) {
                return sprintf('%s #%s', \get_class($value), $value->getId());
            }

            return sprintf('%s #%s', \get_class($value), substr(md5(spl_object_hash($value)), 0, 7));
        }

        return '';
    }
}
