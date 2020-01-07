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
     * 退出
     */
    public function actionLogout()
    {
        //保存用户当前页到session
        $callback_url = Yii::$app->request->get('callback_url');
        if (!$callback_url) return ['code' => self::$CODE_ERR, 'msg' => '回调地址不能为空'];
        Yii::$app->session->set('callback_url2', $callback_url);

        //清除cookie
        $path = Yii::$app->params['img_host'];
        $host = str_replace('http://', '', $path);

        setrawcookie("account", '', time() - 3600, '/', $host);
        setrawcookie("expire", '', time() - 3600, '/', $host);

        $domain = Yii::$app->params['ssoLoginServer'];#"http://sso2.vmobel.cn";
        $collegecode = Yii::$app->params['sso_collegecode'];
        $app_id = Yii::$app->params['sso_app_id'];
        $app_secret = Yii::$app->params['sso_app_secret'];
        $params = [
            'nonce' => strtolower(Yii::$app->security->generateRandomString(8)),
            'timestamp' => time(),
            'sign_method' => 'md5',
            'format' => 'json',
            'v' => '1.0',
            'partner_id' => 'vmb-sdk-python',
            'method' => 'token',
            'terminal' => $this->isMobile() ? 'wechat' : 'pc',
            'app_id' => $app_id,
            'collegecode' => $collegecode,
            'nexturl' => Yii::$app->params['api_url'] . '/user/login/callback2?t=1',
        ];
        ksort($params);
        $merge_string = 'secret=' . $app_secret;
        foreach ($params as $k => $v) {
            $merge_string .= '&' . strtolower($k) . '=' . strtolower($v);
        }
        $merge_string .= '&secret=' . $app_secret;
        $sign = strtoupper(md5($merge_string));
        $request_url = $domain . ($this->isMobile() ? '/wechat_unbind_by_accountid' : '/logout') . '?sign=' . $sign;
        foreach ($params as $k => $v) {
            if ($k == 'callback_url') {
                $v = urlencode($v);
            }
            $request_url .= '&' . $k . '=' . $v;
        }

        $res = file_get_contents($request_url);
        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $request_url];
    }

    /**
     * 退出返回函数
     * @return array|\yii\web\Response
     */
    public function actionCallback2()
    {
        Yii::$app->session->remove('account_id');
        Yii::$app->session->remove('callback_url');
        Yii::$app->session->destroy();

        $callback = Yii::$app->session->get('callback_url2');
        if (!$callback) {
            $callback = Yii::$app->params['img_host'] . ($this->isMobile() ? '/wap' : '/web');
        }
        return $this->redirect($callback);
    }


    public function actionWechatlogout()
    {
        //销毁session
        Yii::$app->session->remove('account_id');
        Yii::$app->session->remove('callback_url');
        Yii::$app->session->destroy();

        //清除cookie
        $path = Yii::$app->params['img_host'];
        $host = str_replace('http://', '', $path);
        setrawcookie("account", '', time() - 3600, '/', $host);
        setrawcookie("expire", '', time() - 3600, '/', $host);


        $domain = Yii::$app->params['ssoLoginServer'];#"http://sso2.vmobel.cn";
        $collegecode = Yii::$app->params['sso_collegecode'];
        $app_id = Yii::$app->params['sso_app_id'];
        $app_secret = Yii::$app->params['sso_app_secret'];
        $params = [
            'nonce' => strtolower(Yii::$app->security->generateRandomString(8)),
            'timestamp' => time(),
            'sign_method' => 'md5',
            'format' => 'json',
            'v' => '1.0',
            'partner_id' => 'vmb-sdk-python',
            'method' => 'token',
            'terminal' => $this->isMobile() ? 'wechat' : 'pc',
            'app_id' => $app_id,
            'collegecode' => $collegecode,
            'nexturl' => $path . '/wap?t=1',
        ];
        ksort($params);
        $merge_string = 'secret=' . $app_secret;
        foreach ($params as $k => $v) {
            $merge_string .= '&' . strtolower($k) . '=' . strtolower($v);
        }
        $merge_string .= '&secret=' . $app_secret;
        $sign = strtoupper(md5($merge_string));
        $request_url = $domain . ($this->isMobile() ? '/wechat_unbind_by_accountid' : '/logout') . '?sign=' . $sign;
        foreach ($params as $k => $v) {
            if ($k == 'callback_url') {
                $v = urlencode($v);
            }
            $request_url .= '&' . $k . '=' . $v;
        }

        //$res = file_get_contents($request_url);
        //echo $request_url;exit;
//        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $request_url];
        $this->redirect($request_url);
    }


    public function curl_get($durl)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $durl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }


}
