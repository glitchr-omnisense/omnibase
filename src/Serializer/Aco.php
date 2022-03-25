<?php

namespace Base\Serializer;

use Exception;

class Aco
{
     const BYTE_SIZE = 8;

     const VERSION_V1 = 1;
     const VERSION_V2 = 2;

     const RGB       = 0;
     const HSB       = 1;
     const CMYK      = 2;
     const LAB       = 7;
     const GRAYSCALE = 8;
     const WideCMYK  = 9;

     public static function parseFile(string $filename, int $flags = self::VERSION_V2): array
     {
          if (!is_file($filename)) {
               throw new ParseException(sprintf('File "%s" does not exist.', $filename));
          }
          
          if (!is_readable($filename)) {
               throw new ParseException(sprintf('File "%s" cannot be read.', $filename));
          }

          $mimetype = mime_content_type($filename);

          switch($mimetype) {
               case "text/plain": return self::parse(fopen($filename, "r"), $flags);
               default: return self::parse(fopen($filename, "rb"), $flags);
          }
     }

     public static function parse($handle, int $flags = self::VERSION_V2): array
     {
          if(!is_resource($handle))
               throw new Exception("resource input expected, please use ".__CLASS__."::parseFile() to read a file.");

          $mimetype = mime_content_type($handle);
          switch($mimetype) {

               case "text/plain": $colors = self::parseAscii($handle, $flags);
               default: $colors = self::parseBinary($handle, $flags);
          }

          self::sortByColor($hsl);

          return $colors;
     }

     protected static function parseBinary($handle, int $flags): array
     {
          $colors  = [];
          $version = 0;
          $ncolors = 0;

          $ftell = ftell($handle);
          rewind($handle);

          for($i = 0; !feof($handle); $i++) {

               if($i == 0 || $i == $ncolors) {

                    list($version, $ncolors) = self::getHeader($handle);
                    $colors = [];
               }

               list($palette, $array, $hexcode, $name) = self::getBlock($handle, $version);
               switch($version) {

                    case self::VERSION_V1: 
                         $colors[] = [$palette, $array, $hexcode];
                         break;
                    case self::VERSION_V2:
                         $colors[] = [$palette, $array, $hexcode, $name];
                         break;
               }

               if($flags == self::VERSION_V1 && $i == $ncolors-1)
                    break;
               if($flags == self::VERSION_V2 && $i == 2*$ncolors-1) 
                    break;
          }

          fseek($handle, $ftell);
          return $colors;
     }

     // Format: #FFFFFF Name with space => Array("hexcode" => FFFFFF, name => "Name with space")
     protected static function parseAscii($handle, int $flags = self::VERSION_V2): array
     {
          $colors = [];

          $ftell = ftell($handle);
          rewind($handle);

          while( ($line = fgets($handle)) ) {

               $words = explode(" ", $line);

               $hexcode = trim(array_shift($words), "# ");
               $hexcode = strlen($hexcode) === 3 ? $hexcode[0].$hexcode[0].$hexcode[1].$hexcode[1].$hexcode[2].$hexcode[2] : $hexcode;

               $array = [
                    hexdec(mb_substr($hexcode,0,2)),
                    hexdec(mb_substr($hexcode,2,2)),
                    hexdec(mb_substr($hexcode,4,2))
               ];

               $name = trim(implode(" ", $words));
               $name = utf8_encode($name);
               $name = str_replace("\0", "", $name);

               $names = explode(",", $name);

               $colors[] = $flags == self::VERSION_V1 ? ["RGB", $array, $hexcode] : ["RGB", $array, $hexcode, $names];
          }

          fseek($handle, $ftell);

          return $colors;
     }

     public static function dump($input, int $flags = 0): string
     {
          $i = 0;
          $handle = fopen('php://temp,', 'wb+');

          $version = count(end($input)) == 3 ? self::VERSION_V1 : self::VERSION_V2;
          $ncolors = count($input);

          fwrite($handle, hex2bin(str_pad(dechex(self::VERSION_V1), 4, "0")), 2);
          fwrite($handle, hex2bin(str_pad(dechex($ncolors), 4, "0")), 2);

          foreach($input as $color)
          {
               $palette = $color[0];
               $hexcode = $color[2];

               $w = strlen($hexcode) >= 0 ? str_pad(mb_substr($hexcode,0,2), 2, "0") : "00";
               $x = strlen($hexcode) >= 2 ? str_pad(mb_substr($hexcode,2,2), 2, "0") : "00";
               $y = strlen($hexcode) >= 4 ? str_pad(mb_substr($hexcode,4,2), 2, "0") : "00";
               $z = strlen($hexcode) >= 6 ? str_pad(mb_substr($hexcode,6,2), 2, "0") : "00";

               $names    = $color[3] ?? "";

               switch($palette) {

                    case "RGB": 
                         $palette = self::RGB;
                         break;
                    case "HSB": 
                         $palette = self::HSB;
                         break;
                    case "CMYK":
                         $palette = self::CMYK; 
                         break;
                    case "LAB": 
                         $palette = self::LAB;
                         break;
                    case "WideCMYK": 
                         $palette = self::WideCMYK;
                         break;
                    case "GRAYSCALE": 
                         $palette = self::GRAYSCALE;
                         break;
               }

               $palette = str_pad(dechex($palette), 4, "0");
               fwrite($handle, hex2bin($palette));
               fwrite($handle, hex2bin($w.$w.$x.$x.$y.$y.$z.$z));
          }

          fwrite($handle, hex2bin(str_pad(dechex(self::VERSION_V2), 4, "0")), 2);
          fwrite($handle, hex2bin(str_pad(dechex($ncolors), 4, "0")), 2);

          foreach($input as $color)
          {
               $palette = $color[0];
               $hexcode = $color[2];

               $w = strlen($hexcode) >= 0 ? str_pad(mb_substr($hexcode,0,2), 2, "0") : "00";
               $x = strlen($hexcode) >= 2 ? str_pad(mb_substr($hexcode,2,2), 2, "0") : "00";
               $y = strlen($hexcode) >= 4 ? str_pad(mb_substr($hexcode,4,2), 2, "0") : "00";
               $z = strlen($hexcode) >= 6 ? str_pad(mb_substr($hexcode,6,2), 2, "0") : "00";
               $t = "00";

               $names = $color[3] ?? "";
               switch($palette) {

                    case "RGB": 
                         $palette = self::RGB;
                         break;
                    case "HSB": 
                         $palette = self::HSB;
                         break;
                    case "CMYK":
                         $palette = self::CMYK; 
                         break;
                    case "LAB": 
                         $palette = self::LAB;
                         break;
                    case "WideCMYK": 
                         $palette = self::WideCMYK;
                         break;
                    case "GRAYSCALE": 
                         $palette = self::GRAYSCALE;
                         break;
               }

               $palette = str_pad(dechex($palette), 4, "0");
               fwrite($handle, hex2bin($palette), 2);
               fwrite($handle, hex2bin($w.$w.$x.$x.$y.$y.$z.$z.$t.$t), 10);

               $name = implode(", ", $names);
               $length = strlen($name);

               fwrite($handle, hex2bin(str_pad(dechex($length+1), 4, "0")), 2);
               for($i = 0; $i < $length; $i++)
                    fwrite($handle, hex2bin(str_pad(bin2hex($name[$i]), 4, "0")), 2);

               fwrite($handle, hex2bin(str_pad("", 4, "0")), 2);
          }

          rewind($handle);
          $value = stream_get_contents($handle);
          fclose($handle);

          return bin2hex($value);
     }

     public static function getHeader($handle)
     {
          $version = hexdec(bin2hex(fread2($handle, 2)));
          $ncolors = hexdec(bin2hex(fread2($handle, 2)));

          return [$version, $ncolors];
     }

     public static function getBlock($handle, int $version)
     {
          $name = "";
          $palette = 0;
          $hexcode = 0;
          $array   = [];

          switch($version) {

               case self::VERSION_V1:

                    $palette = hexdec(bin2hex(fread2($handle, 2)));

                    $hexcode = 0;
                    $array   = [];

                    $w = str_pad(dechex((hexdec(bin2hex(fread2($handle, 2))) & 0xFF00) >> self::BYTE_SIZE), 2, "0");
                    $x = str_pad(dechex((hexdec(bin2hex(fread2($handle, 2))) & 0xFF00) >> self::BYTE_SIZE), 2, "0");
                    $y = str_pad(dechex((hexdec(bin2hex(fread2($handle, 2))) & 0xFF00) >> self::BYTE_SIZE), 2, "0");
                    $z = str_pad(dechex((hexdec(bin2hex(fread2($handle, 2))) & 0xFF00) >> self::BYTE_SIZE), 2, "0");

                    switch($palette) {

                         case self::RGB: 
                              $palette = "RGB";
                              $hexcode = $w.$x.$y;
                              $array = [hexdec($w)/256, hexdec($x)/256, hexdec($y)/256];
                              break;

                         case self::HSB: 
                              $palette = "HSB";
                              $hexcode = $w.$x.$y;
                              $array = [hexdec($w)/182.04, hexdec($x)/655.35, hexdec($y)/655.35];

                              break;

                         case self::CMYK: 
                              $palette = "CMYK";
                              $hexcode = $w.$x.$y.$z;
                              $array = [100 - hexdec($w)/655.35, 100 - hexdec($x)/655.35, 100 - hexdec($y)/655.35, 100 - hexdec($z)/655.35];
                              break;

                         case self::LAB: 
                              $palette = "LAB";
                              $hexcode = $w.$x.$y;
                              $array = []; 
                              throw new Exception("LAB COLOR SPACE NOT IMPLEMENTED");
                              break;

                         case self::WideCMYK: 
                              $palette = "WideCMYK";
                              $hexcode = $w.$x.$y.$z;
                              $array = [hexdec($w)/100, hexdec($x)/100, hexdec($y)/100, hexdec($z)/100];
                              break;

                         case self::GRAYSCALE: 
                              $palette = "GRAYSCALE";
                              $hexcode = $w;
                              $array = hexdec($w)/39.0625;
                              break;
                    }

               break;

               case self::VERSION_V2:

                    list($palette, $array, $hexcode) = self::getBlock($handle, self::VERSION_V1);
                    $teal   = (hexdec(bin2hex(fread2($handle, 2))) & 0xFF00) >> self::BYTE_SIZE;
                    $length =  hexdec(bin2hex(fread2($handle, 2)));

                    $name = utf8_encode(fread2($handle, 2*$length));
                    $name = preg_replace('~\(.*\)~' , "", $name);
                    $name = str_replace("\0", "", $name);

                    break;

               default: 
                    throw new Exception("Unexpected block version.");
          }

          return [$palette, $array, $hexcode, array_map("trim", explode(",", $name))];
     }

     function sortByColor($array)
     {
          usort_column($colors, 1, function ($a, $b) {

               $a = rgb2hsl($a);
               $b = rgb2hsl($b);
               
               if(!huesAreinSameInterval($a[0],$b[0])) {
                  if ($a[0] < $b[0]) return -1;
                  if ($a[0] > $b[0]) return 1;
               }

               if ($a[1] < $b[1]) return 1;
               if ($a[1] > $b[1]) return -1;
               if ($a[2] < $b[2]) return -1;
               if ($a[2] > $b[2]) return 1;

               return 0;
          });

          function huesAreinSameInterval(float $hue1, float $hue2, int $interval = 30): bool 
          {
               return (round(($hue1 / $interval), 0, PHP_ROUND_HALF_DOWN) === round(($hue2 / $interval), 0, PHP_ROUND_HALF_DOWN));
          }
     }

     public function getClosest($rgb, $colors)
     {
         $distance = 0;
         $closest = "";
         foreach ($colors as $name => $reference) {

             if (distance($reference, $rgb) > $distance) {

                 $distance = distance($reference, $rgb);
                 $closest = $name;
             }
         }

         return $closest;
     }
}
