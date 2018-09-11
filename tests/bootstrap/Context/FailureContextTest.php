<?php

use Behat\Testwork\Hook\Scope\AfterTestScope;
use PHPUnit_Framework_TestCase;
use ReflectionClass;

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
            'siteFilters' => '',
            'debugBarSelectors' => ''
        ];

        $this->reflection = new ReflectionClass(FailreContext::class);
        $this->testObject = $this->reflection->newInstanceArgs($this->dependencies);
    }

    //tmethod

    private function getScopeObject($fail)
    {
        $scopeMock = $this->getMockBuilder(AfterTestScope::class)->getMock();

        $testResult
        $scopeMock->expects($this->any())
            ->method('getTestResult')
            ->willReturn();


        return $scopeMock;
    }
}