<?php

namespace FailAid\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\MinkExtension\Context\MinkAwareContext;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\ServiceContainer\Configuration\ConfigurationLoader;
use Behat\Testwork\Tester\Result\TestResult;
use DirectoryIterator;
use Exception;
use FailAid\Context\Contracts\DebugBarInterface;
use FailAid\Context\Contracts\FailStateInterface;
use FailAid\Context\Contracts\ScreenshotInterface;
use ReflectionObject;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Defines application features from the specific context.
 */
class FailureContext implements MinkAwareContext, FailStateInterface, ScreenshotInterface, DebugBarInterface
{
    const SCREENSHOT_MODE_DEFAULT = 'default';

    const SCREENSHOT_MODE_HTML = 'html';

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
     * @var array
     *
     * @example [
     *     '/images/' => 'http://dev.environment/images/',
     *     '/js/' => 'http://dev.environment/js/'
     * ]
     */
    private $siteFilters = [];

    /**
     * @var string
     */
    private $screenshotMode;

    /**
     * @var string
     */
    private $screenshotDir;

    /**
     * @var boolean
     */
    private $screenshotAutoClean = false;

    /**
     * @var array
     */
    private $debugBarSelectors = [];

    /**
     * @var string
     */
    private static $exceptionHash;

    /**
     * @var array
     */
    private static $states = [];

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        date_default_timezone_set('Europe/London');
        $this->screenshotDir = tempnam(sys_get_temp_dir(), date('Ymd-'));
        $this->screenshotMode = self::SCREENSHOT_MODE_DEFAULT;
    }

    /**
     * @param array $screenshot
     * @param array $siteFilters
     * @param array $debugBarSelectors
     */
    public function setConfig(
        array $screenshot = [],
        array $siteFilters = [],
        array $debugBarSelectors = []
    ) {
        $this->siteFilters = $siteFilters;
        $this->debugBarSelectors = $debugBarSelectors;

        if (isset($screenshot['directory'])) {
            $this->screenshotDir = realpath($screenshot['directory']) . DIRECTORY_SEPARATOR . date('Ymd-');
        }

        if (isset($screenshot['mode'])) {
            $this->screenshotMode = $screenshot['mode'];
        }

        if (isset($screenshot['autoClean'])) {
            $this->screenshotAutoClean = $screenshot['autoClean'];
        }
    }

    /**
     * @Given I take a screenshot
     */
    public function iTakeAScreenshot()
    {
        $session = $this->getMink()->getSession();
        $screenshotPath = $this->takeScreenshot(
            $this->screenshotDir,
            $session->getPage(),
            $session->getDriver()
        );

        echo '[SCREENSHOT] ' . $screenshotPath;
    }

    /**
     * @Given I gather facts for the current state
     */
    public function iGatherFactsForTheCurrentState()
    {
        $session = $this->getMink()->getSession();
        $page = $session->getPage();
        $driver = $session->getDriver();

        echo $this->gatherFacts(
            $session,
            $page,
            $driver,
            $this->debugBarSelectors,
            'NA',
            'NA',
            $this->screenshotDir
        );
    }

    /**
     * @BeforeSuite
     * 
     * Load the config file again as the context params aren't available until the context is initialised.
     *
     * @param mixed $arg1
     */
    public static function autoClean($arg1)
    {
        $configPath = self::getConfigFilePath();
        $config = (new ConfigurationLoader('BEHAT_PARAMS', $configPath))->loadConfiguration();

        if (! isset($config[0]['extensions']['FailAid\\Extension']['screenshot'])) {
            return;
        }

        $screenshotConfig = $config[0]['extensions']['FailAid\\Extension']['screenshot'];

        if (! $screenshotConfig['autoClean']) {
            return;
        }

        $extensions = ['png', 'html'];
        $directory = isset($screenshotConfig['directory']) ? $screenshotConfig['directory'] : sys_get_temp_dir();
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions)) {
                unlink($directory . DIRECTORY_SEPARATOR . $file->getFilename());
            }
        }
    }

    /**
     * Get the behat.yml config path from provided cli path or the default expected location.
     *
     * @return string
     */
    private static function getConfigFilePath()
    {
        $input = new ArgvInput();
        $path = $input->getParameterOption(['-c', '--config'],  'behat.yml');
        $basePath = '';

        // If the path provided isn't an absolute path, then find the folder it is in recursively.
        if (substr($path, 0, 1) !== '/') {
            $basePath = self::getBasePathForFile($path, getcwd()) . DIRECTORY_SEPARATOR;
        }

        $configFile = $basePath . $path;

        if (! file_exists($configFile)) {
            throw new Exception(
                "Autoclean: Config file '$path' not found at base path: '$basePath',
                please pass in the path to the config file through the -c flag and check permissions."
            );
        }

        return $configFile;
    }

    /**
     * @param string $file
     * @param string $path
     *
     * @return string
     */
    private static function getBasePathForFile($file, $path)
    {
        if (! file_exists($path . DIRECTORY_SEPARATOR . $file)) {
            $chunks = explode(DIRECTORY_SEPARATOR, $path);

            if (null === array_pop($chunks) || !$path) {
                throw new Exception($file . ' not found in hierarchy of directory.');
            }

            $path = implode(DIRECTORY_SEPARATOR, $chunks);
            echo $path . PHP_EOL;
            self::getBasePathForFile($file, $path);
        }

        return $path;
    }

    /**
     * @AfterStep
     */
    public function takeScreenShotAfterFailedStep(AfterStepScope $scope)
    {
        if ($scope->getTestResult()->getResultCode() === TestResult::FAILED) {
            try {
                $message = null;
                // To get away from appending exception details multiple times in one lifecycle
                // of a test suite - we need to make sure the exception thrown is different
                // from the previous one before working with it. This happens because each scenario
                // initialises new context files but the exception remains the same, and each context
                // goes through the afterStep.
                $objectHash = spl_object_hash($scope->getTestResult()->getException());
                if (self::$exceptionHash !== $objectHash) {
                    self::$exceptionHash = $objectHash;
                    $exception = $scope->getTestResult()->getException();

                    $message = '';
                    $mink = $this->getMink();
                    if ($mink) {
                        $session = $this->getMink()->getSession();
                        $page = $session->getPage();
                        $driver = $session->getDriver();

                        $message = $this->gatherFacts(
                            $session,
                            $page,
                            $driver,
                            $this->debugBarSelectors,
                            $scope->getFeature()->getFile(),
                            $exception->getFile(),
                            $this->screenshotDir
                        );
                    }

                    $message = $this->addStateDetails($message, $this->getStateDetails(self::$states));

                    $this->setAdditionalExceptionDetailsInException(
                        $exception,
                        $message
                    );
                }

                return $message;
            } catch (DriverException $e) {
                // The driver is not available, dont fail - allow behat to print out the actual error message.
                echo 'Error message: ' . $e->getMessage();
            }
        }
    }

    private function gatherFacts(
        Session $session,
        DocumentElement $page,
        DriverInterface $driver,
        array $debugBarSelectors,
        $featureFile,
        $exceptionFile,
        $screenshotDir
    ) {
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
                $screenshotDir,
                $page,
                $driver
            );
        } catch (Exception $e) {
            // Doesn't work.
            $screenshotPath = 'Unable to produce screenshot: ' . $e->getMessage();
        }

        $debugBarDetails = '';
        try {
            $debugBarDetails = $this->gatherDebugBarDetails(
                $debugBarSelectors,
                $page
            );
        } catch (Exeption $e) {
            $debugBarDetails = 'Unable to capture debug bar details: ' . $e->getMessage();
        }

        $message = $this->getExceptionDetails(
            $currentUrl,
            $statusCode,
            $featureFile,
            $exceptionFile,
            $screenshotPath,
            $debugBarDetails,
            get_class($driver)
        );

        return $message;
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
     * @BeforeScenario
     */
    public function refreshStates()
    {
        self::$states = [];
    }

    /**
     * @param string     $name
     * @param string|int $value
     */
    public static function addState($name, $value)
    {
        self::$states[$name] = $value;
    }

    /**
     * @param string $filename The filename for the screenshot.
     * @param Page   $page     The page object.
     * @param Driver $driver   The driver used to run the test.
     *
     * @return string
     */
    public function takeScreenshot($filename, ElementInterface $page, $driver)
    {
        if (! $page->getOuterHtml()) {
            throw new Exception('Unable to take screenshot, page content not found.');
        }

        $content = null;
        $filename .= microtime(true);
        // If not selenium driver, extract the html and put in file.
        if ($this->screenshotMode === self::SCREENSHOT_MODE_HTML || ! ($driver instanceof Selenium2Driver)) {
            $filename .= '.html';
            $content = $this->applySiteSpecificFilters($page->getOuterHtml());
        } else {
            $filename .= '.png';
            $content = $driver->getScreenshot();
        }

        file_put_contents($filename, $content);

        return 'file://' . $filename;
    }

    /**
     * Override if gathering details is complex.
     *
     * @param array           $debugBarSelectors
     * @param DocumentElement $page
     *
     * @return string
     */
    public function gatherDebugBarDetails(array $debugBarSelectors, DocumentElement $page)
    {
        $details = '';
        foreach ($debugBarSelectors as $name => $selector) {
            $details .= '  [' . strtoupper($name) . '] ';
            if ($detailText = $page->find('css', $selector)) {
                $details .= $detailText->getText();
            } else {
                $details .= 'Element "' . $selector . '" Not Found.';
            }
            $details .= PHP_EOL;
        }
        return $details;
    }

    /**
     * @param array $state
     *
     * @return string
     */
    public function getStateDetails(array $states)
    {
        $stateDetails = '';
        foreach ($states as $stateName => $stateValue) {
            $stateDetails .= '  [' . strtoupper($stateName) . '] ' . $stateValue . PHP_EOL;
        }

        return $stateDetails;
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
        return 'Mismatch: (- expected, + actual)' . PHP_EOL . PHP_EOL .
            '- ' . $expected . PHP_EOL .
            '+ ' . $actual . PHP_EOL . PHP_EOL .
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
        return $this->siteFilters;
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

    /**
     * @param string $currentUrl
     * @param int    $statusCode
     * @param string $featureFile
     * @param string $contextFile
     * @param string $screenshotPath
     * @param string $driver
     * @param mixed  $debugBarDetails
     *
     * @return string
     */
    private function getExceptionDetails(
        $currentUrl,
        $statusCode,
        $featureFile,
        $contextFile,
        $screenshotPath,
        $debugBarDetails,
        $driver
    ) {
        $message = PHP_EOL . PHP_EOL;
        $message .= '[URL] ' . $currentUrl . PHP_EOL;
        $message .= '[STATUS] ' . $statusCode . PHP_EOL;
        $message .= '[FEATURE] ' . $featureFile . PHP_EOL;
        $message .= '[CONTEXT] ' . $contextFile . PHP_EOL;
        $message .= '[SCREENSHOT] ' . $screenshotPath . PHP_EOL;
        $message .= '[DRIVER] ' . $driver . PHP_EOL;
        $message .= '[RERUN] ' . './vendor/bin/behat ' . $featureFile . PHP_EOL;

        if ($debugBarDetails) {
            $message .= PHP_EOL . '[DEBUG BAR INFO]' . PHP_EOL;
            $message .= $debugBarDetails;
        }

        return $message;
    }

    /**
     * @param string $message      The message to append the details onto.
     * @param string $stateDetails The state details.
     *
     * @return string
     */
    private function addStateDetails($message, $stateDetails)
    {
        if ($stateDetails) {
            $message .= PHP_EOL . '[STATE]' . PHP_EOL;
            $message .= $stateDetails;
        }
        $message .= PHP_EOL;

        return $message;
    }

    /**
     * @param Exception $exception The original exception.
     * @param mixed     $message
     */
    private function setAdditionalExceptionDetailsInException(Exception $exception, $message)
    {
        $reflectionObject = new ReflectionObject($exception);
        $reflectionObjectProp = $reflectionObject->getProperty('message');
        $reflectionObjectProp->setAccessible(true);
        $reflectionObjectProp->setValue($exception, $exception->getMessage() . $message);
    }
}
