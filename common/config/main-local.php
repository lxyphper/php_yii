<?php

return [
    'components' => [
        // 'db'     => [
        //     'class'    => 'yii\db\Connection',
        //     'dsn'      => 'mysql:host=rm-bp128pqf5i369y123oo.mysql.rds.aliyuncs.com;dbname=dauyan',
        //     'username' => 'dauyan_user',
        //     'password' => 'jPGWJJfAMf6jQ6Da',
        //     'charset'  => 'utf8mb4',
        //     // 连接超时和稳定性配置
        //     'attributes' => [
        //         PDO::ATTR_TIMEOUT => 30, // 连接超时 30 秒
        //         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        //     ],
        //     'enableSchemaCache' => true, // 启用表结构缓存
        //     'schemaCacheDuration' => 3600, // 缓存 1 小时
        // ],
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=rm-bp10uv3ty30e60qamuo.mysql.rds.aliyuncs.com;dbname=dauyan',
            'username' => 'dauyan_user',
            'password' => 'PpCwwY7aS48Utckg',
            'charset' => 'utf8mb4',
            // 连接超时和稳定性配置
            'attributes' => [
                PDO::ATTR_TIMEOUT => 30, // 连接超时 30 秒
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
            'enableSchemaCache' => true, // 启用表结构缓存
            'schemaCacheDuration' => 3600, // 缓存 1 小时
        ],
        'mailer' => [
            'class'            => 'yii\swiftmailer\Mailer',
            'viewPath'         => '@common/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'gii'    => [
            'class'      => 'yii\gii\Module',
            'allowedIPs' => ['127.0.0.1', '::1', '192.168.0.*', '*'], // 按需调整这里
        ],
    ],
];
