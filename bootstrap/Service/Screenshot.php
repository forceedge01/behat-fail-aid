<?php

namespace FailAid\Service;

use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Exception\DriverException;
use Exception;
use FailAid\Context\Contracts\ScreenshotInterface;

/**
 * Screenshot class.
 */
class Screenshot implements ScreenshotInterface
{
    const SCREENSHOT_MODE_DEFAULT = 'default';

    const SCREENSHOT_MODE_PNG = 'png';

    const SCREENSHOT_MODE_HTML = 'html';

    /**
     * @var string
     */
    public static $screenshotMode;

    /**
     * @var string
     */
    public static $screenshotDir;

    /**
     * @var boolean
     */
    public static $screenshotAutoClean = false;

    /**
     * @var array
     */
    public static $screenshotSize = [];

    /**
     * @var string
     */
    public static $screenshotHostDirectory;

    /**
     * @var array
     */
    public static $siteFilters;

    public static function setOptions(array $options, array $siteFilters)
    {
        self::$screenshotDir = tempnam(sys_get_temp_dir(), date('Ymd-'));
        self::$screenshotMode = self::SCREENSHOT_MODE_DEFAULT;

        if (isset($options['directory'])) {
            self::$screenshotDir = realpath($options['directory']) . DIRECTORY_SEPARATOR . date('Ymd-');
        }

        if (isset($options['mode'])) {
            self::$screenshotMode = $options['mode'];
        }

        if (isset($options['autoClean'])) {
            self::$screenshotAutoClean = $options['autoClean'];
        }

        if (isset($options['size'])) {
            self::$screenshotSize = explode('x', $options['size'], 2);
        }

        if (isset($options['hostDirectory'])) {
            self::$screenshotHostDirectory = rtrim($options['hostDirectory'], DIRECTORY_SEPARATOR) .
                DIRECTORY_SEPARATOR .
                date('Ymd-');
        }

        self::$siteFilters = $siteFilters;
    }

    /**
     * Screenshot based on mode defined. Modes are:
     * - default: png if possible, html otherwise. Suitable for running packs with both types drivers enabled.
     * - html: all in html.
     * - png: all in png, Throws exception if unable to.
     *
     * @param Page   $page   The page object.
     * @param Driver $driver The driver used to run the test.
     *
     * @return string
     */
    public static function takeScreenshot(ElementInterface $page, $driver)
    {
        if (!$page->getOuterHtml()) {
            throw new Exception('Unable to take screenshot, page content not found.');
        }

        $content = null;
        $filename = microtime(true);

        switch (self::$screenshotMode) {
            case self::SCREENSHOT_MODE_DEFAULT:
                try {
                    $content = $driver->getScreenshot();
                    $filename .= '.png';
                    self::handleResize(self::$screenshotSize, $driver);
                } catch (DriverException $e) {
                    $content = static::applySiteSpecificFilters($page->getOuterHtml());
                    $filename .= '.html';
                }
                break;
            case self::SCREENSHOT_MODE_HTML:
                $content = static::applySiteSpecificFilters($page->getOuterHtml());
                $filename .= '.html';
                break;
            case self::SCREENSHOT_MODE_PNG:
                try {
                    self::handleResize(self::$screenshotSize, $driver);
                    $content = $driver->getScreenshot();
                    $filename .= '.png';
                } catch (DriverException $e) {
                    throw new Exception('unable to produce screenshot: ' . $e->getMessage());
                }
                break;
        }

        file_put_contents(self::$screenshotDir . $filename, $content);

        if (self::$screenshotHostDirectory) {
            return 'file://' . rtrim(self::$screenshotHostDirectory, '/') . DIRECTORY_SEPARATOR . $filename;
        }

        return 'file://' . self::$screenshotDir . $filename;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    public static function applySiteSpecificFilters($content)
    {
        $filters = self::getSiteSpecificFilters();

        $from = array_keys($filters);
        $to = array_values($filters);

        return str_replace($from, $to, $content);
    }

    private static function handleResize($size, $driver)
    {
        if (!$size) {
            return;
        }

        $driver->resizeWindow((int) $size[0], (int) $size[1], 'current');
    }

    /**
     * Override this method if you're using Goutte to produce html screenshots and want to fix broken relative links for
     * assets.
     *
     * @example [
     *     '/images/' => 'http://dev.environment/images/',
     *     '/js/' => 'http://dev.environment/js/'
     * ]
     *
     * @return array
     */
    protected static function getSiteSpecificFilters()
    {
        return self::$siteFilters;
    }
}
