<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiLog;
use Yii;

class LogController extends ApiController
{
    public function init()
    {
        parent::init();
    }

    /**获取案例提交/审核日志
     * @return array
     */
    public function actionIndex()
    {
        $case_id = Yii::$app->request->get('case_id');
        if(!$case_id) return  ['code' => self::$CODE_ERR, 'msg' => '案例ID不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiLog = new ApiLog();
        $caseLogList = $apiLog->getCaseLog($case_id, $user_account_id);

        //获取案例二维码
        $qrcode = self::createQrCode($case_id);

        //合并数组
        $caseLogList = array_merge($caseLogList, ['qrcode' => $qrcode]);
        //var_dump($caseList);exit;
        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $caseLogList];
    }




}
