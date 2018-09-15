<?php

namespace FailAid\Context\Contracts;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Mink\Element\ElementInterface;

/**
 * BasicFailInterface interface.
 */
interface ScreenshotInterface
{
    /**
     * @param string $filename The filename for the screenshot.
     * @param Page $page The page object.
     * @param Driver $driver The driver used to run the test.
     *
     * @return string
     */
    public function takeScreenshot($filename, ElementInterface $page, $driver);
}
