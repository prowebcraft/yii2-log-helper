<?php
namespace prowebcraft\yii2log;

use appiclab\yii2module\helpers\Utils;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;
use yii\base\InvalidConfigException;

/**
 * Yii 2.0 Telegram Log Target
 * TelegramTarget sends selected log messages to the specified telegram chats or channels
 *
 * You should set [telegram bot token](https://core.telegram.org/bots#botfather) and chatId in your config file like below code:
 * ```php
 * 'log' => [
 *     'targets' => [
 *         [
 *             'class' => 'prowebcraft\yii2log\TelegramTarget',
 *             'levels' => ['error'],
 *             'botToken' => '123456:abcde', // bot token secret key
 *             'defaultTarget' => '123456', // chat id or channel username with @ like 12345 or @channel
 *         ],
 *     ],
 * ],
 * ```
 */
class TelegramTarget extends Target
{

    public string $defaultChatId;
    public array $extraTargets = [];

    /**
     * Check required properties
     */
    public function init()
    {
        parent::init();
        foreach (['defaultChatId'] as $property) {
            if ($this->$property === null) {
                throw new InvalidConfigException(self::class . "::\$$property property must be set");
            }
        }
    }

    /**
     * Get telegram target by message category
     * @param string $category
     * @return string
     */
    public function getTarget(string $category): string
    {
        return $this->defaultChatId;
    }

    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export()
    {
        $messages = array_map([$this, 'formatMessage'], $this->messages);
        if (count($messages) <= 3) {
            $message = implode("\n", $messages);
            \Yii::$app->telegram->sendMessage($this->defaultChatId, $message);
        } else {
            foreach ($messages as $message) {
                \Yii::$app->telegram->sendMessage($this->getTarget($message['category']), $message);
            }
        }
    }

    /**
     * Formats a log message for display as a string.
     * @param array $message the log message to be formatted.
     * The message structure follows that in [[Logger::messages]].
     * @return string the formatted message
     */
    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Exception || $text instanceof \Throwable) {
                $text = (string) $text;
            } else {
                $text = VarDumper::export($text);
            }
        }
        $traces = [];
        if (isset($message[4])) {
            foreach ($message[4] as $trace) {
                $traces[] = "<code>in {$trace['file']}:{$trace['line']}</code>";
            }
        }

        $prefix = $this->getMessagePrefix($message);
        return '<code>' . $this->getTime($timestamp) . " {$prefix}[$level][$category]</code>\n$text"
            . (empty($traces) ? '' : "\n    " . implode("\n    ", $traces));
    }
}
