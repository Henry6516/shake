<?php
header("Access-Control-Allow-Origin: *");
// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', '');
ini_set("display_errors","On");
error_reporting(2047);
require(__DIR__ . '/../vendor/autoload.php');

require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

$config = require(__DIR__ . '/../config/api.php');

(new yii\web\Application($config))->run();
