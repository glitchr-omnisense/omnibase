<?php

namespace Base\Twig\Extension;

use Base\Model\Color\Intl\Colors;
use Base\Service\BaseService;
use Base\Service\FileService;
use Base\Service\IconProvider;
use Base\Service\ImageService;
use Base\Service\LocaleProvider;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use ReflectionFunction;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
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
     * @var IconProvider
     */
    protected $iconProvider;

    /**
     * @var string
     */
    protected string $projectDir;

    public function __construct(TranslatorInterface $translator, RequestStack $requestStack, RoutingExtension $routingExtension, AssetExtension $assetExtension, IconProvider $iconProvider, ImageService $imageService) {

        $this->translator       = $translator;
        $this->routingExtension = $routingExtension;
        $this->assetExtension   = $assetExtension;
        $this->requestStack     = $requestStack;
        $this->intlExtension    = new IntlExtension();
        $this->mimeTypes        = new MimeTypes();

        $this->iconProvider     = $iconProvider;
        $this->imageService     = $imageService;
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

            new TwigFunction('synopsis', 'synopsis'),
            new TwigFunction('title',                        [$this, 'title'  ], ['is_safe' => ['all']]),
            new TwigFunction('excerpt',                      [$this, 'excerpt'  ], ['is_safe' => ['all']]),
            new TwigFunction('image',                        [$this, 'image'], ['needs_environment' => true, 'needs_context' => true]),
            new TwigFunction('get_class',                    [$this, 'get_class']),
            new TwigFunction('is_countable',                 [$this, 'is_countable']),
            new TwigFunction('is_callable',                  [$this, 'is_callable']),
            new TwigFunction('call_user_func_with_defaults', [$this, 'call_user_func_with_defaults']),
            new TwigFunction('method_exists',                [$this, 'method_exists']),
            new TwigFunction('static_call',                  [$this, 'static_call'  ]),

            new TwigFunction('html_attributes', 'html_attributes', ["is_safe" => ['all']]),

            new TwigFunction('urlify',          [$this,               'urlify' ], ["is_safe" => ['all']]),
            new TwigFunction('iconify',         [IconProvider::class, 'iconify'], ["is_safe" => ['all']]),
            new TwigFunction('asset',           [$this,               'asset']),
        ];
    }

    public function getFilters() : array
    {
        return [
            new TwigFilter('str_shorten', 'str_shorten'),
            new TwigFilter('intval',      'intval'),
            new TwigFilter('strval',      'strval'),
            new TwigFilter('urldecode',   'urldecode'),
            new TwigFilter('synopsis',    'synopsis'),
            new TwigFilter('closest',     'closest'),
            new TwigFilter('distance',    'distance'),
            new TwigFilter('color_name',  'color_name'),
            new TwigFilter('is_uuidv4',   'is_uuidv4'),
            new TwigFilter('basename',    'basename'),
            new TwigFilter('uniq',      'array_unique'),

            new TwigFilter('preg_split',     [$this,'preg_split']),
            new TwigFilter('nargs',          [$this, 'nargs']),
            new TwigFilter('instanceof',     [$this, 'instanceof']),
            new TwigFilter('url',            [$this, 'url']),
            new TwigFilter('join_if_exists', [$this, 'joinIfExists']),
            new TwigFilter('stringify',      [$this, 'stringify']),
            new TwigFilter('highlight',      [$this, 'highlight']),
            new TwigFilter('array_flatten',  [$this, 'array_flatten']),
            new TwigFilter('filesize',       [$this, 'filesize']),
            new TwigFilter('datetime',       [$this, 'datetime'],    ['needs_environment' => true]),
            new TwigFilter('less_than',      [$this, 'less_than']),
            new TwigFilter('greater_than',   [$this, 'greater_than']),
            new TwigFilter('filter',         [$this, 'filter'], ['needs_environment' => true]),
            new TwigFilter('transforms',     [$this, 'transforms'], ['needs_environment' => true]),
            new TwigFilter('pad',            [$this, 'pad']),
            new TwigFilter('mb_ucfirst',     [$this, 'mb_ucfirst']),
            new TwigFilter('mb_ucwords',     [$this, 'mb_ucwords']),
            new TwigFilter('second',         "second"),

            new TwigFilter('trans',          [Translator::class, 'trans']),
            new TwigFilter('time',           [Translator::class, 'time']),
            new TwigFilter('enum',           [Translator::class, 'enum']),
            new TwigFilter('entity',         [Translator::class, 'entity']),

            new TwigFilter('lang',           [LocaleProvider::class, 'getLang']),
            new TwigFilter('lang_name',      [LocaleProvider::class, 'getLangName']),
            new TwigFilter('country',        [LocaleProvider::class, 'getCountry']),
            new TwigFilter('country_name',   [LocaleProvider::class, 'getCountryName']),

            new TwigFilter('urlify',         [$this,               'urlify' ], ["is_safe" => ['all']]),
            new TwigFilter('iconify',        [IconProvider::class, 'iconify'], ["is_safe" => ['all']]),
            new TwigFilter('imagify',        [ImageService::class, 'imagify'], ["is_safe" => ['all']]),

            new TwigFilter('public',         [FileService::class, 'public']),
            new TwigFilter('downloadable',   [FileService::class, 'downloadable']),
            new TwigFilter('mimetype',       [FileService::class, 'getMimeType']),
            new TwigFilter('extensions',     [FileService::class, 'getExtensions']),

            new TwigFilter('obfuscate',      [FileService::class, 'obfuscate']),
            new TwigFilter('imagine',        [ImageService::class, 'imagine']),
            new TwigFilter('webp',           [ImageService::class, 'webp']),
            new TwigFilter('crop',           [ImageService::class, 'crop']),
            new TwigFilter('image',          [ImageService::class, 'image']),

            new TwigFilter('thumbnail',          [ImageService::class, 'thumbnail']),
            new TwigFilter('thumbnail_inset   ', [ImageService::class, 'thumbnail_inset   ']),
            new TwigFilter('thumbnail_outbound', [ImageService::class, 'thumbnail_outbound']),
            new TwigFilter('thumbnail_noclone ', [ImageService::class, 'thumbnail_noclone ']),
            new TwigFilter('thumbnail_upscale ', [ImageService::class, 'thumbnail_upscale ']),
        ];
    }


    public function mb_ucfirst(string $string, ?string $encoding = null): string { return mb_ucfirst($string, $encoding); }
    public function mb_ucwords(string $string, ?string $encoding = null, ?string $separator = null): string { return mb_ucwords($string, $encoding, $separator); }

    public function is_callable(mixed $value, bool $syntax_only = false, &$callable_name = null): bool { return is_callable($value, $syntax_only, $callable_name); }
    public function nargs(callable $fn): int { return (new ReflectionFunction($fn))->getNumberOfParameters(); }
    public function call_user_func_with_defaults(callable $fn, ...$args) { return call_user_func_with_defaults($fn, ...$args); }
    public function pad(array $array = [], int $length = 0, mixed $value = null): array { return array_pad($array, $length, $value); }
    public function transforms(array $array = [], $arrow = null) { return $arrow instanceof \Closure ? $arrow($array) : $array; }
    public function filter(Environment $env,  $array = [], $arrow = null)
    {
        if($arrow === null) $arrow = function($el) {
            return $el !== null && $el !== false && $el !== "";
        };

        return twig_array_filter($env, $array, $arrow);
    }

    public function asset($path, ?string $packageName = null) {

        if($path === false || $path === null) return null;
        return $this->assetExtension->getAssetUrl($path, $packageName);
    }

    public function color_name(string $hex) {

        $color = hex2rgb($hex);

        $closestNames = array_transforms(
            fn($hex, $name):array => [$name, distance($color, hex2rgb($hex))],
            Colors::getNames());

        asort($closestNames);

        $closestNames = array_keys($closestNames);
        return begin($closestNames);
    }

    public function is_countable($object) { return is_countable($object); }
    public function get_class($object, $method) { return class_exists($object) ? get_class($object, $method) : null; }
    public function method_exists($object, $method) { return $object ? method_exists($object, $method) : false; }
    public function preg_split(string $subject, string $pattern, int $limit = -1, int $flags = 0) { return preg_split($pattern, $subject, $limit, $flags); }

    public function instanceof(mixed $object, string $class): bool { return is_instanceof($object, $class, true); }

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

    public function url(?string $url): ?string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);

        if(!array_key_exists("schema", $parseUrl)) {

            $path = $url;

            $https      = $_SERVER['HTTPS']       ?? $this->baseService->getSettingBag()->scheme();
            $serverName = $_SERVER['SERVER_NAME'] ?? $this->baseService->getSettingBag()->domain();
            $baseDir    = $_SERVER['BASE']        ?? $_SERVER["CONTEXT_PREFIX"] ?? $this->baseService->getSettingBag()->base_dir();
            $baseDir    = "/".trim($baseDir, "/");

            if(str_starts_with($path, "http://") || str_starts_with($path, "https://")) $domain = "";
            else $domain = ($https ? "https://" : "http://") . $serverName;

            if (!empty($domain)) $join = str_starts_with($path, "/") ? "" : $baseDir;
            else $join = "";

            $url = $domain . $join . $path;
        }

        return $url;
    }

    public function urlify(string $url, ?string $label = null, array $attributes = []) { return "<a href='".$url."' ".html_attributes($attributes).">".($label ?? $url)."</a>"; }
    public function title(string $name, array $parameters = array(), ?string $domain = "controllers", ?string $locale = null): string
    {
        $ret = $this->translator->trans($name.".title", $parameters, $domain, $locale);
        return $ret == $name.".title" ? "@".$domain.".".$ret : $ret;
    }

    public function excerpt(string $name, array $parameters = array(), ?string $domain = "controllers", ?string $locale = null): string
    {
        $ret = $this->translator->trans($name.".excerpt", $parameters, $domain, $locale);
        return $ret == $name.".excerpt" ? "@".$domain.".".$ret : $ret;
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
