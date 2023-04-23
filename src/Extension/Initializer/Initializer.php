<?php

namespace FailAid\Extension\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use FailAid\Context\FailureContext;
use FailAid\Service\StaticCallerService;

/**
 * ContextInitialiser class.
 */
class Initializer implements ContextInitializer
{
    public array $screenshot;

    public array $siteFilters;

    public array $debugBarSelectors;

    public array $trackJs;

    public $defaultSession;

    public array $output;

    public function __construct(
        array $screenshot,
        array $siteFilters = [],
        array $debugBarSelectors = [],
        array $trackJs = [],
        $defaultSession = null,
        array $output = []
    ) {
        $this->screenshot = $screenshot;
        $this->siteFilters = $siteFilters;
        $this->debugBarSelectors = $debugBarSelectors;
        $this->trackJs = $trackJs;
        $this->defaultSession = $defaultSession;
        $this->output = $output;
    }

    /**
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof FailureContext) {
            $context->setStaticCaller(new StaticCallerService());
            $context->setConfig(
                $this->screenshot,
                $this->siteFilters,
                $this->debugBarSelectors,
                $this->trackJs,
                $this->defaultSession,
                $this->output
            );
        }
    }
}
