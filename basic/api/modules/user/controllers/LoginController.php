<?php

namespace app\api\modules\user\controllers;


use app\api\components\ApiController;
use app\api\modules\user\models\ApiLogin;
use app\models\Game;
use app\models\UserInfo;
use Yii;

class LoginController extends ApiController
{
    public function init()
    {
        parent::init();
    }

    public $callback_url;

    /**
     * login
     */
    public function actionLogin()
    {
        $app_id = Yii::$app->params['appid'];
        $app_secret = Yii::$app->params['secret'];
        $codeStr = file_get_contents('php://input');
        $codeArr = json_decode($codeStr);
        $code = $codeArr->code;

        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$app_id.'&secret='.$app_secret.'&js_code='.$code.'&grant_type=authorization_code';
        $res = file_get_contents($url);
        //$res = $this->curl_get($url);
        //var_dump($res);exit;
        $data = json_decode($res);
        if(array_key_exists('errcode', $data) && $data->errcode){

            return ['code' => $data->errcode, 'message' => $data->errmsg];
        }else{
            $user = UserInfo::findOne(['openid' => $data->openid]);
            if(!$user){
                $user = new  UserInfo();
            }
            $user->openid = $data->openid;
            if(isset($data->session_key) && $data->session_key) $user->session_key = $data->session_key;
            if(isset($data->unionid) && $data->unionid) $user->unionid = $data->unionid;
            $user->save();
            return ['code' => 200, 'message' => 'successful', 'data' => $user];
        }
    }

    public function actionSaveInfo()
    {
        $condition = file_get_contents('php://input');
        //var_dump($condition);exit;
        $condArr = json_decode($condition, true);
        $user = UserInfo::findOne(['openid' => $condArr['openid']]);
        $user->attributes = $condArr;
        $user->save();
        return $user;
    }

    /**
     * 开始
     * Date: 2020-01-03 9:27
     * Author: henry
     * @throws \yii\db\Exception
     */
    public function actionBegin(){
        $game = Game::findOne(1);
        $time = time();
        if ($time >= strtotime($game['startTime']) && $time <= strtotime($game['endTime'])){
            return [
                'code' => 400,
                'message' => '你有当前正在进行的游戏！',
            ];
        }
        //现有用户所有分数清零
        Yii::$app->db->createCommand()->update('userInfo',['num' => 0])->execute();
        if(!$game) $game = new Game();
        $startTime = time();
        $endTime = $startTime + $game['ready'] + $game['last'];
        $game->startTime = date('Y-m-d H:i:s', $startTime);
        $game->endTime = date('Y-m-d H:i:s', $endTime);
        $game->save();
        return ApiLogin::getGameTimeData();
    }





    /**
     * 单个用户计数
     * Date: 2019-12-31 11:56
     * Author: henry
     */
    public function actionCount()
    {
        //判断活动是否开始
        $game = Game::findOne(1);
        $time = time();
        if ($time < strtotime($game['startTime'])){
            return [
                'code' => 400,
                'message' => '游戏还未开始！',
            ];
        }elseif($time < strtotime($game['startTime']) + $game['ready']){
            return [
                'code' => 400,
                'message' => '游戏正在准备中！',
            ];
        }elseif($time > strtotime($game['endTime'])){
            return [
                'code' => 400,
                'message' => '游戏已经结束！',
            ];
        }else{
            $condition = file_get_contents('php://input');
            $condArr = json_decode($condition, true);
            if(isset($condArr['num']) && $condArr['num']) {
                $num = $condArr['num'];
            }else{
                $num = 1;
            }
            $user = UserInfo::findOne(['openid' => $condArr['openid']]);
            $user->num += $num;
            $user->save();
            return $user;
        }

    }


    /**
     * 统计结果
     * Date: 2020-01-07 17:00
     * Author: henry
     * @return array
     */
    public function actionList(){
        $game = ApiLogin::getGameTimeData();
        $user = UserInfo::find()
            ->select('nickName,avatar,num')
            ->orderBy('num DESC')
            ->limit(10)
            ->asArray()->all();
        return [
            'userInfo' => $user,
            'gameInfo' => $game,
        ];
    }





}
