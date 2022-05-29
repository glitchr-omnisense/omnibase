<?php

namespace Base\Serializer\Encoder;

use Base\Serializer\Aco;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class AcoEncoder implements EncoderInterface, DecoderInterface
{
     const FORMAT = 'aco';

     public function supportsEncoding   (string $format, array $context = []): bool { return self::FORMAT === $format; }
     public function encode(mixed $data, string $format, array $context = []): string
     {
          return Aco::dump($data, $context["flags"] ?? 0);
     }

     public function supportsDecoding    (string $format, array $context = []): bool { return self::FORMAT === $format; }
     public function decode(string $data, string $format, array $context = []): mixed
     {
          return Aco::parse($data, $context["flags"] ?? 0);
     }
}