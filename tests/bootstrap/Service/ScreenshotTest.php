<?php

namespace FailAid\Service;

function date() {
    return '123';
}

namespace FailAid\Tests\Context;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\Element;
use Behat\Mink\Exception\DriverException;
use Exception;
use FailAid\Service\Screenshot;
use PHPUnit_Framework_TestCase;

/**
 * @group screenshotTests
 */
class ScreenshotTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        Screenshot::setOptions([
            'hostDirectory' => null,
            'hostUrl' => null,
        ], []);
    }

    public function testSetOptions()
    {
        $options = [
            'directory' => __DIR__,
            'mode' => 'html',
            'autoClean' => true,
            'size' => '1024x2000',
            'hostDirectory' => '/abc/123/'
        ];
        $siteFilters = [
            'abc' => '123',
            'xyz' => '789'
        ];

        Screenshot::setOptions($options, $siteFilters);

        self::assertEquals($siteFilters, Screenshot::$siteFilters);
        self::assertEquals($options['directory'] . '/123', Screenshot::$screenshotDir);
        self::assertEquals($options['mode'], Screenshot::$screenshotMode);
        self::assertEquals($options['autoClean'], Screenshot::$screenshotAutoClean);
        self::assertEquals(['1024', '2000'], Screenshot::$screenshotSize);
        self::assertEquals($options['hostDirectory'] . '123', Screenshot::$screenshotHostDirectory);
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

        Screenshot::takeScreenshot($page, $driver);
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
        $result = Screenshot::takeScreenshot($page, $driver);

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
        $result = Screenshot::takeScreenshot($page, $driver);

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
        $result = Screenshot::takeScreenshot($page, $driver);

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
        $result = Screenshot::takeScreenshot($page, $driver);

        self::assertEquals('png', pathinfo($result, PATHINFO_EXTENSION));
    }

    public function testTakeScreenshotWithHostUrl()
    {
        $options = [
            'directory' => null,
            'mode' => 'html',
            'autoClean' => true,
            'size' => '1024x2000',
            'hostUrl' => 'http://ci/failures/'
        ];
        $siteFilters = [];

        $filename = '/file/name-';
        $page = $this->getMockBuilder(Element::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->any())
            ->method('getOuterHtml')
            ->willReturn('<html></html>');
        $driver = $this->getMockBuilder(Selenium2Driver::class)->getMock();

        Screenshot::setOptions($options, $siteFilters);
        $result = Screenshot::takeScreenshot($page, $driver);

        self::assertRegExp('#http://ci/failures/.+\.html#', $result);
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