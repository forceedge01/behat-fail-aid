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
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\Element;
use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\ExceptionResult;
use Behat\Testwork\Tester\Result\TestResult;
use Exception;
use FailAid\Context\FailureContext;
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

class FailreContextTest extends PHPUnit_Framework_TestCase
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
     * Set up the testing object.
     */
    public function setUp()
    {
        $this->dependencies = [];

        $this->reflection = new ReflectionClass(FailureContext::class);
        $this->testObject = $this->reflection->newInstanceArgs($this->dependencies);

        $this->testObject->setConfig([], [], []);
    }

    public function testInitStateDefault()
    {
        $this->testObject = new FailureContext();

        $expectedSiteFilters = [];
        $expectedDebugBarSelectors = [];

        $siteFilters = $this->getPrivatePropertyValue('siteFilters');
        $screenshotMode = $this->getPrivatePropertyValue('screenshotMode');
        $debugBarSelectors = $this->getPrivatePropertyValue('debugBarSelectors');
        $screenshotDirectory = $this->getPrivatePropertyValue('screenshotDir');

        self::assertTrue(strlen($screenshotDirectory) > 0);
        self::assertEquals(FailureContext::SCREENSHOT_MODE_DEFAULT, $screenshotMode);
        self::assertEquals($expectedSiteFilters, $siteFilters);
        self::assertEquals($expectedDebugBarSelectors, $debugBarSelectors);
    }

    public function testInitStateWithParams()
    {
        $expectedScreenshotDirectory = '/abc/123/';
        $expectedScreenshotMode = 'html';
        $expectedScreenshotAutoclean = true;
        $expectedSiteFilters = [
            '/js/' => 'http://site.dev/js/',
            '/css/' => 'http://site.dev/css/',
        ];
        $expectedDebugBarSelectors = [
            'message' => '.debugBar .message',
            'queries' => '.debugBar .queries',
        ];

        $this->testObject = new FailureContext();
        $this->testObject->setConfig(
            [
                'directory' => $expectedScreenshotDirectory,
                'mode' => $expectedScreenshotMode,
                'autoClean' => $expectedScreenshotAutoclean,
            ],
            $expectedSiteFilters,
            $expectedDebugBarSelectors
        );

        $siteFilters = $this->getPrivatePropertyValue('siteFilters');
        $screenshotMode = $this->getPrivatePropertyValue('screenshotMode');
        $debugBarSelectors = $this->getPrivatePropertyValue('debugBarSelectors');
        $screenshotDirectory = $this->getPrivatePropertyValue('screenshotDir');
        $screenshotAutoClean = $this->getPrivatePropertyValue('screenshotAutoClean');

        self::assertStringStartsWith($expectedScreenshotDirectory, $screenshotDirectory);
        self::assertNotEquals($expectedScreenshotDirectory, $screenshotDirectory);
        self::assertEquals($expectedScreenshotMode, $screenshotMode);
        self::assertEquals($expectedSiteFilters, $siteFilters);
        self::assertEquals($expectedScreenshotAutoclean, $screenshotAutoClean);
        self::assertEquals($expectedDebugBarSelectors, $debugBarSelectors);
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

    public function testTakeScreenshotAfterFailedStepPassed()
    {
        $scope = $this->getAfterStepScopeWithMockedParams();

        $scope->getTestResult()->expects($this->once())
            ->method('getResultCode')
            ->willReturn(TestResult::PASSED);

        $scope->getTestResult()->expects($this->never())
            ->method('getException');

        $result = $this->testObject->takeScreenShotAfterFailedStep($scope);

        self::assertNull($result);
    }

    public function testTakeScreenshotAfterFailedStepPageEmpty()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;

        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())
            ->method('getResultCode')
            ->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())
            ->method('getException')
            ->willReturn(new Exception($exceptionMessage));
        $scope->getFeature()->expects($this->atLeastOnce())
            ->method('getFile')
            ->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode) {
            $pageMock = $this->getMockBuilder(DocumentElement::class)
                ->disableOriginalConstructor()
                ->getMock();
            $pageMock->expects($this->atLeastOnce())
                ->method('getOuterHtml')
                ->will($this->throwException(new \WebDriver\Exception\NoSuchElement('No html found.')));
            $driverMock = $this->getMockBuilder(DriverInterface::class)
                ->disableOriginalConstructor()
                ->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->getMock();
            $sessionMock->expects($this->atLeastOnce())
                ->method('getPage')
                ->willReturn($pageMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getDriver')
                ->willReturn($driverMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getCurrentUrl')
                ->willReturn($currentUrl);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getStatusCode')
                ->willReturn($statusCode);

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())
                ->method('getSession')
                ->willReturn($sessionMock);

            return $minkMock;
        };

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->any())
            ->method('getScenario')
            ->willReturn($scenarioMock);
        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);
        $result = $this->testObject
            ->setMink($minkMock())
            ->takeScreenShotAfterFailedStep($scope);

        self::assertContains('The page is blank, is the driver/browser ready to receive the request?', $result);
    }

    public function testTakeScreenshotAfterFailedStepFailedBasic()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;
        $html = '<html><body>Hello World</body></html>';
        $expectedLineNumber = 73;

        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())
            ->method('getResultCode')
            ->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())
            ->method('getException')
            ->willReturn(new Exception($exceptionMessage));
        $scope->getFeature()->expects($this->atLeastOnce())
            ->method('getFile')
            ->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode, $html) {
            $pageMock = $this->getMockBuilder(DocumentElement::class)
                ->disableOriginalConstructor()
                ->getMock();
            $pageMock->expects($this->atLeastOnce())
                ->method('getOuterHtml')
                ->willReturn($html);
            $driverMock = $this->getMockBuilder(DriverInterface::class)
                ->disableOriginalConstructor()
                ->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->getMock();
            $sessionMock->expects($this->atLeastOnce())
                ->method('getPage')
                ->willReturn($pageMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getDriver')
                ->willReturn($driverMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getCurrentUrl')
                ->willReturn($currentUrl);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getStatusCode')
                ->willReturn($statusCode);
            $sessionMock->expects($this->any())
                ->method('evaluateScript')
                ->will($this->throwException(new DriverException('Unsupported action')));

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())
                ->method('getSession')
                ->willReturn($sessionMock);

            return $minkMock;
        };

        $this->setPrivatePropertyValue('trackJs', [
            'errors' => true,
            'logs' => true,
            'warns' => true,
            'trim' => false
        ]);

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $scenarioMock->expects($this->any())
            ->method('getLine')
            ->willReturn($expectedLineNumber);
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->any())
            ->method('getScenario')
            ->willReturn($scenarioMock);
        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);
        $result = $this->testObject
            ->setMink($minkMock())
            ->takeScreenShotAfterFailedStep($scope);

        self::assertContains('[URL] http://site.dev/login', $result);
        self::assertContains('[STATUS] 200', $result);
        self::assertContains('[FEATURE] my/example/scenarios.feature', $result);
        self::assertRegExp('/\[CONTEXT\] \/.+\.php/', $result);
        self::assertRegExp('/\[SCREENSHOT\] file:\/\/\/.+/', $result);
        self::assertContains('[DRIVER] Mock_DriverInterface_', $result);
        self::assertContains('[RERUN] ./vendor/bin/behat my/example/scenarios.feature:' . $expectedLineNumber, $result);
        self::assertContains('[JSERRORS] Unable to fetch js errors: Unsupported action', $result);
        self::assertNotContains('[DEBUG BAR INFO]', $result);
        self::assertNotContains('[STATE]', $result);
    }

    public function testTakeScreenshotAfterFailedStepFailedDebugBarDetails()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;
        $html = '<html><body>Hello World</body></html>';
        $expectedLineNumber = 79;

        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())
            ->method('getResultCode')
            ->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())
            ->method('getException')
            ->willReturn(new Exception($exceptionMessage));
        $scope->getFeature()->expects($this->atLeastOnce())
            ->method('getFile')
            ->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode, $html) {
            $elementMock = $this->getMockBuilder(ElementInterface::class)
                ->getMock();
            $elementMock->expects($this->once())
                ->method('getText')
                ->willReturn('A registered service was not found.');

            $pageMock = $this->getMockBuilder(DocumentElement::class)
                ->disableOriginalConstructor()
                ->getMock();
            $pageMock->expects($this->atLeastOnce())
                ->method('getOuterHtml')
                ->willReturn($html);
            $pageMock->expects($this->at(2))
                ->method('find')
                ->with('css', '#debugBar .message')
                ->willReturn($elementMock);
            $driverMock = $this->getMockBuilder(DriverInterface::class)
                ->disableOriginalConstructor()
                ->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->getMock();
            $sessionMock->expects($this->atLeastOnce())
                ->method('getPage')
                ->willReturn($pageMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getDriver')
                ->willReturn($driverMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getCurrentUrl')
                ->willReturn($currentUrl);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getStatusCode')
                ->willReturn($statusCode);

            $sessionMock->expects($this->any())
                ->method('evaluateScript')
                ->withConsecutive(['return window.jsErrors'], ['return window.jsWarns'], ['return window.jsLogs'])
                ->willReturnOnConsecutiveCalls(
                    ['first error', 'second error'],
                    ['first warn', 'second warn'],
                    ['first log', 'second log']
                );

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())
                ->method('getSession')
                ->willReturn($sessionMock);

            return $minkMock;
        };

        $this->setPrivatePropertyValue('debugBarSelectors', [
            'message' => '#debugBar .message',
            'queries' => '#debugBar .queries'
        ]);

        $this->setPrivatePropertyValue('trackJs', [
            'errors' => true,
            'logs' => true,
            'warns' => true,
            'trim' => false
        ]);

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $scenarioMock->expects($this->any())
            ->method('getLine')
            ->willReturn($expectedLineNumber);
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->any())
            ->method('getScenario')
            ->willReturn($scenarioMock);
        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);
        $result = $this->testObject
            ->setMink($minkMock())
            ->takeScreenShotAfterFailedStep($scope);

        self::assertContains('[URL] http://site.dev/login', $result);
        self::assertContains('[STATUS] 200', $result);
        self::assertContains('[FEATURE] my/example/scenarios.feature', $result);
        self::assertRegExp('/\[CONTEXT\] \/.+\.php/', $result);
        self::assertRegExp('/\[SCREENSHOT\] file:\/\/\/.+/', $result);
        self::assertContains('[DRIVER] Mock_DriverInterface_', $result);
        self::assertContains('[RERUN] ./vendor/bin/behat my/example/scenarios.feature:' . $expectedLineNumber, $result);
        self::assertContains('[DEBUG BAR INFO]', $result);
        self::assertContains('[JSERRORS] first error', $result);
        self::assertContains('second error', $result);
        self::assertContains('[JSWARNS] first warn', $result);
        self::assertContains('second warn', $result);
        self::assertContains('[JSLOGS] first log', $result);
        self::assertContains('second log', $result);
        self::assertContains('  [MESSAGE] A registered service was not found.', $result);
        self::assertContains('  [QUERIES] Element "#debugBar .queries" Not Found.', $result);
        self::assertNotContains('[STATE]', $result);

        $this->setPrivatePropertyValue('debugBarSelectors', []);
    }

    public function testTakeScreenshotAfterFailedStepFailedState()
    {
        $featureFile = 'my/example/scenarios.feature';
        $exceptionMessage = 'something went wrong';
        $currentUrl = 'http://site.dev/login';
        $statusCode = 200;
        $html = '<html><body>Hello World</body></html>';
        $expectedLineNumber = 99;

        $scope = $this->getAfterStepScopeWithMockedParams();
        $scope->getTestResult()->expects($this->once())
            ->method('getResultCode')
            ->willReturn(TestResult::FAILED);
        $scope->getTestResult()->expects($this->atLeastOnce())
            ->method('getException')
            ->willReturn(new Exception($exceptionMessage));
        $scope->getFeature()->expects($this->atLeastOnce())
            ->method('getFile')
            ->willReturn($featureFile);

        $minkMock = function () use ($currentUrl, $statusCode, $html) {
            $pageMock = $this->getMockBuilder(DocumentElement::class)
                ->disableOriginalConstructor()
                ->getMock();
            $pageMock->expects($this->atLeastOnce())
                ->method('getOuterHtml')
                ->willReturn($html);
            $driverMock = $this->getMockBuilder(DriverInterface::class)
                ->disableOriginalConstructor()
                ->getMock();

            $sessionMock = $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->getMock();
            $sessionMock->expects($this->atLeastOnce())
                ->method('getPage')
                ->willReturn($pageMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getDriver')
                ->willReturn($driverMock);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getCurrentUrl')
                ->willReturn($currentUrl);
            $sessionMock->expects($this->atLeastOnce())
                ->method('getStatusCode')
                ->willReturn($statusCode);

            $minkMock = $this->getMockBuilder(Mink::class)->getMock();
            $minkMock->expects($this->atLeastOnce())
                ->method('getSession')
                ->willReturn($sessionMock);

            return $minkMock;
        };

        FailureContext::addState('test user email', 'its.inevitable@hotmail.com');
        FailureContext::addState('postcode', 'LD34 8GG');

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $scenarioMock->expects($this->any())
            ->method('getLine')
            ->willReturn($expectedLineNumber);
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->any())
            ->method('getScenario')
            ->willReturn($scenarioMock);
        $this->setPrivatePropertyValue('currentScenario', $currentScenarioMock);
        $result = $this->testObject
            ->setMink($minkMock())
            ->takeScreenShotAfterFailedStep($scope);

        self::assertContains('[URL] http://site.dev/login', $result);
        self::assertContains('[STATUS] 200', $result);
        self::assertContains('[FEATURE] my/example/scenarios.feature', $result);
        self::assertRegExp('/\[CONTEXT\] \/.+\.php/', $result);
        self::assertRegExp('/\[SCREENSHOT\] file:\/\/\/.+/', $result);
        self::assertContains('[DRIVER] Mock_DriverInterface_', $result);
        self::assertContains('[RERUN] ./vendor/bin/behat my/example/scenarios.feature:' . $expectedLineNumber, $result);
        self::assertNotContains('[DEBUG BAR INFO]', $result);
        self::assertContains('[STATE]', $result);
        self::assertContains('  [TEST USER EMAIL] its.inevitable@hotmail.com', $result);
        self::assertContains('  [POSTCODE] LD34 8GG', $result);
    }

    /**
     * @expectedException Exception
     */
    public function testTakeScreenshotNoHtml()
    {
        $filename = '/file/name.png';
        $page = $this->getMockBuilder(Element::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->once())
            ->method('getOuterHtml')
            ->will($this->throwException(new Exception()));
        $driver = $this->getMockBuilder(DriverInterface::class)->getMock();

        $this->testObject->takeScreenshot($filename, $page, $driver);
    }

    public function testTakeScreenshotWithHtmlAndDefaultScreenshotModeWithSeleniumDriver()
    {
        $filename = '/file/name-';
        $page = $this->getMockBuilder(Element::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->any())
            ->method('getOuterHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(Selenium2Driver::class)->getMock();

        $this->setPrivatePropertyValue('screenshotMode', 'default');
        $result = $this->testObject->takeScreenshot($filename, $page, $driver);

        self::assertEquals('png', pathinfo($result, PATHINFO_EXTENSION));
    }

    public function testTakeScreenshotWithHtmlAndDefaultScreenshotModeWithNonSeleniumDriver()
    {
        $filename = '/file/name-';
        $page = $this->getMockBuilder(Element::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->any())
            ->method('getOuterHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(DriverInterface::class)->getMock();
        $driver
            ->expects($this->once())
            ->method('getScreenshot')
            ->will($this->throwException(new DriverException('Not supported.')));

        $this->setPrivatePropertyValue('screenshotMode', 'default');
        $result = $this->testObject->takeScreenshot($filename, $page, $driver);

        self::assertEquals('html', pathinfo($result, PATHINFO_EXTENSION));
    }

    public function testTakeScreenshotWithHtmlAndHtmlScreenshotMode()
    {
        $filename = '/file/name-';
        $page = $this->getMockBuilder(Element::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->any())
            ->method('getOuterHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(DriverInterface::class)->getMock();

        $this->setPrivatePropertyValue('screenshotMode', FailureContext::SCREENSHOT_MODE_DEFAULT);

        $this->setPrivatePropertyValue('screenshotMode', 'html');
        $result = $this->testObject->takeScreenshot($filename, $page, $driver);

        self::assertEquals('html', pathinfo($result, PATHINFO_EXTENSION));
    }

    public function testTakeScreenshotWithHtmlAndPNGScreenshotMode()
    {
        $filename = '/file/name-';
        $page = $this->getMockBuilder(Element::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->any())
            ->method('getOuterHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(Selenium2Driver::class)->getMock();

        $this->setPrivatePropertyValue('screenshotMode', 'png');
        $result = $this->testObject->takeScreenshot($filename, $page, $driver);

        self::assertEquals('png', pathinfo($result, PATHINFO_EXTENSION));
    }

    public function testProvideDiff()
    {
        $expected = 'abc';
        $actual = 'xyz';
        $message = 'clearly not equal.';

        $result = FailureContext::provideDiff($expected, $actual, $message);

        $expectedOutput = 'Mismatch: (- expected, + actual)

- abc
+ xyz

Info: clearly not equal.';

        self::assertEquals($expectedOutput, $result);
    }

    public function testApplySiteSpecificFilters()
    {
        $content = '<html>
        <body>
        <img src="/assets/images/abc.png" />
        <link rel="text/stylesheet" src="/assets/css/style.css" />
        <script src="/assets/script/script.js" />
        </body>
        </html>';

        $expectedContent = '<html>
        <body>
        <img src="http://site.dev/assets/images/abc.png" />
        <link rel="text/stylesheet" src="http://site.dev/assets/css/style.css" />
        <script src="http://site.dev/assets/javascripts/script.js" />
        </body>
        </html>';

        $this->setPrivatePropertyValue('siteFilters', [
            '/assets/images/' => 'http://site.dev/assets/images/',
            '/assets/css/' => 'http://site.dev/assets/css/',
            '/assets/script/' => 'http://site.dev/assets/javascripts/'
        ]);

        $result = $this->callProtectedMethod('applySiteSpecificFilters', [$content]);

        self::assertEquals($expectedContent, $result);
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

    public function testGetExceptionDetails()
    {
        $currentUrl = 'http://site.dev/';
        $statusCode = '500';
        $featureFile = 'features/login.feature';
        $contextFile = '/Assertions/WebAssert.php';
        $screenshotPath = '/private/var/tmp/2873438.png';
        $expectedLineNumber = 4;
        $debugBarDetails = '  [MESSAGE] Page not found.
  [QUERY] Element "#debug .query" Not Found.
';
        $stateDetails = '  [USER EMAIL] its.inevitable@hotmail.com';

        $jsErrors = [
            '[Console error]: Undefined var "abc"',
            '[Console error]: Undefined var "xyz"'
        ];
        $jsWarns = [
            '[Console warn]: Could not load data in.',
        ];
        $jsLogs = [
            '[Console log]: OOps left debug in.'
        ];

        $scenarioMock = $this->getMockBuilder(ScenarioInterface::class)->getMock();
        $scenarioMock->expects($this->any())
            ->method('getLine')
            ->willReturn($expectedLineNumber);
        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->any())
            ->method('getScenario')
            ->willReturn($scenarioMock);

        $result = $this->callProtectedMethod('getExceptionDetails', [
            $currentUrl,
            $statusCode,
            $featureFile,
            $contextFile,
            $screenshotPath,
            $debugBarDetails,
            $jsErrors,
            $jsLogs,
            $jsWarns,
            DriverInterface::class,
            $currentScenarioMock
        ]);

        self::assertEquals('

[URL] http://site.dev/
[STATUS] 500
[FEATURE] features/login.feature
[CONTEXT] /Assertions/WebAssert.php
[SCREENSHOT] /private/var/tmp/2873438.png
[DRIVER] Behat\Mink\Driver\DriverInterface
[RERUN] ./vendor/bin/behat features/login.feature:4

[JSERRORS] [Console error]: Undefined var "abc"
------
[Console error]: Undefined var "xyz"

[JSWARNS] [Console warn]: Could not load data in.

[JSLOGS] [Console log]: OOps left debug in.

[DEBUG BAR INFO]
  [MESSAGE] Page not found.
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
}
