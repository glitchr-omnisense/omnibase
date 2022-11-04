<?php

namespace Base\Service;

use Hashids\Hashids;

class Obfuscator implements ObfuscatorInterface
{
    protected $hashids;
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->hashids = new Hashids($parameterBag->get("kernel.secret"));
    }

    public function encode(mixed $value, string $compressor = null): string
    {
        ksort($value); // Make sure keys are sorted before serializing..
        return $this->hashids->encodeHex(bin2hex(serialize($value)));
    }

    public function decode(string $hash, string $compressor = null): mixed  {

        if(!preg_match("/^[a-zA-Z0-9]+$/",$hash)) return null; // Only hash candidate can pass this line
        return unserialize(hex2bin($this->hashids->decodeHex($hash))); }
}
