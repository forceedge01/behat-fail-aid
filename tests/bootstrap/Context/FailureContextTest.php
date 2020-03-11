<?php

namespace FailAid\Context;

function file_put_contents($filename, $content)
{
    return true;
}

function realpath($path)
{
    return $path;
}

namespace FailAid\Tests\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\ExceptionResult;
use Behat\Testwork\Tester\Result\TestResult;
use Exception;
use FailAid\Context\FailureContext;
use FailAid\Service\JSDebug;
use FailAid\Service\Output;
use FailAid\Service\Screenshot;
use FailAid\Service\StaticCallerService;
use PHPUnit_Framework_TestCase;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;

class FailedStep implements ExceptionResult, StepResult
{
    public function hasException()
    {
    }

    public function getException()
    {
    }

    public function isPassed()
    {
    }

    public function getResultCode()
    {
    }
}

class FailureContextTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var FailreContextInterface The object to be tested.
     */
    private $testObject;

    /**
     * @var ReflectionClass The reflection class.
     */
    private $reflection;

    /**
     * @var array The test object dependencies.
     */
    private $dependencies = [];

    /**
     * @var [type]
     */
    private $at;

    /**
     * Set up the testing object.
     */
    public function setUp()
    {
        $this->dependencies = [
            'staticCallerMock' => $this->getMockBuilder(StaticCallerService::class)->getMock()
        ];

        $this->reflection = new ReflectionClass(FailureContext::class);
        $this->testObject = $this->reflection->newInstanceArgs();

        $this->testObject->setStaticCaller($this->dependencies['staticCallerMock']);
        $this->testObject->setConfig([], [], []);
        $this->setPrivatePropertyValue('exceptionHash', null);
        $this->at = 0;
    }

    public function testInitStateWithParams()
    {
        $expectedScreenshotDirectory = '/abc/123/';
        $expectedScreenshotMode = 'html';
        $expectedScreenshotAutoclean = true;
        $expectedSize = '1024x997';
        $expectedHostDirectory = '/host/dir/';
        $expectedSiteFilters = [
            '/js/' => 'http://site.dev/js/',
            '/css/' => 'http://site.dev/css/',
        ];
        $expectedDebugBarSelectors = [
            'message' => '.debugBar .message',
            'queries' => '.debugBar .queries',
        ];
        $expectedDefaultSession = 'javascript';
        $expectedTrackJs = ['errors' => true];
        $expectedOutputOptions = [
            'status' => false
        ];
        $expectedScreenshotOptions = [
            'directory' => $expectedScreenshotDirectory,
            'mode' => $expectedScreenshotMode,
            'autoClean' => $expectedScreenshotAutoclean,
            'hostDirectory' => $expectedHostDirectory,
            'size' => $expectedSize,
        ];

        $this->mockStaticCaller(Screenshot::class, 'setOptions', [$expectedScreenshotOptions, $expectedSiteFilters], null)
            ->mockStaticCaller(JSDebug::class, 'setOptions', [$expectedTrackJs], null)
            ->mockStaticCaller(Output::class, 'setOptions', [$expectedOutputOptions], null);

        $this->testObject->setConfig(
            $expectedScreenshotOptions,
            $expectedSiteFilters,
            $expectedDebugBarSelectors,
            $expectedTrackJs,
            $expectedDefaultSession,
            $expectedOutputOptions
        );

        $debugBarSelectors = $this->getPrivatePropertyValue('debugBarSelectors');
        $defaultSession = $this->getPrivatePropertyValue('defaultSession');

        self::assertEquals($expectedDebugBarSelectors, $debugBarSelectors);
        self::assertEquals($expectedDefaultSession, $defaultSession);
    }

    public function testSetMink()
    {
        $mink = $this->getMockBuilder(Mink::class)->getMock();

        $this->testObject->setMink($mink);

        $result = $this->getPrivatePropertyValue('mink');

        self::assertEquals($result, $mink);
    }

    public function testGetMink()
    {
        $this->setPrivatePropertyValue('mink', 'testing');

        $result = $this->testObject->getMink();

        self::assertEquals('testing', $result);
    }

    public function testSetMinkParameters()
    {
        $params = ['hey', 'whats', 'up'];

        $this->testObject->setMinkParameters($params);

        $result = $this->getPrivatePropertyValue('minkParameters');

        self::assertEquals($result, $params);
    }

    public function testGetMinkParameters()
    {
        $params = ['hey', 'whats', 'up'];

        $this->setPrivatePropertyValue('minkParameters', $params);

        $result = $this->testObject->getMinkParameters();

        self::assertEquals($result, $params);
    }

    public function testgatherStateFactsAfterFailedStepPassed()
    {
        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())->method('getResultCode')->willReturn(TestResult::PASSED);
        $scope->getTestResult()->expects($this->never())->method('getException');

        $result = $this->testObject->gatherStateFactsAfterFailedStep($scope);

        self::assertNull($result);
    }

    public function testgatherStateFactsAfterFailedStepPageEmpty()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;

        $exceptionMock = $this->getMockBuilder(Exception::class)->getMock();
        $exceptionMock->expects($this->never())->method('getMessage')->willReturn($exceptionMessage);

        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())->method('getResultCode')->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())->method('getException')->willReturn($exceptionMock);
        $scope->getFeature()->expects($this->atLeastOnce())->method('getFile')->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode) {
            $pageMock = $this->getMockBuilder(DocumentElement::class)->disableOriginalConstructor()->getMock();
            $pageMock->expects($this->once())->method('getOuterHtml')
                ->will($this->throwException(new \WebDriver\Exception\NoSuchElement('No html found.')));

            $driverMock = $this->getMockBuilder(DriverInterface::class)->disableOriginalConstructor()->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
            $sessionMock->expects($this->atLeastOnce())->method('getPage')->willReturn($pageMock);
            $sessionMock->expects($this->atLeastOnce())->method('getDriver')->willReturn($driverMock);
            $sessionMock->expects($this->never())->method('getCurrentUrl');
            $sessionMock->expects($this->never())->method('getStatusCode');

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())->method('getSession')->willReturn($sessionMock);

            return $minkMock;
        };

        $this->mockStaticCaller(Output::class, 'getOption', ['screenshot'], true);
        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->never())->method('getScenario')->willReturn($scenarioMock);
        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);

        $result = $this->testObject
            ->setMink($minkMock())
            ->gatherStateFactsAfterFailedStep($scope);

        self::assertContains('The page is blank, is the driver/browser ready to receive the request?', $result);
    }

    public function testTakeScreenshotWontHappenIfTurnedOff()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;

        $exceptionMock = $this->getMockBuilder(Exception::class)->getMock();
        $exceptionMock->expects($this->never())->method('getMessage')->willReturn($exceptionMessage);

        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())->method('getResultCode')->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())->method('getException')->willReturn($exceptionMock);
        $scope->getFeature()->expects($this->atLeastOnce())->method('getFile')->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode) {
            $driverMock = $this->getMockBuilder(DriverInterface::class)
                ->disableOriginalConstructor()
                ->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
            $sessionMock->expects($this->never())->method('getPage');
            $sessionMock->expects($this->atLeastOnce())->method('getDriver')->willReturn($driverMock);
            $sessionMock->expects($this->never())->method('getCurrentUrl');
            $sessionMock->expects($this->never())->method('getStatusCode');

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())->method('getSession')->willReturn($sessionMock);

            return $minkMock;
        };

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->never())->method('getScenario')->willReturn($scenarioMock);

        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);
        $this->mockStaticCaller(Output::class, 'getOption', ['screenshot'], false);

        $this->testObject
            ->setMink($minkMock())
            ->gatherStateFactsAfterFailedStep($scope);
    }

    public function testgatherStateFactsAfterFailedStepFailedBasic()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $currentUrl = 'http://site.dev/login';
        $exceptionFile = '/abc/23243234234/Service.php';
        $statusCode = 200;
        $html = '<html><body>Hello World</body></html>';
        $expectedLineNumber = 73;
        $expectedScreenshotPath = '/abc/failures/82738492783432.png';

        $exceptionMock = $this->getMockBuilder(Exception::class)->getMock();
        $exceptionMock->expects($this->never())->method('getMessage');

        // Exception object has final method which are not mockable by phpunit.
        $this->setObjectPrivatePropertyValue($exceptionMock, 'file', $exceptionFile);
        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())->method('getResultCode')->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())->method('getException')->willReturn($exceptionMock);
        $scope->getFeature()->expects($this->atLeastOnce())->method('getFile')->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode, $html) {
            $pageMock = $this->getMockBuilder(DocumentElement::class)->disableOriginalConstructor()->getMock();
            $pageMock->expects($this->atLeastOnce())->method('getOuterHtml')->willReturn($html);

            $driverMock = $this->getMockBuilder(DriverInterface::class)->disableOriginalConstructor()->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
            $sessionMock->expects($this->atLeastOnce())->method('getPage')->willReturn($pageMock);
            $sessionMock->expects($this->atLeastOnce())->method('getDriver')->willReturn($driverMock);
            $sessionMock->expects($this->atLeastOnce())->method('getCurrentUrl')->willReturn($currentUrl);
            $sessionMock->expects($this->atLeastOnce())->method('getStatusCode')->willReturn($statusCode);
            $sessionMock->expects($this->never())->method('evaluateScript');

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())->method('getSession')->willReturn($sessionMock);

            return $minkMock;
        };
        $minkMock = $minkMock();

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $scenarioMock->expects($this->never())->method('getLine');
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->never())->method('getScenario');

        $sessionMock = $minkMock->getSession();
        $pageMock = $sessionMock->getPage();
        $driverMock = $sessionMock->getDriver();

        $this->mockStaticCaller(Output::class, 'getOption', ['screenshot'], true)
            ->mockStaticCaller(Output::class, 'getOption', ['url'], true)
            ->mockStaticCaller(Output::class, 'getOption', ['status'], true)
            ->mockStaticCaller(Output::class, 'getOption', ['screenshot'], true)
            ->mockStaticCaller(Screenshot::class, 'canTakeScreenshot', [$sessionMock], true)
            ->mockStaticCaller(Screenshot::class, 'takeScreenshot', [$pageMock, $driverMock], $expectedScreenshotPath)
            ->mockStaticCaller(Output::class, 'getOption', ['debugBarSelectors'], true)
            ->mockStaticCaller(JSDebug::class, 'getJsErrors', [$sessionMock], ['Undefined var: name'])
            ->mockStaticCaller(JSDebug::class, 'getJsWarns', [$sessionMock], [])
            ->mockStaticCaller(JSDebug::class, 'getJsLogs', [$sessionMock], [])
            ->mockStaticCaller(Output::class, 'getExceptionDetails', [
                $currentUrl,
                $statusCode,
                $featureFile,
                $exceptionFile,
                $expectedScreenshotPath,
                '',
                $jsErrors = ['Undefined var: name'],
                $jsLogs = [],
                $jsWarns = [],
                get_class($driverMock),
                $currentScenarioMock
            ], '[URL] http://site.dev/login');

        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);

        $result = $this->testObject
            ->setMink($minkMock)
            ->gatherStateFactsAfterFailedStep($scope);

        self::assertInternalType('string', $result);
        self::assertContains('[URL] http://site.dev/login', $result);
        self::assertNotContains('[DEBUG BAR INFO]', $result);
        self::assertNotContains('[STATE]', $result);
    }

    public function testAfterFailedStepFailedDebugBarDetails()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $exceptionFile = '/abc/23243234234/Service.php';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;
        $html = '<html><body>Hello World</body></html>';
        $expectedLineNumber = 79;

        $exceptionMock = $this->getMockBuilder(Exception::class)->getMock();
        $exceptionMock->expects($this->never())->method('getMessage');

        // Exception object has final method which are not mockable by phpunit.
        $this->setObjectPrivatePropertyValue($exceptionMock, 'file', $exceptionFile);
        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())->method('getResultCode')->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())->method('getException')->willReturn($exceptionMock);
        $scope->getTestResult()->expects($this->atLeastOnce())->method('getException')
            ->willReturn(new Exception($exceptionMessage));
        $scope->getFeature()->expects($this->atLeastOnce())->method('getFile')->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode, $html) {
            $elementMock = $this->getMockBuilder(ElementInterface::class)->getMock();
            $elementMock->expects($this->once())->method('getText')
                ->willReturn('A registered service was not found.');

            $pageMock = $this->getMockBuilder(DocumentElement::class)->disableOriginalConstructor()->getMock();
            $pageMock->expects($this->never())->method('getOuterHtml')->willReturn($html);
            $pageMock->expects($this->at(0))->method('find')->with('css', '#debugBar .message')
                ->willReturn($elementMock);

            $driverMock = $this->getMockBuilder(DriverInterface::class)->disableOriginalConstructor()->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
            $sessionMock->expects($this->atLeastOnce())->method('getPage')->willReturn($pageMock);
            $sessionMock->expects($this->atLeastOnce())->method('getDriver')->willReturn($driverMock);
            $sessionMock->expects($this->never())->method('getCurrentUrl');
            $sessionMock->expects($this->never())->method('getStatusCode');
            $sessionMock->expects($this->any())
                ->method('evaluateScript')
                ->withConsecutive(['return window.jsErrors'], ['return window.jsWarns'], ['return window.jsLogs'])
                ->willReturnOnConsecutiveCalls(
                    ['first error', 'second error'],
                    ['first warn', 'second warn'],
                    ['first log', 'second log']
                );

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())->method('getSession')->willReturn($sessionMock);

            return $minkMock;
        };
        $minkMock = $minkMock();

        $this->setPrivatePropertyValue('debugBarSelectors', [
            'message' => '#debugBar .message',
            'queries' => '#debugBar .queries'
        ]);

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $scenarioMock->expects($this->never())->method('getLine');
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->never())->method('getScenario');

        $sessionMock = $minkMock->getSession();
        $pageMock = $sessionMock->getPage();
        $driverMock = $sessionMock->getDriver();

        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);
        $this->mockStaticCallerAt(4, Output::class, 'getOption', ['debugBarSelectors'], true)
            ->mockStaticCallerAt(8, Output::class, 'getExceptionDetails', [
                null,
                null,
                $featureFile,
                $exceptionFile,
                null,
                '  [MESSAGE] A registered service was not found.
  [QUERIES] Element "#debugBar .queries" Not Found.
',
                null,
                null,
                null,
                get_class($driverMock),
                $currentScenarioMock
            ], '[URL] http://site.dev/login');

        $result = $this->testObject
            ->setMink($minkMock)
            ->gatherStateFactsAfterFailedStep($scope);

        self::assertEquals('[URL] http://site.dev/login' . PHP_EOL, $result);
        $this->setPrivatePropertyValue('debugBarSelectors', []);
    }

    public function testAfterFailedStepFailedState()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $exceptionFile = '/abc/23243234234/Service.php';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;
        $html = '<html><body>Hello World</body></html>';
        $expectedLineNumber = 99;

        $exceptionMock = $this->getMockBuilder(Exception::class)->getMock();
        $exceptionMock->expects($this->never())->method('getMessage');

        $this->setObjectPrivatePropertyValue($exceptionMock, 'file', $exceptionFile);
        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())->method('getResultCode')->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())->method('getException')->willReturn($exceptionMock);
        $scope->getTestResult()->expects($this->atLeastOnce())->method('getException')
            ->willReturn(new Exception($exceptionMessage));
        $scope->getFeature()->expects($this->atLeastOnce())->method('getFile')->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode, $html) {
            $driverMock = $this->getMockBuilder(DriverInterface::class)->disableOriginalConstructor()->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
            $sessionMock->expects($this->never())->method('getPage');
            $sessionMock->expects($this->atLeastOnce())->method('getDriver')->willReturn($driverMock);
            $sessionMock->expects($this->never())->method('getCurrentUrl')->willReturn($currentUrl);
            $sessionMock->expects($this->never())->method('getStatusCode')->willReturn($statusCode);

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())->method('getSession')->willReturn($sessionMock);

            return $minkMock;
        };
        $minkMock = $minkMock();

        FailureContext::addState('test user email', 'its.inevitable@hotmail.com');
        FailureContext::addState('postcode', 'LD34 8GG');

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $scenarioMock->expects($this->never())->method('getLine')->willReturn($expectedLineNumber);
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->never())->method('getScenario')->willReturn($scenarioMock);

        $sessionMock = $minkMock->getSession();
        $driverMock = $sessionMock->getDriver();

        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);
        $this->mockStaticCallerAt(8, Output::class, 'getExceptionDetails', [
            null,
            null,
            $featureFile,
            $exceptionFile,
            null,
            '',
            null,
            null,
            null,
            get_class($driverMock),
            $currentScenarioMock
        ], '[URL] http://site.dev/login');

        $result = $this->testObject
            ->setMink($minkMock)
            ->gatherStateFactsAfterFailedStep($scope);

        self::assertContains('[URL] http://site.dev/login', $result);
        self::assertContains('[STATE]', $result);
        self::assertContains('  [TEST USER EMAIL] its.inevitable@hotmail.com', $result);
        self::assertContains('  [POSTCODE] LD34 8GG', $result);
    }

    public function testGatherDebugBarDetailsAllFound()
    {
        $debugBarSelectors = [
            'message' => '#debug .message',
            'query' => '#debug .query'
        ];

        $elementMock = $this->getMockBuilder(ElementInterface::class)->getMock();
        $elementMock->expects($this->any())
            ->method('getText')
            ->willReturn('Page not found.');

        $elementMock2 = $this->getMockBuilder(ElementInterface::class)->getMock();
        $elementMock2->expects($this->any())
            ->method('getText')
            ->willReturn('Unable to execute query.');

        $page = $this->getMockBuilder(DocumentElement::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->at(0))
            ->method('find')
            ->with('css', '#debug .message')
            ->willReturn($elementMock);
        $page->expects($this->at(1))
            ->method('find')
            ->with('css', '#debug .query')
            ->willReturn($elementMock2);

        $result = $this->callProtectedMethod('gatherDebugBarDetails', [$debugBarSelectors, $page]);

        self::assertEquals('  [MESSAGE] Page not found.
  [QUERY] Unable to execute query.
', $result);
    }

    public function testGatherDebugBarDetailsCallback()
    {
        $debugBarSelectors = [
            'xhrRequests' => [
                'callback' => FailureContextTest::class . '::extract',
            ],
        ];

        $elementMock = $this->getMockBuilder(ElementInterface::class)->getMock();
        $elementMock->expects($this->any())
            ->method('getText')
            ->willReturn('Page not found.');

        $elementMock2 = $this->getMockBuilder(ElementInterface::class)->getMock();
        $elementMock2->expects($this->any())
            ->method('getText')
            ->willReturn('Unable to execute query.');

        $page = $this->getMockBuilder(DocumentElement::class)->disableOriginalConstructor()->getMock();

        $result = $this->callProtectedMethod('gatherDebugBarDetails', [$debugBarSelectors, $page]);

        self::assertEquals('  [XHRREQUESTS] Found xhr requests
', $result);
    }

    /**
     * Test method for debug bar selector callback test.
     *
     * @param PageElement $page
     *
     * @return string
     */
    public static function extract(DocumentElement $page)
    {
        return 'Found xhr requests';
    }

    public function testGatherDebugBarDetailsAllNotFound()
    {
        $debugBarSelectors = [
            'message' => '#debug .message',
            'query' => '#debug .query'
        ];

        $elementMock = $this->getMockBuilder(ElementInterface::class)->getMock();
        $elementMock->expects($this->any())
            ->method('getText')
            ->willReturn('Page not found.');

        $page = $this->getMockBuilder(DocumentElement::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->at(0))
            ->method('find')
            ->with('css', '#debug .message')
            ->willReturn($elementMock);
        $page->expects($this->at(1))
            ->method('find')
            ->with('css', '#debug .query')
            ->willReturn(false);

        $result = $this->callProtectedMethod('gatherDebugBarDetails', [$debugBarSelectors, $page]);

        self::assertEquals('  [MESSAGE] Page not found.
  [QUERY] Element "#debug .query" Not Found.
', $result);
    }

    public function testSetAdditionalExceptionDetailsInException()
    {
        $exception = new Exception('default message.');
        $message = 'More details follow.';

        $this->callProtectedMethod('setAdditionalExceptionDetailsInException', [
            $exception, $message
        ]);

        self::assertEquals('default message.More details follow.', $exception->getMessage());
    }

    private function getScopeObject($resultCode)
    {
        $scopeMock = $this->getMockBuilder(AfterStepScope::class)->getMock();

        $testResult = $this->getMockBuilder(TestResult::class)->getMock();
        $testResult->expects($this->any())
            ->method('getResultCode')
            ->willReturn($resultCode);

        $scopeMock->expects($this->any())
            ->method('getTestResult')
            ->willReturn($testResult);

        return $scopeMock;
    }

    private function getPrivatePropertyValue($property)
    {
        $reflectionProperty = new ReflectionProperty(get_class($this->testObject), $property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($this->testObject);
    }

    private function callProtectedMethod($method, array $params = [])
    {
        $reflector = new ReflectionObject($this->testObject);
        $method = $reflector->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($this->testObject, $params);
    }

    /**
     * @return AfterStepScope
     */
    private function getAfterStepScopeWithMockedParams()
    {
        $env = $this->getMockBuilder(Environment::class)->getMock();
        $feature = $this->getMockBuilder(FeatureNode::class)
            ->disableOriginalConstructor()
            ->getMock();
        $step = $this->getMockBuilder(StepNode::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stepResult = $this->getMockBuilder(FailedStep::class)->getMock();

        return new AfterStepScope(
            $env,
            $feature,
            $step,
            $stepResult
        );
    }

    private function setPrivatePropertyValue($property, $value)
    {
        $reflectionProperty = new ReflectionProperty(get_class($this->testObject), $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, $value);

        return $this;
    }

    private function setObjectPrivatePropertyValue($object, $property, $value)
    {
        $reflectionProperty = new ReflectionProperty(get_class($object), $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);

        return $this;
    }

    private function mockStaticCaller($service, $method, $params, $return)
    {
        $this->mockStaticCallerAt($this->at, $service, $method, $params, $return);
        $this->at += 1;

        return $this;
    }

    private function mockStaticCallerAt($at, $service, $method, $params, $return)
    {
        $this->dependencies['staticCallerMock']->expects($this->at($at))
            ->method('call')
            ->with($service, $method, $params)
            ->willReturn($return);

        return $this;
    }
}
