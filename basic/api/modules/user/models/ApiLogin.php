<?php
namespace app\api\modules\user\models;

use app\api\components\ApiController;
use app\api\components\ApiModel;
use app\models\base\CpicCase;
use app\models\base\CpicCaseAuditorLog;
use app\models\base\CpicCaseFiles;
use app\models\base\CpicCaseLabel;
use app\models\base\CpicCaseRaterLog;
use app\models\base\CpicCaseScore;
use app\models\base\CpicCaseUserLog;
use app\models\base\CpicDept;
use app\models\base\CpicDeptAuditors;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserLine;
use app\models\Game;
use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class ApiLogin extends ApiModel
{

    /**
     * 获取游戏状态信息
     * @param $user_account_id
     * Date: 2020-01-03 17:18
     * Author: henry
     * @return array
     */
    public static function getGameTimeData()
    {
        $game = Game::findOne(1);
        $time = time();
        if ($time < strtotime($game['startTime'])){
            $status = '未开始';
            $seconds = 0;
        }elseif($time < strtotime($game['startTime']) + $game['ready']){
            $status = '准备中';
            $seconds = $game['startTime'] + $game['ready'] - $time;
        }elseif($time > strtotime($game['endTime'])){
            $status = '已开始';
            $seconds = $game['startTime'] + $game['ready'] + $game['last'] - $time;
        }else{
            $status = '已结束';
            $seconds = 0;
        }
        return [
            'status' => $status,
            'seconds' => $seconds,
        ];
    }

    /**
     * 发送消息
     * Date: 2020-01-03 17:14
     * Author: henry
     * @return bool|string
     */
    public static function pushData(){
        // 推送的url地址，使用自己的服务器地址
        $push_api_url = "tcp://127.0.0.1:5678/";
        $info = self::getGameTimeData();
        $client = stream_socket_client($push_api_url, $errno, $errmsg, 1);
        $data = json_encode($info);
        fwrite($client, (string)$data."\n");
        return fread($client, 8192);
    }


}