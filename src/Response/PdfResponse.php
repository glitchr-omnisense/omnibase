<?php

namespace Base\Response;

use DOMDocument;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfResponse extends Response
{
    public function __construct(string|Response $data = null, int $status = 200, array $headers = [])
    {
        if ($data instanceof Response)
            $data = $data->getContent();

        $options = array_pop_key("options", $headers) ?? [];
        $stream  = array_pop_key("stream", $headers) ?? [];
        $debug   = array_pop_key("debug", $headers) ?? false;
        if($debug) return parent::__construct($data, $status, $headers);

        $dompdf = new Dompdf(array_merge([
            'isFontSubsettingEnabled' => false,
            'isRemoteEnabled' => true,
        ], $options));

        $dompdf->loadHtml($data);
        $dompdf->render();

        parent::__construct(
            $dompdf->stream('document.pdf', array_merge(['compress' => true, 'Attachment' => false], $stream)),
            $status,
            array_merge($headers, ['Content-Type' => 'application/pdf']));
    }

    /**
     * Factory method for chainability.
     *
     * Example:
     *
     *     return JsonResponse::fromJsonString('{"key": "value"}')
     *         ->setSharedMaxAge(300);
     *
     * @param string $data    The JSON response string
     * @param int    $status  The response status code
     * @param array  $headers An array of response headers
     *
     * @return static
     */
    public static function fromPdfString(string $source, int $status = 200, array $headers = []): Response
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($source);
        $dompdf->render();

        return new static(
            $dompdf->stream('resume', ["Attachment" => false]),
            $status,
            array_merge($headers, ['Content-Type' => 'application/pdf'])
        );
    }

    public static function fromPdfFile(string $filename, int $status = 200, array $headers = []): Response
    {
        return new BinaryFileResponse($filename);
    }
}
