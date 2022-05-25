<?php

namespace Base\Console\Command;

use App\Entity\User;
use Base\Entity\Extension\Log;

use Base\Service\BaseService;
use Doctrine\ORM\EntityManager;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Base\Console\Command;
use Doctrine\ORM\EntityManagerInterface;

class UserLogCommand extends Command
{
    protected static $defaultName = 'user:log';

    public function __construct(EntityManagerInterface $entityManager, BaseService $baseService)
    {
        $this->entityManager = $entityManager;
        $this->baseService = $baseService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('user',         null, InputOption::VALUE_OPTIONAL, 'Should I consider them with a specific user ?');
        $this->addOption('impersonator', null, InputOption::VALUE_OPTIONAL, 'Should I consider them with a specific impersonator user ?');
        $this->addOption('event',        null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific event ?');
        $this->addOption('expiry',       null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific expiry value ?');
        $this->addOption('pretty',       null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific pretty value ?');
        $this->addOption('statutCode',   null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific status code ?');

        $this->addOption('show',      null, InputOption::VALUE_NONE, 'Should I show them ?');
        $this->addOption('clear',     null, InputOption::VALUE_NONE, 'Should I clear them ?');
        $this->addOption('clear-all', null, InputOption::VALUE_NONE, 'Should I clear them all?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $event                  = $input->getOption('event');

        $userIdentifier         = $input->getOption('user');
        $impersonatorIdentifier = $input->getOption('impersonator');

        $actionShow             = $input->getOption('show');
        $actionClear            = $input->getOption('clear');
        $actionClearAll         = $input->getOption('clear-all');

        $logRepository = $this->entityManager->getRepository(Log::class);
        $userRepository = $this->entityManager->getRepository(User::class);

        if($actionClearAll) $filteredLogs = $logRepository->findAll();
        else {

            $expiry = $input->getOption('expiry');
            $defaultExpiry = $this->baseService->getParameterBag("base.extension.logging_default_expiry");

            // Format monitored entries
            $user = $userRepository->loadUserByIdentifier($userIdentifier);
            $impersonator = ($impersonatorIdentifier ? $userRepository->loadUserByIdentifier($impersonatorIdentifier) : null);

            $filteredLogs = [];
            if($event) {

                $filter = [];
                $filter["event"]        = $event;
                $filter["impersonator"] = $impersonator;
                $filter["pretty"]       = $pretty ?? ".*";
                $filter["pretty"]       = str_replace("\\", "\\\\", $filter["pretty"]);
                $filter["pretty"]       = trim(ltrim($filter["pretty"], '\\'));
                $filter['expiry']       = $expiry ?? $defaultExpiry;
                $filter['statusCode']   = trim($statusCode ?? ".*");

                if($user) $logs = $logRepository->findByUserAndCreatedAtYoungerThan($user, $filter["expiry"], ["event" => $filter["event"]])->getResult();
                else      $logs = $logRepository->findByCreatedAtYoungerThan($filter["expiry"], ["event" => $event])->getResult();

                $filteredLogs = $this->applyFilter($logs, $filter);

            } else {

                // Monitored listeners
                $monitoredEntries = $this->baseService->getParameterBag("base.logging") ?? [];
                foreach ($monitoredEntries as $key => $entry) {

                    if (!array_key_exists("event", $entry))
                        throw new \Exception("Missing key \"event\" in monitored events #" . $key);

                    $filter["event"]        = $entry["event"];
                    $filter["impersonator"] = $impersonator;
                    $filter["pretty"]       = $entry["pretty"] ?? ".*";
                    $filter["pretty"]       = str_replace("\\", "\\\\", $filter["pretty"]);
                    $filter["pretty"]       = trim(ltrim($filter["pretty"], '\\'));
                    $filter['expiry']       = $expiry ?? $entry["expiry"] ?? $defaultExpiry;
                    $filter["statusCode"]   = trim($entry["statusCode"] ?? ".*");

                    if($user) $logs = $logRepository->findByUserAndCreatedAtYoungerThan($user, $filter["expiry"], ["event" => $filter["event"]])->getResult();
                    else      $logs = $logRepository->findByCreatedAtYoungerThan($filter["expiry"], ["event" => $filter["event"]])->getResult();

                    $filteredLogs = array_merge($filteredLogs, $this->applyFilter($logs, $filter));
                }
            }
        }

        // Show log list
        $nLogs = count($filteredLogs);
        if($actionShow) {
            
            foreach ($logs as $key => $log) {

                $message = "Entry ID #" .($key+1) . " / <info>Log #" . $log->getId()." \"".$log."\"</info>";
                $output->section()->writeln($message);
            }
        }

        $output->section()->writeln($nLogs . ' log(s) found');
        if($actionClear || $actionClearAll) {

            $output->section()->writeln('<warning>These logs are now erased..</warning>');
            foreach($filteredLogs as $log) {
                $this->entityManager->remove($log);
                $this->entityManager->flush();
            }
        }

        return Command::SUCCESS;
    }

    public function applyFilter($logs, $filter)
    {
        $filteredLogs = array_filter($logs, function($log) use ($filter) {

            if( !preg_match("/".$filter["statusCode"]."/", $log->getStatusCode())) return false;
            if( !preg_match("/".$filter["pretty"]."/", $log->getPretty()) ) return false;
            if($log->getImpersonator() != $filter["impersonator"]) return false;

            return true;
        });

        return $filteredLogs;
    }
}
