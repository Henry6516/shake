<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/7/19
 * Time: 17:27
 */

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiScore;
use app\api\modules\content\models\ApiWork_z;
use app\models\base\CpicCase;
use Yii;

class ScoreController extends ApiController
{

    /**
     * 获取评分转派人员列表
     * @return array
     */
    public function actionIndex()
    {
        $case_id = Yii::$app->request->get('case_id');
        if ($case_id === null) return parent::sendMessageJson('案例ID不能为空');

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269660;

        $apiScore = new ApiScore();
        $list = $apiScore->getCaseRaterChangeList($user_account_id, $case_id);

        return ['code' => parent::$CODE_SUC, 'data' => $list, 'msg' => 'successful'];
    }


    /**
     * 评分转派
     * @return array
     */
    public function actionChange()
    {
        $case_id = Yii::$app->request->get('case_id');
        $new_rater_account_id = Yii::$app->request->get('new_rater_account_id');
        if ($case_id === null) return parent::sendMessageJson('案例ID不能为空');
        if ($new_rater_account_id === null) return parent::sendMessageJson('被转派评委ID不能为空');

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269660;

        //判断用户是否可转派案例
        $case = CpicCase::findOne($case_id);
        if($case['status'] != 3) return parent::sendMessageJson('该案例不可转派');

        $status = ApiWork_z::checkCaseIsChange($case, $user_account_id);
        if(!$status) return parent::sendMessageJson('您没有转派该案例的权限');

        $apiScore = new ApiScore();
        $result = $apiScore->caseRaterChange($user_account_id, $new_rater_account_id, $case_id);
        if($result) return parent::sendMessageJson('案例转派成功', parent::$CODE_SUC);
        return parent::sendMessageJson('案例转派失败');
    }

    /**
     * 案例评分
     * @return array
     */
    public function actionScore()
    {
        $case_id = Yii::$app->request->get('case_id');
        $part_1 = Yii::$app->request->get('part_1');
        $part_2 = Yii::$app->request->get('part_2');
        $part_3 = Yii::$app->request->get('part_3');
        if ($case_id === null) return parent::sendMessageJson('案例ID不能为空');
        if ($part_1 === null) return parent::sendMessageJson('案例选题评分不能为空');
        if ($part_1 > 40) return parent::sendMessageJson('案例选题评分不能超过40分');
        if ($part_2 === null) return parent::sendMessageJson('案例萃取评分不能为空');
        if ($part_2 > 30) return parent::sendMessageJson('案例萃取评分不能超过30分');
        if ($part_3 === null) return parent::sendMessageJson('案例表达评分不能为空');
        if ($part_3 > 30) return parent::sendMessageJson('案例表达评分不能超过30分');

        //验证用户是否登录
        $res = self::verifyAuth();
        //if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269674;

        //判断案例是否可评分
        $case = CpicCase::findOne($case_id);
        if($case['status'] != 3) return parent::sendMessageJson('该案例不可评分');

        $apiScore = new ApiScore();
        //判断用户是否有权限评分
        $flag = $apiScore->checkScorePermissions($user_account_id, $case_id);
        if(!$flag) return parent::sendMessageJson('您没有该权限');

        //判断案例（多人评分）是否可评分
        $flag2 = $apiScore->checkMoreScorePermissions($user_account_id, $case_id);
        if(!$flag2) return parent::sendMessageJson('您已经为该案例打过分，请勿重复打分');

        $params = [
            'part_1' => $part_1,
            'part_2' => $part_2,
            'part_3' => $part_3,
        ];
        $result = $apiScore->doCaseScore($user_account_id, $case_id, $params);
        //var_dump($result);exit;
        if($result) return parent::sendMessageJson('案例评分成功', parent::$CODE_SUC);
        return parent::sendMessageJson('案例评分失败');
    }

    /**
     * 案例打回
     * @return array
     */
    public function actionRefuse()
    {
        $case_id = Yii::$app->request->get('case_id');
        $reason = Yii::$app->request->get('reason');
        if ($case_id === null) return parent::sendMessageJson('案例ID不能为空');
        if ($reason === null) return parent::sendMessageJson('打回理由不能为空');

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269660;

        //判断案例是否可打回
        $case = CpicCase::findOne($case_id);
        if($case['status'] != 3) return parent::sendMessageJson('该案例不可打回');

        $apiScore = new ApiScore();
        //判断用户是否有权限打回
        $flag = $apiScore->checkScorePermissions($user_account_id, $case_id);
        if(!$flag) return parent::sendMessageJson('您没有该权限');

        //判断案例（多人评分）是否可打回/评分
        $flag2 = $apiScore->checkMoreScorePermissions($user_account_id, $case_id);
        if(!$flag2) return parent::sendMessageJson('您已经为该案例打过分，请勿重复打分');

        $result = $apiScore->doCaseRefuse($user_account_id, $case_id, $reason);
        if($result) return parent::sendMessageJson('案例打回成功', parent::$CODE_SUC);
        return parent::sendMessageJson('案例打回失败');
    }



}