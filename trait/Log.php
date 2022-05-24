<?php

namespace prowebcraft\yii2log\trait;

use Yii;
use yii\helpers\FileHelper;
use yii\log\Logger;

/**
 * Trait with log functions
 */
trait Log
{

    protected static array $logBuffer = [];
    protected static array $extraRequestInfo = [];

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
        // Apeend extra log from buffer
        while ($extra = array_shift(self::$logBuffer)) {
            $message .= "\n" . $extra;
        }
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
                $args = implode(", ", $args);
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

    /**
     * Get request data
     * @return string
     * @noinspection PhpComposerExtensionStubsInspection
     */
    public static function getRequestContext(): string
    {
        $res = [];
        if (isset($_SERVER['REQUEST_METHOD'])) {
            if (isset($_SERVER['HTTP_HOST'])) {
                $res['host'] = $_SERVER['HTTP_HOST'];
            }
            if ($hostname = gethostname()) {
                $res['server'] = $hostname;
            }
            if (isset($_SERVER['REQUEST_URI'])) {
                $res['path'] = $_SERVER['REQUEST_URI'];
            }
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $res['agent'] = $_SERVER['HTTP_USER_AGENT'];
            }
            if (isset($_SERVER['HTTP_REFERER'])) {
                $res['referer'] = $_SERVER['HTTP_REFERER'];
            }
            if ($ip = Yii::$app->getRequest()->getUserIP()) {
                $res['ip'] = $ip;
            }
            $session = Yii::$app->session;
            if ($session->isActive) {
                $res['session'] = $session->id;
            }
            $res['headers'] = Yii::$app->getRequest()->getHeaders()->toArray();
        } else {
            if (isset($_SERVER['SCRIPT_FILENAME'])) {
                $res['file'] = $_SERVER['SCRIPT_FILENAME'];
            }
            $args = $_SERVER['argv'];
            unset($args[0]);
            if (!empty($args)) {
                $res['args'] = implode(" ", $args);
            }
            $by = get_current_user();
            if (function_exists('posix_geteuid')) {
                $processUser = posix_getpwuid(posix_geteuid());
                $by = $processUser["name"] ?: 'root';
            }
            if ($by) {
                $res['user'] = $by;
            }
            if ($hostname = gethostname()) {
                $res['server'] = $hostname;
            }
        }
        $version = phpversion();
        $res['php'] = $version;

        if (!empty(self::$extraRequestInfo)) {
            foreach (self::$extraRequestInfo as $name => $info) {
                $res[$name] = $info;
            }
        }
        $out = '';
        foreach ($res as $k => $v) {
            $out .= "$k: ". (is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."\n";
        }

        return $out;
    }

    /**
     * Add request data information
     * @return static
     * @noinspection JsonEncodingApiUsageInspection
     */
    public static function withRequestData($external = false): static
    {
        $details = self::getRequestContext();
        $request = Yii::$app->getRequest();
        if (!empty($request->getBodyParams())) {
            $details .= "\n<b>Request Params:</b> "
                . json_encode($request->getBodyParams(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        return self::addLogBuffer("<b>Request Data:</b> %s", ($external ? self::createTraceFile($details) : "<code>$details</code>"));
    }

    /**
     * Store data in external file
     * @param string|array $data
     * @param string $title
     * @return static
     * @noinspection JsonEncodingApiUsageInspection
     */
    public static function withExternalData(string|array $data, string $title = 'Details'): static
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return self::addLogBuffer("<b>%s:</b> %s", $title, self::createTraceFile($data));
    }

    /**
     * Create external file with some data
     * @param string $content
     * Content of the file
     * @param string $filename
     * Filename suffix
     * @param string $ext
     * Extension of the file
     * @return string|null
     */
    public static function createTraceFile(string $content, string $filename = 'trace', string $ext = 'txt'): ?string
    {
        try {
            $traceDir = 'trace';
            $tracePath = \Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $traceDir;
            FileHelper::createDirectory($tracePath);
            $traceName = sprintf("%s_%s_%s.%s", date('Ymd_His'), $filename, random_int(1000, 9999), $ext);
            file_put_contents($tracePath . DIRECTORY_SEPARATOR . $traceName, $content);
            return \Yii::$app->urlManager->createAbsoluteUrl('/' .  $traceDir . '/' . $traceName);
        } catch (\Throwable $e) {
            self::error('Error creating trace file: %s', $e);
        }
        return null;
    }

    /**
     * Extra data, will be appended to next message
     * @param $format
     * @param ...$args
     * @return static
     */
    public static function addLogBuffer($format, ...$args): static
    {
        if (($message = static::getMessageBody(func_get_args()))) {
            static::$logBuffer[] = $message;
        }

        return new static(self::class);
    }

    /**
     * Add extra request info
     * @param string $key
     * @param string $value
     * @return void
     */
    public static function setExtraRequestInfo(string $key, string $value): void
    {
        self::$extraRequestInfo[$key] = $value;
    }

}