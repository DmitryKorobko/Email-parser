<?php

$params = array_merge(
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

if (file_exists(__DIR__ . '/db-local.php')) {
    $db = require(__DIR__ . '/db-local.php');
} else {
    $db = require(__DIR__ . '/db.php');
}

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class'          => 'yii\log\FileTarget',
                    'levels'         => ['error', 'warning'],
                    'categories' => ['parser'],
                    'logFile'        => '@app/runtime/logs/parser.log',
                    'exportInterval' => 1,
                    'maxFileSize'    => 1024 * 2,
                    'maxLogFiles'    => 20,
                    'logVars'        => []
                ],
                [
                    'class'      => 'yii\log\EmailTarget',
                    'mailer'     => 'mailer',
                    'levels'     => ['error'],
                    'categories' => ['parser'],
                    'message'    => [
                        'from'    => ['order@laxa24.com'],
                        'to'      => ['support@laxa24.com'],
                        'subject' => 'Parsing Error',
                    ],
                    'logVars'    => []
                ],
            ],
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'useFileTransport' => false,
            'transport' => [
                'class' => 'Swift_SmtpTransport',
                'host' => 'mail.laxa24.com'
            ],
        ],
        'db' => $db,
        'apiClient' => [
            'class' => \app\modules\parser\components\ApiClient::class
        ],
        'messageDispatcher' => [
            'class' => \app\modules\parser\components\MessageDispatcher::class,
            'parsers' => [
                \app\modules\parser\services\ParserClorder::class,
                \app\modules\parser\services\ParserEat24::class,
                \app\modules\parser\services\ParserGrubhub::class,
                \app\modules\parser\services\ParserEatStreet::class,
            ]
        ],
        'messageValidator' => [
            'class' => \app\modules\parser\validators\MessageValidator::class
        ],
    ],
    'params' => $params,
    'modules' => [
        'parser' => [
            'class' => 'app\modules\parser\Module',
        ],
    ]
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
