<?php

namespace FailAid\Context\Contracts;

use Behat\Mink\Element\DocumentElement;

/**
 * FailStateInterface interface.
 */
interface FailStateInterface
{
    /**
     * @BeforeScenario
     */
    public function refreshStates();

    /**
     * @param string $name
     * @param string|int $value
     */
    public static function addState($name, $value);

    /**
     * @param array $state
     *
     * @return string
     */
    public function getStateDetails(array $states);
}
