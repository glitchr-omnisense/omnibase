<?php

namespace Base\Serializer;

use Base\Serializer\Encoder\ExcelEncoder;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader as Readers;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer as Writers;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;

class Excel
{
    public function __construct(mixed $data, string $format, array $context = []) 
    { 
        $this->filesystem = new Filesystem();
        $this->data = $data;
        $this->format = $format;
        $this->context = $context;
    }

    private $filesystem;

    public const AS_COLLECTION_KEY = CsvEncoder::AS_COLLECTION_KEY;
    public const HEADERS_IN_BOLD_KEY = 'headers_in_bold';
    public const HEADERS_HORIZONTAL_ALIGNMENT_KEY = 'headers_horizontal_alignment';
    public const COLUMNS_AUTOSIZE_KEY = 'columns_autosize';
    public const COLUMNS_MAXSIZE_KEY = 'columns_maxsize';

    /**
     * @internal
     *
     * @throws NotNormalizableValueException when a value is not valid
     */
    private static function flatten(iterable $data, array &$result, string $parentKey = ''): void
    {
        foreach ($data as $key => $value) {

            if (is_object($value))
                $value = get_object_vars($value);

            if (is_iterable($value)) {
                self::flatten($value, $result, $parentKey."[".$key."]");
                continue;
            }

            $newKey = $parentKey.$key;

            if (!is_scalar($value)) {
                throw new NotNormalizableValueException(sprintf('Expected key "%s" of type object, array or scalar, %s given', $newKey, gettype($value)));
            }

            $result[sprintf('="%s"', $newKey)] = false === $value ? 0 : (true === $value ? 1 : $value);
        }
    }

    public function dump(): string
    {
        if (!is_iterable($this->data))
            throw new NotEncodableValueException(sprintf('Expected data of type iterable, %s given', gettype($this->data)));

        $spreadsheet = new Spreadsheet();

        switch ($this->format) {

            // Excel 2003
            case ExcelEncoder::XLS:
                $writer = new Writers\Xls($spreadsheet);
            break;

            // Excel 2007
            case ExcelEncoder::XLSM:
            case ExcelEncoder::XLSX:
                $writer = new Writers\Xlsx($spreadsheet);
            break;
    
            default:
                throw new InvalidArgumentException(sprintf('The format "%s" is not supported', $this->format));
        }

        $sheetIndex = 0;

        foreach ($this->data as $sheetName => $sheetData) {

            if (!is_iterable($sheetData))
                throw new NotEncodableValueException(sprintf('Expected data of sheet #%d of type "iterable", "%s" given', $sheetName, gettype($sheetData)));

            if ($sheetName === $sheetIndex)
                $sheetName = sprintf('Sheet_%d', $sheetIndex);

            $spreadsheet->setActiveSheetIndex($sheetIndex);
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle($sheetName);
            $sheetData = (array) $sheetData;

            foreach ($sheetData as $rowIndex => $cells) {

                if (!is_iterable($cells))
                    throw new NotEncodableValueException(sprintf('Expected cells of type "iterable" for data sheet #%d at row #%d, "%s" given', $sheetIndex, $rowIndex, gettype($cells)));

                $flattened = [];
                self::flatten($cells, $flattened);
                $sheetData[$rowIndex] = $flattened;
            }

            $headers = [];

            foreach ($sheetData as $cells) {

                $headers = array_keys($cells);
                break;
            }

            array_unshift($sheetData, $headers);
            $worksheet->fromArray($sheetData, null, 'A1', true);
            $headerLineStyle = $worksheet->getStyle('A1:'.$worksheet->getHighestDataColumn().'1');

            if ($this->context[self::HEADERS_HORIZONTAL_ALIGNMENT_KEY]) {
                switch ($this->context[self::HEADERS_HORIZONTAL_ALIGNMENT_KEY]) {
                    case 'left':
                        $alignment = Alignment::HORIZONTAL_LEFT;
                    break;
                    case 'center':
                        $alignment = Alignment::HORIZONTAL_CENTER;
                    break;
                    case 'right':
                        $alignment = Alignment::HORIZONTAL_RIGHT;
                    break;
                    default:
                        throw new InvalidArgumentException(sprintf('The value of context key "%s" is not valid (possible values: "left", "center" or "right")', self::HEADERS_HORIZONTAL_ALIGNMENT_KEY));
                }

                $headerLineStyle
                    ->getAlignment()
                    ->setHorizontal($alignment)
                ;
            }

            if (true === $this->context[self::HEADERS_IN_BOLD_KEY])
                $headerLineStyle->getFont()->setBold(true);

            for ($i = 1; $i <= Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()); ++$i)
                $worksheet->getColumnDimensionByColumn($i)->setAutoSize($this->context[self::COLUMNS_AUTOSIZE_KEY]);

            $worksheet->calculateColumnWidths();

            foreach ($worksheet->getColumnDimensions() as $columnDimension) {

                $colWidth = $columnDimension->getWidth();
                if ($colWidth > $this->context[self::COLUMNS_MAXSIZE_KEY])
                    $columnDimension->setAutoSize(false)->setWidth($this->context[self::COLUMNS_MAXSIZE_KEY]);
            }
        }

        try {

            $tmpFile = self::$filesystem->tempnam(sys_get_temp_dir(), $this->format);
            $writer->save($tmpFile);
            
            $content = (string) file_get_contents($tmpFile);
            self::$filesystem->remove($tmpFile);

        } catch (Exception $e) {

            throw new RuntimeException(sprintf('Excel encoding failed - %s', $e->getMessage()), 0, $e);
        }

        return $content;
    }

    public static function extension(string $path) {

        try { $extension = exif_imagetype($path); }
        catch (Exception $e) { $extension = false; }
        return $extension !== false ? mb_substr(image_type_to_extension($extension), 1) : pathinfo($path, PATHINFO_EXTENSION) ?? null;
    }

    public static function parseFile(string $filename, array $context = []): mixed
    {
        if(filter_var($filename, FILTER_VALIDATE_URL) === false) {
            
            if (!is_file($filename))
                throw new ParseException(sprintf('File "%s" does not exist.', $filename));
        
            if (!is_readable($filename))
                throw new ParseException(sprintf('File "%s" cannot be read.', $filename));
        }

        return self::parse(file_get_contents($filename), self::extension($filename), $context);
    }
    
    public static function parse(string $data, string $format, array $context = []): mixed
    {
        if (!is_scalar($data))
            throw new NotEncodableValueException(sprintf('Expected data of type scalar, %s given', gettype($data)));

        $tmpFile = (string) tempnam(sys_get_temp_dir(), $format);

        $filesystem = new Filesystem();
        $filesystem->dumpFile($tmpFile, $data);

        switch ($format) {

            // Excel 2003
            case ExcelEncoder::XLS:
                $reader = new Readers\Xls();
            break;

            // Excel 2007
            case ExcelEncoder::XLSM:
            case ExcelEncoder::XLSX:
                $reader = new Readers\Xlsx();
            break;

            default:
                throw new InvalidArgumentException(sprintf('The format "%s" is not supported', $format));
        }

        try {
            $spreadsheet = $reader->load($tmpFile);
            $filesystem->remove($tmpFile);
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Excel decoding failed - %s', $e->getMessage()), 0, $e);
        }

        $loadedSheetNames = $spreadsheet->getSheetNames();
        $data = [];

        foreach ($loadedSheetNames as $sheetIndex => $loadedSheetName) {
            $worksheet = $spreadsheet->getSheet($sheetIndex);
            $sheetData = $worksheet->toArray();

            if (0 === count($sheetData)) {
                continue;
            }

            if (false === $context[self::AS_COLLECTION_KEY]) {
                $data[$loadedSheetName] = $sheetData;
                continue;
            }

            $labelledRows = [];
            $headers = null;

            foreach ($sheetData as $rowIndex => $cells) {
                $rowIndex = (int) $rowIndex;

                if (null === $headers) {

                    $headers = [];
                    foreach ($cells as $key => $value) {

                        if (null === $value || '' === $value) continue;

                        $headers[$key] = $value;
                        unset($sheetData[$rowIndex][$key]);
                    }

                    continue;
                }

                foreach ($cells as $key => $value) {

                    if (array_key_exists($key, $headers)) 
                        $labelledRows[$rowIndex - 1] = array_merge_recursive($labelledRows[$rowIndex - 1] ?? [], self::expand((string) $headers[$key], $value));
                    else 
                        $labelledRows[$rowIndex - 1][''][$key] = $value;

                    unset($sheetData[$rowIndex][$key]);
                }

                unset($sheetData[$rowIndex]);
            }

            $data[$loadedSheetName] = $labelledRows;
        }

        return $data;
    }

    public static function expand($header, $value)
    {
        $subheader = null;
        if (preg_match('#(.+)\[(.*?)\]#', $header, $matches)) {
            $header    = $matches[1] ?? "";
            $subheader = $matches[2] ?? null;
        }

        if (!$subheader) return [$header => $value];

        return self::expand($header, [$subheader => $value]);
    }
}