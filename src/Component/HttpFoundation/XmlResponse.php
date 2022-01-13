<?php

namespace Base\Component\HttpFoundation;

use DOMDocument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class XmlResponse extends Response
{
    public function __construct($data = null, int $status = 200, array $headers = [])
    {
        parent::__construct($data, $status, array_merge($headers, ['Content-Type' => 'text/xml']));
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
    public static function fromXmlString(string $source, int $options = null, int $status = 200, array $headers = [])
    {
        $dom = new DOMDocument();
        $dom->loadXML($source, $options);
        return new static($dom->saveXML(), $status, $headers, true);
    }

    public static function fromXmlFile(string $filename, int $options = null, int $status = 200, array $headers = [])
    {
        $dom = new DOMDocument();
        $dom->load($filename, $options);
        return new static($dom->saveXML(), $status, $headers, true);
    }

}