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
        $screenshotDirectory = null,
        $screenshotMode = self::SCREENSHOT_MODE_DEFAULT,
        array $siteFilters = [],
        array $debugBarSelectors = []
    ) {
        $this->screenshotDirectory = $screenshotDirectory;
        $this->screenshotMode = $screenshotMode;
        $this->siteFilters = $siteFilters;
        $this->debugBarSelectors = $debugBarSelectors;
    }

    /**
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof FailureContext) {
            $context->setConfig(
                $this->screenshotDirectory,
                $this->screenshotMode,
                $this->siteFilters,
                $this->debugBarSelectors
            );
        }
    }
}
