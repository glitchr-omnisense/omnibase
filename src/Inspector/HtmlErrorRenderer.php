<?php

namespace Base\Inspector;

use \Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer as SymfonyHtmlErrorRenderer;
use Psr\Log\LoggerInterface;

class HtmlErrorRenderer extends SymfonyHtmlErrorRenderer
{
    public function __construct(
        bool|callable $debug = false,
        ?string $charset = null,
        string|FileLinkFormatter|null $fileLinkFormat = null,
        private ?string $projectDir = null,
        string|callable $outputBuffer = '',
        private ?LoggerInterface $logger = null,
    ) {
        // HtmlErrorRenderer is loaded very early, so we need to ensure that the project directory is set
        // This is done by hand using the environment variable APP_PATH and the file link formatter 
        // using invalid is better than nothing when the project directory is not set or using Docker containers.
        $projectDir = $projectDir ?? $_ENV["PWD"] ?? project_dir();
        $appPath = $_ENV["APP_PATH"] ?? null;
        if ($appPath) {
            $format = "vscode://file/%f[%b:".$_ENV["APP_PATH"]."]:%l";
        }

        $fileLinkFormat = new FileLinkFormatter($format ?? null, null, $projectDir);
        parent::__construct($debug, $charset, $fileLinkFormat, $projectDir, $outputBuffer, $logger);
    }
}