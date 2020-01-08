<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-11-29
 * Time: 16:32
 * Author: henry
 */

/**
 * @name WorkermanController.php
 * @desc PhpStorm.
 * @author: Create by henry
 * @since: Created on 2019-11-29 16:32
 */


namespace app\commands;
use app\api\modules\user\models\ApiLogin;
use app\models\Game;
use app\models\User;
use app\models\UserInfo;
use Workerman\Lib\Timer;
use Yii;
use Workerman\Worker;
use yii\console\Controller;
use yii\helpers\Console;

class WorkermanController extends Controller
{
    public $send;
    public $daemon;
    public $gracefully;

    public  $websocket;

    // 这里不需要设置，会读取配置文件中的配置
    public $config = [];
    private $ip = '0.0.0.0';
    private $port = '2346';

    public function options($actionID)
    {
        return ['send', 'daemon', 'gracefully'];
    }


    public function optionAliases()
    {
        return [
            's' => 'send',
            'd' => 'daemon',
            'g' => 'gracefully',
        ];
    }

    public function actionIndex()
    {
        $this->worker();

    }

    public function sendMessage($message){
        $count = 0;
        foreach($this->websocket->connections as $connection)
        {
            $res = $connection->send($message);
            if($res == 1){
                $count++;
            }
        }
        //记录日志
        file_put_contents(__DIR__ . '/../workerman.log','向 '.$count." 个连接发送了消息!\n");
        return true;
    }

    public function worker()
    {
        if (PHP_OS === 'WINNT') {
          $this->initWorker();
          Worker::runAll();
        }
        else {

            if ('start' == $this->send) {
                try {
                    $this->start($this->daemon);
                } catch (\Exception $e) {
                    $this->stderr($e->getMessage() . "\n", Console::FG_RED);
                }
            } else if ('stop' == $this->send) {
                $this->stop();
            } else if ('restart' == $this->send) {
                $this->restart();
            } else if ('reload' == $this->send) {
                $this->reload();
            } else if ('status' == $this->send) {
                $this->status();
            } else if ('connections' == $this->send) {
                $this->connections();
            }
        }
    }

    public function initWorker()
    {
        $ip = isset($this->config['ip']) ? $this->config['ip'] : $this->ip;
        $port = isset($this->config['port']) ? $this->config['port'] : $this->port;
        if(PHP_OS === 'WINNT'){
            $this->websocket = new Worker("websocket://{$ip}:{$port}");
        }else{
            // 更多ssl选项请参考手册 http://php.net/manual/zh/context.ssl.php
            $context = [
                'ssl' => [
                    // 请使用绝对路径
                    'local_cert'                 => '/usr/local/ssl/3283212_shake.shyouranindustry.com.pem', // 也可以是crt文件
                    'local_pk'                   => '/usr/local/ssl/3283212_shake.shyouranindustry.com.key',
                    'verify_peer'                => false,
                    // 'allow_self_signed' => true, //如果是自签名证书需要开启此选项
                ]
            ];
            $this->websocket = new Worker("websocket://{$ip}:{$port}", $context);
            $this->websocket->transport = 'ssl';
        }
        $this->websocket->onWorkerStart = function($worker) {
            // 定时，每10秒一次
            Timer::add(1, function () {
                // 遍历当前进程所有的客户端连接，发送当前服务器的时间
                $data = ApiLogin::getGameTimeData();
                //$this->sendMessage($data);
                $this->sendMessage(json_encode($data));
            });

        };

        // 4 processes

        /*if(PHP_OS !== 'WINNT') {
            $this->websocket->count = 4;
        }*/

        // Emitted when new connection come
        $this->websocket->onConnect = function ($connection) {
            echo "Congratulations, connect server successful! \n";
            $data = ApiLogin::getGameTimeData();
            $connection->send(json_encode($data));
        };

        // Emitted when data received
        $this->websocket->onMessage = function ($connection, $data) {
            // Send hello
            if($data === 'new'){  //指定请求，会发送指定数据
                $data = '123';
            }
            $connection->send($data);
        };

        // Emitted when connection closed
        $this->websocket->onClose = function ($connection) {
            //array_diff($this->session, $connection);
            $connection->send("Connection closed. \n");
            $connection->close();
            echo "Connection closed. \n";
        };
    }

    /**
     * workman websocket start
     */
    public function start()
    {
        $this->initWorker();
        // 重置参数以匹配Worker
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'start';
        if ($this->daemon) {
            $argv[2] = '-d';
        }
        //var_dump($argv);exit;
        // Run worker
        Worker::runAll();
    }

    /**
     * workman websocket restart
     */
    public function restart()
    {
        $this->initWorker();
        // 重置参数以匹配Worker
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'restart';
        if ($this->daemon) {
            $argv[2] = '-d';
        }

        if ($this->gracefully) {
            $argv[2] = '-g';
        }

        // Run worker
        Worker::runAll();
    }

    /**
     * workman websocket stop
     */
    public function stop()
    {
        $this->initWorker();
        // 重置参数以匹配Worker
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'stop';
        if ($this->gracefully) {
            $argv[2] = '-g';
        }

        // Run worker
        Worker::runAll();
    }

    /**
     * workman websocket reload
     */
    public function reload()
    {
        $this->initWorker();
        // 重置参数以匹配Worker
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'reload';
        if ($this->gracefully) {
            $argv[2] = '-g';
        }

        // Run worker
        Worker::runAll();
    }

    /**
     * workman websocket status
     */
    public function status()
    {
        $this->initWorker();
        // 重置参数以匹配Worker
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'status';
        if ($this->daemon) {
            $argv[2] = '-d';
        }

        // Run worker
        Worker::runAll();
    }

    /**
     * workman websocket connections
     */
    public function connections()
    {
        $this->initWorker();
        // 重置参数以匹配Worker
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'connections';

        // Run worker
        Worker::runAll();
    }
}