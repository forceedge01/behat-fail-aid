<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use FailAid\Context\FailureContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        
    }

    /**
     * @Given I record the state of the user
     */
    public function iRecordTheStateOfTheUser()
    {
        FailureContext::addState('user email', 'its.inevitable@hotmail.com');
        FailureContext::addState('postcode', 'B23 7QQ');
    }
}
