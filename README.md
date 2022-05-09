# yii2-log-helper
Yii2 Log Helper

## General usage

Attach **\prowebcraft\yii2log\trait\Log** trait to your class or extend it from **\prowebcraft\yii2log\Log**

Call static `debug`, `info`, `warning` or `error` method to log some info, for example:

```php
<?php

use \prowebcraft\yii2log\trait\Log;

class MarsExpedition {

    use Log;
    
    public function doHeavyJob(){
        self::info('Prepare to launch');
        $missionInfo = [
            'title' => 'Mission to Mars',
            'distance' => '54.6m km',
            'pilot' => 'Elon Musk',
        ];
        self::debug('Flight data: %s', $missionInfo);
        try {
            // mission inpossible            
        } catch($e) {
            self::error('Error flying to Mars: %s', $e);
        }
    }
}
```

## Configuration

## Send messages to telegram
To send error/warning messages to telegram add component and log target to your config (ex. **common/main-local.php**):

```php
    'components' => [
        // ...
        'telegram' => [
            'class' => \prowebcraft\yii2log\TelegramBot::class,
            'token' => '123:xyz' // telegram bot token,
            'defaultChatId' => -1000000000, // group or channel id,
            'targetPerCategory' => [
                'mission_control' => -20000000
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \prowebcraft\yii2log\TelegramTarget::class,
                    'levels' => ['error', 'warning'], // log levels
                    'logVars' => []
                ]
            ],
        ],
        // ...
    ],
];
```

