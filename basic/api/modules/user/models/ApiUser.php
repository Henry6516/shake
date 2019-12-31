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
use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class ApiUser extends ApiModel
{

    /**
     * 获取用户信息
     * @param $user_account_id
     * @return array|boolean
     */
    public static function getUserInfo($user_account_id)
    {
        $token = self::getToken();
        $url =  Yii::$app->params['api_demain']. Yii::$app->params['api_uri_info']."?access_token=".$token."&accountid=".$user_account_id;
        //var_dump($url);exit;
        if ($token){
            $info =  json_decode(file_get_contents($url));
            $result = ArrayHelper::toArray($info);
            //var_dump($result);exit;
            if(isset($result['code']) && $result['code'] == 200){
                return $result['vmb_response']['data'];
            }else{
                return false;
            }
        }else{
            return false;
        }

    }

    /**
     * 获取用户信息
     * @param $user_account_id
     * @return array|boolean
     */
    public static function getUserInfos($user_account_ids)
    {
        $retVal = [];
        if (!is_array($user_account_ids) or empty($user_account_ids)){
            return $retVal;
        }

        $token = self::getToken();

        $user_account_str = implode(",",$user_account_ids);
        $url =  Yii::$app->params['api_demain']. Yii::$app->params['api_uri_infos']."?access_token=".$token."&accountid=".$user_account_str;
        if ($token){
            $info =  json_decode(file_get_contents($url));

            $result = ArrayHelper::toArray($info);
            if(isset($result['code']) && $result['code'] == 200){
                return $result['vmb_response']['data'];
            }else{
                return $retVal;
            }
        }else{
            return $retVal;
        }
        return $retVal;
    }

    /**
     * 获取Token
     * @return bool
     */
    public static function getToken(){
        $domain = Yii::$app->params['ssoLoginServer'];#"http://sso2.vmobel.cn";
        $collegecode = Yii::$app->params['sso_collegecode'];
        $app_id =Yii::$app->params['api_app_id'];
        $app_secret = Yii::$app->params['api_app_secret'];
        $api_domanin = Yii::$app->params['api_demain'];
        $params = [
            'nonce' => strtolower(Yii::$app->security->generateRandomString(8)),
            'timestamp' => time(),
            'sign_method' => 'md5',
            'format' => 'json',
            'v' => '1.0',
            'partner_id' => 'vmb-sdk-python',
            'method' => 'token',
            'terminal' => ApiController::isMobile()?'wechat':'pc',
            'app_id' => $app_id,
            'collegecode' => $collegecode,
            'nexturl' => 'http://localhost/site/callback',
        ];
        ksort($params);
        $merge_string = 'secret='.$app_secret;
        foreach ($params as $k=>$v){
            $merge_string .= '&'.strtolower($k).'='.strtolower($v);
        }
        $merge_string .= '&secret='.$app_secret;
        $sign = strtoupper(md5($merge_string));
        $request_url =$api_domanin.'/token/get/?sign='.$sign;
        foreach ($params as $k=>$v){
            if($k == 'callback_url'){
                $v = urlencode($v);
            }
            $request_url .= '&'.$k.'='.$v;
        }
        $result = @file_get_contents($request_url);
        $result = json_decode($result,true);
        if($result && isset($result["access_token"] )){
            return $result["access_token"];
        }
        return false;
    }



}