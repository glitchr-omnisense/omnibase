<?php

namespace Base\Serializer\Encoder;

use Base\Serializer\Excel;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class ExcelEncoder implements EncoderInterface, DecoderInterface
{
    /**
     * Formats constants.
     */
    public const XLS = 'xls';
    public const XLSX = 'xlsx';
    public const XLSM = 'xlsm';

    /**
     * Default context constants.
     */
    private array $defaultContext = [
        Excel::AS_COLLECTION_KEY => true,
        Excel::HEADERS_IN_BOLD_KEY => true,
        Excel::HEADERS_HORIZONTAL_ALIGNMENT_KEY => 'center',
        Excel::COLUMNS_AUTOSIZE_KEY => true,
        Excel::COLUMNS_MAXSIZE_KEY => 50,
    ];

    public function supportsEncoding   (string $format, array $context = []): bool { return self::XLS === $format || self::XLSX === $format|| self::XLSM === $format; }
    public function encode(mixed $data, string $format, array $context = []): string
    {
          $context = array_merge($this->defaultContext, $context);
          $excel = new Excel($data, $format, $context);
          return $excel->dump(); // PHP TO Excel binary
    }

    public function supportsDecoding    (string $format, array $context = []): bool { return self::XLS === $format || self::XLSX === $format || self::XLSM === $format; }
    public function decode(string $data, string $format, array $context = []): mixed
    {
          $context = array_merge($this->defaultContext, $context);
          return Excel::parse($data, $format, $context); // Excel binary to PHP
    }
}