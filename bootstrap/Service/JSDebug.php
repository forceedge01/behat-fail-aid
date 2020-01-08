<?php

namespace FailAid\Service;

use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Exception;

/**
 * JSDebug class.
 */
class JSDebug
{
    /**
     * @var array
     */
    private static $trackJs;

    public static function setOptions(array $trackJs)
    {
        self::$trackJs = $trackJs;
    }

    public static function getOptions()
    {
        return self::$trackJs;
    }

    /**
     *
     * @return array
     */
    private static function getJSErrorsFromPage(Session $session)
    {
        return $session->evaluateScript('return window.jsErrors');
    }

    /**
     *
     * @return array
     */
    private static function getJSLogsFromPage(Session $session)
    {
        return $session->evaluateScript('return window.jsLogs');
    }

    /**
     *
     * @return array
     */
    private static function getJSWarnsFromPage(Session $session)
    {
        return $session->evaluateScript('return window.jsWarns');
    }

    public static function getJsErrors(Session $session)
    {
        $jsErrors = [];
        try {
            if (isset(self::$trackJs['errors']) && self::$trackJs['errors']) {
                $jsErrors = self::getJSErrorsFromPage($session);
            }
        } catch (UnsupportedDriverActionException $e) {
            // ignore...
        } catch (Exception $e) {
            $jsErrors = ['Unable to fetch js errors: ' . $e->getMessage()];
        }

        if (!empty(self::$trackJs['trim'])) {
            $trimLength = self::$trackJs['trim'];
            $jsErrors = self::trimArrayMessages($jsErrors, $trimLength);
        }

        return $jsErrors;
    }

    public static function getJsLogs(Session $session)
    {
        $jsLogs = [];

        try {
            if (isset(self::$trackJs['logs']) && self::$trackJs['logs']) {
                $jsLogs = self::getJSLogsFromPage($session);
            }
        } catch (UnsupportedDriverActionException $e) {
            // ignore...
        } catch (Exception $e) {
            $jsLogs = ['Unable to fetch js logs: ' . $e->getMessage()];
        }

        if (!empty(self::$trackJs['trim'])) {
            $trimLength = self::$trackJs['trim'];
            $jsLogs = self::trimArrayMessages($jsLogs, $trimLength);
        }

        return $jsLogs;
    }

    public static function getJsWarns(Session $session)
    {
        $jsWarns = [];
        try {
            if (isset(self::$trackJs['warns']) && self::$trackJs['warns']) {
                $jsWarns = self::getJSWarnsFromPage($session);
            }
        } catch (UnsupportedDriverActionException $e) {
            // ignore...
        } catch (Exception $e) {
            $jsWarns = ['Unable to fetch js warns: ' . $e->getMessage()];
        }

        if (!empty(self::$trackJs['trim'])) {
            $trimLength = self::$trackJs['trim'];
            $jsWarns = self::trimArrayMessages($jsWarns, $trimLength);
        }

        return $jsWarns;
    }

    /**
     * @param int $length
     *
     * @return array
     */
    private static function trimArrayMessages(array $messages, $length)
    {
        array_walk($messages, function (&$msg) use ($length) {
            $msg = substr($msg, 0, $length);
        });

        return $messages;
    }
}
