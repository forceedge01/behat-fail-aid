<?php

namespace FailAid\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\MinkExtension\Context\MinkAwareContext;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Testwork\Tester\Result\TestResult;
use Exception;
use QuickPack\Base\Interfaces\CommonContextInterface;
use ReflectionObject;

/**
 * Defines application features from the specific context.
 */
final class FailureContext implements MinkAwareContext, CommonContextInterface
{
    /**
     * @var Mink
     */
    private $mink;

    /**
     * @var array
     */
    private $minkParameters;

    /**
     * @var ExceptionDetailsProvider
     */
    private $exceptionDetailsProvider;

    /**
     * @var string
     */
    private static $exceptionHash;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct($screenshotDirectory = null)
    {
        date_default_timezone_set('Europe/London');

        if ($screenshotDirectory) {
            $this->screenshotFileName = $screenshotDirectory . DIRECTORY_SEPARATOR . date('Ymd-');
        } else {
            $this->screenshotDir = tempnam(sys_get_temp_dir(), date('Ymd-'));
        }
    }

    public function setMink(Mink $mink)
    {
        $this->mink = $mink;

        return $this;
    }

    public function setMinkParameters(array $parameters)
    {
        $this->minkParameters = $parameters;

        return $this;
    }

    public function getMink()
    {
        return $this->mink;
    }

    public function getMinkParameters()
    {
        return $this->minkParameters;
    }

    /**
     * @AfterStep
     */
    public function takeScreenShotAfterFailedStep(AfterStepScope $scope)
    {
        if ($scope->getTestResult()->getResultCode() === TestResult::FAILED) {
            try {
                // To get away from appending exception details multiple times in one lifecycle
                // of a test suite - we need to make sure the exception thrown is different
                // from the previous one before working with it. This happens because each scenario
                // initialises new context files but the exception remains the same, and each context
                // goes through the afterStep.
                $objectHash = spl_object_hash($scope->getTestResult()->getException());
                if (self::$exceptionHash !== $objectHash) {
                    self::$exceptionHash = $objectHash;
                    $exception = $scope->getTestResult()->getException();
                    $message = null;

                    $session = $this->getMink()->getSession();
                    $page = $session->getPage();
                    $driver = $session->getDriver();
                    
                    $currentUrl = null;
                    try {
                        $currentUrl = $session->getCurrentUrl();
                    } catch (Exception $e) {
                        $currentUrl = 'Unable to fetch current url, error: ' . $e->getMessage();
                    }

                    $statusCode = null;
                    try {
                        $statusCode = $session->getStatusCode();
                    } catch (DriverException $e) {
                        $statusCode = 'Unable to fetch status code, error: ' . $e->getMessage();
                    }

                    $screenshotPath = null;
                    try {
                        $screenshotPath = $this->takeScreenshot(
                            $this->screenshotDir,
                            $page,
                            $driver
                        );
                    } catch (Exception $e) {
                        // Doesn't work.
                        $screenshotPath = 'Unable to produce screenshot: ' . $e->getMessage();
                    }

                    $message = $this->getExceptionDetails(
                        $currentUrl,
                        $statusCode,
                        $scope->getFeature()->getFile(),
                        $exception->getFile(),
                        $screenshotPath
                    );

                    $this->setAdditionalExceptionDetailsInException(
                        $exception,
                        $message
                    );
                }
            } catch (DriverException $e) {
                // The driver is not available, dont fail - allow behat to print out the actual error message.
                echo 'Error message: ' . $e->getMessage();
            }
        }
    }

    /**
     * @param string $currentUrl The current url.
     * @param int $statusCode
     * @param string $featureFile The feature file.
     * @param string $contextFile The context file.
     * @param string $screenshotPath The screenshot path.
     *
     * @return string
     */
    private function getExceptionDetails($currentUrl, $statusCode, $featureFile, $contextFile, $screenshotPath)
    {
        $message = PHP_EOL . PHP_EOL;
        $message .= '[URL] ' . $currentUrl . PHP_EOL;
        $message .= '[STATUS] ' . $statusCode . PHP_EOL;
        $message .= '[FEATURE] ' . $featureFile . PHP_EOL;
        $message .= '[CONTEXT] ' . $contextFile . PHP_EOL;
        $message .= '[SCREENSHOT] ' . $screenshotPath . PHP_EOL;
        $message .= '[RERUN] ' . './vendor/bin/behat ' . $featureFile . PHP_EOL;
        $message .= PHP_EOL;

        return $message;
    }

    /**
     * @param Exception $exception The original exception.
     * @param mixed $message
     */
    private function setAdditionalExceptionDetailsInException(Exception $exception, $message)
    {
        $reflectionObject = new ReflectionObject($exception);
        $reflectionObjectProp = $reflectionObject->getProperty('message');
        $reflectionObjectProp->setAccessible(true);
        $reflectionObjectProp->setValue($exception, $exception->getMessage() . $message);
    }

    /**
     * @param string $filename The filename for the screenshot.
     * @param Page $page The page object.
     * @param Driver $driver The driver used to run the test.
     *
     * @return string
     */
    public function takeScreenshot($filename, $page, $driver)
    {
        if (! $page->getHtml()) {
            throw new Exception('Unable to take screenshot, page content not found.');
        }

        $content = null;
        $filename .= microtime(true);
        // If not selenium driver, extract the html and put in file.
        if (! ($driver instanceof Selenium2Driver)) {
            $filename .= '.html';
            $content = $this->applySiteSpecificFilters($page->getHtml());
        } else {
            $filename .= '.png';
            $content = $driver->getScreenshot();
        }

        file_put_contents($filename, $content);

        return 'file://' . $filename;
    }

    /**
     * @param string $expected
     * @param string $actual
     * @param string $message
     *
     * @return string
     */
    public static function provideDiff($expected, $actual, $message = null)
    {
        return 'Mismatch: (expected +, actual -)' . PHP_EOL . PHP_EOL .
            '+ ' . $expected . PHP_EOL .
            '- ' . $actual . PHP_EOL . PHP_EOL .
            'Info: ' . $message;
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
    protected function getSiteSpecificFilters()
    {
        return [];
    }

    /**
     * Override this method if the complexity of applying the filters is beyond what getSiteSpecificFilters() can
     * provide.
     *
     * @param string $content
     *
     * @return string
     */
    protected function applySiteSpecificFilters($content)
    {
        $filters = $this->getSiteSpecificFilters();

        $from = array_keys($filters);
        $to = array_values($filters);

        return str_replace($from, $to, $content);
    }
}
