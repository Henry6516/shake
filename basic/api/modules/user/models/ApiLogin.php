<?php
namespace app\api\modules\user\models;

use app\api\components\ApiModel;
use app\models\Game;

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
        if ($time > strtotime($game['endTime'])){
            $status = '已结束';
            $seconds = 0;
        }elseif($time > strtotime($game['startTime']) + $game['ready']){
            $status = '已开始';
            $seconds = strtotime($game['startTime']) + $game['ready'] + $game['last'] - $time;
        }elseif($time > strtotime($game['startTime'])){
            $status = '准备中';
            $seconds = strtotime($game['startTime']) + $game['ready'] - $time;
        }else{
            $status = '未开始';
            $seconds = 0;
        }
        //return 'status='.$status.' and seconds='.$seconds;
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
        $client = stream_socket_client($push_api_url, $errno, $errmsg, 1);
        $data = 'begin';
        fwrite($client, (string)$data."\n");
        return fread($client, 8192);
    }


}