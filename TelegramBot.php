<?php

namespace prowebcraft\yii2log;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;

/**
 * Telegram Bot
 */
class TelegramBot extends Component
{
    public const API_BASE_URL = 'https://api.telegram.org/bot';
    protected const MAX_MESSAGE_LENGTH = 4096;
    protected const MAX_TOTAL_LENGTH = self::MAX_MESSAGE_LENGTH * 5;

    /**
     * Bot api token secret key
     * @var string
     */
    public string $token;

    /**
     * Default telegram target
     * @var string
     */
    public string $defaultChatId;

    /**
     * Override default target per log category
     * @var array
     */
    public array $targetPerCategory = [];

    protected ?Client $client = null;

    /**
     * Check required property
     */
    public function init()
    {
        parent::init();
        foreach (['token', 'defaultChatId'] as $property) {
            if ($this->$property === null) {
                throw new InvalidConfigException(self::class . "::\$$property property must be set");
            }
        }
    }

    /**
     * Get client
     * @return Client
     */
    public function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client(['baseUrl' => self::API_BASE_URL . $this->token]);
        }

        return $this->client;
    }

    /**
     * Get telegram target by message category
     * @param string $category
     * @return string
     */
    public function getTarget(string $category): string
    {
        return $this->targetPerCategory[$category] ?? $this->defaultChatId;
    }

    /**
     * Send message to the telegram chat or channel
     * @param string $chatId
     * Target telegram id
     * @param string $message
     * Message to be sent
     * @param string $parseMode
     * Parse html or markdown markup
     * @param array $payload
     * Extra params
     * @see https://core.telegram.org/bots/api#sendmessage
     * @param bool $autoSplit
     * Auto-split long messages to chunks
     * @return mixed
     * @throws \yii\httpclient\Exception
     * return array
     */
    public function sendMessage(
        string $chatId,
        string $message,
        string $parseMode = 'html',
        array  $payload = [],
        bool   $autoSplit = true,
    )
    {
        $message = strip_tags($message, '<b><strong><i><em><a><code><pre>');
        $payload = array_merge([
            'chat_id' => $chatId,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => null,
            'disable_notification' => null,
            'reply_to_message_id' => null,
        ], $payload);

        if ($autoSplit && ($size = mb_strlen($message)) > self::MAX_MESSAGE_LENGTH) {
            if ($size > self::MAX_TOTAL_LENGTH) {
                $size = self::MAX_TOTAL_LENGTH;
                $message = mb_substr($message, 0, self::MAX_TOTAL_LENGTH);
            }
            $textChunks = [ ];
            for ($i = 0; $i < ceil($size / self::MAX_MESSAGE_LENGTH); $i ++) {
                $textChunks[] = mb_substr($message, $i > 0 ? $i * self::MAX_MESSAGE_LENGTH : 0, self::MAX_MESSAGE_LENGTH);
            }
        } else {
            $textChunks = [ $message ];
        }

        foreach ($textChunks as $text) {
            $payload['text'] = $text;
            $response = $this->getClient()->post('sendMessage', $payload)->send();
        }

        return isset($response) ? $response->data : null;
    }
}
