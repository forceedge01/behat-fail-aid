<?php

namespace FailAid\Extension\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use FailAid\Context\FailureContext;

/**
 * ContextInitialiser class.
 */
class Initializer implements ContextInitializer
{
    public function __construct(
        array $screenshot,
        array $siteFilters = [],
        array $debugBarSelectors = [],
        array $trackJs = [],
        $defaultSession = null
    ) {
        $this->screenshot = $screenshot;
        $this->siteFilters = $siteFilters;
        $this->debugBarSelectors = $debugBarSelectors;
        $this->trackJs = $trackJs;
        $this->defaultSession = $defaultSession;
    }

    /**
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof FailureContext) {
            $context->setConfig(
                $this->screenshot,
                $this->siteFilters,
                $this->debugBarSelectors,
                $this->trackJs,
                $this->defaultSession
            );
        }
    }
}
