<?php

namespace FailAid\Context;

use Behat\Testwork\Cli\Controller;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FeedbackOnFailure class.
 */
class FeedbackOnFailure
{
    /**
     * Configures command to be executable by the controller.
     *
     * @param SymfonyCommand $command
     */
    public function configure(SymfonyCommand $command)
    {
        $command->addOption('--feedback-on-failure', null, InputOption::VALUE_NONE, 'Display failure information after failure, used when running tets in progress format.');
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
        if ($input->getOption('feedback-on-failure')) {
            FailureContext::setFeedbackOnFailure(true);
        }
    }
}
