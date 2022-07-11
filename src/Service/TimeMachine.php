<?php

namespace Base\Service;

use BackupManager\Compressors\CompressorProvider;
use BackupManager\Compressors\GzipCompressor;
use BackupManager\Compressors\NullCompressor;
use BackupManager\Config\Config;
use BackupManager\Databases\Database;
use BackupManager\Databases\DatabaseProvider;
use BackupManager\Databases\MysqlDatabase;
use BackupManager\Databases\PostgresqlDatabase;
use BackupManager\Filesystems\Awss3Filesystem;
use BackupManager\Filesystems\Destination;
use BackupManager\Filesystems\DropboxFilesystem;
use BackupManager\Filesystems\FilesystemProvider;
use BackupManager\Filesystems\FtpFilesystem;
use BackupManager\Filesystems\GcsFilesystem;
use BackupManager\Filesystems\LocalFilesystem;
use BackupManager\Filesystems\RackspaceFilesystem;
use BackupManager\Filesystems\SftpFilesystem;
use BackupManager\Filesystems\WebdavFilesystem;
use BackupManager\Manager as BackupManager;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Exception;
use League\Flysystem\Filesystem;
use Phar;
use PharData;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use UnexpectedValueException;

class TimeMachine extends BackupManager implements TimeMachineInterface
{
    /** @var CompressorProvider */
    protected $compressors;

    /** @var FilesystemProvider */
    protected $filesystems;
    /** @var array */
    protected $filesystemConfigs;

    /** @var DatabaseProvider */
    protected $databases;
    /** @var array */
    protected $databaseConfigs;

    public function getCacheDir() { return $this->cacheDir."/timemachine"; }
    public function preventAbort()
    {
        ignore_user_abort(true);
        pcntl_signal(SIGINT, "signal_handler");

        function signal_handler($signal) {
            switch($signal) {
                case SIGINT:
                    echo "Time machine is preventing to abort. Please kindly wait until the end of this script.\n";
            }
        }
    }

    public function __construct(Flysystem $flysystem, Registry $doctrine, ParameterBagInterface $parameterBag)
    {
        //
        // Common variables
        $this->cacheDir      = $parameterBag->get("kernel.cache_dir");
        $this->compression   = $parameterBag->get("base.time_machine.compression");
        $this->snapshotLimit = $parameterBag->get("base.time_machine.snapshot_limit");

        //
        // Prepare filesystem configuration
        foreach($flysystem->getStorageNames() as $storageName)
            $this->filesystemConfigs[] = ['type' => 'local', 'name' => $storageName];

        //
        // Prepare database configuration
        foreach($doctrine->getConnectionNames() as $connectionName => $_) {

            $params = $doctrine->getConnection($connectionName)->getParams();
            $this->databaseConfigs[] = [
                "type" => $params["driver"],
                "host" => $params["host"],
                "port" => $params["port"],
                "user" => $params["user"],
                "pass" => $params["password"],
                "database" => $params["dbname"] ?? null,
                "extraParams" => $params["driverOptions"],
            ];
        }

        //
        // Build providers
        $filesystems = new FilesystemProvider(new Config(["type" => "Local", "root" => $this->getCacheDir()]));

        $filesystems->add(new Awss3Filesystem);
        $filesystems->add(new GcsFilesystem);
        $filesystems->add(new DropboxFilesystem);
        $filesystems->add(new FtpFilesystem);
        $filesystems->add(new LocalFilesystem);
        $filesystems->add(new RackspaceFilesystem);
        $filesystems->add(new SftpFilesystem);
        $filesystems->add(new WebdavFilesystem);

        $databases = new DatabaseProvider(new Config($this->databaseConfigs));
        $databases->add(new MysqlDatabase);
        $databases->add(new PostgresqlDatabase);

        $compressors = new CompressorProvider;
        $compressors->add(new GzipCompressor);
        $compressors->add(new NullCompressor);

        parent::__construct($filesystems, $databases, $compressors);
        $this->filesystems = $filesystems;
        $this->databases   = $databases;
        $this->compressors = $compressors;
    }

    //
    //
    protected ?string $compression;
    public function getCompression(): ?string { return $this->compression; }
    public function setCompression(?string $compression)
    {
        $this->compression = $compression;
        return $this;
    }

    protected array $destinations = [];
    public function getDestinations(int|array $ids): array
    {
        if(!is_array($ids)) $ids = [$ids];
        return array_filter($this->destinations, fn($id) => in_array($id, $ids), ARRAY_FILTER_USE_KEY);
    }
    public function addDestination(Destination $destination)
    {
        if(in_array($destination, $this->destinations) === false)
            $this->destination = $destination;

        return $this;
    }
    public function removeDestination(Destination $destination)
    {
        if(in_array($destination, $this->destinations))
            $this->destinations[] = $destination;

        return $this;
    }

    public function getDatabase(string $name): Database { return $this->databases->get($name); }
    public function getDatabaseConfiguration(string $name): Database { return $this->databases->get($name); }
    public function getDatabaseList(string $name): Database { return $this->databases->get($name); }
    public function getStorage(string $name): Filesystem { return $this->filesystems->get($name); }
    public function getStorageList(): array { return [] /*$this->filesystems->get($name)*/; }

    public function getLastSnapshot(int|array $ids): array { $snapshot = $this->getSnapshots(); return end($snapshot); }
    public function getSnapshots(int|array $ids = []): array
    {
        $destinations = $this->getDestinations($ids);
        dump($destinations);
        exit(1);
        // Flysystem
        // $filesystem->listContents($path, );
        return [];
    }

    public function backup(int|array $ids)
    {
        $destinations = $this->getDestinations($ids);
        return $this->makeBackup()->run($this->databaseName, $destinations, $this->compression);
    }

    public function restore(int $id, int $version = -1)
    {
        if($version < 0)

        $fs = $this->destination->destinationFilesystem();
        $location    = 'test/backup.sql.gz';

        try {
            $a = new PharData('archive.tar');

            // ADD FILES TO archive.tar FILE
            $a->addFile('data.xls');
            $a->addFile('index.php');

            // COMPRESS archive.tar FILE. COMPRESSED FILE WILL BE archive.tar.gz
            $a->compress(Phar::GZ);

            unlink('archive.tar');

        } catch (Exception $e) { throw $e; }

        // return $this->makeRestore()->run($fs, , $this->databaseName, $this->compression);
    }
}
