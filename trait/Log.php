<?php

namespace prowebcraft\yii2log\trait;

use yii\log\Logger;

/**
 * Trait with log functions
 */
trait Log
{

    /** @var string Log Category */
    protected static string $category = 'app';

    /**
     * Debug message (sprintf format)
     * @param $format
     * @param ...$args
     * @return void
     */
    public static function debug($format, ...$args): void
    {
        if (($message = static::getMessageBody(func_get_args()))) {
            static::processLog($message, Logger::LEVEL_TRACE);
        }
    }

    /**
     * Info message (sprintf format)
     * @param $format
     * @param ...$args
     * @return void
     */
    public static function info($format, ...$args): void
    {
        if (($message = static::getMessageBody(func_get_args()))) {
            static::processLog($message, Logger::LEVEL_INFO);
        }
    }

    /**
     * Warning message (sprintf format)
     * @param $format
     * @param ...$args
     * @return void
     */
    public static function warning($format, ...$args): void
    {
        if (($message = static::getMessageBody(func_get_args()))) {
            static::processLog($message, Logger::LEVEL_WARNING);
        }
    }

    /**
     * Error message (sprintf format)
     * @param $format
     * @param ...$args
     * @return void
     */
    public static function error($format, ...$args): void
    {
        if (($message = static::getMessageBody(func_get_args()))) {
            static::processLog($message, Logger::LEVEL_ERROR);
        }
    }

    /**
     * Process logging
     * @param string $message
     * @param int $level
     * @return void
     */
    protected static function processLog(string $message, int $level = Logger::LEVEL_INFO): void
    {
        \Yii::getLogger()->log($message, $level, static::$category);
    }

    /**
     * Process log message body in sprintf style
     * @param array $args
     * @return string|null
     */
    protected static function getMessageBody(array $args): ?string
    {
        if (empty($args)) {
            return null;
        }
        if (count($args) === 1) {
            $message = $args[0];
        } else {
            $text = array_shift($args);
            $args = array_map(static function ($v) {
                if (is_bool($v)) {
                    return $v ? 'true' : 'false';
                }
                if (is_object($v) && method_exists($v, '__toString')) {
                    return (string)$v;
                }
                if (is_array($v) || is_object($v)) {
                    return json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
                return $v;
            }, $args);
            $message = vsprintf($text, $args);
        }

        return $message;
    }

}