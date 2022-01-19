<?php

namespace Base\Twig\Extension;

use Base\Service\BaseService;
use Base\Controller\BaseController;
use Base\Service\IconService;
use Base\Service\ImageService;
use Base\Service\LocaleProvider;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use Twig\Extra\Intl\IntlExtension;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Twig\Error\LoaderError;
use Twig\TwigFunction;

final class BaseTwigExtension extends AbstractExtension
{
    /**
     * @var RoutingExtension
     */
    protected $routingExtension;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var ImageService
     */
    protected $imageService;

    /**
     * @var IconService
     */
    protected $iconService;

    /**
     * @var string
     */
    protected string $projectDir;

    public function __construct(TranslatorInterface $translator, RoutingExtension $routingExtension, IconService $iconService, ImageService $imageService) {

        BaseController::$foundBaseTwigExtension = true;

        $this->translator               = $translator;
        $this->routingExtension         = $routingExtension;
        
        $this->intlExtension            = new IntlExtension();
        $this->mimeTypes                = new MimeTypes();
        
        $this->iconService  = $iconService;
        $this->imageService = $imageService;
    }

    public function setBase(BaseService $baseService) 
    {
        $this->baseService = $baseService;
        $this->projectDir = $this->baseService->getProjectDir();

        return $this;
    }

    public function getIntlExtension()   :IntlExtension    { return $this->intlExtension;    }
    public function getRoutingExtension():RoutingExtension { return $this->routingExtension; }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('exit',  'exit'),

            new TwigFunction('synopsis',                'synopsis'),
            new TwigFunction("path",            [$this, 'path']),
            new TwigFunction('image',           [$this, 'image'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFunction('method_exists',   [$this, 'method_exists']),
            new TwigFunction('static_call',     [$this, 'static_call']),
            new TwigFunction('html_attributes',         'html_attributes', ['is_safe' => ['all']]),

            new TwigFunction('asset',         [AssetExtension::class, 'getAssetUrl']),
        ];
    }

    public function getFilters() : array
    {
        return [
            new TwigFilter('preg_split',      [$this,'preg_split']),
            new TwigFilter('str_shorten',     'str_shorten'),
            new TwigFilter('intval',          'intval'),
            new TwigFilter('strval',          'strval'),
            new TwigFilter('urldecode',       'urldecode'),
            new TwigFilter('synopsis',        'synopsis'),

            new TwigFilter('url',             [$this, 'url']),
            new TwigFilter('join_if_exists',  [$this, 'joinIfExists']),
            new TwigFilter('stringify',       [$this, 'stringify']),
            new TwigFilter('highlight',       [$this, 'highlight']),
            new TwigFilter('array_flatten',   [$this, 'array_flatten']),
            new TwigFilter('filesize',        [$this, 'filesize']),
            new TwigFilter('datetime',        [$this, 'datetime'],    ['needs_environment' => true]),
            new TwigFilter('less_than',       [$this, 'less_than']),
            new TwigFilter('greater_than',    [$this, 'greater_than']),

            new TwigFilter('is_uuid',         [Translator::class, 'is_uuid']),
            new TwigFilter('trans',           [Translator::class, 'trans']),
            new TwigFilter('time',            [Translator::class, 'time']),
            new TwigFilter('enum',            [Translator::class, 'enum']),
            new TwigFilter('entity',          [Translator::class, 'entity']),

            new TwigFilter('lang',            [LocaleProvider::class, 'getLang']),
            new TwigFilter('lang_name',       [LocaleProvider::class, 'getLangName']),
            new TwigFilter('country',         [LocaleProvider::class, 'getCountry']),
            new TwigFilter('country_name',    [LocaleProvider::class, 'getCountryName']),

            new TwigFilter('iconify',         [IconService::class, 'iconify']),
            new TwigFilter('imagify',         [ImageService::class, 'imagify']),

            new TwigFilter('mimetype',        [ImageService::class, 'mimetype']),
            new TwigFilter('extension',       [ImageService::class, 'extension']),
            new TwigFilter('extensions',      [ImageService::class, 'extensions']),
            new TwigFilter('imagine',         [ImageService::class, 'imagine']),
            new TwigFilter('webp',            [ImageService::class, 'webp']),
            new TwigFilter('image',           [ImageService::class, 'image']),
            new TwigFilter('thumbnail',       [ImageService::class, 'thumbnail'])
        ];
    }

    public function method_exists($object, $method) { return $object ? method_exists($object, $method) : false; }
    public function preg_split(string $subject, string $pattern, int $limit = -1, int $flags = 0) { return preg_split($pattern, $subject, $limit, $flags); }
    
    public function joinIfExists(?array $array, string $separator) 
    {
        if($array === null) return null;
        return implode($separator, array_filter($array));
    }

    public function image(Environment $env, array $context, $src) 
    { 
        if(!$src) return $src;
        if(is_array($src)) return array_map(fn($s) => $this->image($s, $context, $env), $src);

        $email = $context["email"] ?? null;
        if( $email instanceof WrappedTemplatedEmail )
            return $email->image($src);

        // Context and public path
        if(str_starts_with($src, "/")) $src = "@Public".$src;
        try { $src = $env->getLoader()->getSourceContext($src)->getPath(); }
        catch(LoaderError $e) { throw new NotFoundResourceException("Image \"$src\" not found."); }
        
        if (substr($src, 0, strlen($this->projectDir)) == $this->projectDir)
            $src = substr($src, strlen($this->projectDir));

        return $src;
    }

    public function filesize($size, array $unitPrefix = DECIMAL_PREFIX): string { return byte2str($size, $unitPrefix); }    

    function static_call($class, $method, ...$args) {
        if (!class_exists($class))
            throw new \Exception("Cannot call static method $method on \"$class\": invalid class");
        if (!method_exists($class, $method))
            throw new \Exception("Cannot call static method $method on \"$class\": invalid method");
   
        return forward_static_call_array([$class, $method], $args);
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
            $baseDir    = $_SERVER['BASE']        ?? $_SERVER["CONTEXT_PREFIX"] ?? $this->baseService->getSettings()->base_dir();
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
        if(is_cli()) {
            $baseDir    = $_SERVER['BASE']        ?? $_SERVER["CONTEXT_PREFIX"] ?? $this->baseService->getSettings()->base_dir();
            $baseDir    = "/".trim($baseDir, "/");
        }

        return $baseDir . $this->routingExtension->getPath($name, $parameters, $relative);
    }

    public function less_than($date, $diff): bool
    {
        if(is_string($date)) $date = new \DateTime($date);
        if($date instanceof \DateTime) $date = $date->getTimestamp();
        if(is_string($diff)) $diff = new \DateTime($diff);
        if($diff instanceof \DateTime) $diff = $diff->getTimestamp() - time();
      
        $deltaTime = time() - $date;
        return $deltaTime < $diff;
    }

    public function greater_than($date, int $diff): bool
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
            if (is_stringeable($value))
                return (string) $value;

            if (method_exists($value, 'getId'))
                return sprintf('%s #%s', $this->translator->entity(get_class($value)), $value->getId());
            else if (method_exists($value, 'getUuid'))
                return sprintf('%s #%s', $this->translator->entity(get_class($value)), $value->getUuid());

            return sprintf('%s #%s', $this->translator->entity(get_class($value)), substr(md5(spl_object_hash($value)), 0, 7));
        }

        return '';
    }
}
