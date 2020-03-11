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

    public static function getJsErrors(Session $session)
    {
        return self::handleRetrieval('errors', $session);
    }

    public static function getJsLogs(Session $session)
    {
        return self::handleRetrieval('logs', $session);
    }

    public static function getJsWarns(Session $session)
    {
        return self::handleRetrieval('warns', $session);
    }

    private static function handleRetrieval($type, Session $session)
    {
        $content = [];
        try {
            if (isset(self::$trackJs[$type]) && self::$trackJs[$type]) {
                $content = self::getJsFromPage($type, $session);
            }

            if (!empty(self::$trackJs['trim'])) {
                $trimLength = self::$trackJs['trim'];
                $content = self::trimArrayMessages($content, $trimLength);
            }
        } catch (UnsupportedDriverActionException $e) {
            // ignore...
        } catch (Exception $e) {
            $content = [sprintf('Unable to fetch js %s: %s', $type, $e->getMessage())];
        }

        return $content;
    }

    /**
     * @return array
     * @param  mixed $type
     */
    private static function getJSFromPage($type, Session $session)
    {
        $var = sprintf('window.js%s', ucfirst($type));

        if ($session->evaluateScript(sprintf('typeof %s', $var)) === 'undefined') {
            throw new Exception(sprintf(
                'JS %s enabled but %s is undefined, please check implementation and on page load js errors.',
                $type,
                $var
            ));
        }

        $errors = $session->evaluateScript('return ' . $var);

        return empty($errors) ? [] : $errors;
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
