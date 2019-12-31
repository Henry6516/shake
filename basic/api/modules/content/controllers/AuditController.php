<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiAudit;
use app\api\modules\content\models\ApiCase;
use app\models\base\CpicCase;
use app\models\base\CpicDeptAuditors;
use Yii;
use yii\helpers\ArrayHelper;

class AuditController extends ApiController
{
    public function init()
    {
        parent::init();
    }

    /**我的案例/待我审核案例列表/我已审核案例列表
     * @return array
     */
    public function actionIndex()
    {
        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $audit_account_id = $res;
        //$audit_account_id = 269674;

        $apiAudit = new ApiAudit();
        list($myCaseList, $unreadNumber) = $apiAudit->getCaseList($audit_account_id);//我的案例列表
        $auditingCaseList = $apiAudit->getAuditingCaseList($audit_account_id);//待我审核案例列表
        //var_dump($auditingCaseList);exit;
        $auditedCaseLogList = $apiAudit->getAuditedCaseList($audit_account_id);//我已审核案例列表
        $list = [
            'myCaseList' => $myCaseList,
            'auditingCaseList' => $auditingCaseList,
            'auditedCaseLogList' => $auditedCaseLogList,
            'unreadNumber' => $unreadNumber
        ];
        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $list];
    }



    /**审核案例
     * @return array
     */
    public function actionAudit()
    {
        $case_id = Yii::$app->request->get('case_id');
        $status = Yii::$app->request->get('status');
        if(!$case_id) return  ['code' => self::$CODE_ERR, 'msg' => '案例ID不能为空'];
        if(!$status) return  ['code' => self::$CODE_ERR, 'msg' => '状态值不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $audit_account_id = $res;

        //判断案例是否可以审核
        $case = CpicCase::findOne($case_id);
        if($case['status'] != 1) return parent::sendMessageJson('该案例不可审核');

        //判断当前用户是否有权限对案例进行审核
        $dept = CpicDeptAuditors::find()
            ->andWhere(['auditor_account_id' => $audit_account_id, 'disabled' => 0])
            ->asArray()->all();
        $dept_arr = ArrayHelper::getColumn($dept, 'dept_id');
        if(!$dept_arr || $dept_arr && !in_array($case['dept_id'], $dept_arr) && $dept[0]['auditor_account_id'] != $audit_account_id){
            return parent::sendMessageJson('您没有该案例的审核权限');
        }

        //案例审核
        $apiAudit = new ApiAudit();
        $result = $apiAudit->auditCase($audit_account_id, $case_id, $status);
        if($result) return parent::sendMessageJson('案例审核成功', parent::$CODE_SUC);
        return parent::sendMessageJson('案例审核失败');
    }




}
