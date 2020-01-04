<?php
$params = require(__DIR__ . '/../config/params.php');

if (YII_ENV == 'dev') {
    //$params['web_url'] = 'http://localhost/MyProject/cpic_case/basic/wap';
    $params['api_url'] = 'http://api.shake.com';
    //$params['img_host'] = 'http://localhost/MyProject/cpic_case/basic';
} else {
    //$params['web_url'] = 'http://cpic.dev/wap';
    $params['api_url'] = 'http://api.shake.com';
    //$params['img_host'] = 'http://cpic.dev';
}

$config = [
    'id' => 'basic',
    'language'=>'zh-CN',
    'timeZone' => 'Asia/Shanghai',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'defaultRoute' => 'user/login/login',

    'components' => [
        'request' => [
            'cookieValidationKey' => '9oVZ7xEIgyyBlpE5_Fp0WxDTCjahLrP1',
            'enableCsrfValidation' => false,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'suffix' => false,//后缀
            'enableStrictParsing'=>false,//不要求网址严格匹配，则不需要输入rules
            //'rules' => require(__DIR__ . '/../api/config/rule.php'),
        ],
        'errorHandler' => [
//            'errorAction' => 'content/default/index',
            'errorAction' => 'site/error',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => false,
//            'enableSession' => false
//            'identityCookie' => ['name' => '_identity-backend', 'httpOnly' => true],
//            'authTimeout' => 24*3600
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => require(__DIR__ . '/db.php'),
    ],

    'params' => $params,
    'modules' => [
        'user' => [
            'class' => 'app\api\modules\user\Module',
        ],
    ],
];

return $config;
