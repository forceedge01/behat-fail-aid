<?php

namespace FailAid\Tests\Context;

use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Mink\Driver\DriverInterface;
use FailAid\Service\Output;
use PHPUnit_Framework_TestCase;

class OutputTest extends PHPUnit_Framework_TestCase
{
    public function testProvideDiff()
    {
        $expected = 'abc';
        $actual = 'xyz';
        $message = 'clearly not equal.';

        $result = Output::provideDiff($expected, $actual, $message);

        $expectedOutput = 'Mismatch: (- expected, + actual)

- abc
+ xyz

Info: clearly not equal.';

        self::assertEquals($expectedOutput, $result);
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
        $scenarioMock->expects($this->atLeastOnce())->method('getLine')->willReturn($expectedLineNumber);
        $scenarioMock->expects($this->atLeastOnce())->method('getTags')->willReturn(['first', 'second']);

        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->atLeastOnce())->method('getScenario')->willReturn($scenarioMock);

        Output::setOptions([
            'url' => true,
            'status' => true,
            'feature' => true,
            'context' => true,
            'screenshot' => true,
            'driver' => true,
            'tags' => true,
            'rerun' => true,
        ]);
        $result = Output::getExceptionDetails(
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
        );

        self::assertEquals('

[URL] http://site.dev/
[STATUS] 500
[FEATURE] features/login.feature
[TAGS] first, second
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

    public function testGetExceptionDetailsTurnOptionsOff()
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
        $scenarioMock->expects($this->never())->method('getLine')->willReturn($expectedLineNumber);
        $scenarioMock->expects($this->atLeastOnce())->method('getTags')->willReturn([]);

        $currentScenarioMock = $this->getMockBuilder(ScenarioScope::class)->getMock();
        $currentScenarioMock->expects($this->atLeastOnce())->method('getScenario')->willReturn($scenarioMock);

        Output::setOptions([
            'url' => true,
            'status' => true,
            'feature' => false,
            'tags' => true,
            'context' => true,
            'screenshot' => false,
            'driver' => true,
            'rerun' => false,
        ]);
        $result = Output::getExceptionDetails(
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
        );

        self::assertEquals('

[URL] http://site.dev/
[STATUS] 500
[TAGS] 
[CONTEXT] /Assertions/WebAssert.php
[DRIVER] Behat\Mink\Driver\DriverInterface

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
}
