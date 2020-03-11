<?php

namespace FailAid\Context\Contracts;

use Behat\Mink\Element\DocumentElement;

/**
 * DebugBarInterface interface.
 */
interface DebugBarInterface
{
    /**
     * Override if gathering details is complex.
     *
     * @param array $debugBarSelectors
     * @param DocumentElement $page
     *
     * @return string
     */
    public function gatherDebugBarDetails(array $debugBarSelectors, DocumentElement $page);
}
