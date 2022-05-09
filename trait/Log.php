<?php

namespace prowebcraft\yii2log\trait;

use prowebcraft\yii2log\Telegram;
use prowebcraft\yii2log\TelegramBot;
use prowebcraft\yii2log\TelegramTarget;
use yii\log\Logger;

/**
 * Trait with log functions
 */
trait Log
{

    /** @var string Log Category */
    protected static string $category = __CLASS__;

    /**
     * Send message (sprintf format) to telegram
     * @param $format
     * @param ...$args
     * @return void
     * @throws \yii\httpclient\Exception
     */
    public static function toTelegram($format, ...$args): void
    {
        if (($message = static::getMessageBody(func_get_args()))) {
            if (\Yii::$app->has('telegram')) {
                $telegram = \Yii::$app->telegram;
                $target = $telegram->getTarget(static::$category);
                $telegram->sendMessage($target, $message);
            }
        }
    }

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
                if ($v instanceof \Throwable) {
                    return self::describeException($v);
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

    /**
     * Describe exception
     * @param \Throwable $v
     * @return string
     */
    public static function describeException(\Throwable $v): string
    {
        return get_class($v) . " " . sprintf("[%s] %s\n\n<b>File:</b> %s:%s\n<b>Trace:</b> <code>%s</code>",
                $v->getCode(),
                $v->getMessage(),
                $v->getFile(),
                $v->getLine(),
                $v->getTraceAsString()
            );
    }


}