<?php

namespace FailAid\Context;

use Behat\Testwork\Cli\Controller;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ScenarioDebugCli class.
 */
class ScenarioDebugCli implements Controller
{
    /**
     * Configures command to be executable by the controller.
     *
     * @param SymfonyCommand $command
     */
    public function configure(SymfonyCommand $command)
    {
        $command->addOption('--scenario-debug', null, InputOption::VALUE_NONE, 'Take screenshots after each step to aid debugging.');
    }

    /**
     * Executes controller.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null|integer
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('scenario-debug')) {
            FailureContext::setDebugScenario(true);
        }
    }
}
