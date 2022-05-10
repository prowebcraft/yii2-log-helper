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
     * @param \Throwable $e
     * @return string
     */
    public static function describeException(\Throwable $e): string
    {
        return get_class($e) . " " . sprintf("[%s] %s\n\n<b>File:</b> %s:%s\n<b>Trace:</b> <code>%s</code>",
                $e->getCode(),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                self::getExceptionTraceAsString($e)
            );
    }

    /**
     * Get Trace as string
     *
     * @param \Throwable $exception
     * @return string
     */
    public static function getExceptionTraceAsString(\Throwable $exception, int $skipFrames = 0, int $frames = 5): string
    {
        $rtn = "";
        $count = 0;
        $trace = array_slice($exception->getTrace(), $skipFrames, $frames);
        foreach ($trace as $frame) {
            $args = "";
            if (isset($frame['args'])) {
                $args = array();
                foreach ($frame['args'] as $arg) {
                    if (is_string($arg)) {
                        if (strlen($arg) > 255) {
                            $arg = substr($arg, 0, 255) . '...';
                        }
                        $args[] = "'" . $arg . "'";
                    } elseif (is_array($arg)) {
                        $args[] = "Array";
                    } elseif (is_null($arg)) {
                        $args[] = 'NULL';
                    } elseif (is_bool($arg)) {
                        $args[] = ($arg) ? "true" : "false";
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_resource($arg)) {
                        $args[] = get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
                $args = join(", ", $args);
            }
            $rtn .= sprintf("#%s %s(%s): %s(%s)\n",
                $count,
                $frame['file'] ?? '',
                $frame['line'] ?? '',
                $frame['function'],
                $args);
            $count++;
        }

        return $rtn;
    }


}