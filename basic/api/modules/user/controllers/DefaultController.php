<?php

namespace app\api\modules\user\controllers;


use app\api\components\ApiController;
use app\api\modules\user\models\ApiUser;
use Yii;

class DefaultController extends ApiController
{
    public function init()
    {
        parent::init();
    }


    /**
     * 获取登陆用户信息
     */
    public function actionIndex()
    {

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $res = ApiUser::getUserInfo($user_account_id);
        if ($res) return parent::sendMessageJson('用户信息获取成功', parent::$CODE_SUC, $res);
        return parent::sendMessageJson('用户信息获取失败');
    }


    /**
     * 获取案例分享信息
     */
    public function actionShare()
    {

        //验证用户是否登录
        /*$res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;*/


        //设置时区
        //date_default_timezone_set('Asia/Shanghai');

        $APP_ID = Yii::$app->params['wechat_app_id'];
        $APP_SECRET = Yii::$app->params['wechat_app_secret'];


        $url = Yii::$app->request->get('url');

        //$keyArr = Yii::$app->session['caseDetail'];
        //启动SESSION
        session_set_cookie_params(7200); //保存两小时
        session_start();
        $keyArr = isset($_SESSION['caseDetail'])?$_SESSION['caseDetail']:[];
        //判断session中是否有值/是否过期
        if ($keyArr && (time() < $keyArr['timestamp'] + 7200)) {
            //获取jsapi_ticket
            $ticket_res = json_decode(file_get_contents('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $keyArr['access_token'] . '&type=jsapi'));

            if ($ticket_res->errcode == 0) {
                $jsapi_ticket = $ticket_res->ticket;
            } else {
                $jsapi_ticket = $ticket_res->errmsg;;
            }

            //生成签名
            $nonceStr = Yii::$app->params['nonceStr'];
            $timestamp = time();


            $str = 'jsapi_ticket=' . $jsapi_ticket . '&noncestr=' . $nonceStr . '&timestamp=' . $timestamp . '&url=' . $url;
            $signature = sha1($str);


            $list = [
                'appId' => $APP_ID,
                'nonceStr' => $nonceStr,
                'timestamp' => $timestamp,
                'access_token' => $keyArr['access_token'],
                'jsapi_ticket' => $jsapi_ticket,
                'signature' => $signature,
            ];
        } else {
            //获取access_token
            $token_res = file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $APP_ID . '&secret=' . $APP_SECRET);
            $access_token = json_decode($token_res)->access_token;

            //获取jsapi_ticket
            $ticket_res = json_decode(file_get_contents('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $access_token . '&type=jsapi'));

            if ($ticket_res->errcode == 0) {
                $jsapi_ticket = $ticket_res->ticket;
            } else {
                $jsapi_ticket = $ticket_res->errmsg;;
            }

            //生成签名
            $nonceStr = Yii::$app->params['nonceStr'];
            $timestamp = time();


            $str = 'jsapi_ticket=' . $jsapi_ticket . '&noncestr=' . $nonceStr . '&timestamp=' . $timestamp . '&url=' . $url;
            $signature = sha1($str);

            //session缓存数据
            $_SESSION['caseDetail'] = [
                'timestamp' => $timestamp,
                'access_token' => $access_token,
            ];

            $list = [
                'appId' => $APP_ID,
                'nonceStr' => $nonceStr,
                'timestamp' => $timestamp,
                'access_token' => $access_token,
                'jsapi_ticket' => $jsapi_ticket,
                'signature' => $signature,
            ];
        }
        return parent::sendMessageJson('successful', parent::$CODE_SUC, $list);

    }

    public function getSign(){

    }


}
