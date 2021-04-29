<?php

namespace FailAid\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\MinkAwareContext;
use Behat\Testwork\ServiceContainer\Configuration\ConfigurationLoader;
use Behat\Testwork\Tester\Result\TestResult;
use DirectoryIterator;
use Exception;
use FailAid\Context\Contracts\DebugBarInterface;
use FailAid\Context\Contracts\FailStateInterface;
use FailAid\Service\JSDebug;
use FailAid\Service\Output;
use FailAid\Service\Screenshot;
use FailAid\Service\StaticCallerService;
use ReflectionObject;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Defines application features from the specific context.
 */
class FailureContext implements MinkAwareContext, FailStateInterface, DebugBarInterface
{
    /**
     * @var string
     */
    public $defaultSession;

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
     * @var integer
     */
    private static $waitOnFailure = 0;

    /**
     * @var boolean
     */
    private static $autoClean = false;

    /**
     * @var boolean
     */
    private static $feedbackOnFailure = false;

    /**
     * @var FeatureContext
     */
    private static $self;

    /**
     * @var array
     */
    private $outputOptions = [];

    /**
     * @var StaticCallerService
     */
    public $staticCaller;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     *
     * @param array $output Overridable output param for each context.
     */
    public function __construct(array $output = [])
    {
        $this->outputOptions = $output;
        self::$self = $this;
    }

    public static function getInstance()
    {
        return self::$self;
    }

    public function setStaticCaller(StaticCallerService $staticCaller)
    {
        $this->staticCaller = $staticCaller;

        return $this;
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
        $defaultSession = null,
        array $outputOptions = []
    ) {
        $this->debugBarSelectors = $debugBarSelectors;
        $this->defaultSession = $defaultSession;
        $this->staticCaller->call(Screenshot::class, 'setOptions', [$screenshot, $siteFilters]);
        $this->staticCaller->call(JSDebug::class, 'setOptions', [$trackJs]);
        $this->staticCaller->call(Output::class, 'setOptions', [$outputOptions]);

        if ($this->outputOptions) {
            foreach ($this->outputOptions as $option => $value) {
                $this->staticCaller->call(Output::class, 'setOption', [$option, $value]);
            }
        }
    }

    /**
     * @Given I take a screenshot
     */
    public function iTakeAScreenshot()
    {
        $session = $this->getSession();
        try {
            $this->staticCaller->call(Screenshot::class, 'canTakeScreenshot', [$session]);
            $screenshotPath = $this->staticCaller->call(Screenshot::class, 'takeScreenshot', [
                $session->getPage(),
                $session->getDriver()
            ]);

            echo '[SCREENSHOT] ' . $screenshotPath;
        } catch (Exception $e) {
            echo 'Unable to take screenshot: ' . $e->getMessage();
        }
    }

    /**
     * @Given I gather facts for the current state
     */
    public function iGatherFactsForTheCurrentState()
    {
        $session = $this->getSession();
        $driver = $session->getDriver();

        echo $this->gatherFacts(
            $session,
            $driver,
            $this->debugBarSelectors,
            'NA',
            'NA',
            $this->currentScenario
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
    public function gatherStateFactsAfterFailedStep(AfterStepScope $scope)
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
                    if (!$this->staticCaller->call(Output::class, 'getOption', ['api'])) {
                        if ($this->staticCaller->call(Output::class, 'getOption', ['screenshot'])) {
                            try {
                                $this->getSession()->getPage()->getOuterHtml();
                            } catch (\WebDriver\Exception\NoSuchElement $e) {
                                $message = PHP_EOL . PHP_EOL . 'The page is blank, is the driver/browser ready to receive the request?';
                            }
                        }

                        $session = $this->getSession();
                        $driver = $session->getDriver();

                        $message .= $this->gatherFacts(
                            $session,
                            $driver,
                            $this->debugBarSelectors,
                            $scope->getFeature()->getFile(),
                            $exception->getFile(),
                            $this->currentScenario
                        );
                    } else {
                        $this->staticCaller->call(Output::class, 'setOption', ['url', false]);
                        $this->staticCaller->call(Output::class, 'setOption', ['status', false]);
                        $this->staticCaller->call(Output::class, 'setOption', ['screenshot', false]);
                        $this->staticCaller->call(Output::class, 'setOption', ['driver', false]);
                        $this->staticCaller->call(Output::class, 'setOption', ['rerun', false]);

                        $message = $this->staticCaller->call(Output::class, 'getExceptionDetails', [
                            null,
                            null,
                            $scope->getFeature()->getFile(),
                            $exception->getFile(),
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            $this->currentScenario
                        ]);
                    }

                    $message = $this->addStateDetails($message, $this->getStateDetails(self::$states));

                    $this->setAdditionalExceptionDetailsInException(
                        $exception,
                        $message
                    );
                }

                if (self::$waitOnFailure) {
                    echo sprintf('Waiting on failure for %d seconds', self::$waitOnFailure) . PHP_EOL;
                }

                if (self::$feedbackOnFailure) {
                    echo PHP_EOL . '-- FAIL --' . PHP_EOL . $exception->getMessage();
                    ob_flush();
                }

                return $message;
            } catch (DriverException $e) {
                // The driver is not available, dont fail - allow behat to print out the actual error message.
                echo 'Error message: ' . $e->getMessage();
            }
        }

        self::$exceptionHash = null;
    }

    /**
     * @AfterScenario
     */
    public function waitOnFailure()
    {
        if (self::$exceptionHash) {
            if (self::$waitOnFailure) {
                sleep(self::$waitOnFailure);
            }
        }
    }

    public static function setDebugScenario($bool)
    {
        self::$debugScenario = $bool;
    }

    public static function setWaitOnFailure($time)
    {
        self::$waitOnFailure = (int) $time;
    }

    public static function setFeedbackOnFailure($bool)
    {
        self::$feedbackOnFailure = $bool;
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
     * @return string
     * @param  mixed  $featureFile
     * @param  mixed  $exceptionFile
     */
    private function gatherFacts(
        Session $session,
        DriverInterface $driver,
        array $debugBarSelectors,
        $featureFile,
        $exceptionFile,
        ScenarioScope $scenario
    ) {
        $message = null;
        $driver = $session->getDriver();

        $currentUrl = null;
        if ($this->staticCaller->call(Output::class, 'getOption', ['url'])) {
            try {
                $currentUrl = $session->getCurrentUrl();
            } catch (Exception $e) {
                $currentUrl = 'Unable to fetch current url, error: ' . $e->getMessage();
            }
        }

        $statusCode = null;
        if ($this->staticCaller->call(Output::class, 'getOption', ['status'])) {
            try {
                $statusCode = $session->getStatusCode();
            } catch (DriverException $e) {
                $statusCode = 'Unable to fetch status code, error: ' . $e->getMessage();
            }
        }

        $screenshotPath = null;
        if ($this->staticCaller->call(Output::class, 'getOption', ['screenshot'])) {
            try {
                $this->staticCaller->call(Screenshot::class, 'canTakeScreenshot', [$session]);
                $screenshotPath = $this->staticCaller->call(Screenshot::class, 'takeScreenshot', [
                    $session->getPage(),
                    $driver
                ]);
            } catch (Exception $e) {
                // Doesn't work.
                $screenshotPath = 'Unable to produce screenshot: ' . $e->getMessage();
            }
        }

        $debugBarDetails = '';
        if ($this->staticCaller->call(Output::class, 'getOption', ['debugBarSelectors'])) {
            if ($debugBarSelectors) {
                try {
                    $debugBarDetails = $this->gatherDebugBarDetails(
                        $debugBarSelectors,
                        $session->getPage()
                    );
                } catch (Exception $e) {
                    $debugBarDetails = 'Unable to capture debug bar details: ' . $e->getMessage();
                }
            }
        }

        $jsErrors = $this->staticCaller->call(JSDebug::class, 'getJsErrors', [$session]);
        $jsWarns = $this->staticCaller->call(JSDebug::class, 'getJsWarns', [$session]);
        $jsLogs = $this->staticCaller->call(JSDebug::class, 'getJsLogs', [$session]);

        $message = $this->staticCaller->call(Output::class, 'getExceptionDetails', [
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
            $scenario
        ]);

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
     * @param string $name
     * @param string $value
     *
     * @return string
     */
    public static function getState($name, $default = null)
    {
        return isset(self::$states[$name]) ? self::$states[$name] : $default;
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
            if (is_array($selector)) {
                if (!isset($selector['callback'])) {
                    throw new Exception('Debug bar selector if array must have callback specified.');
                }
                list($class, $method) = explode('::', $selector['callback'], 2);
                $details .= $class::$method($page);
            } elseif ($detailText = $page->find('css', $selector)) {
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
