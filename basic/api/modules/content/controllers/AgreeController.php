<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiAgree;
use app\models\base\CpicCase;
use app\models\base\CpicCaseAgree;
use app\models\UploadFile;
use dosamigos\qrcode\lib\Enum;
use dosamigos\qrcode\QrCode;
use Yii;

class AgreeController extends ApiController
{
    /**
     * 点赞/取消点赞接口
     * @return array
     */
    public function actionAgree()
    {
        $case_id = Yii::$app->request->get('case_id');
        $status = Yii::$app->request->get('status');
        if ($case_id === null) return parent::sendMessageJson('案例ID不能为空');
        if ($status != 0 && $status != 1) return parent::sendMessageJson('点赞参数错误');

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        //判断案例是否可以点赞
        $case = CpicCase::findOne($case_id);
        if($case['status'] != 3 && $case['status'] != 4) ['code'=>parent::$CODE_ERR, 'data'=>[], 'msg' => '当前案例不可点赞'];

        //判断用户当前点赞状态
        $caseAgreeLog = CpicCaseAgree::findOne(['user_account_id' => $user_account_id, 'case_id' => $case_id]);

        if($caseAgreeLog && $caseAgreeLog['status'] == $status) {
            if($status) return ['code'=>parent::$CODE_ERR, 'data'=>[], 'msg' => '您已经对此案例点过赞，请勿重复点赞'];
            return ['code'=>parent::$CODE_ERR, 'data'=>[], 'msg' => '您还未对此案例点过赞'];
        }


        $apiAgree = new ApiAgree();
        $result = $apiAgree->doAgree($user_account_id, $case_id, $status);
        //var_dump($result);exit;

        if($status){
            $msg = '点赞';
        }else{
            $msg = '取消点赞';
        }


        if($result) return ['code'=>parent::$CODE_SUC, 'data'=>[], 'msg' => $msg.'成功'];
        return ['code'=>parent::$CODE_ERR, 'data'=>[], 'msg' => $msg.'失败'];
    }
}
