<?php

namespace prowebcraft\yii2log;

use yii\httpclient\Response;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\httpclient\Exception;

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
     * @param string|null $category
     * @return string
     */
    public function getTarget(string $category = null): string
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

    /**
     * Send document to telegram. You must provide content or file path to send
     * @param string|null $chatId
     * target chat id
     * @param string|null $content
     * Content to send
     * @param string|null $file
     * File to send
     * @param string|null $caption
     * File Caption
     * @param string|null $filename
     * File name.
     * @param array $payload
     * Extra/overrided payload params
     * @return array|null
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function sendDocument(
        string $chatId = null,
        string $content = null,
        string $file = null,
        string $filename = null,
        string $caption = null,
        array $payload = [],
    ) {
        if ($chatId === null) {
            $chatId = $this->getTarget();
        }
        if (!$file && !$content) {
            throw new \InvalidArgumentException('No file or content provided');
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'disable_web_page_preview' => null,
            'disable_notification' => null,
            'reply_to_message_id' => null,
        ], $payload);
        if ($caption) {
            $caption = strip_tags($caption, '<b><strong><i><em><a><code><pre>');
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'html';
        }
        $request = $this->getClient()->createRequest()
            ->setUrl('sendDocument')
            ->setData($payload)
        ;
        if ($file) {
            if (empty($filename)) {
                $filename = basename($file);
            }
            $request->addFile('document', $file, [
                'fileName' => $filename
            ]);
        } else {
            $request->addFileContent('document', $content, [
                'fileName' => $filename
            ]);
        }
        $response = $request->send();
        $this->validateResponse($response);

        return isset($response) ? $response->data : null;
    }

    /**
     * Validate Api response and throw exception if necessary
     * @param Response $response
     * @return void
     */
    protected function validateResponse(Response $response): void
    {
        if (!empty($response->data['error_code'])) {
            if (!empty($response->data['description'])) {
                throw new \RuntimeException('Telegram Api Error: ' . $response->data['description']);
            }
            throw new \RuntimeException('Telegram Api Error: ' . $response->content);
        }
    }
}
