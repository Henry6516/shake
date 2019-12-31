<?php

namespace app\api\modules\user\controllers;


use app\api\components\ApiController;
use app\api\components\Auth;
use app\api\components\Des;
use Yii;
use yii\web\Cookie;

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
        //保存用户当前页到session
        $callback_url = Yii::$app->request->get('callback_url');
        if (!$callback_url) return ['code' => self::$CODE_ERR, 'msg' => '回调地址不能为空'];
        //判断cookie是否登陆
        $res = self::verifyAuth();
        /*if ($res) {
            //var_dump($res);exit;
            return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $callback_url];
        } else {*/
            Yii::$app->session->set('callback_url', $callback_url);
            $this->callback_url = $callback_url;

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
                'method' => 'km_sso',
                'terminal' => $this->isMobile() ? 'wechat' : 'pc',
                'app_id' => $app_id,
                'collegecode' => $collegecode,
                'callback_url' => Yii::$app->params['api_url'] . '/user/login/callback?t=1',
            ];
            ksort($params);
            $merge_string = 'secret=' . $app_secret;
            foreach ($params as $k => $v) {
                $merge_string .= '&' . strtolower($k) . '=' . strtolower($v);
            }
            $merge_string .= '&secret=' . $app_secret;
            $sign = strtoupper(md5($merge_string));
            $request_url = $domain . '/login?sign=' . $sign;
            foreach ($params as $k => $v) {
                if ($k == 'callback_url') {
                    $v = urlencode($v);
                }
                $request_url .= '&' . $k . '=' . $v;
            }
            //var_dump($request_url);exit;
            return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $request_url];
        //}
    }

    /**
     * 登录返回函数
     * @return array|\yii\web\Response
     */
    public function actionCallback()
    {
        $sso_token = Yii::$app->request->get('sso_token');
        if (!$sso_token) return ['code' => self::$CODE_ERR, 'msg' => 'Token不能为空'];

        $domain = Yii::$app->params['ssoLoginServer'];;
        $request_url = $domain . '/verify_login?access_token=' . $sso_token;
        $result = json_decode(file_get_contents($request_url), true);

        $callback_url = Yii::$app->session->get('callback_url');

        //保存到cookie
        $value = '';
        $expire = time() + 3600 * 24 * 30;
        $path = Yii::$app->params['img_host'];
        $host = str_replace('http://','',$path);
        if (isset($result['code']) && $result['code'] == 200) {
            $value = Des::encrypt($result['vmb_response']['accountid']);
        } else {
            return $this->redirect($callback_url);
        }

        setrawcookie("account", $value, $expire, '/', $host);
        setrawcookie("expire", $expire, $expire, '/', $host);

        //提取登陆后要跳转的路径
        $callback_url = $callback_url ? $callback_url : $this->callback_url;
        if (!$callback_url) {
            $callback_url = Yii::$app->params['img_host'] . ($this->isMobile() ? '/wap' : '/web');
        }
        return $this->redirect($callback_url);
    }

    /**退出
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


}
