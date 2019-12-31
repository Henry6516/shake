<?php

namespace app\api\modules\content\models;

use app\api\components\ApiModel;
use app\api\modules\user\models\ApiUser;
use app\models\base\CpicCase;
use app\models\base\CpicCaseAuditorLog;
use app\models\base\CpicCaseFiles;
use app\models\base\CpicCaseLabel;
use app\models\base\CpicCaseRaterLog;
use app\models\base\CpicCaseScore;
use app\models\base\CpicCaseUserLog;
use app\models\base\CpicDept;
use app\models\base\CpicDeptAuditors;
use app\models\base\CpicScoreMoreLog;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserLine;
use app\models\base\CpicUserRaterMore;
use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class ApiLog extends ApiModel
{

    /**
     * 案例日志
     * @param $case_id
     * @param $user_account_id
     * @return array
     */
    public function getCaseLog($case_id, $user_account_id)
    {
        //分别获取案例作者/初审/评委的用户ID
        $case = CpicCase::findOne($case_id);
        $case_user_id = $case['user_account_id'];
        //判断案例作者是不是分公司部门负责人
        $userDept = CpicUserDept::findOne(['user_account_id' => $case['user_account_id']]);
        //var_dump($userDept);exit;
        if(strpos($userDept['job'], '分公司部门负责人') !== false){
            $case_auditor_id = CpicDeptAuditors::findOne(['dept_id' => $userDept['dept_id']])['auditor_account_id'];
        }else{
            $case_auditor_id = CpicDeptAuditors::findOne(['dept_id' => $case['dept_id']])['auditor_account_id'];
        }
        $case_rater_id = $case['rater_account_id'];

        $case_user_log = CpicCaseUserLog::find()
            ->andWhere(['case_id' => $case_id, 'cur_status' => $case['status'], 'disabled' => 0])
            ->orderBy('create_time DESC')->one();


        $case_auditor_log = $case_rater_log = $case_score = [];

        switch ($case['status']) {
            case 0://待提交

                break;
            case 1://待审核
                $case_auditor_log = ['cur_status' => 1];
                break;
            case 2://撤回

                break;
            case 3://待评分
                //提交记录
                $case_user_log = CpicCaseUserLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => 1, 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                //合规记录
                $case_auditor_log = CpicCaseAuditorLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => $case['status'], 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                $case_rater_log = ['cur_status' => 3];
                break;
            case 4://已评分
                //提交记录
                $case_user_log = CpicCaseUserLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => 1, 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                //合规通过记录
                $case_auditor_log = CpicCaseAuditorLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => 3, 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                //评分记录
                $case_rater_log = CpicCaseRaterLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => $case['status'], 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                $case_score = CpicCaseScore::findOne(['case_id' => $case_id]);
                break;
            case 5://评审打回
                //提交记录
                $case_user_log = CpicCaseUserLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => 1, 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                //合规记录
                $case_auditor_log = CpicCaseAuditorLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => $case['status'], 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                break;
            case 6://评委打回
                //提交记录
                $case_user_log = CpicCaseUserLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => 1, 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                //合规记录
                $case_auditor_log = CpicCaseAuditorLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => 3, 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                //评分记录
                $case_rater_log = CpicCaseRaterLog::find()
                    ->andWhere(['case_id' => $case_id, 'cur_status' => $case['status'], 'disabled' => 0])
                    ->orderBy('create_time DESC')->one();
                break;
            default:

                break;
        }

        //判断评分类型
        if ($case['rater_type'] == 1) {
            $rater_log = $this->getCaseMoreScoreLog($case_id, $user_account_id);
        } else {
            //判断该案例是否有评委
            if ($case_rater_id) {
                $rater = ApiUser::getUserInfo($case_rater_id);
                $rater_time = (isset($case_rater_log['create_time']) && $case_rater_log['create_time']) ? $case_rater_log['create_time'] : null;
                $rater_cur_status = (isset($case_rater_log['cur_status']) && $case_rater_log['cur_status']) ? $case_rater_log['cur_status'] : null;
                $reason = $rater_cur_status == 6 ? $case_rater_log['comment'] : null;;

                switch ($rater_cur_status) {
                    case 3:
                        $rater_status_name = '待评分';
                        break;
                    case 4:
                        $rater_status_name = '已评分';
                        break;
                    case 6:
                        $rater_status_name = '评委打回';
                        break;
                    default:
                        $rater_status_name = '';
                        break;
                }
                $rater_log = [
                    'rater_id' => $case_rater_id,
                    'rater_name' => $case_rater_id == $user_account_id ? '我' : ($rater ? $rater['fullname'] : ''),
                    'rater_head_image' => $rater ? $rater['headimage'] : '',
                    'cur_status' => $rater_cur_status,
                    'status_name' => $rater_status_name,
                    'reason' => $reason,
                    'score' => (isset($case_score['score']) && $case_score['score']) ? $case_score['score'] : null,
                    'time' => $rater_time ? date('Y.m.d H:i:s', $rater_time) : null,
                ];
            } else {
                $rater_log = [];
            }
        }


        $user = ApiUser::getUserInfo($case_user_id);
        $audit = ApiUser::getUserInfo($case_auditor_id);
        $audit_time = (isset($case_auditor_log['create_time']) && $case_auditor_log['create_time']) ? $case_auditor_log['create_time'] : null;
        $audit_cur_status = (isset($case_auditor_log['cur_status']) && $case_auditor_log['cur_status']) ? $case_auditor_log['cur_status'] : null;

        switch ($audit_cur_status) {
            case 3:
                $audit_status_name = '待审核';
                break;
            case 4:
                $audit_status_name = '已审核';
                break;
            case 6:
                $audit_status_name = '核规不通过';
                break;
            default:
                $audit_status_name = '';
                break;
        }

        $list = [
            'user_log' => [
                'user_id' => $case_user_id,
                'user_name' => $case_user_id == $user_account_id ? '我' : ($user ? $user['fullname'] : ''),
                'user_head_image' => $user ? $user['headimage'] : '',
                'cur_status' => $case_user_log['cur_status'],
                'status_name' => $case_user_log['cur_status'] == 0 ? '待提交' : ($case_user_log['cur_status'] == 1 ? '已提交' : '撤回'),
                'time' => date('Y.m.d H:i:s', $case_user_log['create_time']),
            ],
            'auditor_log' => [
                'auditor_id' => $case_auditor_id,
                'user_name' => $case_auditor_id == $user_account_id ? '我' : ($audit ? $audit['fullname'] : ''),
                'user_head_image' => $audit ? $audit['headimage'] : '',
                'cur_status' => $audit_cur_status,
                'status_name' => $audit_status_name,
                'time' => $audit_time ? date('Y.m.d H:i:s', $audit_time) : null,
            ],
            'rater_log' => $rater_log,
        ];
        //var_dump($list);exit;
        return $list;
    }


    /**
     * 获取案例多人评分日志
     * @param $case_id
     */
    public function getCaseMoreScoreLog($case_id, $user_id)
    {
        $case = CpicCase::findOne($case_id);
        if ($case['rater_type'] != 1) return [];
        //评委列表
        $list = CpicUserRaterMore::findAll(['disabled' => 0]);
        //$raterList = ArrayHelper::getColumn($list, 'rater_account_id');
        $arr = [];
        foreach ($list as $v) {
            $user = ApiUser::getUserInfo($v['rater_account_id']);
            $item['rater_account_id'] = $v['rater_account_id'];
            $item['rater_name'] = isset($user['fullname']) && $user['fullname'] ? $user['fullname'] : '';
            $item['rater_img'] = isset($user['headimage']) && $user['headimage'] ? $user['headimage'] : '';

            $score = CpicScoreMoreLog::findOne(['case_id' => $case_id, 'user_account_id' => $v['rater_account_id']]);
            $item['is_score_or_not'] = $score ? 1 : 0;//是否评分
            $item['is_show_score'] = $score && $v['rater_account_id'] == $user_id ? 1 : 0;//是否显示评分
            $item['score'] = $score ? (!empty($score['score']) ? $score['score'] : 0) : 0;
            $item['reason'] = $score ? (!empty($score['reason']) ? $score['reason'] : '') : '';
            $item['score_time'] = $score && $score['create_time'] ? date('Y.m.d H:i:s', $score['create_time']) : 0;

            $arr[] = $item;

        }
        switch ($case['status']) {
            case 3://待评分
                $total_score = 0;
                $status = 3;
                $status_name = '待评分';
                break;
            case 4://已评分
                $caseScore = CpicCaseScore::findOne(['case_id' => $case_id]);
                $total_score = (isset($caseScore['score']) && $caseScore['score']) ? $caseScore['score'] : 0;
                $status = 4;
                $status_name = '已评分';
                break;
            case 6://评委打回
                $total_score = 0;
                $status = 6;
                $status_name = '评委打回';
                break;
            default:
                $total_score = 0;
                $status = 0;
                $status_name = '';
                break;
        }
        return  [
            'cur_status' => $status,
            'status_name' => $status_name,
            'score' => $total_score,
            'rater_list' => $arr
        ];
    }


}