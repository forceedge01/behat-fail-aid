<?php

namespace FailAid\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\MinkExtension\Context\MinkAwareContext;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\ServiceContainer\Configuration\ConfigurationLoader;
use Behat\Testwork\Tester\Result\TestResult;
use DirectoryIterator;
use Exception;
use FailAid\Context\Contracts\DebugBarInterface;
use FailAid\Context\Contracts\FailStateInterface;
use FailAid\Service\JSDebug;
use FailAid\Service\Output;
use FailAid\Service\Screenshot;
use ReflectionObject;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Defines application features from the specific context.
 */
class FailureContext implements MinkAwareContext, FailStateInterface, DebugBarInterface
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
     * @var array
     */
    private $debugBarSelectors = [];

    /**
     * @var string
     */
    private $defaultSession;

    /**
     * @var string
     */
    private static $exceptionHash;

    /**
     * @var array
     */
    private static $states = [];

    /**
     * @var boolean
     */
    private static $cleaned = false;

    /**
     * @var ScenarioEvent
     */
    private $currentScenario = null;

    /**
     * @var bool
     */
    private static $debugScenario = false;

    /**
     * @var boolean
     */
    private static $autoClean = false;

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
    }

    /**
     * @param mixed  $trackJs
     * @param string $defaultSession
     * @param array  $screenshot
     * @param array  $siteFilters
     * @param array  $debugBarSelectors
     * @param array  $options
     */
    public function setConfig(
        array $screenshot = [],
        array $siteFilters = [],
        array $debugBarSelectors = [],
        array $trackJs = ['errors' => false, 'logs' => false, 'warns' => false, 'trim' => false],
        string $defaultSession = null,
        array $outputOptions = []
    ) {
        $this->debugBarSelectors = $debugBarSelectors;
        $this->defaultSession = $defaultSession;
        Screenshot::setOptions($screenshot, $siteFilters);
        Output::setOptions($outputOptions);
        JSDebug::setOptions($trackJs);
    }

    /**
     * @Given I take a screenshot
     */
    public function iTakeAScreenshot()
    {
        $session = $this->getSession();
        $screenshotPath = Screenshot::takeScreenshot(
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
        $session = $this->getSession();
        $page = $session->getPage();
        $driver = $session->getDriver();

        echo $this->gatherFacts(
            $session,
            $page,
            $driver,
            $this->debugBarSelectors,
            'NA',
            'NA'
        );
    }

    /**
     * @BeforeSuite
     *
     * Load the config file again as the context params aren't available until the context is initialised.
     * @param mixed $arg1
     */
    public static function autoCleanBeforeTestExecution($arg1)
    {
        if (self::$cleaned) {
            return;
        }

        $configPath = self::getConfigFilePath();
        $config = (new ConfigurationLoader('BEHAT_PARAMS', $configPath))->loadConfiguration();

        if (!isset($config[0]['extensions']['FailAid\\Extension']['screenshot'])) {
            return;
        }

        $screenshotConfig = $config[0]['extensions']['FailAid\\Extension']['screenshot'];

        if (!$screenshotConfig['autoClean'] && !self::$autoClean) {
            return;
        }

        $directory = isset($screenshotConfig['directory']) ? $screenshotConfig['directory'] : sys_get_temp_dir();
        self::clearDir($directory);

        self::$cleaned = true;
    }

    /**
     * @BeforeScenario
     * @param mixed $scenarioEvent
     */
    public function currentScenario($scenarioEvent)
    {
        $this->currentScenario = $scenarioEvent;

        return $this;
    }

    /**
     * @BeforeScenario
     */
    public function refreshStates()
    {
        self::$states = [];
    }

    /**
     * @AfterStep
     */
    public function takeScenarioScreenShot(AfterStepScope $scope)
    {
        if (self::$debugScenario) {
            try {
                $this->iTakeAScreenshot();
            } catch (Exception $e) {
                // Ignore...
            }
        }
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
                    try {
                        $this->getSession()->getPage()->getOuterHtml();
                    } catch (\WebDriver\Exception\NoSuchElement $e) {
                        $message = PHP_EOL . PHP_EOL . 'The page is blank, is the driver/browser ready to receive the request?';
                    }

                    $mink = $this->getMink();
                    if ($mink) {
                        $session = $this->getSession();
                        $page = $session->getPage();
                        $driver = $session->getDriver();

                        $message .= $this->gatherFacts(
                            $session,
                            $page,
                            $driver,
                            $this->debugBarSelectors,
                            $scope->getFeature()->getFile(),
                            $exception->getFile()
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

    public static function setDebugScenario($bool)
    {
        self::$debugScenario = $bool;
    }

    public static function clearDir($directory)
    {
        $extensions = ['png', 'html'];
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions)) {
                unlink($directory . DIRECTORY_SEPARATOR . $file->getFilename());
            }
        }
    }

    public static function setAutoClean($bool)
    {
        self::$autoClean = $bool;
    }

    /**
     * Get the behat.yml config path from provided cli path or the default expected location.
     *
     * @return string
     */
    private static function getConfigFilePath()
    {
        $input = new ArgvInput();
        $path = $input->getParameterOption(['-c', '--config'], 'behat.yml');
        $basePath = '';

        // If the path provided isn't an absolute path, then find the folder it is in recursively.
        if (substr($path, 0, 1) !== '/') {
            $basePath = self::getBasePathForFile($path, getcwd()) . DIRECTORY_SEPARATOR;
        }

        $configFile = $basePath . $path;

        if (!file_exists($configFile)) {
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
        if (!file_exists($path . DIRECTORY_SEPARATOR . $file)) {
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
     * @param string $name
     *
     * @return Session
     */
    private function getSession($name = null)
    {
        return $this->getMink()->getSession($name ? $name : $this->defaultSession);
    }

    /**
     * @param string $featureFile
     * @param string $exceptionFile
     * @param string $screenshotDir
     *
     * @return string
     */
    private function gatherFacts(
        Session $session,
        DocumentElement $page,
        DriverInterface $driver,
        array $debugBarSelectors,
        $featureFile,
        $exceptionFile
    ) {
        $message = null;
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
            $screenshotPath = Screenshot::takeScreenshot(
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

        $jsErrors = JSDebug::getJsErrors($session);
        $jsWarns = JSDebug::getJsWarns($session);
        $jsLogs = JSDebug::getJsLogs($session);

        $message = $this->getExceptionDetails(
            $currentUrl,
            $statusCode,
            $featureFile,
            $exceptionFile,
            $screenshotPath,
            $debugBarDetails,
            $jsErrors,
            $jsLogs,
            $jsWarns,
            get_class($driver),
            $this->currentScenario
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

    /**
     * @return Mink
     */
    public function getMink()
    {
        return $this->mink;
    }

    /**
     * @return array
     */
    public function getMinkParameters()
    {
        return $this->minkParameters;
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
     * Override if gathering details is complex.
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
     * @param string $currentUrl
     * @param int    $statusCode
     * @param string $featureFile
     * @param string $contextFile
     * @param string $screenshotPath
     * @param string $driver
     * @param string $jsErrors
     * @param mixed  $debugBarDetails
     * @param mixed  $jsLogs
     * @param mixed  $jsWarns
     * @param mixed  $scenario
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
        $jsErrors,
        $jsLogs,
        $jsWarns,
        $driver,
        $scenario
    ) {
        return Output::getExceptionDetails(
            $currentUrl,
            $statusCode,
            $featureFile,
            $contextFile,
            $screenshotPath,
            $debugBarDetails,
            $jsErrors,
            $jsLogs,
            $jsWarns,
            $driver,
            $scenario
        );
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
