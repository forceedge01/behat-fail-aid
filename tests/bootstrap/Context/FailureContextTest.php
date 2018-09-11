<?php

namespace FailAid\Context;

function file_put_contents($filename, $content)
{
    return true;
}

namespace FailAid\Tests\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Mink;
use Behat\Testwork\Hook\Scope\AfterTestScope;
use Behat\Testwork\Tester\Result\TestResult;
use Exception;
use FailAid\Context\FailureContext;
use PHPUnit_Framework_TestCase;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;

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
        $this->dependencies = [
            'screenshotDirectory' => '',
            'screenshotMode' => '',
            'siteFilters' => [],
            'debugBarSelectors' => []
        ];

        $this->reflection = new ReflectionClass(FailureContext::class);
        $this->testObject = $this->reflection->newInstanceArgs($this->dependencies);
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
        $expectedSiteFilters = [
            '/js/' => 'http://site.dev/js/',
            '/css/' => 'http://site.dev/css/',
        ];
        $expectedDebugBarSelectors = [
            'message' => '.debugBar .message',
            'queries' => '.debugBar .queries',
        ];

        $this->testObject = new FailureContext(
            $expectedScreenshotDirectory,
            $expectedScreenshotMode,
            $expectedSiteFilters,
            $expectedDebugBarSelectors
        );

        $siteFilters = $this->getPrivatePropertyValue('siteFilters');
        $screenshotMode = $this->getPrivatePropertyValue('screenshotMode');
        $debugBarSelectors = $this->getPrivatePropertyValue('debugBarSelectors');
        $screenshotDirectory = $this->getPrivatePropertyValue('screenshotDir');

        self::assertStringStartsWith($expectedScreenshotDirectory, $screenshotDirectory);
        self::assertNotEquals($expectedScreenshotDirectory, $screenshotDirectory);
        self::assertEquals($expectedScreenshotMode, $screenshotMode);
        self::assertEquals($expectedSiteFilters, $siteFilters);
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
        $this->markTestIncomplete();

        // $scopeMock = $this->getMockBuilder(AfterStepScope::class)->getMock();

        // $testResult = $this->getMockBuilder(TestResult::class)->getMock();
        // $testResult->expects($this->once())
        //     ->method('getResultCode')
        //     ->willReturn(TestResult::PASSED);

        // $scopeMock->expects($this->once())
        //     ->method('getTestResult')
        //     ->willReturn($testResult);

        // $this->testObject->takeScreenShotAfterFailedStep($scopeMock);
    }

    public function testTakeScreenshotAfterFailedStepFailed()
    {
        $this->markTestIncomplete();

        // $scopeMock = $this->getMockBuilder(AfterStepScope::class)->getMock();

        // $testResult = $this->getMockBuilder(TestResult::class)->getMock();
        // $testResult->expects($this->once())
        //     ->method('getResultCode')
        //     ->willReturn(TestResult::PASSED);

        // $scopeMock->expects($this->once())
        //     ->method('getTestResult')
        //     ->willReturn($testResult);

        // $this->testObject->takeScreenShotAfterFailedStep($scopeMock);
    }

    /**
     * @expectedException Exception
     */
    public function testTakeScreenshotNoHtml()
    {
        $filename = '/file/name.png';
        $page = $this->getMockBuilder(ElementInterface::class)->getMock();
        $page->expects($this->once())
            ->method('getHtml')
            ->will($this->throwException(new Exception()));
        $driver = $this->getMockBuilder(DriverInterface::class)->getMock();

        $this->testObject->takeScreenshot($filename, $page, $driver);
    }

    public function testTakeScreenshotWithHtmlAndDefaultScreenshotMode()
    {
        $filename = '/file/name-';
        $page = $this->getMockBuilder(ElementInterface::class)->getMock();
        $page->expects($this->any())
            ->method('getHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(Selenium2Driver::class)->getMock();

        $result = $this->testObject->takeScreenshot($filename, $page, $driver);

        self::assertEquals('png', pathinfo($result, PATHINFO_EXTENSION));
    }

    public function testTakeScreenshotWithHtmlAndHtmlScreenshotMode()
    {
        $filename = '/file/name-';
        $page = $this->getMockBuilder(ElementInterface::class)->getMock();
        $page->expects($this->any())
            ->method('getHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(DriverInterface::class)->getMock();

        $this->setPrivatePropertyValue('screenshotMode', FailureContext::SCREENSHOT_MODE_DEFAULT);

        $result = $this->testObject->takeScreenshot($filename, $page, $driver);

        self::assertEquals('html', pathinfo($result, PATHINFO_EXTENSION));
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
        $debugBarDetails = '  [MESSAGE] Page not found.
  [QUERY] Element "#debug .query" Not Found.
';

        $result = $this->callProtectedMethod('getExceptionDetails', [
            $currentUrl,
            $statusCode,
            $featureFile,
            $contextFile,
            $screenshotPath,
            $debugBarDetails
        ]);

        self::assertEquals('

[URL] http://site.dev/
[STATUS] 500
[FEATURE] features/login.feature
[CONTEXT] /Assertions/WebAssert.php
[SCREENSHOT] /private/var/tmp/2873438.png
[RERUN] ./vendor/bin/behat features/login.feature
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

    private function setPrivatePropertyValue($property, $value)
    {
        $reflectionProperty = new ReflectionProperty(get_class($this->testObject), $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, $value);

        return $this;
    }
}