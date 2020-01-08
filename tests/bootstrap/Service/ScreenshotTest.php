<?php

namespace FailAid\Tests\Context;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\Element;
use Behat\Mink\Exception\DriverException;
use Exception;
use FailAid\Service\Screenshot;
use PHPUnit_Framework_TestCase;

class ScreenshotTest extends PHPUnit_Framework_TestCase
{
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

        Screenshot::takeScreenshot($filename, $page, $driver);
    }

    public function testTakeScreenshotWithHtmlAndDefaultScreenshotModeWithSeleniumDriver()
    {
        $filename = '/file/name-';
        $page = $this->getMockBuilder(Element::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->any())
            ->method('getOuterHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(Selenium2Driver::class)->getMock();

        Screenshot::$screenshotMode = 'default';
        $result = Screenshot::takeScreenshot($filename, $page, $driver);

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

        Screenshot::$screenshotMode = 'default';
        $result = Screenshot::takeScreenshot($filename, $page, $driver);

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

        Screenshot::$screenshotMode = 'html';
        $result = Screenshot::takeScreenshot($filename, $page, $driver);

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

        Screenshot::$screenshotMode = 'png';
        $result = Screenshot::takeScreenshot($filename, $page, $driver);

        self::assertEquals('png', pathinfo($result, PATHINFO_EXTENSION));
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

        Screenshot::$siteFilters = [
            '/assets/images/' => 'http://site.dev/assets/images/',
            '/assets/css/' => 'http://site.dev/assets/css/',
            '/assets/script/' => 'http://site.dev/assets/javascripts/'
        ];

        $result = Screenshot::applySiteSpecificFilters($content);

        self::assertEquals($expectedContent, $result);
    }
}