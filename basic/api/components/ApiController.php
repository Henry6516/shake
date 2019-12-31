<?php
/**
 * 控制器基础类，所有控制器均需继承此类
 * @author chenfenghua <843958575@qq.com>
 * version v2.0
 */

namespace app\api\components;

use Yii;
use yii\web\Response;

class ApiController extends \yii\rest\Controller
{
    public static $CODE_SUC = "200";
    public static $CODE_ERR = "400";
    public static $CODE_GRANT_FAILED = "403";
    const LIMIT = 10;
    public $data = [];

    private $_user_id;


    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $result = parent::afterAction($action, $result);
        $data['code'] = isset($result['code']) ? $result['code'] : 200;
        $data['message'] = isset($result['message']) ? $result['message'] : 'success';
        if ($result === null) {
            $result = [];
        }
        if ($data['code'] === 200 && (is_array($result))) {
            $data['data'] = $result;
        }
        if ($result === false) {
            $data['code'] = 400;
            $data['message'] = 'error';
        }
        return $this->serializeData($data);
    }


    //获取当前用户ID
    public function getUserId()
    {
        return $this->_user_id;
    }


    /**
     * 验证用户权限
     * @return boolean
     */
    public static function verifyAuth()
    {

        //判断cookie
        if (isset($_COOKIE['account']) && $_COOKIE['account']) {
            $res = Des::decrypt($_COOKIE['account']);
            if ($res && is_numeric($res)) {
                if(isset($_COOKIE['expire']) && $_COOKIE['expire']){
                    return $_COOKIE['expire'] >= time() ? $res : false;
                }
                return $res;
            }
            return false;
        }
        return false;
    }

    /**
     * 创建qrcode
     *
     * @param $case_id
     * @return array
     */
    public static function createQrCode($case_id)
    {

        $code = '9' . str_pad($case_id, 5, 0, STR_PAD_LEFT);
        //var_dump($code);exit;
        $text = Yii::$app->params['img_host'] . '/wap/preview.html?case_id='.$case_id;
        $path_model = 'qrcode';
        $file_name = $code . '.png';

        $path_arr = UploadFile::savePath($path_model);

        QrCode::png($text, $path_arr['path'] . $file_name, Enum::QR_ECLEVEL_H, 18, 2);

        return Yii::$app->params['img_host'] . $path_arr['file'] . '/' . $file_name;
    }

    /**
     * 判断用户是手机端/PC端登陆
     * @return boolean
     */
    public static function isMobile()
    {
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])) return true;

        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if (isset ($_SERVER['HTTP_VIA'])) return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;// 找不到为flase,否则为true
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset ($_SERVER['HTTP_USER_AGENT'])) {
            $clientKeywords = array('nokia',
                'sony',
                'ericsson',
                'mot',
                'samsung',
                'htc',
                'sgh',
                'lg',
                'sharp',
                'sie-',
                'philips',
                'panasonic',
                'alcatel',
                'lenovo',
                'iphone',
                'ipod',
                'blackberry',
                'meizu',
                'android',
                'netfront',
                'symbian',
                'ucweb',
                'windowsce',
                'palm',
                'operamini',
                'operamobi',
                'openwave',
                'nexusone',
                'cldc',
                'midp',
                'wap',
                'mobile'
            );
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientKeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }
        // 协议法，因为有可能不准确，放到最后判断
        if (isset ($_SERVER['HTTP_ACCEPT'])) {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }

}