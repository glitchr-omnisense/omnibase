<?php

namespace Base\Service;

use Base\BaseBundle;
use Base\Cache\Abstract\AbstractSimpleCache;
use Base\Repository\Layout\ImageRepository;
use Base\Service\Model\Obfuscator\CompressionInterface;
use Hashids\Hashids;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV5;

class Obfuscator extends AbstractSimpleCache implements ObfuscatorInterface
{
    protected string $uuid;
    protected array $compressions = [];

    protected $compression;
    protected int $level = -1;
    protected int $maxLength = 0;
    protected ?string $encoding;

    public function warmUp(string $cacheDir): array { return []; }

    public function __construct(ParameterBagInterface $parameterBag, string $cacheDir)
    {
        $this->uuid        = $parameterBag->get("base.obfuscator.uuid") ?? Uuid::NAMESPACE_URL;

        $this->compression = $parameterBag->get("base.obfuscator.compression") ?? "null";
        $this->maxLength   = $parameterBag->get("base.obfuscator.max_length");
        $this->level       = $parameterBag->get("base.obfuscator.level");
        $this->encoding    = $parameterBag->get("base.obfuscator.encoding");

        parent::__construct($cacheDir);
    }

    public function addCompression(CompressionInterface $compression): self
    {
        $this->compressions[get_class($compression)] = $compression;
        return $this;
    }

    public function removeCompression(CompressionInterface $compression): self
    {
        array_values_remove($this->compressions, $compression);
        return $this;
    }

    public function getCompressions(): array { return $this->compressions; }
    public function getCompression(string $idOrClass): ?CompressionInterface
    {
        if (class_exists($idOrClass)) {

            $compression = $this->compressions[$idOrClass] ?? null;

        } else {

            foreach ($this->compressions as $availableCompression) {

                if ($availableCompression->supports($idOrClass)) {

                    $compression = $availableCompression;
                    break;
                }
            }
        }

        if($compression == null) {
            $compressionIds = array_keys($this->getCompressions());
            throw new \LogicException("No compression class retrieved from \"" . $compressionId . "\" identifier (available: ".implode(", ", $compressionIds).")");
        }

        $compression->setLevel($this->level);
        $compression->setMaxLength($this->maxLength);
        $compression->setEncoding($this->encoding);

        return $compression;
    }

    public function isShort() { return $this->uuid !== null; }
    public function getUuid(string $name): ?UuidV5
    {
        return Uuid::v5(Uuid::fromString($this->uuid), $name);
    }

    public function encode(array $value): string
    {
        ksort($value); // Make sure keys are sorted before serializing..

        $data = serialize($value);
        if($this->uuid == null)
            return $this->getCompression($this->compression)->encode($data);

        $identifier = $this->getUuid($data);
        if (BaseBundle::USE_CACHE && $this->hasCache("/Identifiers/" . $identifier)) {
            return $identifier;
        }

        $this->setCache("/Identifiers/".$identifier, $this->getCompression($this->compression)->encode($data));
        return $identifier;
    }

    public function decode(string $data): ?array
    {
        $uuid = $data;
        if(Uuid::isValid($uuid) && BaseBundle::USE_CACHE) {

            $_ = $this->getCache("/Identifiers/" . $uuid);
            if($_) $data = $_;
        }

        $data = $this->getCompression($this->compression)->decode($data);
        try { if($data) return unserialize($data); }
        catch (\ErrorException $e) { }

        if(Uuid::isValid($uuid) && BaseBundle::USE_CACHE)
            $this->deleteCache("/Identifiers/".$uuid);

        return null;
    }
}
