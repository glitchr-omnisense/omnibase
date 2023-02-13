<?php

namespace {

    function         dumpif(...$variadic) { \Base\BaseBundle::dump("", ...$variadic); }
    function  enable_dumpif() { \Base\BaseBundle::enableDump(""); }
    function disable_dumpif() { \Base\BaseBundle::disableDump(""); }

    if( !extension_loaded('bcmath') )
        throw new RuntimeException("bcmath is not installed");

    const MAX_DIRSIZE = 255;
    function path_subdivide($path, int $subdivision, int|array $length = 1)
    {
        $dirname = dirname($path);
        $basename = basename($path);

        $last = strlen($basename);
        $length = array_pad(is_array($length) ? $length : [], $subdivision, is_array($length) ? 1 : $length);

        $remainingSubdivision = ceil(($last - array_sum($length))/MAX_DIRSIZE);
        $length = array_pad($length, $subdivision+$remainingSubdivision, MAX_DIRSIZE);

        $subPath = $dirname . "/";
        $last = strlen($basename);

        for($i = 0, $cursor = 0, $N = count($length); $i < $N; $i++ ) {

            if($cursor > $last) break;
            $subPath .= substr($basename, $cursor, $length[$i]) . "/";
            $cursor += $length[$i];

        }

        $subPath .= substr($basename, $cursor);
        return str_strip($subPath, "./", "/");
    }

    function str_strip_chars(string $str)
    {
        return preg_replace("/[a-zA-Z]/", "", $str);
    }
    function str_strip_numbers(string $str)
    {
        return preg_replace("/[^a-zA-Z]/", "", $str);
    }

    function format_uuid(string $uuid):string|false {

        $uuid = str_replace("-", "", $uuid);
        if(!preg_match("/[a-f0-9]{32}/i", $uuid)) return false;

        return
            substr($uuid, 0 , 8)."-".
            substr($uuid, 8 , 4)."-".
            substr($uuid, 12, 4)."-".
            substr($uuid, 16, 4)."-".
            substr($uuid, 20, 12);
    }

    function typeof(mixed $input): string { return gettype($input); }
    function interpret_link($input)
    {
        return preg_replace_callback(
            "@
        (?:http|ftp|https)://
        (?:
            (?P<domain>\S+?) (?:/\S+)|
            (?P<domain_only>\S+)
        )
        @sx",
        function ($a) {
            $link = "<a href='" . $a[0] . "'>";
            $link .= $a["domain"] !== "" ? $a["domain"] : $a["domain_only"];
            $link .= "</a>";
            return $link;
        }, $input);
    }

    // Value (1) => 0 degrees: the correct orientation, no adjustment is required.
    // Value (2) => 0 degrees, mirrored: image has been flipped back-to-front.
    // Value (3) => 180 degrees: image is upside down.
    // Value (4) => 180 degrees, mirrored: image has been flipped back-to-front and is upside down.
    // Value (5) => 90 degrees: image has been flipped back-to-front and is on its side.
    // Value (6) => 90 degrees, mirrored: image is on its side.
    // Value (7) => 270 degrees: image has been flipped back-to-front and is on its far side.
    // Value (8) => 270 degrees, mirrored: image is on its far side.
    function getimageorientation(string $fname): int {

        try { $exif = exif_read_data($fname); }
        catch(\ErrorException $e) { return 1; }

        return $exif["Orientation"] ?? 1;
    }

    function imagedimswap(string $fname): bool { return getimageorientation($fname) > 4; }

    function image_fix_orientation(&$image, $fname) {
        $exif = exif_read_data($fname);

        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;

                case 6:
                    $image = imagerotate($image, 90, 0);
                    break;

                case 8:
                    $image = imagerotate($image, -90, 0);
                    break;
            }
        }
    }

    function start_timer() { $_SERVER["APP_TIMER"] = microtime(true); }
    function get_lap() // ms
    {
        if(!array_key_exists("APP_TIMER", $_SERVER)) return 0;
        return 1000*(microtime(true) - $_SERVER["APP_TIMER"]);
    }

    function get_url(?string $scheme = null, ?string $http_host = null, ?string $request_uri = null) : ?string
    {
        $scheme = $_SERVER['HTTPS'] ?? $_SERVER["USE_HTTPS"]?? $_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null ;
        $scheme = $scheme && (strcasecmp('on', $scheme) == 0 || strcasecmp('https', $scheme) == 0);
        $scheme = $scheme ? "https" : "http";

        $domain  = explode(":", $http_host ?? $_SERVER["HTTP_HOST"] ?? "")[0] ?? $_SERVER["SERVER_NAME"] ?? null;
        $port    = explode(":", $http_host ?? $_SERVER["HTTP_HOST"] ?? "")[1] ?? $_SERVER["SERVER_PORT"] ?? null;
        $port    = $port != 80 && $port != 443 ? $port : null;

        $request_uri ??= $_SERVER["REQUEST_URI"]    ?? null;

        return compose_url($scheme, null, null, null, null, $domain, $port, $request_uri == "/" ? null : $request_uri);
    }

    function delete_directory(string $dir)
    {
        if (!file_exists($dir))
            return true;

        if (!is_dir($dir))
            return unlink($dir);

        foreach (scandir($dir) as $item) {

            if ($item == '.' || $item == '..') continue;
            if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item))
                return false;
        }

        return rmdir($dir);
    }

    define("SANITIZE_URL_STANDARD", 0);
    define("SANITIZE_URL_NOMACHINE", 1);
    define("SANITIZE_URL_NOSUBDOMAIN", 2);
    define("SANITIZE_URL_KEEPSLASH", 4);
    function sanitize_url(string $url, int $format = SANITIZE_URL_STANDARD) { return format_url($url, $format); }

    define("FORMAT_URL_STANDARD", SANITIZE_URL_STANDARD);
    define("FORMAT_URL_NOMACHINE", SANITIZE_URL_NOMACHINE);
    define("FORMAT_URL_NOSUBDOMAIN", SANITIZE_URL_NOSUBDOMAIN);
    define("FORMAT_URL_KEEPSLASH", SANITIZE_URL_KEEPSLASH);
    function format_url(string $url, int $format = FORMAT_URL_STANDARD) : ?string
    {
        $parse = parse_url2($url);
        if($parse === false) return null;

        if($format & FORMAT_URL_NOMACHINE  ) $parse = array_key_removes($parse, "machine");
        if($format & FORMAT_URL_NOSUBDOMAIN) $parse = array_key_removes($parse, "subdomain");

        $urlButQuery   = explode("?", $url)[0] ?? "";
        $pathEndsWithSlash = str_ends_with($urlButQuery, "/");
        return compose_url(
            $parse["scheme"] ?? null,
            $parse["user"] ?? null,
            $parse["password"] ?? null,
            $parse["machine"] ?? null,
            $parse["subdomain"] ?? null,
            $parse["domain"] ?? $parse["host"] ?? null,
            $parse["port"] ?? null,
            str_replace("//", "/", $parse["path"] ?? "").( ($format & FORMAT_URL_KEEPSLASH) && $pathEndsWithSlash ? "/" : ""),
            $parse["query"] ?? null);
    }

    function compose_url(
        ?string $scheme = null,
        ?string $user = null,
        ?string $password = null,
        ?string $machine = null,
        ?string $subdomain = null,
        ?string $domain = null,
        string|int|null $port = null, ?string $path = null, ?string $query = null): array|string|int|false|null
    {
        $scheme    = ($domain && $scheme   ) ? $scheme."://" : null;
        $user      = ($domain && $user     ) ? $user."@" : null;
        $password  = ($domain && $user && $password) ? ":".$password : null;

        $subdomain = ($domain && $subdomain) ? $subdomain . "." : null;
        $machine   = ($domain && $machine  ) ? $machine . "." : null;
        $port      = ($domain && $port && $port != 80 && $port != 443) ? ":".$port : null;

        $query     =  $query ? "?".$query : null;

        $url = $scheme.$machine.$subdomain.$domain.$port.$user.$password.$path.$query;
        return $url ? $url : "/";
    }

    // NB: Path variable should not be removed, at most empty string..
    function parse_url2(string $url, int $component = -1, string $base = "/"): array|string|int|false|null
    {
        $noscheme = !str_contains($url, "://");
        if($noscheme) $url = "file://".$url;

        $parse = parse_url($url, $component);

        if($parse === false) return false;
        foreach($parse as &$_)
            $_ = str_lstrip($_, "file://");

        if($noscheme) unset($parse["scheme"]);

        $path = str_rstrip($parse['path'] ?? "", "/");
        $parse["path"] = str_replace("//", "/", $path);

        $tail = str_strip($url, ["file://", $base], "/");
        $root = str_strip($url, ["file://", $base], $tail);
        if(!empty($root)) $parse["root"] = $root;

        if(array_key_exists("host", $parse)) {

            //
            // Check if IP address provided
            if(filter_var($parse["host"], FILTER_VALIDATE_IP) ) $parse["ip"] = $parse["host"];
            if(filter_var($parse["host"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) $parse["ipv4"] = $parse["host"];
            if(filter_var($parse["host"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) $parse["ipv6"] = $parse["host"];

            //
            // Check if hostname
            if(preg_match('/[a-z0-9][a-z0-9\-]{0,63}\.[a-z]{2,6}(\.[a-z]{1,2})?$/i', strtolower($parse["host"] ?? ""), $match)) {

                $parse["fqdn"] = $parse["host"].".";
                $parse["domain"] = $match[0];

                $subdomain = str_rstrip($parse["host"], ".".$parse["domain"]);
                if ($parse["domain"] !== $subdomain)
                    $parse["subdomain"] = $subdomain;

                if(array_key_exists("subdomain", $parse)) {

                    $list = explode(".", $parse["subdomain"]);
                    $parse["subdomain"] = array_pop($list);

                    if(!empty($list))
                        $parse["machine"] = implode(".", $list);
                }

                $domain = explode(".", $match[0]);
                $parse["sld"]   = first($domain);
                $parse["tld"]   = implode(".", tail($domain));
            }
        }

        if(array_key_exists("root", $parse))
            $parse["url"] = $root.$path;
        if(!array_key_exists("base_dir", $parse))
            $parse["base_dir"] = $base;

        return $parse;
    }

    function is_instanceof(mixed $object_or_class, string|array $instanceOf): bool
    {
        // At least one class detection
        if(is_array($instanceOf)) {

            foreach($instanceOf as $_instanceOf)
                if(is_instanceof($object_or_class, $_instanceOf)) return true;

            return false;
        }

        // Default one
        if(interface_exists($instanceOf)) return class_implements_interface($object_or_class, $instanceOf);
        if(!class_exists($instanceOf))
            throw new Exception("Class \"$instanceOf\" doesn't exists.");

        return is_a($object_or_class, $instanceOf, !is_object($object_or_class));
    }

    function is_abstract(mixed $object_or_class) : bool
    {
        if(!class_exists($object_or_class))
            throw new Exception("Class \"$object_or_class\" doesn't exists.");

        $class = new ReflectionClass($object_or_class);
        return $class->isAbstract();
    }

    function shrinkhex(?string $_hex): ?string {

        $hex = str_lstrip($_hex, "#");
        if(!$hex) return null;

        switch(strlen($hex)) {

            case 3: break;

            case 4:
                if(str_ends_with($hex, "F")) $hex = substr($hex, 0,3);
                break;

            case 6:

                if ($hex[0] == $hex[1] && $hex[2] == $hex[3] && $hex[4] == $hex[5])
                    $hex = $hex[0].$hex[2].$hex[4];

                break;

            case 8:

                if ($hex[0] == $hex[1] && $hex[2] == $hex[3] && $hex[4] == $hex[5] && $hex[6] == $hex[7]) {

                    $hex = $hex[0].$hex[2].$hex[4].$hex[6];
                    if(str_ends_with($hex, "F")) $hex = substr($hex, 0,3);

                } else if(str_ends_with($hex, "FF")) {

                    $hex = substr($hex, 0,6);
                }

                if(strlen($hex) === 4 && str_ends_with($hex, "F")) $hex = substr($hex, 0,3);
                else if(str_ends_with($hex, "FF")) $hex = substr($hex, 0,6);

                break;

            default:
                return $_hex;
        }

        return "#".$hex;
    }

    function expandhex(?string $_hex, bool $extended = false): ?string
    {
        $hex = str_lstrip($_hex, "#");
        if(!$hex) return null;

        switch(strlen($hex)) {

            case 3:
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                if($extended) $hex .= "FF";
                break;

            case 4:
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
                break;

            default:
                return $_hex;
        }

        return "#".$hex;
    }


    function is_url(?string $url): bool { return filter_var($url, FILTER_VALIDATE_URL); }
    function camel2snake(string $input, string $separator = "_") { return mb_strtolower(str_replace($separator.$separator, $separator, str_replace('.'.$separator, '.', preg_replace('/(?<!^)[A-Z]/', $separator.'$0', str_replace(" ", $separator,$input))))); }
    function snake2camel(string $input, string $separator = "_") { return lcfirst(str_replace(' ', '', mb_ucwords(str_replace($separator, ' ', $input)))); }

    function preg_match_array(string $pattern, array $subject) {

        $search = [];
        foreach($subject as $el) {
            if(is_array($el)) $search[] = preg_match_array($pattern, $el);
            else if(preg_match($pattern, $el)) $search[] = $el;
        }

        return $search;
    }

    function array_unique_object(array $array): array
    {
        $unique = array_keys(array_unique(array_map(fn($e) => spl_object_hash($e), $array)));
        return array_filter($array, fn($k) => in_array($k, $unique), ARRAY_FILTER_USE_KEY);
    }

    function valid_response(string $url, int $status = 200, $follow_redirects = true, $redirect_limitation = 10): bool
    {
        if(!filter_var($url, FILTER_VALIDATE_URL)) return false;

        $headers = array_filter(get_headers($url), fn($h) => str_starts_with($h, "HTTP/"));
        $header = $follow_redirects ? end($headers) : first($headers);

        $nRedirects = count(array_filter($headers, fn($h) => str_contains($h, "302")));
        if($nRedirects > $redirect_limitation) return false;

        return preg_match("/^HTTP\/[0-9]\.[0-9] ".$status."/", $header);
    }

    function fetch_url(string $url, string $prefix = "file", string $tmpdir = "/tmp")
    {
        $tmpfname = tempnam($tmpdir, $prefix);

        $contents = curl_get_contents($url);
        if($contents !== false)
            file_put_contents($tmpfname, $contents);

        return $tmpfname;
    }

    function curl_get_contents(string $url, bool $follow_location = true, bool $verify_ssl = false) {

        $curl = curl_init();
        $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: ";

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $follow_location);

        curl_exec($curl);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);

        // I have added below two lines.. because of some certificate issue
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verify_ssl);

        $data = curl_exec($curl);

        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if($code > 400) throw new LogicException("Failed to fetch \"$url\": error $code received.");

        return $data;
    }

    function make_pair(array|string $values): array|false {

        if(count($values)%2 != 0) return false;

        $pairs = [];
        for($i = 0, $N = count($values)/2; $i < $N; $i++)
            $pairs[$values[$i]] = $values[$i+1];

        return $pairs;
    }

    function at(array $array, int $index) { return $array[$index] ?? null; }
    function is_tmpfile(string $fname):bool { return belongs_to($fname, sys_get_temp_dir()); }
    function unlink_tmpfile(string $fname):bool { return is_tmpfile($fname) && file_exists($fname) ? unlink($fname) : false; }
    function belongs_to(string $fname, string $base):bool
    {
        $fname = realpath($fname);
        return $fname !== false && strncmp($fname, $base, strlen($base)) === 0;
    }

    function to_array(object $class) {

        $array = array_transforms(
            fn($k,$v):array => [str_replace("\x00".get_class($class)."\x00", "", $k), $v],
            (array) $class
        );

        return $array;
    }
    function in_class(object $class, mixed $needle) {

        $haystack = array_filter((array) $class);
        return in_array($needle, $haystack, true);
    }

    function is_uuidv4(?string $uuid) { return is_string($uuid) && !(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1); }
    function synopsis(...$args) { return class_synopsis(...$args); }
    function class_synopsis(...$args)
    {
        foreach($args as $object) {

            if (!$object) return dump("Object passed is null");
            $objectID = (is_object($object)) ? "Object: 0x" . spl_object_hash($object) . "\n" : "";

            if (!is_object($object) && !is_string($object)) return dump($object);

            $className    = (is_string($object) ? $object : get_class($object));
            if(!class_exists($className))
                return dump("Class \"$className\" not found.");

            $classParent  = get_parent_class($className);
            $classMethods = get_class_methods($className);
            $classVars    = get_class_vars($className);

            $classReflection = new \ReflectionClass($object);

            $methods = "";
            foreach ($classMethods as $methodName) {

                $params = (new \ReflectionMethod($className, $methodName))->getParameters();

                $args = "";
                foreach ($params as $param) {
                    $optional = ($param->isOptional()) ? " = optional" : "";
                    $args .= (!empty($args)) ? ", " : "";
                    $args .= "$" . $param->getName() . $optional;
                }

                $methods .= "\n     public function " . $methodName . "(" . $args . ") { ... }";
            }

            $vars = "";
            foreach ($classVars as $varName => $value) {

                $value = ( is_array($value)) ? print_r($value, true) : (
                            (is_object($value) && !method_exists($value, '__toString')) ? get_class($value)."(not is_stringeable)" : $value);

                $vars .= (!empty($vars)) ? ",\n" : "";
                $vars .= "     $" . $varName . " = \"" . $value . "\"";
            }

            if (empty($vars)) $vars = "     -- No public variable available";
            if (empty($methods)) $methods = "     -- No public method available";
            $parentName = (!empty($classParent)) ? "            extends " . $classParent : "";

            dump(
                $classReflection,
                $objectID .
                    "class " . $className . $parentName . " {\n\n" .
                    $vars . "\n" .
                    $methods . "\n}\n\nMore information in the ReflectionMethod below.."
            );
        }
    }

    function format_sql(string $sql): string
    {
        return trim(str_replace(
            ["SELECT", ",", "FROM", "WHERE", "INNER", "RIGHT", "LEFT"],
            [PHP_EOL."SELECT".PHP_EOL." ", ",".PHP_EOL." ", PHP_EOL."FROM".PHP_EOL." ", PHP_EOL."WHERE".PHP_EOL." ", PHP_EOL."  INNER", PHP_EOL."  RIGHT", PHP_EOL."  LEFT"],
            $sql
        ));
    }

    function debug_backtrace_short(?string $file = null, ?string $class = null, ?string $func = null)
    {
        $backtrace = [];

        $debug_backtrace = debug_backtrace();
        foreach($debug_backtrace as $key => $trace) {

            $entry = "";
            if(array_key_exists("file", $trace))
                $entry = $trace["file"].":".$trace["line"];

            $entry .= " >> ".
                (array_key_exists("class"   , $trace) ? $trace["class"   ]."::" : "").
                (array_key_exists("function", $trace) ? $trace["function"]."()" : "");

            $backtrace[$key] = $entry;

            $autostop  = ($file  && array_key_exists("file"  , $trace)   && $trace["file"]   == $file);
            $autostop &= ($class && array_key_exists("class" , $trace)   && $trace["class"]  == $class);
            $autostop &= ($func  && array_key_exists("function", $trace) && $trace["function"] == $func);

            $autostop |= ($file  && str_starts_with($backtrace[$key], $file));
            if($autostop && $key < count($debug_backtrace)-1) {
                $backtrace[$key+1] = "[..]";
                break;
            }
        }

        return $backtrace;
    }

    define("SHORTEN_FRONT", -1); // [..] dolor sit amet
    define("SHORTEN_MIDDLE", 0); // Lorem ipsum [..] amet
    define("SHORTEN_BACK",   1); // Lorem ipsum dolor [..]
    function str_shorten(?string $str, int $length = 100, int $position = SHORTEN_BACK, string $separator = " [..] "): ?string
    {
        if(!$str) return $str;
        if($length == 0) return "";

        $nChr = strlen($str);

        if($nChr == 0) return "";
        if($nChr > $length + strlen($separator)) {

            switch($position) {

                case SHORTEN_FRONT:
                    return ltrim($separator) . substr($str, $nChr, $length+1);

                case SHORTEN_MIDDLE:
                    return substr($str, 0, $length/2). $separator . substr($str, $nChr-$length/2, $length/2+1);

                case SHORTEN_BACK:
                    return substr($str, 0, $length) . rtrim($separator);
            }
        }

        return $str;
    }

    function rand_int(int $digits) { return rand(pow(10, $digits-1), pow(10, $digits)-1); }
    function rand_str(?int $length = null, ?string $chars = null): ?string
    {
        if ($length === null)
            $length = 8;
        if ($chars === null)
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        if(!$chars)
            return null;

        $randomString = '';
        for ($i = 0, $N = strlen($chars); $i < $length; $i++)
            $randomString .= $chars[rand(0, $N - 1)];

        return $randomString;
    }

    function rotate_str(int $n = 1)
    {
        return substr($str, -$n) . substr($str, 0, -$n);
    }



    const     BIT_PREFIX = array("b");
    const    BYTE_PREFIX = array("B", "O", "o");
    const  BINARY_PREFIX = array("", "ki", "mi", "gi", "ti", "pi", "ei", "zi", "yi");
    const DECIMAL_PREFIX = array("", "k",  "m",  "g",  "t",  "p",  "e",  "z",  "y");

    function all_in_array(array $needle, array $haystack): bool { return !array_diff($needle, $haystack); }

    function byte2bit(int $num): int { return 8*$num; } // LMFAO :o)
    function bit2byte(int $num): int { return $num/8; }
    function byte2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string { return dec2str($num, $unitPrefix).BYTE_PREFIX[0]; }
    function  bit2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string { return dec2str($num, $unitPrefix).BIT_PREFIX[0]; }
    function  dec2str(int $num, array $unitPrefix = DECIMAL_PREFIX): string
    {
             if (all_in_array($unitPrefix, DECIMAL_PREFIX)) $divider = 1000;
        else if (all_in_array($unitPrefix, BINARY_PREFIX))  $divider = 1024;
        else throw new \Exception("Unknown prefix found: \"$unitPrefix\"");
        $unitPrefix = [''] + $unitPrefix;

        $factor   = (int) floor(log($num) / log($divider));
        $quotient = (int) ($num / ($divider ** $factor));

        $rest     = $num - $divider*$quotient;
        if($rest < 0) $factor--;

        $quotient = (int) ($num / ($divider ** $factor));
        $diff = $factor - count($unitPrefix) + 1;
        if($diff > 0) $quotient *= $divider ** $diff;

        return strval($factor > 0 ? $quotient.@mb_ucfirst($unitPrefix[$factor] ?? end($unitPrefix)) : $num);
    }

    function only_alphachars(string $str) { return preg_replace("/[^a-zA-Z]+/", "", $str); }
    function only_alphanumerics(string $str) { return preg_replace("/[^a-zA-Z0-9]+/", "", $str); }
    function trim_brackets(string $str, $brackets = "()")
    {
        $leftBracket  = preg_quote($brackets[0] ?? "");
        $rightBracket = preg_quote($brackets[1] ?? "");
        if(!$leftBracket && !$rightBracket) return $str;

        return trim(preg_replace('/\s*'.$leftBracket.'[^)]*'.$rightBracket.'/', '', $str));
    }

    function str2dec(string $str): int
    {
        $val = trim($str);
        if(!preg_match('/^([bo]{0,2})([a-z]{0,2})([0-9]*)/i', strrev($val), $matches))
            throw new \Exception("Failed to parse string \"".$str."\"");

        $val        = intval($matches[3] == "" ? 1 : strrev($matches[3]));
        $unitPrefix = mb_strtolower(strrev($matches[2]));
        $units      = strrev($matches[1]);

        if(in_array($units,  BIT_PREFIX)) $val *= 1; // LMFAO
        if(in_array($units, BYTE_PREFIX)) $val *= 8;
        if ($unitPrefix) {

            $binFactor = array_search($unitPrefix, BINARY_PREFIX);
            $decFactor = array_search($unitPrefix, DECIMAL_PREFIX);
            if( ! (($decFactor !== false) xor ($binFactor !== false)) )
                throw new \Exception("Unexpected prefix unit \"$unitPrefix\" for \"".$str."\"");

            if($decFactor !== false) $val *= 1000**($decFactor);
            if($binFactor !== false) $val *= 1024**($binFactor);
        }

        return intval($val);
    }

    function get_depth_class($object_or_class): int
    {
        if(!get_parent_class($object_or_class)) return 0;
        return get_depth_class(get_parent_class($object_or_class)) + 1;
    }

    function get_family_class($object_or_class): array
    {
        $family = [is_object($object_or_class) ? get_class($object_or_class) : $object_or_class];
        while($object_or_class = get_parent_class($object_or_class))
            $family[] = is_object($object_or_class) ? get_class($object_or_class) : $object_or_class;

        return $family;
    }

    function property_declarer($object_or_class, string $property): ?string
    {
        $class = is_object($object_or_class) ? get_class($object_or_class): $object_or_class;

        $reflProperty = new ReflectionProperty($class, $property);
        return $reflProperty->getDeclaringClass()->getName();
    }

    function method_declarer($object_or_class, string $method): ?string
    {
        $class = is_object($object_or_class) ? get_class($object_or_class): $object_or_class;

        $reflMethod = new ReflectionMethod($class, $method);
        return $reflMethod->getDeclaringClass()->getName();
    }

    function builtin_default(string $builtin) {

        switch($builtin) {
            case "string":
                return "";
            case "array":
                return [];
            case "float":
                return NAN;
            case "int" :
                return 0;
            case "callable" :
                return function() {};

            default:
                return null;
        }
    }

    function initialize_property(object $object, string $property): bool
    {
        $reflProperty = new ReflectionProperty(get_class($object), $property);
        $reflProperty->setAccessible(true);

        if($reflProperty->isInitialized($object))
            return true;

        $propertyType = $reflProperty->getType();
        if(!$propertyType->isBuiltin())
            return false;

        $builtinDefault = builtin_default($propertyType->getName());
        if(!$propertyType->allowsNull() && $builtinDefault === null)
            return false;

        $reflProperty->setValue($object, $builtinDefault);
        return true;
    }

    function path_suffix(string|array|null $path, $suffix, $separator = "_"): string|array|null
    {
        if($path === null) return $path;

        if(!is_array($suffix)) $suffix = [$suffix];
        $suffix = implode($separator, array_filter($suffix));

        if(is_array($path))
            return array_map(fn($p) => path_suffix($p, $suffix, $separator), $path);

        $path = pathinfo($path);
        $path["dirname"] = $path["dirname"] ?? null;
        if($path["dirname"]) $path["dirname"] .= "/";
        $path["extension"] = $path["extension"] ?? null;
        if($path["extension"]) $path["extension"] = ".".$path["extension"];

        $filename = $path["filename"] ?? null;
        $suffix = ($filename && $suffix) ? $separator.$suffix : $suffix;
        return $path["dirname"].$path["filename"].$suffix.$path["extension"];
    }

    function path_prefix(string|array|null $path, $prefix, $separator = "_"): string|array|null
    {
        if($path === null) return $path;

        if(!is_array($prefix)) $prefix = [$prefix];
        $prefix = implode($separator, array_filter($prefix));

        if(is_array($path))
            return array_map(fn($p) => path_prefix($p, $prefix, $separator), $path);

        $path = pathinfo($path);
        $path["dirname"] = $path["dirname"] ?? null;
        if($path["dirname"]) $path["dirname"] .= "/";
        $path["extension"] = $path["extension"] ?? null;
        if($path["extension"]) $path["extension"] = ".".$path["extension"];

        $filename = $path["filename"] ?? null;
        $prefix = ($filename && $prefix) ? $separator.$prefix : $prefix;

        return $path["dirname"].$prefix.$path["filename"].$path["extension"];
    }

    function explodeByArray(array|string $separator, string $str, $keepDelimiters = false, int $limit = PHP_INT_MAX)
    {
        if($limit == 0) return [$str];
        if(is_string($separator)) $separator = [$separator];

        if (preg_match('/(.*)('.implode("|", array_map("preg_quote", $separator)).')(.*)/', $str, $matches)) {

            $delimiter = $keepDelimiters ? $matches[2] : "";
            return array_merge(explodeByArray($separator, $matches[1], --$limit), [$delimiter. $matches[3]]);
        }

        return [$str];
    }

    function implodeByArray(array|string $separator, ?array $array)
    {
        if(!$array) return "";
        if(is_string($separator)) return implode($separator, $array);

        $str = $array[0];
        for($i = 1, $iN = count($array), $jN = count($separator); $i < $iN; $i++)
            $str .= $separator[($i-1)%$jN].$array[$i];

        return $str;
    }

    function str_strip(?string $haystack, array|string $lneedle = " ", array|string $rneedle = " ", bool $recursive = true): ?string { return str_rstrip(str_lstrip($haystack, $lneedle, $recursive), $rneedle, $recursive); }
    function str_rstrip(?string $haystack, array|string $needle = " ", bool $recursive = true): ?string
    {
        if($haystack === null) return null;
        if(is_array($needle)) {

            $lastHaystack = null;
            while($haystack != $lastHaystack) {

                $lastHaystack = $haystack;
                foreach($needle as $n)
                    $haystack = str_rstrip($haystack, $n);
            }

            return $haystack;
        }

        $needleLength = strlen($needle);
        if(!$needleLength) return $haystack;

        while(strlen($haystack) === strrpos($haystack, $needle) + $needleLength) {

            $haystack = substr($haystack, 0, strlen($haystack)-$needleLength);
            if(!$recursive) break;
        }

        return $haystack;
    }

    function str_lstrip(?string $haystack, array|string $needle = " ", bool $recursive = true): ?string
    {
        if($haystack === null) return null;
        if(is_array($needle)) {

            $lastHaystack = null;
            while($haystack != $lastHaystack) {

                $lastHaystack = $haystack;
                foreach($needle as $n)
                    $haystack = str_lstrip($haystack, $n);
            }

            return $haystack;
        }

        $needleLength = strlen($needle);
        if(!$needleLength) return $haystack;

        while(!empty($needle) && 0 === strpos($haystack, $needle)) {
            $haystack = substr($haystack, $needleLength);
            if(!$recursive) break;
        }

        return $haystack;
    }

    function strmultipos(string $haystack, array $needles, int $offset = 0): int|false
    {
        for($i = $offset, $N = strlen($haystack); $i < $N; $i++)
            if(in_array($haystack[$i], $needles)) return $i;

        return false;
    }

    function begin(object|array &$array) { return array_values(array_slice($array, 0, 1))[0] ?? null; }
    function head(object|array &$array):mixed { return begin($array); }
    function last(object|array &$array)  { return end($array)   ?? null; }
    function tail(object|array &$array, int $length = -1, bool $preserve_keys = false):array  { return array_slice($array, -min(count($array)-1, $length), null, $preserve_keys); }

    function first (object|array|null $array) { return $array ? begin($array) : null; }
    function second(object|array|null $array) { return first (tail($array)); }
    function third (object|array|null $array) { return second(tail($array)); }
    function fourth(object|array|null $array) { return third (tail($array)); }
    function fifth (object|array|null $array) { return fourth(tail($array)); }

    function distance(array $arr1, array $arr2)
    {
        $min = min(count($arr1), count($arr2));
        if($min == count($arr1))
            $arr2 = array_pad($arr2, $min, 0);
        if($min == count($arr2))
            $arr1 = array_pad($arr1, $min, 0);

        return sqrt(array_sum(array_map(fn($d1, $d2) => (intval($d1) - intval($d2)) * (intval($d1) - intval($d2)), $arr1, $arr2)));
    }

    function fread2($handle, int $bytes = 1, int $rollback = 0): ?string
    {
        if(!$handle) return null;
        $ftell = ftell($handle);
        if($rollback)
            fseek($handle, $ftell-$rollback);

        return fread($handle, $bytes);
    }

    function digits(int $num, int $ndigits): string
    {
        $str = strval($num);

        $length = strlen($str);
        for($i = $length; $i < $ndigits; $i++)
            $str = '0'.$str;

        return $str;
    }

    function closest(array $array, $position = -1) { return $array[$position] ?? ($position < 0 ? ($array[0] ?? false) : end($array)); }
    function is_html(?string $str)  { return $str !== null && $str != strip_tags($str); }
    function is_stringeable($value) { return (!is_object($value) && !is_array($value)) || ((is_string($value) || is_object($value)) && method_exists($value, '__toString')); }
    function tmpfile2(string $extension = "", string $suffix = "", string $prefix = "")
    {
        $extension = $extension ? '.'.$extension : "";
        $fname = tempnam(sys_get_temp_dir(), $prefix);
        rename($fname, $fname .= $suffix . $extension);

        return fopen($fname, "w");
    }

    define("HEADER_FOLLOW_REDIRECT", 1);
    function file_get_contents2(string $filename, int $mode = HEADER_FOLLOW_REDIRECT, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null) {

        if($mode == HEADER_FOLLOW_REDIRECT) get_headers2($filename, $filename);
        return file_get_contents($filename, $use_include_path, $context, $offset, $length);
    }

    function get_headers2(string $url, &$redirect = null, int $mode = HEADER_FOLLOW_REDIRECT) {

        if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
        if ($mode != HEADER_FOLLOW_REDIRECT)
            return get_headers($url, false);

        do {

            $http_response_header = []; // Special PHP variable
            $context = stream_context_create(["http" => ["follow_location" => false]]);
            get_headers($url, false, $context);

            $pattern = "/^Location:\s*(.*)$/i";
            $location_headers = preg_grep($pattern, $http_response_header);

            $repeat = !empty($location_headers) && preg_match($pattern, array_values($location_headers)[0], $matches);
            if($repeat) $url = $matches[1];

        } while ($repeat);

        $redirect = $url;

        return $http_response_header;
    }

    function dir_empty(string $dir): bool { return !file_exists($dir) || (is_dir($dir) && !(new \FilesystemIterator($dir))->valid()); }

    function mime_content_type2(string $filename, int $mode = HEADER_FOLLOW_REDIRECT)
    {
        // Search by looking at the header if url format
        if (filter_var($filename, FILTER_VALIDATE_URL)) {

            $headers = get_headers2($filename, $filename, $mode);
            if (strpos($headers[0], '200'))
                return explode("Content-Type: ", $headers[1])[1] ?? null;
        }

        if(!file_exists($filename)) return null;

        try { return mime_content_type($filename); }
        catch (TypeError|Exception $e) { return null; }
    }

    function str2bin($str)
    {
        $characters = str_split($str);

        $binary = [];
        foreach ($characters as $character) {
            $data = unpack('H*', $character);
            $binary[] = base_convert($data[1], 16, 2);
        }

        return implode(' ', $binary);
    }

    function bin2str($binary)
    {
        $binaries = explode(' ', $binary);

        $str = null;
        foreach ($binaries as $binary) {
            $str .= pack('H*', dechex(bindec($binary)));
        }

        return $str;
    }

    function class_implements_interface(mixed $objectOrClass, $interface)
    {
        if(!is_object($objectOrClass) && !is_string($objectOrClass)) return false;

        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
        if(!class_exists($class)) return false;

        $classImplements = class_implements($class);
        return array_key_exists($interface, $classImplements);
    }

    function class_name(array|object|string|null $arrayOrObjectOrClass) { return class_basename($arrayOrObjectOrClass); }
    function class_basename(array|object|string|null $arrayOrObjectOrClass)
    {
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => class_basename($a), $arrayOrObjectOrClass);

        $class = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        $class = explode("::", $class)[0];

        return str_replace("/", "\\", basename(str_replace('\\', '/', $class)));
    }

    function basenameWithoutExtension(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return basename($path, $ext ? ".".$ext : "");
    }

    function class_namespace(array|object|string|null $arrayOrObjectOrClass) { return class_dirname($arrayOrObjectOrClass); }
    function class_dirname(array|object|string|null $arrayOrObjectOrClass)
    {
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => class_dirname($a), $arrayOrObjectOrClass);

        $class = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        $dirname = str_replace("/", "\\", dirname(str_replace('\\', '/', $class)));
        return $dirname == "." ? "" : $dirname;
    }

    function is_cli(): bool { return (php_sapi_name() == "cli"); }
    function mb_lcfirst (array|string $str, ?string $encoding = null): array|string
    {
        if(is_array($str)) {

            $array = [];
            foreach($str as $s)
                $array[] = mb_lcfirst($s, $encoding);

            return $array;
        }

        return mb_strtolower(mb_substr($str, 0, 1, $encoding), $encoding).mb_substr($str, 1, null, $encoding);
    }

    function mb_lcwords (array|string $str, ?string $encoding = null, string $separators = " '\t\r\n\f\v"): array|string
    {
        if(is_array($str)) {

            $array = [];
            foreach($str as $s)
                $array[] = mb_lcwords($s, $encoding, $separators);

            return $array;
        }

        return implode("", array_map(function($s) use ($encoding, $separators) {

            $s1 = ltrim($s, $separators);
            return $s != $s1 ? $s[0].mb_lcfirst($s1, $encoding) : mb_lcfirst($s, $encoding);

        }, explodeByArray(is_array($separators) ? $separators : str_split($separators), $str, true)));
    }

    function mb_ucfirst (array|string $str, ?string $encoding = null): array|string
    {
        if(is_array($str)) {

            $array = [];
            foreach($str as $s)
                $array[] = mb_ucfirst($s, $encoding);

            return $array;
        }

        return mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding).mb_substr($str, 1, null, $encoding);
    }

    function mb_ucwords (array|string $str, ?string $encoding = null, string $separators = " '\t\r\n\f\v"): array|string
    {
        if(is_array($str)) {

            $array = [];
            foreach($str as $s)
                $array[] = mb_ucwords($s, $encoding, $separators);

            return $array;
        }

        return implode("", array_map(function($s) use ($encoding, $separators) {

            $s1 = ltrim($s, $separators);
            return $s != $s1 ? $s[0].mb_ucfirst($s1, $encoding) : mb_ucfirst($s, $encoding);

        }, explodeByArray(is_array($separators) ? $separators : str_split($separators), $str, true)));
    }

    function implode_attributes(string $separator, array ...$attributes)
    {
        $attributes = array_merge(...$attributes);
        return trim(implode($separator, array_map(fn($k) => trim($k)."=\"".trim($attributes[$k] ?? "")."\"", array_keys($attributes))));
    }

    function explode_attributes(string $separator, string $attributes): array
    {
        $list = [];
        foreach(explode($separator, $attributes) as $entry) {

            $explode = explode("=", $entry, 2);
            $key = head($explode);
            if($key) $list[$key] = $explode[1] ?? "";
        }

        return $list;
    }

    function html_attributes(array ...$attributes) { return implode_attributes(" ", ...$attributes); }

    function browser_name()    : ?string { return get_browser2()["name"] ?? null; }
    function browser_platform(): ?string { return get_browser2()["platform"] ?? null; }
    function browser_version() : ?string { return get_browser2()["version"] ?? null; }

    function get_browser2(?string $userAgent = null)
    {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
        if($userAgent == null) return [];

        $platform = "unknown";
        if (preg_match('/android/i', $userAgent))
            $platform = 'android';
        elseif (preg_match('/linux/i', $userAgent))
            $platform = 'linux';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent))
            $platform = 'apple';
        elseif (preg_match('/windows|win32/i', $userAgent))
            $platform = 'windows';

        $name = "Unknown";
        if(preg_match('/MSIE/i',$userAgent) && !preg_match('/Opera/i',$userAgent))
            $name = "MSIE";
        else if(preg_match('/Firefox/i',$userAgent))
            $name = "Firefox";
        else if(preg_match('/OPR/i',$userAgent))
            $name = "Opera";
        else if(preg_match('/Chrome/i',$userAgent) && !preg_match('/Edge/i',$userAgent))
            $name = "Chrome";
        else if(preg_match('/Safari/i',$userAgent) && !preg_match('/Edge/i',$userAgent))
            $name = "Safari";
        else if(preg_match('/Netscape/i',$userAgent))
            $name = "Netscape";
        else if(preg_match('/Edge/i',$userAgent))
            $name = "Edge";
        else if(preg_match('/Trident/i',$userAgent))
            $name = "MSIE";

        $device = "computer";
        if (preg_match('/tablet|ipad/i', $userAgent))
            $device = 'tablet';
        else if (preg_match('/mobile|iphone|ipod/i', $userAgent))
            $device = 'mobile';


        $known = implode("|", ['Version', $name, 'other']);
        preg_match_all('#(?<browser>' . $known .')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#', $userAgent, $matches);

        $version = "";
        if (count($matches['browser']) == 1) $version = $matches['version'][0] ?? null;
        else {

            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if (strripos($userAgent, "Version") < strripos($userAgent,$name)) $version = $matches['version'][0] ?? null;
            else $version = $matches['version'][1] ?? null;
        }

        if (!$version) $version = "?";

        return [
            'user_agent' => $userAgent,
            'name'       => $name,
            'version'    => $version,
            'device'     => $device,
            'platform'   => $platform
        ];
    }

    function array_limit(array &$array, $limit = -1)
    {
        if($limit < 0) return $array;
        return array_splice($array, 0, $limit);
    }

    function array_concat(array ...$arrays): array {

        $array = [];
        foreach($arrays as $arr)
            foreach($arr as $key => $element) $array[] = $element;

        return $array;
    }

    function array_clear(array &$array) { while(array_pop($array)) {} }

    function array_prepend(array &$array, ...$value):int { return array_unshift($array, ...(!is_array($value) ? [$value] : $value)); }
    function array_append (array &$array, ...$value):int { return array_push($array, ...(!is_array($value) ? [$value] : $value)); }
    function array_append_recursive()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);

        if (!is_array($base)) $base = empty($base) ? [] : array($base);

        foreach ($arrays as $append) {
            if (!is_array($append)) $append = array($append);
            foreach ($append as $key => $value) {

                if (!array_key_exists($key, $base) and !is_numeric($key)) {
                    $base[$key] = $append[$key];
                    continue;
                }

                if (is_array($value) or is_array($base[$key])) {
                    $base[$key] = array_append_recursive($base[$key], $append[$key]);
                } else if (is_numeric($key)) {
                    if (!in_array($value, $base)) $base[] = $value;
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }

    function browser_supports_webp(): bool
    {
        if(strpos( $_SERVER['HTTP_ACCEPT'] ?? "", 'image/webp' ) !== false)
            return true;

        if(browser_name() == "Safari" && version_compare("14.0", browser_version()) < 0)
            return true;
        if(browser_name() == "Chrome" && version_compare("23.0", browser_version()) < 0)
            return true;
        if(browser_name() == "Firefox" && version_compare("65.0", browser_version()) < 0)
            return true;
        if(browser_name() == "Edge" && version_compare("1809", browser_version()) < 0)
            return true;
        if(browser_name() == "Opera" && version_compare("12.1", browser_version()) < 0)
            return true;

        return false;
    }

    function array_insert(array $array, bool|int|string $index = false, ...$val): array
    {
        $keys = array_keys($array);

        $pos  = $index === false ? false : array_search($index, $keys, true);
        if($pos === false) $pos = count($array);

        return array_merge(array_slice($array, 0, $pos), $val, array_slice($array, $pos));
    }

    function array_remove(array $array, ...$values): array
    {
        foreach ($values as $val) {

            foreach (array_keys($array, $val) as $key)
                unset($array[$key]);
        }

        return $array;
    }

    function next_key(array $array, $key): mixed
    {
        $keys = array_keys($array);

        $position = array_search($key, $keys);
        if (isset($keys[$position + 1]))
            return $keys[$position + 1];

        return false;
    }

    function is_multidimensional(array $array) {
        return count($array) !== count($array, COUNT_RECURSIVE);
    }

    function array_filter_column(array $array, ?callable $callback = null): array
    {
        if(!is_multidimensional($array)) return $array;

        $empties = [];
        foreach(first($array) as $key => $_) {

            $error = array_column($array, $key);
            if(empty(array_filter($error, $callback)) ) $empties[] =  $key;
        }

        foreach($array as &$entry)
            $entry = array_filter($entry, fn($e) => !in_array($e, $empties) );

        return $array;
    }

    function array_search_by(?array $array, string $column, mixed $value) : ?array {

        if($array === null) return null;
        if(!is_multidimensional($array)) return $array;

        $results = [];
        foreach ($array as $k => &$v) {

            if (!is_array($array[$k])) continue;
            if (!array_key_exists($column, $array[$k]))
                continue;

            if ($array[$k][$column] === $value)
                $results[] = $v;
        }

        return $results ? $results : null;
    }


    function get_permutations(array $data = [], bool $duplicates = true, ?int $limit = null) {

        $permutations = [[]];

        for ($i = 0, $N = $limit ? min(count($data), $limit) : count($data); $i < $N; $i++) {

            $buffer = [];
            foreach ($permutations as $permutation) {

                foreach ($data as $inputValue)
                    $buffer[] = array_merge($permutation, [$inputValue]);

                if(!$duplicates) $buffer = array_unique($buffer);
            }

            $permutations = $buffer;
        }

        return $permutations;
    }

    function dumplight(mixed $value)
    {
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    function str_accent($text) {

        $utf8 = array(
            '/[áàâãªä]/u'   =>   'a',
            '/[ÁÀÂÃÄ]/u'    =>   'A',
            '/[ÍÌÎÏ]/u'     =>   'I',
            '/[íìîï]/u'     =>   'i',
            '/[éèêë]/u'     =>   'e',
            '/[ÉÈÊË]/u'     =>   'E',
            '/[óòôõºö]/u'   =>   'o',
            '/[ÓÒÔÕÖ]/u'    =>   'O',
            '/[úùûü]/u'     =>   'u',
            '/[ÚÙÛÜ]/u'     =>   'U',
            '/ç/'           =>   'c',
            '/Ç/'           =>   'C',
            '/ñ/'           =>   'n',
            '/Ñ/'           =>   'N',
            '/–/'           =>   '-', // UTF-8 hyphen to "normal" hyphen
            '/[’‘‹›‚]/u'    =>   ' ', // Literally a single quote
            '/[“”«»„]/u'    =>   ' ', // Double quote
            '/ /'           =>   ' ', // nonbreaking space (equiv. to 0x160)
        );

        return preg_replace(array_keys($utf8), array_values($utf8), $text);
    }

    function is_multiligne(string $str):bool { return strstr($str, PHP_EOL); }

    function pathinfo_extension(string $path, ?string $extension = null)
    {
        $info = pathinfo($path);
        $extension = $extension ?? $info["extension"] ?? "";

        $dirname = $info['dirname'] ? $info['dirname'] . "/" : '';
        return $dirname . $info['filename'] . ($extension ? "." .$extension : "");
    }

    function pathinfo_relationship(string $path):?string
    {
        if(is_multiligne($path)) return null;

        $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION);
        if(empty($extension)) return null;

        switch($extension) {

            case "ico": return "icon";
            case "css": return "stylesheet";
            case "js": return "javascript";

            default: return "preload";
        }
    }

    function is_associative(array $arr): bool
    {
        if(!$arr) return false;

        $keys = array_keys($arr);
        foreach($keys as $key)
            if(gettype($key) != "integer") return true;

        return $keys !== range(0, count($arr) - 1);
    }

    function array_is_nested($a)
    {
        $rv = array_filter($a, 'is_array');
        if (count($rv) > 0) return true;
        return false;
    }

    function array_replace_keys($array, array|string|int $old, array|string|int $new) {

        if(gettype($old) == "array" && gettype($new) == "array") {

            foreach($old as $i => $_)
                $array = array_replace_keys($array, $old[$i], $new[$i]);

            return $array;

        } else if(gettype($old) == "array" || gettype($new) == "array") {

            if(gettype($new) != gettype($old))
                throw new \Exception(__FUNCTION__."() : Argument #2 (\$new) must be of same type as argument #1 (\$old)");
        }

        $keys = array_keys($array);
        $idx  = array_search($old, $keys);

        array_splice($keys, $idx, 1, $new);
        return array_combine($keys, array_values($array));
    }

    function array_map_recursive(callable $callback, array $array) :array {

        $func = function ($item) use (&$func, &$callback) {
            return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
        };

        return array_map($func, $array);
    }

    function count_leaves(array $array)
    {
        $counter = 0;
        array_map_recursive(function($k) use (&$counter) { $counter++; }, $array);
        return $counter;
    }

    /**
     * Check if a JPEG image file uses the CMYK colour space.
     * @param string $path The path to the file.
     * @return bool
     */
    function is_cmyk(string $path)
    {
        if(!$path || !file_exists($path))
            return false;

        $imagesize = @getimagesize($path);
        if($imagesize === false) return false;
        return array_key_exists('mime', $imagesize) && 'image/jpeg' == $imagesize['mime'] &&
               array_key_exists('channels', $imagesize) && 4 == $imagesize['channels'];
    }

    function is_rgb(string $path)
    {
        if(!$path || !file_exists($path))
            return false;

        $imagesize = @getimagesize($path);
        return array_key_exists('channels', $imagesize) && 3 == $imagesize['channels'];
    }

    function cmyk_profile(string $path, string $name)
    {
        $image = new \Imagick($path); // load image
        return $image->getImageProfiles($name, true)[$name] ?? null; // get profiles
    }

    function write_cmyk_profiles(string $path, array $profiles)
    {
        $image = new \Imagick($path); // load image
        foreach($profiles as $name => $data)
            $image->profileImage($name, $data);

        $image->setImageColorSpace(is_cmyk($path) ? Imagick::COLORSPACE_CMYK : Imagick::COLORSPACE_RGB);
        $image->writeImage($path);
    }

    function write_cmyk_profile(string $path, string $name, $data)
    {
        $image = new \Imagick($path); // load image
        $image->profileImage($name, $data);
        $image->setImageColorSpace(is_cmyk($path) ? Imagick::COLORSPACE_CMYK : Imagick::COLORSPACE_RGB);
        $image->writeImage($path);
    }

    function cmyk_profiles(string $path)
    {
        $image = new \Imagick($path); // load image
        return $image->getImageProfiles('*', true); // get profiles
    }

    function cmyk_icc_profile(string $path)
    {
        $image = new \Imagick($path); // load image
        return $image->getImageProfiles('icc', true)['icc'] ?? null; // get profiles
    }

    function cmyk2rgb(string $path) // Not working... color are distorded
    {
        if(is_rgb($path)) return $path;
        if(!class_exists("Imagick"))
            throw new Exception(__FUNCTION__."(): Imagick driver not found.");

        $image = new \Imagick($path);
        $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
        $image->writeImage($path);
        $image->destroy();
    }

    function rgb2cmyk($path)
    {
        if(is_cmyk($path)) return $path;
        if(!class_exists("Imagick"))
            throw new Exception(__FUNCTION__."(): Imagick driver not found.");

        $image = new \Imagick($path);
        $image->transformImageColorspace(\Imagick::COLORSPACE_CMYK);
        $image->writeImage($path);
        $image->destroy();
    }

    function is_emptydir($dir)
    {
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {

            if ($entry != "." && $entry != "..") {

                closedir($handle);
                return false;
            }
        }

        closedir($handle);
        return true;
    }

    define("ARRAY_USE_KEYS"  , 0);
    define("ARRAY_USE_VALUES", 1);
    function array_contains   (array $haystack, string $needle, int $mode = ARRAY_USE_KEYS) { return array_filter($haystack, fn($k,$v) => str_contains   ($mode == ARRAY_USE_KEYS ? $k : $v, $needle), ARRAY_FILTER_USE_BOTH); }
    function array_starts_with(array $haystack, string $needle, int $mode = ARRAY_USE_KEYS) { return array_filter($haystack, fn($k,$v) => str_starts_with($mode == ARRAY_USE_KEYS ? $k : $v, $needle), ARRAY_FILTER_USE_BOTH); }
    function array_ends_with  (array $haystack, string $needle, int $mode = ARRAY_USE_KEYS) { return array_filter($haystack, fn($k,$v) => str_ends_with  ($mode == ARRAY_USE_KEYS ? $k : $v, $needle), ARRAY_FILTER_USE_BOTH); }

    define("FORMAT_IDENTITY",     0); // "no changes"
    define("FORMAT_TITLECASE",    1); // Lorem Ipsum Dolor Sit Amet
    define("FORMAT_SENTENCECASE", 2); // Lorem ipsum dolor sit amet
    define("FORMAT_LOWERCASE",    3); // lorem ipsum dolor sit amet
    define("FORMAT_UPPERCASE",    4); // LOREM IPSUM DOLOR SIT AMET

    function array_any($array, ...$values) {

        if(empty($values)) return true;
        foreach($values as $value)
            if(in_array($value, $array)) return true;

        return false;
    }

    function array_every($array, ...$values) {

        foreach($values as $value)
            if(!in_array($value, $array)) return false;

        return true;
    }

    function call_user_func_with_defaults(callable $fn, ...$args): mixed
    {
        $reflectionFn = new ReflectionFunction($fn);

        $nArgs = count($args);
        if($reflectionFn->getNumberOfRequiredParameters() > $nArgs)
            throw new InvalidArgumentException("Missing arguments (".$nArgs." provided out of ".$reflectionFn->getNumberOfParameters().")  in the callable function \"". $reflectionFn->getName()."\"");

        return call_user_func($fn, ...array_slice($args, 0, min($nArgs, $reflectionFn->getNumberOfParameters())));
    }

    function castcase(string $str, int $format = 0): string
    {
        switch($format) {

            case FORMAT_TITLECASE:
                return mb_ucwords($str);
                break;

            case FORMAT_SENTENCECASE:
                return mb_ucfirst(mb_strtolower($str));
                break;

            case FORMAT_LOWERCASE:
                return mb_strtolower($str);
                break;

            case FORMAT_UPPERCASE:
                return mb_strtoupper($str);
                break;

            default:
            case FORMAT_IDENTITY:
                return $str;
        }
    }


    define('ARRAY_TRANSFORMS_OVERRIDE', 1);
    define('ARRAY_TRANSFORMS_MERGE'   , 2);

    function array_transforms(callable $callback, array $array, int $depth = 0, int $prevent_conflicts = ARRAY_TRANSFORMS_OVERRIDE): array {

        $reflection = new ReflectionFunction($callback);
        if (!$reflection->getReturnType() || !in_array($reflection->getReturnType()->getName(), ['array', 'Generator']))
            throw new \Exception('Callable function must use "array" or "Generator" return type');

        $tArray = [];
        $counter = 0;
        foreach($array as $key => $entry) {

            // Call user function with defaults parameters if required
            try { $ret = call_user_func_with_defaults($callback, $key, $entry, $callback, $counter, $depth); }
            catch(Exception $e) { throw $e; }

            // Process generators
            if($ret instanceof Generator) {

                foreach($ret as $tKey => $yield)
                {
                    $tKey = !empty($tKey) ? $tKey : count($tArray);
                    switch($prevent_conflicts) {

                        case ARRAY_TRANSFORMS_MERGE:

                            $yield ??= [];

                            $tArray[$tKey] ??= [];
                            $tArray[$tKey] = array_merge_recursive2(
                                is_array($tArray[$tKey]) ? $tArray[$tKey] : [$tArray[$tKey]],
                                is_array($yield) ? $yield : [$yield]
                            );

                            break;

                        default:
                        case ARRAY_TRANSFORMS_OVERRIDE:
                            $tArray[$tKey] = $yield;
                    }
                }

                $ret = $ret->getReturn();
            }

            // Process returned value if found
            if($ret === null) continue;

            list($tKey, $tEntry) = [$ret[0] ?? count($tArray), $ret[1] ?? null];
            switch($prevent_conflicts) {

                case ARRAY_TRANSFORMS_MERGE:

                    if(!is_array($tEntry)) $tArray[$tKey] = $tEntry;
                    else {

                        $tArray[$tKey] ??= [];
                        $tArray[$tKey] = array_merge_recursive(
                            is_array($tArray[$tKey]) ? $tArray[$tKey] : [$tArray[$tKey]],
                            is_array($tEntry) ? $tEntry : [$tEntry]
                        );
                    }

                    break;

                default:
                case ARRAY_TRANSFORMS_OVERRIDE:
                    $tArray[$tKey] = $tEntry;
            }

            $counter++;
        }

        return $tArray;
    }

    function curlranger($url, int $start = 0, int $end = 32768): string|bool {

        $headers = ["Range: bytes=".$start."-".$end];

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($curl);
        curl_close($curl);

        return $data;
    }

    function curlimagesize(string $url): ?array {

        $raw = curlranger($url);
        if($raw == false) return null;

        try { $im = imagecreatefromstring($raw); }
        catch (Exception $e) { return null; }

        return [imagesx($im), imagesy($im)];
    }

    function array_filter_recursive(array $array, ?callable $callback = null, int $mode = 0)
    {
        return array_transforms(function ($k,$v,$fn) use ($callback, $mode) :?array {

            $v = is_array($v) ? array_transforms($fn, array_filter($v, $callback, $mode)) : $v;
            return $v === [] || $v === null ? null : [$k, $v];

        }, $array);
    }

    function mod($a,$b) { return $a - floor($a/$b) * $b; }
    function gcd($a,$b) { return ($a % $b) ? gcd($b,$a % $b) : $b; }

    function array_slice_recursive(array $array, int $offset, ?int $length, bool $preserve_keys = false): array
    {
        $offsetCounter = 0;
        $lengthCounter = 0;

        return array_transforms(function($k, $v, $callback, $i) use ($preserve_keys, &$offsetCounter, $offset, &$lengthCounter, $length):?array {

            if(is_array($v)) {

                $v = array_transforms($callback, $v);
                $array = empty($v) ? null : [$preserve_keys ? $k : $i, $v];
                return $array;
            }

            $array = ($offsetCounter < $offset || ($lengthCounter >= $length)) ? null : [$preserve_keys ? $k : $i, $v];
            if($array !== null) $lengthCounter++;

            $offsetCounter++;
            return $array;

        }, $array);

    }

    define('ARRAY_FLATTEN_PRESERVE_KEYS', 1);
    define('ARRAY_FLATTEN_PRESERVE_DUPLICATES', 2);
    function array_key_flattens(string $separator, ?array $array, int $limit = PHP_INT_MAX) { return array_flatten($separator, $array, $limit, ARRAY_FLATTEN_PRESERVE_KEYS); }
    function array_flatten(string $separator, ?array $array, int $limit = PHP_INT_MAX, int $mode = 0, ?callable $fn = null)
    {
        if ($fn === null)
            $fn = function($k, $v):bool { return is_array($v); };

        $reflection = new ReflectionFunction($fn);
        if (!$reflection->getReturnType() || !in_array($reflection->getReturnType()->getName(), ['bool']))
            throw new \Exception('Callable function must use "bool" return type');

        $ret = [];
        if (!is_array($array)) $array = func_get_args();

        if (!$limit) return $array;
        foreach ($array as $key => $value) {

            switch($mode) {

                default:
                case ARRAY_FLATTEN_PRESERVE_KEYS:
                    $flattenValues = is_array($value) && $fn($key,$value) ? array_flatten($separator, $value, $limit == PHP_INT_MAX ? PHP_INT_MAX : --$limit, $mode, $fn) : $value;

                    if(is_array($value) && $fn($key, $flattenValues)) {

                        foreach($flattenValues as $key2 => $flattenValue)
                            $ret[$key.$separator.$key2] = $flattenValue;

                    } else $ret[$key] = $flattenValues;

                    break;

                case ARRAY_FLATTEN_PRESERVE_DUPLICATES:
                    $flattenValues = $fn($key,$value) ? array_flatten($separator, $value, $limit == PHP_INT_MAX ? PHP_INT_MAX : --$limit, $mode, $fn) : [$key => $value];
                    foreach($flattenValues as $key2 => $flattenValue) {

                        if(!array_key_exists($key2, $ret))
                            $ret[$key.$separator.$key2] = [];

                        $ret[$key.$separator.$key2][] = $flattenValue;
                    }
                    break;
            }
        }

        return $ret;
    }

    define('ARRAY_INFLATE_INCREMENT_INTKEYS', 1);
    function array_inflate(string $separator, ?array $array, int $mode = 0, int $limit = PHP_INT_MAX) {

        if(!is_array($array)) return $array;

        $ret = [];
        foreach ($array as $key => $value) {

            $keys = explode($separator, $key, $limit);
            list($head, $tail) = [head($keys), implode($separator, tail($keys))];
            $ret[$head] ??= [];

            $limit = ($limit == PHP_INT_MAX) ? PHP_INT_MAX : $limit - count($keys);
            if($tail !== "") {

                switch($mode) {

                    case ARRAY_INFLATE_INCREMENT_INTKEYS:
                        $ret[$head] = array_merge_recursive ($ret[$head], array_inflate($separator, [$tail => $value], $mode, $limit));
                        break;

                    default:
                        $ret[$head] = array_merge_recursive2($ret[$head], array_inflate($separator, [$tail => $value], $mode, $limit));
                }

            } else {

                if(is_array($value)) $ret[$head] = array_inflate($separator, $value, $mode, $limit);
                else if(!empty($ret[$head])) $ret[$head][] = $value;
                else $ret[$head] = $value;
            }
        }

        return $ret;
    }

    function array_merge_recursive2(array ...$arrays) {

        $ret = head($arrays);
        foreach (tail($arrays) as $_) {

            foreach ($_ as $key => $value) {

                if(!array_key_exists($key, $ret)) $ret[$key] = $value;
                else {

                    if(is_array($value)) $ret[$key] = array_merge_recursive2($ret[$key], $value);
                    else {

                        if(!empty($ret[$key])) {

                            if(is_array($ret[$key])) array_push($ret[$key], $value);
                            else {
                                $ret[$key] = [$ret[$key]];
                                $ret[$key][] = $value;
                            }

                        } else if(empty($ret[$key])) $ret[$key] = $value;
                    }
                }
            }
        }

        return $ret;
    }

    function array_class($objectOrClass, array $haystack): string|int|false
    {
        $className = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
        foreach($haystack as $key => $item)
            if($item instanceof $className) return $key;

        return false;
    }

    function array_class_last($objectOrClass, array $haystack): string|int|false
    {
        $haystack = array_reverse($haystack);
        if(is_associative($haystack))
            return array_class($objectOrClass, $haystack);

        $position = array_class($objectOrClass, $haystack);
        if($position === false) return false;

        return count($haystack) - $position - 1;
    }

    function array_search_last(mixed $needle, array $haystack, bool $strict = false): string|int|false
    {
        $haystack = array_reverse($haystack);
        if(is_associative($haystack))
            return array_search($needle, $haystack, $strict);

        $position = array_search($needle, $haystack, $strict);
        if($position === false) return false;

        return count($haystack) - $position - 1;
    }

    function array_search_recursive(mixed $needle, array $haystack):array|false {

        foreach ($haystack as $key => $value) {

            if($value === $needle) return [$key];
            if( is_array($value) && ($current = array_search_recursive($needle, $value)) )
                return  array_merge([$key], $current);
        }

        return false;
    }

    function array_pop_key(mixed $key, array &$array): mixed
    {
        if(empty($array)) return null;

        $entry = $array[$key] ?? null;
        $array = array_key_removes($array, $key);

        return $entry;
    }

    function array_key_startsWith(array $array, string $needle): array
    {
        return array_transforms(fn($k,$v):?array => str_starts_with($k, $needle) ? [$k,$v] : null, $array);
    }

    function array_key_endsWith(array $array): array
    {
        return array_transforms(fn($k,$v):?array => str_ends_with($k, $needle) ? [$k,$v] : null, $array);
    }

    function array_keys_recursive(array $array): array
    {
        $keys = [];

        foreach($array as $key => $value) {

            if (is_array($value)) $keys[$key] = array_keys_recursive($value);
            else $keys[] = $key;
        }

        return $keys;
    }

    function array_occurrence_removes(array $array, $value, int $limit = 1)
    {
        while($limit-- > 0 && ($pos = array_search($value, $array)) !== false)
            array_splice($array, $pos, 1);
    }

    function array_search_user($array, $fn) {

        foreach ($array as $key => $entry) {

            if (call_user_func($fn, $key, $entry) === true)
                return $key;
        }

        return false;
    }

    function array_key_keeps(array $array, string ...$keys  ): array
    {
        $keys = array_diff(array_keys($array), $keys);
        foreach($keys as $key)
            unset($array[$key]);

        return $array;
    }

    function in_array_object(mixed $needle, array $haystack) {

        if(!is_object($needle)) return false;

        foreach($haystack as $entry) {

            if(is_object($entry) && strcmp(spl_object_hash($entry), spl_object_hash($needle)) === 0)
                return true;
        }

        return false;
    }

    function array_diff_object(array $array, ...$rest) {

        $rest[] = fn($a, $b) => strcmp(spl_object_hash($a), spl_object_hash($b));
        return array_udiff($array, ...$rest);
    }

    function array_key_removes(array $array, string ...$keys  ): array
    {
        foreach($keys as $key)
            unset($array[$key]);

        return $array;
    }

    function array_key_removes_numerics(array $array, bool $recursive = true)
    {
        $arrayOut = [];
        foreach($array as $k => $v) {

            if(is_numeric($k)) continue;
            else if($recursive && is_array($v)) $arrayOut[$k] = array_key_removes_numerics($v, $recursive);
            else $arrayOut[$k] = $v;
        }

        return $arrayOut;
    }

    function array_key_removes_string(array $array, bool $recursive = true)
    {
        $arrayOut = [];
        foreach($array as $k => $v) {

            if(is_string($k)) continue;
            else if($recursive && is_array($v)) $arrayOut[$k] = array_key_removes_string($v, $recursive);
            else $arrayOut[$k] = $v;
        }

        return $arrayOut;
    }

    function array_key_removes_startsWith(array $array, bool $recursive = true, ...$needles)
    {
        $arrayOut = [];
        foreach($array as $k => $v) {

            if(array_filter($needles, fn($needle) => str_starts_with($needle, $k))) continue;
            else if($recursive && is_array($v)) $arrayOut[$k] = array_key_removes_startsWith($v, $recursive, ...$needles);
            else $arrayOut[$k] = $v;
        }

        return $arrayOut;
    }

    function array_key_removes_endsWith(array $array, bool $recursive = true, ...$needles)
    {
        $arrayOut = [];
        foreach($array as $k => $v) {

            if(array_filter($needles, fn($needle) => str_ends_with($needle, $k))) continue;
            else if($recursive && is_array($v)) $arrayOut[$k] = array_key_removes_endsWith($v, $recursive, ...$needles);
            else $arrayOut[$k] = $v;
        }

        return $arrayOut;
    }

    function array_key_explodes(array|string $separator, array $array, int $limit = PHP_INT_MAX)
    {
        return array_transforms(function($k, $v, $callback, $_, $depth) use ($separator, $limit) :array {

            if($limit >= 0 && $depth >= $limit)
                return [$k, $v];

            $subk = explodeByArray($separator, $k);
            return [head($subk), count($subk) > 1 ? array_transforms($callback, [implode(".", tail($subk)) => $v], ++$depth, ARRAY_TRANSFORMS_MERGE) : $v];

        }, $array, 0, ARRAY_TRANSFORMS_MERGE);
    }

    function array_values_keep(array $array, string ...$values  ): array
    {
        foreach($array as $key => $value) {

            if(!in_array($value, $values))
                unset($array[$key]);
        }

        return $array;
    }

    function array_values_remove(array $array, ...$values):array { return array_filter($array, fn($v) => !in_array($v, $values)); }
    function array_values_insert(array $array, ...$values):array
    {
        foreach($values as $value)
            if(!in_array($value, $array)) $array[] = $value;

        return $array;
    }

    function array_values_insert_any(array $array, ...$values): array
    {
        foreach($values as $value)
            $array[] = $value;

        return $array;
    }

    function array_union(...$arrays): array
    {
        $union = [];
        foreach($arrays as $array)
            $union += $array;

        return $union;
    }

    function mailparse(string $addresses): array
    {
        $regex = '/(?:\w*:)*\s*(?:"([^"]*)"|([^,;\/""<>]*))?\s*(?:(?:[,;\/]|<|\s+|^)([^<@\s;,]+@[^>@\s,;\/]+)>?)\s*/';
        if (preg_match_all($regex, $addresses, $matches, PREG_SET_ORDER) > 0)
            $matches = array_transforms(fn($k, $x): array => [trim($x[3]), trim($x[1] . $x[2])], $matches);

        return $matches;
    }

    function mailformat(array $address, ?string $name = null, ?string $email = null): string
    {
        $email ??= array_keys($address)[0] ?? null;
        if(!$email) return "";

        $name ??= first($address);
        $name = $name ? $name : array_keys($address)[0] ?? $email;

        $name = str_replace("@", "[at]", $name);
        return $name." <".$email.">";
    }

    function str_replace_prefix(string $search, string $replace, string $subject): string
    {
        if(!str_starts_with($subject, $search)) return $subject;

        $count = 1;
        return str_replace($search, $replace, $subject, $count);
    }

    function str_replace_suffix(string $search, string $replace, string $subject): string
    {
        if(!str_ends_with($subject, $search)) return $subject;

        $count = 1;
        return strrev(str_replace(strrev($search), strrev($replace), strrev($subject), $count));
    }

    function array_unique_end($array)
    {
        $len = count($array);
        return array_transforms(fn($k,$v):array => [$len-$k-1,$v], array_unique(array_reverse($array)));
    }

    function array_unique_map(callable $callback, array $array, int $flags = SORT_STRING): array
    {
        $arrayMask = array_fill_keys(array_keys(array_unique(array_map($callback, $array), $flags)), null);
        return array_intersect_key($array, $arrayMask);
    }

    function object_hydrate(object $object, ?array $vars = null)
    {
        if($vars === null) return $object;
        $reflClass      = new ReflectionClass($object);

        do {

            foreach ($reflClass->getProperties() as $reflProperty) {

                $value = $vars[$reflProperty->getName()] ?? null;
                if(!$value) continue;

                $reflProperty->setAccessible(true);
                $reflProperty->setValue($object, $value);
            }

        } while ($reflClass = $reflClass->getParentClass());

        return $object;
    }

    function cast_from_array(array $array, string $newClass) { return unserialize(str_replace('O:8:"stdClass"','O:'.strlen($newClass).':"'.$newClass.'"',serialize((object) $array) )); }
    function cast_empty(string $newClass) { return unserialize(str_replace('O:8:"stdClass"','O:'.strlen($newClass).':"'.$newClass.'"', serialize((object) []) )); }
    function cast($object, $newClass, ...$args)
    {
        if($object == null) return null;

        $reflClass      = new ReflectionClass($object);
        $reflProperties = $reflClass->getProperties();

        $reflNewClass = new ReflectionClass($newClass);
        $reflConstr   = new ReflectionMethod($newClass, '__construct');

        if($reflConstr->getNumberOfParameters() && !count($args)) $newObject = $reflNewClass->newInstanceWithoutConstructor();
        else $newObject = new $newClass(...$args);

        do {

            foreach ($reflNewClass->getProperties() as $reflNewProperty) {

                $reflNewProperty->setAccessible(true);

                $reflProperty = array_filter($reflProperties, fn($p) => $p->getName() == $reflNewProperty->getName());
                $reflProperty = begin($reflProperty) ?? null;
                if ($reflProperty) {

                    $reflProperty->setAccessible(true);

                    $value = $reflProperty->getValue($object);
                    $reflNewProperty->setValue($newObject, $value);
                }
            }

        } while ($reflNewClass = $reflNewClass->getParentClass());

        return $newObject;
    }

    function is_serialized($str): bool { return is_string($str) && ($str == 'b:0;' || @unserialize($str) !== false); }
    function is_serializable($object): bool
    {
        try { serialize($object); }
        catch (Exception $e) { return false; }

        return true;
    }

    function str_strip_accents($str) {
        return strtr($str,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }

    function abc2dec(string $s): int {

        $abc = array_flip(range('a', 'z'));
        $dec = "";

        foreach(str_split(strtolower($s)) as $c)
            $dec .= $abc[$c];

        return intval($dec);
    }

    function dec2abc(int $dec): int {

        $dec = strval($dec);

        $abc = "";
        foreach(str_split($dec) as $c)
            $abc .= chr($c);

        return $abc;
    }

    function hexv2abc(string $s)
    {
        return strtr(strtoupper(base_convert($s, 10, 26)), "0123456789ABCDEFGHIJKLMNOP", "ABCDEFGHIJKLMNOPQRSTUVWXYZ");
    }

    function abc2hexv(string $s)
    {
        return base_convert(strtr(strtoupper($s), "0123456789ABCDEFGHIJKLMNOP", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 10, 26);
    }

    function hex2rgba(string $hex): array { return sscanf(strtoupper($hex), "#%02x%02x%02x%02x"); }
    function hex2rgb (string $hex): array { return sscanf(strtoupper($hex), "#%02x%02x%02x"); }
    function hex2hsl (string $hex): array { return rgb2hsl(hex2rgb($hex)); }
    function hex2int (string $hex): int   { return hexdec(ltrim($hex, '#')); }

    function hex2alpha(string $hex):float   { return hexdec(ltrim($hex, '#')) / 0xFF; }
    function alpha2hex(float $alpha, bool $hash = true):string { return ($hash ? '#' : ''). ($alpha * 0xFF); }

    function int2hex(int $int, bool $hash = true):string { return ($hash ? '#' : '').sprintf('%06X', $int); }
    function int2rgb(int $int):array { return [$int >> 16 & 0xFF, $int >> 8 & 0xFF, $int & 0xFF]; }
    function float2rgba(float $float):array { return [$float >> 24 & 0xFF, $float >> 16 & 0xFF, $float >> 8 & 0xFF, $float & 0xFF]; }

    function rgb2int(array $rgb) { return ($rgb[0] * 65536) + ($rgb[1] * 256) + ($rgb[2]); }
    function rgb2hex(array $rgb) :string { return sprintf("#%02X%02X%02X", ...array_pad($rgb,3,0)); }
    function rgba2float(array $rgba):float { return ($rgba[0] * 4294967296) + ($rgba[1] * 65536) + ($rgba[2] * 256) + ($rgba[3]); }
    function rgba2hex(array $rgba) :string { return sprintf("#%02X%02X%02X%02X", ...array_pad($rgba,4,0)); }

    function str_blankspace(int $length) { return $length < 1 ? "" : str_repeat(" ", $length); }
    function usort_column(array &$array, string $column, callable $fn):bool { return usort($array, fn($a1, $a2) => $fn($a1[$column] ?? null, $a2[$column] ?? null)); }
    function usort_key(array $array, array $ordering = []):array
    {
        $ordering = array_flip($ordering);
        ksort($ordering);

        return array_replace(array_flip($ordering), $array);
    }

    function usort_startsWith(array &$array, string|array $startingWith)
    {
        if(!is_array($startingWith)) $startingWith = [$startingWith];

        return usort($array, function($a1, $a2) use ($startingWith) {

            foreach($startingWith as $needle) {

                if (str_starts_with($a1, $needle) == true  && str_starts_with($a2, $needle) == true ) return  0;
                if (str_starts_with($a1, $needle) == false && str_starts_with($a2, $needle) == true ) return  1;
                if (str_starts_with($a1, $needle) == true  && str_starts_with($a2, $needle) == false) return -1;
            }

            return 0;
        });
    }

    function usort_endsWith(array $array, string|array $startingWith)
    {
        if(!is_array($startingWith)) $startingWith = [$startingWith];

        return usort($array, function($a1, $a2) use ($startingWith) {

            foreach($startingWith as $needle) {

                if (str_ends_with($a1, $needle) == true  && str_ends_with($a2, $needle) == true ) return  0;
                if (str_ends_with($a1, $needle) == true  && str_ends_with($a2, $needle) == false) return  1;
                if (str_ends_with($a1, $needle) == false && str_ends_with($a2, $needle) == true ) return -1;
            }

            return 0;
        });
    }

    function array_swap(array &$array, $a, $b)
    {
        if(array_key_exists($a, $array) && array_key_exists($b, $array))
            [$array[$a], $array[$b]] = [$array[$b], $array[$a]];
    }

    function array_reverseByMask(array $array, array $mask)
    {
        $mask = array_pad($mask, count($array), false);

        $keys = [];
        foreach($mask as $key => $b)
            if($b) $keys[] = $key;

        return array_reverseByKey($array, $keys);
    }

    function array_reverseByKey(array $array, array $keys)
    {
        if(count($keys) == 1) return $array;

        $N = ceil(count($keys)/2);
        for($i = 0; $i < $N; $i++)
            array_swap($array, $keys[$i], $keys[count($keys)-$i-1]);

        return $array;
    }

    function hsl2hex(array $hsl) :string { return rgb2hex(hsl2rgb($hsl)); }
    function rgb2hsl(array $rgb): array
    {
        list($r, $g, $b) = $rgb;
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $min = min($r, min($g, $b));
        $max = max($r, max($g, $b));
        $delta = $max - $min;

        $s = 0;
        $l = ($min + $max) / 2;
        if ($l > 0 && $l < 1)
            $s = $delta / ($l < 0.5 ? (2 * $l) : (2 - 2 * $l));

        $h = 0;
        if ($delta > 0) {
            if ($max == $r && $max != $g) $h += ($g - $b) / $delta;
            if ($max == $g && $max != $b) $h += (2 + ($b - $r) / $delta);
            if ($max == $b && $max != $r) $h += (4 + ($r - $g) / $delta);
            $h /= 6;
        }

        return [round($h,5), round($s,5), round($l,5)];
    }

    function hsl2rgb(array $hsl): array
    {
        list($h, $s, $l) = $hsl;
        $m2 = ($l <= 0.5) ? $l * ($s + 1) : $l + $s - $l*$s;
        $m1 = $l * 2 - $m2;
        return [
            0 => intval(round(hue2rgb($m1, $m2, $h + 0.33333) * 255)),
            1 => intval(round(hue2rgb($m1, $m2, $h) * 255)),
            2 => intval(round(hue2rgb($m1, $m2, $h - 0.33333) * 255)),
        ];
    }

    function hue2rgb($m1, $m2, $h)
    {
        $h = ($h < 0) ? $h + 1 : (($h > 1) ? $h - 1 : $h);
        if ($h * 6 < 1) return $m1 + ($m2 - $m1) * $h * 6;
        if ($h * 2 < 1) return $m2;
        if ($h * 3 < 2) return $m1 + ($m2 - $m1) * (0.66666 - $h) * 6;

        return $m1;
    }

    function apply_callback(callable $callback, $value)
    {
        if(is_array($value)) {

            $array = [];
            foreach($value as $v)
                $array[] = $callback($v);

            return $array;
        }

        return $callback($value);
    }

    function array_order(array $array, array $reference)
    {
        if(!array_every($array, ...$reference) || count($array) != count($reference))
            throw new Exception("Bijective array requested.");

        $order = [];
        foreach($reference as $value) {

            $order[] = array_search($value, $array);
            $array = array_key_removes($array, end($order));
        }

        return array_merge(array_values($array), $order);
    }

    function is_identity(?array $array)
    {
        foreach($array ?? [] as $key => $value) {

            if(is_array($value) && !is_identity($value)) return false;
            else if ($key !== $value) return false;
        }

        return true;
    }

    function cast_datetime(null|string|int|DateTime $datetime)
    {
        if($datetime === null) return null;
        return is_int($datetime)    ? (new DateTime())->setTimestamp($datetime) :
             ( is_string($datetime) ?  new DateTime($datetime) : (clone $datetime));
    }

    function daydiff(null|string|int|DateTime $datetime):?int
    {
        if($datetime == null) return null;

        $datetime = cast_datetime($datetime);
        $today = new DateTime("today");
        $diff  = $today->diff( $datetime->setTime( 0, 0, 0 ) );
        return (integer) $diff->format( "%R%a" );
    }

    function ceil_datetime(null|string|int|DateTime $datetime, null|string|int $precision): \DateTime
    {
        $datetime  = cast_datetime($datetime);
        $precision = cast_datetime($precision);

        $timestamp = $datetime->format("U");
        $modulo = abs($precision->format("U") - time());
        $delta = $timestamp % $modulo;

        return $datetime->setTimestamp($timestamp - $delta + $modulo);
    }

    function floor_datetime(null|string|int|DateTime $datetime, null|string|int $precision): \DateTime
    {
        $datetime  = cast_datetime($datetime);
        $precision = cast_datetime($precision);

        $timestamp = $datetime->format("U");
        $modulo = abs($precision->format("U") - time());
        $delta = $timestamp % $modulo;

        return $datetime->setTimestamp($timestamp - $delta);
    }

    function is_length_safe(string $directory): bool
    {
        $maxPathLength = constant("PHP_MAXPATHLEN");
        return strlen($directory) <= $maxPathLength;
    }

    function mkdir_length_safe(string $directory, int $permissions = 0777, bool $recursive = false, $context = null): bool
    {
        if(is_length_safe($directory))
            return mkdir($directory, $permissions, $recursive, $context);

        $ls = getcwd();
        $directory = str_lstrip($directory, $ls);
        if(str_starts_with($directory, "/"))
            chdir("/");

        $directories = explode("/", $directory);
        foreach($directories as $directory) {

            if(!$directory) continue;
            if(!is_length_safe($directory)) {
                //throw new LogicException("Directory name is too long. (PHP_MAXPATHLEN = ".constant("PHP_MAXPATHLEN").")");
                return false;
            }

            if(!file_exists($directory))
                shell_exec("mkdir -p ".$directory);
            if(!is_dir($directory))
                throw new LogicException("\"".getcwd()."/".$directory."\" is a file.");

            chdir($directory);
        }

        chdir($ls);
        return true;
    }

    function round_datetime(null|string|int|DateTime $datetime, null|string|int $precision): \DateTime
    {
        $datetime  = cast_datetime($datetime);
        $precision = cast_datetime($precision);

        $timestamp = $datetime->format("U");
        $modulo = abs($precision->format("U") - time());
        $delta = $timestamp % $modulo;

        return $datetime->setTimestamp($timestamp - $delta + ($delta < $modulo/2 ? 0 : $modulo));
    }

    function datetime_is_between(null|string|int|DateTime $datetime, null|string|int|DateTime $dt1 = null, null|string|int|DateTime $dt2 = null)
    {
        $datetime  = cast_datetime($datetime);
        if($datetime === null) return false;

        $datetime1 = cast_datetime($dt1);
        if($datetime1 !== null && $datetime <= $datetime1) return false;

        $datetime2 = cast_datetime($dt2);
        if($datetime2 !== null && $datetime > $datetime2) return false;

        return $datetime1 != null || $datetime1 != null;
    }

    function date_is_between(null|string|DateTime $datetime, null|string|int|DateTime $d1 = null, null|string|int|DateTime $d2 = null) {

        $datetime  = cast_datetime($datetime);
        if($datetime === null) return false;

        $datetime->setTime(0,0,0);
        $datetime1 = cast_datetime($d1);
        $datetime1->setTime(0,0,0);
        $datetime2 = cast_datetime($d2);
        $datetime2->setTime(0,0,0);

        return datetime_is_between($datetime, $datetime1, $datetime2);
    }

    function check_backtrace(string $str_starts_with = "", string $str_ends_with = "", array $debug_backtrace = null)
    {
        $debug_backtrace ??= debug_backtrace();
        foreach($debug_backtrace as $trace) {

            if(!array_key_exists("class", $trace)) continue;
            if(($str_ends_with   &&   str_ends_with($trace["class"], $str_ends_with  )) &&
               ($str_starts_with && str_starts_with($trace["class"], $str_starts_with))) return true;
        }

        return false;
    }



    function time_is_between(null|string|DateTime $datetime, null|string|int|DateTime $t1 = null, null|string|int|DateTime $t2 = null)
    {
        $datetime  = cast_datetime($datetime);
        if($datetime === null) return false;

        $datetime->setDate(0,0,0);
        $datetime1 = cast_datetime($t1);
        $datetime1->setDate(0,0,0);
        $datetime2 = cast_datetime($t2);
        $datetime2->setDate(0,0,0);

        return datetime_is_between($datetime, $datetime1, $datetime2);
    }

    function http_parse_query($query) {
        $parameters = [];
        $queryParts = explode('&', $query);
        foreach ($queryParts as $queryPart) {
            $keyValue = explode('=', $queryPart, 2);
            $parameters[$keyValue[0]] = $keyValue[1];
        }
        return $parameters;
    }

    function build_url(array $parts) {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
            ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
            (isset($parts['user']) ? "{$parts['user']}" : '') .
            (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
            (isset($parts['user']) ? '@' : '') .
            (isset($parts['host']) ? "{$parts['host']}" : '') .
            (isset($parts['port']) ? ":{$parts['port']}" : '') .
            (isset($parts['path']) ? "{$parts['path']}" : '') .
            (isset($parts['query']) ? "?{$parts['query']}" : '') .
            (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }

    /**
     * Handling resource files
     */
    function valid_url(string $url): bool
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

    function callable_hash(mixed $func): string
    {
        if(!is_callable($func)) throw new Exception("Function must be a callable");
        if(is_string($func)) return $func;

        if(is_array($func)) {

            if(count($func) !== 2) throw new Exception("Array-callables must have exactly 2 elements");
            if(!is_string($func[1])) throw new Exception("Second element of array-callable must be a string function name");
            if(is_object($func[0])) return spl_object_hash($func[0]).'::'.$func[1];
            elseif(is_string($func[0])) return implode('::',$func);
            throw new Exception("First element of array-callable must be a class name or object instance");

        }

        if(is_object($func))
            return spl_object_hash($func);

        throw new Exception("Unhandled callable type");
    }
}
