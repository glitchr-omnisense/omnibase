<?php

namespace Base\Console\Command;

use Base\Service\Flysystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

#[AsCommand(name:'cache:clear', aliases:[], description:'')]
class CacheClearCommand extends \Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand
{
    public function __construct(CacheClearerInterface $cacheClearer, Filesystem $filesystem = null, ?Flysystem $flysystem = null)
    {
        $this->flysystem = $flysystem;
        parent::__construct($cacheClearer, $filesystem);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $kernel = $this->getApplication()->getKernel();
        $realCacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir');

        $testFile = $realCacheDir."/.test";
        $testFileExists = file_exists($testFile);

        $ret = parent::execute($input, $output);

        file_put_contents($testFile, "Hello World !");
        if(!$testFileExists)
            $io->warning(sprintf('Cache requires to run a second `cache:clear` to account for custom base bundle features.', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        if($this->flysystem !== null) {

            $io->note("Flysystem symlink(s) got generated in public directory.");

            foreach($this->flysystem->getStorageNames(false) as $storageName) {

                if(!$this->flysystem->hasStorage($storageName.".public"))
                    continue;

                $realPath = str_rstrip($this->flysystem->prefixPath("", $storageName), "/");

                $publicPath = $this->flysystem->getPublicRoot($storageName.".public");
                $publicPath = str_rstrip($publicPath, "/");
                if($realPath == $publicPath)
                    continue;

                    if(is_link($publicPath) || file_exists($publicPath)) {

                    if(is_link($publicPath)) unlink($publicPath);
                    else if(is_emptydir($publicPath)) rmdir($publicPath);
                    else exit("Public path \"$publicPath\" already exists but it is not a symlink\n");
                }

                symlink($realPath, $publicPath);
            }
        }

        return $ret;
    }
}
