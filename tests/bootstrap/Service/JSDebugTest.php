<?php

namespace FailAid\Tests\Context;

use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Exception;
use FailAid\Service\JSDebug;
use PHPUnit_Framework_TestCase;

class JSDebugTest extends PHPUnit_Framework_TestCase
{
    public function testGetJsLogs()
    {
        $expectedResult = ['A console log output goes longer than twenty characters', 'another console log output'];

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->at(0))
            ->method('evaluateScript')
            ->with('typeof window.jsLogs')
            ->willReturn('array');
        $session->expects($this->at(1))
            ->method('evaluateScript')
            ->with('return window.jsLogs')
            ->willReturn($expectedResult);

        JSDebug::setOptions(['trim' => 20, 'logs' => true]);
        $result = JSDebug::getJsLogs($session);

        self::assertInternalType('array', $result);
        self::assertEquals('A console log output', $result[0]);
        self::assertEquals('another console log ', $result[1]);
    }

    public function testGetJsLogsImplementationError()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->at(0))
            ->method('evaluateScript')
            ->with('typeof window.jsLogs')
            ->willReturn('undefined');

        JSDebug::setOptions(['trim' => 20, 'logs' => true]);
        $result = JSDebug::getJsLogs($session);

        self::assertInternalType('array', $result);
        self::assertEquals(['Unable to fetch js logs: JS logs enabled but window.jsLogs is undefined, please check implementation and on page load js errors.'], $result);
    }

    public function testGetJsLogsUnsupportedDriverAction()
    {
        $exception = $this->getMockBuilder(UnsupportedDriverActionException::class)->disableOriginalConstructor()->getMock();

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->any())
            ->method('evaluateScript')
            ->will($this->throwException($exception));

        JSDebug::setOptions(['trim' => 20, 'logs' => true]);
        $result = JSDebug::getJsLogs($session);

        self::assertInternalType('array', $result);
        self::assertEquals([], $result);
    }

    public function testGetJsLogsAnotherException()
    {
        $message = 'Something went terribly wrong...';
        $exception = new Exception($message);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->any())
            ->method('evaluateScript')
            ->will($this->throwException($exception));

        JSDebug::setOptions(['trim' => 20, 'logs' => true]);
        $result = JSDebug::getJsLogs($session);

        self::assertInternalType('array', $result);
        self::assertEquals(['Unable to fetch js logs: ' . $message], $result);
    }

    public function testGetJsWarns()
    {
        $expectedResult = ['A console log output goes longer than twenty characters', 'another console log output'];

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->at(0))
            ->method('evaluateScript')
            ->with('typeof window.jsWarns')
            ->willReturn('array');
        $session->expects($this->at(1))
            ->method('evaluateScript')
            ->with('return window.jsWarns')
            ->willReturn($expectedResult);

        JSDebug::setOptions(['trim' => 20, 'warns' => true]);
        $result = JSDebug::getJsWarns($session);

        self::assertInternalType('array', $result);
        self::assertEquals('A console log output', $result[0]);
        self::assertEquals('another console log ', $result[1]);
    }

    public function testGetJsWarnsImplementationError()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->at(0))
            ->method('evaluateScript')
            ->with('typeof window.jsWarns')
            ->willReturn('undefined');

        JSDebug::setOptions(['trim' => 20, 'warns' => true]);
        $result = JSDebug::getJsWarns($session);

        self::assertInternalType('array', $result);
        self::assertEquals(['Unable to fetch js warns: JS warns enabled but window.jsWarns is undefined, please check implementation and on page load js errors.'], $result);
    }

    public function testGetJsWarnsUnsupportedDriverAction()
    {
        $exception = $this->getMockBuilder(UnsupportedDriverActionException::class)->disableOriginalConstructor()->getMock();

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->any())
            ->method('evaluateScript')
            ->will($this->throwException($exception));

        JSDebug::setOptions(['trim' => 20, 'warns' => true]);
        $result = JSDebug::getJsWarns($session);

        self::assertInternalType('array', $result);
        self::assertEquals([], $result);
    }

    public function testGetJsWarnsAnotherException()
    {
        $message = 'Something went terribly wrong...';
        $exception = new Exception($message);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->any())
            ->method('evaluateScript')
            ->will($this->throwException($exception));

        JSDebug::setOptions(['trim' => 20, 'warns' => true]);
        $result = JSDebug::getJsWarns($session);

        self::assertInternalType('array', $result);
        self::assertEquals(['Unable to fetch js warns: ' . $message], $result);
    }

    public function testGetJsErrors()
    {
        $expectedResult = ['A console log output goes longer than twenty characters', 'another console log output'];

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->at(0))
            ->method('evaluateScript')
            ->with('typeof window.jsErrors')
            ->willReturn('array');
        $session->expects($this->at(1))
            ->method('evaluateScript')
            ->with('return window.jsErrors')
            ->willReturn($expectedResult);

        JSDebug::setOptions(['trim' => 20, 'errors' => true]);
        $result = JSDebug::getJsErrors($session);

        self::assertInternalType('array', $result);
        self::assertEquals('A console log output', $result[0]);
        self::assertEquals('another console log ', $result[1]);
    }

    public function testGetJsErrorsImplementationError()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->at(0))
            ->method('evaluateScript')
            ->with('typeof window.jsErrors')
            ->willReturn('undefined');

        JSDebug::setOptions(['trim' => 20, 'errors' => true]);
        $result = JSDebug::getJsErrors($session);

        self::assertInternalType('array', $result);
        self::assertEquals(['Unable to fetch js errors: JS errors enabled but window.jsErrors is undefined, please check implementation and on page load js errors.'], $result);
    }

    public function testGetJsErrorsUnsupportedDriverAction()
    {
        $exception = $this->getMockBuilder(UnsupportedDriverActionException::class)->disableOriginalConstructor()->getMock();

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->any())
            ->method('evaluateScript')
            ->will($this->throwException($exception));

        JSDebug::setOptions(['trim' => 20, 'errors' => true]);
        $result = JSDebug::getJsErrors($session);

        self::assertInternalType('array', $result);
        self::assertEquals([], $result);
    }

    public function testGetJsErrorsAnotherException()
    {
        $message = 'Something went terribly wrong...';
        $exception = new Exception($message);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->any())
            ->method('evaluateScript')
            ->will($this->throwException($exception));

        JSDebug::setOptions(['trim' => 20, 'errors' => true]);
        $result = JSDebug::getJsErrors($session);

        self::assertInternalType('array', $result);
        self::assertEquals(['Unable to fetch js errors: ' . $message], $result);
    }
}
