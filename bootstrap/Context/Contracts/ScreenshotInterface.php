<?php

namespace FailAid\Context\Contracts;

use Behat\Mink\Element\ElementInterface;

/**
 * BasicFailInterface interface.
 */
interface ScreenshotInterface
{
    /**
     * @param Page   $page   The page object.
     * @param Driver $driver The driver used to run the test.
     *
     * @return string
     */
    public static function takeScreenshot(ElementInterface $page, $driver);
}
