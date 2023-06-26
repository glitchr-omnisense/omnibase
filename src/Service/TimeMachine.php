<?php

namespace Base\Service;

use Backup\Manager\Compressors\CompressorProvider;
use Backup\Manager\Compressors\CompressorTypeNotSupported;
use Backup\Manager\Compressors\GzipCompressor;
use Backup\Manager\Compressors\NullCompressor;
use Backup\Manager\Config\Config;
use Backup\Manager\Config\ConfigFieldNotFound;
use Backup\Manager\Config\ConfigNotFoundForConnection;
use Backup\Manager\Databases\Database;
use Backup\Manager\Databases\DatabaseProvider;
use Backup\Manager\Databases\DatabaseTypeNotSupported;
use Backup\Manager\Databases\MysqlDatabase;
use Backup\Manager\Databases\PostgresqlDatabase;

use Backup\Manager\Filesystems\Awss3Filesystem;
use Backup\Manager\Filesystems\Destination;
use Backup\Manager\Filesystems\DropboxFilesystem;
use Backup\Manager\Filesystems\FilesystemProvider;
use Backup\Manager\Filesystems\FilesystemTypeNotSupported;
use Backup\Manager\Filesystems\FtpFilesystem;
use Backup\Manager\Filesystems\GcsFilesystem;
use Backup\Manager\Filesystems\LocalFilesystem;
use Backup\Manager\Filesystems\RackspaceFilesystem;
use Backup\Manager\Filesystems\SftpFilesystem;
use Backup\Manager\Filesystems\WebdavFilesystem;
use Backup\Manager\Manager as BackupManager;

use DateTime;
use League\Flysystem\FilesystemException;
use LogicException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use Doctrine\Bundle\DoctrineBundle\Registry;
use League\Flysystem\Filesystem;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 *
 */
class TimeMachine extends BackupManager implements TimeMachineInterface
{
    /** @var CompressorProvider */
    protected $compressors;

    /** @var FlysystemInterface */
    protected $flysystem;

    /** @var FilesystemProvider */
    protected $filesystems;
    /** @var array */
    protected $filesystemConfigs;

    /** @var DatabaseProvider */
    protected $databases;
    /** @var array */
    protected $databaseConfigs;

    /** @var OutputInterface */
    protected $output;

    protected string $cacheDir;

    /**
     * @param OutputInterface $output
     * @return $this
     */
    public function setCommandOutput(OutputInterface $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir . "/timemachine";
    }

    public function preventAbort()
    {
        ignore_user_abort(true);
        pcntl_signal(SIGINT, "signal_handler");

        /**
         * @param $signal
         * @return void
         */
        function signal_handler($signal)
        {
            switch ($signal) {
                case SIGINT:
                    echo "Time machine is preventing you to abort the procedure. Please kindly wait until the end of this script.\n";
            }
        }
    }

    /**
     * @var string
     */
    protected string $environment;

    public function __construct(Flysystem $flysystem, Registry $doctrine, ParameterBagInterface $parameterBag)
    {
        //
        // Common variables
        $this->cacheDir      = $parameterBag->get("kernel.cache_dir");
        $this->compression   = $parameterBag->get("base.time_machine.compression");
        $this->environment   = $parameterBag->get("kernel.environment"); 
       
        //
        // Prepare filesystem configuration
        $config = ["type" => "local", "root" => $this->getCacheDir()];
        $this->filesystemConfigs["local"] = $config;

        foreach ($flysystem->getStorageNames() as $storageName) {
            if (str_ends_with($storageName, ".public")) {
                continue;
            }
            $type = explode(".", $storageName)[0] ?? "local";

            $config = match ($type) {
                "ftp", "sftp" => ["type" => $type, 'root' => $flysystem->prefixPath("", $storageName), "connection" => $flysystem->getConnectionOptions($storageName)],
                default => ['type' => $type, 'root' => $flysystem->prefixPath("", $storageName)],
            };

            $this->filesystemConfigs[$storageName] = $config;
        }

        //
        // Prepare database configuration
        foreach ($doctrine->getConnectionNames() as $connectionName => $_) {
            $params = $doctrine->getConnection($connectionName)->getParams();
            $this->databaseConfigs[$connectionName] = [
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
        $filesystems = new FilesystemProvider(new Config($this->filesystemConfigs));
        if (class_exists(Awss3Filesystem::class)) {
            $filesystems->add(new Awss3Filesystem());
        }
        if (class_exists(GcsFilesystem::class)) {
            $filesystems->add(new GcsFilesystem());
        }
        if (class_exists(DropboxFilesystem::class)) {
            $filesystems->add(new DropboxFilesystem());
        }
        if (class_exists(FtpFilesystem::class)) {
            $filesystems->add(new FtpFilesystem());
        }
        if (class_exists(LocalFilesystem::class)) {
            $filesystems->add(new LocalFilesystem());
        }
        if (class_exists(SftpFilesystem::class)) {
            $filesystems->add(new SftpFilesystem());
        }
        if (class_exists(WebdavFilesystem::class)) {
            $filesystems->add(new WebdavFilesystem());
        }

        $databases = new DatabaseProvider(new Config($this->databaseConfigs));
        $databases->add(new MysqlDatabase());
        $databases->add(new PostgresqlDatabase());

        $compressors = new CompressorProvider();
        $compressors->add(new GzipCompressor());
        $compressors->add(new NullCompressor());

        parent::__construct($filesystems, $databases, $compressors);
        $this->filesystems = $filesystems;
        $this->flysystem = $flysystem;
        $this->databases = $databases;
        $this->compressors = $compressors;

        $this->maxCycle = $parameterBag->get("base.time_machine.max_cycle");
        $this->timeLimit = $parameterBag->get("base.time_machine.time_limit");
    }

    protected int $maxCycle;
    public function getMaxCycle(): ?int
    {
        return 4;
        return $this->maxCycle;
    }

    /**
     * @param int $maxCycle
     * @return $this
     */
    public function setMaxCycle(int $maxCycle)
    {
        $this->maxCycle = $maxCycle;
        return $this;
    }

    protected mixed $timeLimit;

    public function getTimeLimit(): ?DateTime
    {
        if(is_string($this->timeLimit) && str_starts_with($this->timeLimit, "+")) {
            $this->timeLimit[0] = "-";
        }
        
        return cast_datetime($this->timeLimit);
    }

    /**
     * @param int|null $timeLimit
     * @return $this
     */
    public function setTimeLimit(null|string|DateTime|int $timeLimit)
    {
        $this->timeLimit = $timeLimit;
        return $this;
    }

    protected ?string $compression = "gzip";

    public function getCompression(): ?string
    {
        return $this->compression;
    }

    /**
     * @param string|null $compression
     * @return $this
     */
    /**
     * @param string|null $compression
     * @return $this
     */
    public function setCompression(?string $compression)
    {
        $this->compression = $compression;
        return $this;
    }

    public function getDatabase(string $name): Database
    {
        return $this->databases->get($name);
    }

    public function getDatabaseConfiguration(string $name): Database
    {
        return $this->databases->get($name);
    }

    public function getDatabaseList(): array
    {
        $list = [];
        foreach ($this->databases->getAvailableProviders() as $connectionName) {
            $list[$connectionName] = $this->databases->get($connectionName);
        }

        return $list;
    }

    public function getStorage(string $name): Filesystem
    {
        return $this->filesystems->get($name);
    }

    public function getStorageList(): array
    {
        $list = [];
        foreach ($this->filesystems->getAvailableProviders() as $storageName) {
            $list[$storageName] = $this->filesystems->get($storageName);
        }

        return $list;
    }

    /**
     * @param int $id
     * @param int|array $storageNames
     * @param string|null $prefix
     * @param int $cycle
     * @return array|null
     */
    public function getSnapshot(int $id, int|array $storageNames, ?string $prefix = null, int $cycle = -1)
    {
        $snapshots = $this->getSnapshots($storageNames, $prefix, $cycle);
        if ($id >= count_leaves($snapshots)) {
            throw new LogicException("Unknown ID #" . $id . " provided.");
        }

        $prefix = $prefix ?? $this->environment;

        $i = 0;

        if ($id < 0) {
            $id = count_leaves($snapshots) - 1;
        }
        foreach ($snapshots as $storageName => $files) {
            foreach ($files as $file) {
                if ($id != $i++) {
                    continue;
                }
                return [$storageName, $file];
            }
        }

        return null;
    }

    /**
     * @param int|array $storageNames
     * @param string|null $prefix
     * @param $cycle
     * @return array
     */
    public function getSnapshots(int|array $storageNames = [], ?string $prefix = null, $cycle = -1): array
    {
        $snapshots = [];
        $prefix = $prefix ?? $this->environment;

        $storageNames = array_flip($storageNames);
        foreach (array_intersect_key($this->getStorageList(), $storageNames) as $storageName => $filesystem) {

            $snapshots[$storageName] = [];
            foreach ($filesystem->listContents("/") as $content) {
                if ($content->type() != "file") {
                    continue;
                }
                if (!str_starts_with($content->path(), $prefix)) {
                    continue;
                }
                if ($cycle > 0 && !str_ends_with(basenameWithoutExtension($content->path()), "-" . $cycle)) {
                    continue;
                }

                $snapshots[$storageName][] = $content->path();
            }

            // Properly sort array
            foreach($snapshots[$storageName] as &$snapshot) {
                $snapshot = str_replace("-","_",$snapshot);
            }

            natsort($snapshots[$storageName]);
            $snapshots[$storageName] = array_values($snapshots[$storageName]);
            foreach($snapshots[$storageName] as &$snapshot) {
                $snapshot = str_replace("_","-",$snapshot);
            }
        }

        return $snapshots;
    }

    public function getSnapshotsByCycle(int|array $storageNames = [], ?string $prefix = null, $cycle = -1): array
    {
        $date = null;
        $snapshotByCycles = [];
        foreach($this->getSnapshots($storageNames, $prefix, $cycle) as $storageName => $files) {

            $snapshotByCycles[$storageName] ??= [];
            foreach($files as $file) {

                $matches = [];
                if(preg_match('/' . preg_quote($prefix) . '\-([0-9]+)\.\w+/', basename($file), $matches)) {
                    $date = $matches[1];
                }
                
                if($date !== null) {
                    $snapshotByCycles[$storageName][$date] ??= [];
                    $snapshotByCycles[$storageName][$date][] = $file;
                }
            }
        }

        return $snapshotByCycles;
    }

    public function getLastCycle(array $files, ?string $prefix = null): int
    {
        $prefix = $prefix ?? "";
        $matches = [];
        $lastCycle = 0;
        if (preg_match('/' . preg_quote($prefix) . '\-([0-9]{1,3})\.\w+/', basename(end($files)), $matches)) {
            $lastCycle = intval($matches[1]);
        } elseif (preg_match('/' . preg_quote($prefix) . '\.\w/', basename(end($files)), $matches)) {
            $lastCycle = 1;
        }

        return $lastCycle;
    }

    /**
     * @param string|array|null $databases
     * @param int|array $storageNames
     * @param string|null $prefix
     * @param int $cycle
     * @return true
     * @throws CompressorTypeNotSupported
     * @throws ConfigFieldNotFound
     * @throws ConfigNotFoundForConnection
     * @throws DatabaseTypeNotSupported
     * @throws FilesystemException
     * @throws FilesystemTypeNotSupported
     */
    public function backup(null|string|array $databases, int|array $storageNames = [], bool $userInfo = false, ?string $prefix = null, int $cycle = -1)
    {
        $prefix = $prefix ?? $this->environment;
        $this->output?->section()->writeln("<info>Backup procedure started for </info> \"" . $prefix. "\"");

        // Remove too old backup
        $dateLimit = $this->getTimeLimit();
        $snapshots = $this->getSnapshots($storageNames, $prefix);
        foreach ($snapshots as $storageName => $files) {

            $filesystem = $this->filesystems->get($storageName);
            foreach ($files as $id => $file) {

                $matches = [];
                $dateTime = null;
                if (preg_match('/' . preg_quote($prefix) . '-([0-9]*)/', $file, $matches)) {
                    $dateTime = DateTime::createFromFormat('Ymd', $matches[1]);
                }

                if ($dateTime && $dateTime < $dateLimit) {
                    $this->output?->section()->writeln("- Too old version found (older than " . $this->timeLimit . "), deleting <warning>" . $file . "</warning>");
                    $filesystem->delete($file);
                    unset($files[$id]);
                }
            }

            $snapshots[$storageName] = array_values($files);
        }

        // Find today versions
        $snapshots = $this->getSnapshots($storageNames, $prefix, $cycle);
        if (!$snapshots) {
            throw new LogicException("No valid storage selected.");
        }

        //
        // Remove too old cycles
        foreach ($snapshots as $storageName => $files) {

            $filesystem = $this->filesystems->get($storageName);
            $lastCycle = $this->getLastCycle($files);

            $date = null;
            $snapshotByCycles = [];
            foreach($files as $file) {

                $matches = [];
                if(preg_match('/' . preg_quote($prefix) . '\-([0-9]+)\.\w+/', basename($file), $matches)) {
                    $date = $matches[1];
                } else if($date !== null) {
                    $snapshotByCycles[$date] ??= [];
                    $snapshotByCycles[$date][] = $file;
                }
            }

            // Remove today cycles
            $cycles = $snapshotByCycles[(new DateTime())->format('Ymd')] ?? [];
            for ($i = 0, $Ncycles = count($cycles), $N = $Ncycles - $this->getMaxCycle() + 1; $i < $N && $this->getMaxCycle() > 0; $i++) {
                
                $this->output?->section()->writeln("- Too many cycles found (limit at ".$this->getMaxCycle()."), deleting <warning>" . $cycles[$i] . "</warning>");
                $filesystem->delete($cycles[$i]);
            }
        }

        // Prepare backup
        $destinations = [];

        $prefix = $prefix . "-" . (new DateTime())->format('Ymd');
        foreach ($snapshots as $storageName => $files) {

            //
            // Remote older version
            $filesystem = $this->filesystems->get($storageName);
            $lastCycle = $this->getLastCycle($files);

            //
            // Compute next version
            $cycle = $cycle < 0 ? $lastCycle + 1 : min($cycle, $lastCycle + 1);
            $file = $cycle > 1 ? $prefix . "-" . $cycle . ".tar" : $prefix . ".tar";

            $destinations[] = new Destination($storageName, $file);
        }

        // Dump database to local repository
        $this->output?->section()->writeln("<info>- Temporary working directory:</info> " . $this->getCacheDir() . "/" . $prefix);
        if ($databases) {
            $databases = is_string($databases) ? [$databases] : $databases;
            $this->output?->section()->writeln("<info>- Backing database(s):</info> " . implode(", ", $databases));

            foreach ($databases as $database) {
                parent::makeBackup()->run($database, [new Destination("local", $prefix . "/databases/" . $database . ".sql")], "null");
            }
        } else {

            $this->output?->section()->writeln("<warning>- No database backed up..</warning> please provide `--database` option");
        }

        // Prepare backup directory
        if (!is_dir($this->getCacheDir() . "/" . $prefix)) {
            mkdir($this->getCacheDir() . "/" . $prefix, 0755);
        }

        // Save some user info
        if($userInfo) {
            $this->extractUserInfo($this->getCacheDir() . "/" . $prefix . "/.user.log");
        }

        // Compress and transfer
        $output = $this->buildArchive($this->getCacheDir() . "/" . $prefix . "/application.tar", getcwd(), [$this->cacheDir], false, false);
        $output = $this->buildCompressedArchive($this->getCacheDir() . "/" . $prefix . ".tar", $this->getCacheDir() . "/" . $prefix);

        foreach ($destinations as $id => $destination) {

            $filesystem = $this->filesystems->get($destination->destinationFilesystem());

            $compressor = $this->compressors->get($this->compression);
            $path = $compressor->getCompressedPath($destination->destinationPath());
            $prefix = $this->flysystem->prefixPath($path, $destination->destinationFilesystem());

            if ($stream = fopen($output, 'r')) {

                $this->output?->section()->writeln("<info>- Sending \"".$prefix."\"..</info> to \"".$destination->destinationFilesystem()."\" ongoing. Please wait..");
                $filesystem->writeStream($path, $stream);
                fclose($stream);
            }

            $this->output?->section()->writeln("<info>- Application backup #" . ($id + 1) . "</info> in \"" . $destination->destinationFilesystem() . "\": " . $prefix);
        }

                
        $this->output?->section()->writeln("<info>- Data backup saved..</info> That's all folks !");
        if (file_exists($output)) {
            unlink($output);
        }

        return true;
    }

    /**
     * @param int $id
     * @param bool $restoreDatabase
     * @param bool $restoreApplication
     * @param int|array $storageNames
     * @param string|null $prefix
     * @param int $cycle
     * @return true
     * @throws CompressorTypeNotSupported
     * @throws ConfigFieldNotFound
     * @throws ConfigNotFoundForConnection
     * @throws DatabaseTypeNotSupported
     * @throws FilesystemTypeNotSupported
     * @throws FilesystemException
     */
    public function restore(int $id, bool $restoreDatabase, bool $restoreApplication, int|array $storageNames = [], ?string $prefix = null, int $cycle = -1)
    {
        $prefix = $prefix ?? $this->environment;

        list($storageName, $file) = $this->getSnapshot($id, $storageNames, $prefix, $cycle);
        if (!$storageName) {
            throw new LogicException("No snapshot found among the list of storages provided: \"" . implode(",", $storageNames) . "\"");
        }

        $location = getcwd() . "-" . str_lstrip(basename(basenameWithoutExtension($file), ".tar"), $prefix . "-") . "-at-" . date("Ymd-his");
        if (!dir_empty($location)) {
            throw new LogicException("Restoration directory is not empty: \"" . $location . "\"");
        }

        $filesystem = $this->filesystems->get($storageName);

        $localFile = $this->getCacheDir() . "/" . basename($file);
        $resource = $filesystem->readStream($file);
        if ($resource) {
            file_put_contents($localFile, $resource);
            fclose($resource);
        }

        $this->openArchive($localFile);

        // Restore filesystem
        $outputDir = dirname($localFile) . "/" . basename(basenameWithoutExtension($localFile), ".tar");
        if (!$restoreApplication) {
            $this->output?->section()->writeln("<info>- Application not restored !</info> ");
        } else {
            $this->openArchive($outputDir . "/application.tar");
            rename($outputDir . "/application", $location);

            if ($this->output) {
                $this->output->section()->writeln("<info>- Restoration location:</info> " . $location);
                $this->output->section()->writeln("<warning>  Please move by yourself to the final location !</warning>");
            }
        }

        if (!$restoreDatabase) {
            $this->output?->section()->writeln("<info>- Database not restored !</info> ");
        } else {
            $finder = new Finder();
            $databases = [];
            foreach ($finder->name('*.sql')->in($outputDir . "/databases") as $sql) {
                $databases[] = basename($sql, ".sql");
            }

            if ($databases) {
                $this->output?->section()->writeln("<info>- Restoring databases:</info> " . implode(", ", $databases));
                foreach ($databases as $database) {
                    parent::makeRestore()->run("local", basename($outputDir) . "/databases/" . $database . ".sql", $database, "null");
                }
            }
        }

        return true;
    }

    public function openCompressedArchive(string $output): ?string
    {
        return $this->openArchive($output, true);
    }

    public function openArchive(string $output, bool $compression = false): ?string
    {
        // Compress tarball
        if ($compression) {

            $compressor = $this->compressors->get($this->compression);
            $decompressedOutput = $compressor->getDecompressedPath($output);

            $this->output?->section()->writeln("<info>- Decompressing.. </info> ./" . basename($decompressedOutput));
            $command = $compressor->getDecompressCommandLine($output);

            list($_, $ret) = [[], false];
            if ($command) {
                exec($command, $_, $ret);
            }

            $output = $decompressedOutput;
        }

        // Untar application
        list($_, $ret) = [[], false];
        $outputDir = dirname($output) . "/" . basename(basenameWithoutExtension($output), ".tar");

        // Prepare backup directory
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755);
        }

        exec(sprintf('tar --directory=%s -xf %s', escapeshellarg($outputDir), escapeshellarg($output)), $_, $ret);

        return $outputDir;
    }

    public function buildCompressedArchive(string $output, string $directory, array $excludes = [], bool $verbose = true): ?string
    {
        return $this->buildArchive($output, $directory, $excludes, true, $verbose);
    }

    public function extractUserInfo(string $output): ?string
    {
        // Prepare tarball archive
        $output = str_replace(getcwd(), ".", $output);

        list($_, $ret) = [[], false];
        exec(sprintf('echo "[CMD] date" > %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('date >> %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('echo "" >> %s', escapeshellarg($output)), $_, $ret);
        
        exec(sprintf('echo "[CMD] hostname" >> %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('hostname >> %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('echo "" >> %s', escapeshellarg($output)), $_, $ret);

        exec(sprintf('echo "[CMD] env" >> %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('env >> %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('echo "" >> %s', escapeshellarg($output)), $_, $ret);

        exec(sprintf('echo "[CMD] last" >> %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('last >> %s', escapeshellarg($output)), $_, $ret);
        exec(sprintf('echo "" >> %s', escapeshellarg($output)), $_, $ret);

        return $ret == 0 ? $output : null;
    }

    public function buildArchive(string $output, string $directory, array $excludes = [], bool $compression = false, bool $verbose = true): ?string
    {
        // Prepare tarball archive
        $output = str_replace(getcwd(), ".", $output);
        $directory = str_replace(getcwd(), ".", $directory);
        $excludes = array_map(fn($o) => str_replace(getcwd(), ".", $o), $excludes);

        $exclusions = "";
        foreach ($excludes as $exclude) {
            $exclusions .= "--exclude='" . $exclude . "'";
        }

        if($verbose) $this->output?->section()->writeln("<info>- Preparing tarball archive:</info> ./" . basename($output). " (temporary working directory: ".escapeshellarg($directory).")");

        list($_, $ret) = [[], false];
        exec(sprintf('tar %s --directory=%s -cf %s %s', $exclusions, escapeshellarg($directory), escapeshellarg($output), '.'), $_, $ret);

        // Compress tarball
        if ($compression) {

            if ($ret) {
                throw new LogicException("Failed to create tarball: " . $output);
            }

            $compressor = $this->compressors->get($this->compression);
            $compressedOutput = $compressor->getCompressedPath($output);

            if($verbose) $this->output?->section()->writeln("<info>- Compressing.. </info> ./" . basename($compressedOutput));
            $command = $compressor->getCompressCommandLine($output);

            list($_, $ret) = [[], false];
            if ($command) {
                exec($command, $_, $ret);
            }

            return $ret == 0 ? $compressedOutput : null;
        }

        return $ret == 0 ? $output : null;
    }
}
