<?php

namespace app\api\modules\content\models;

use app\api\components\ApiModel;
use app\api\modules\user\models\ApiUser;
use app\models\base\CpicCase;
use app\models\base\CpicCaseAuditorLog;
use app\models\base\CpicCaseFiles;
use app\models\base\CpicCaseLabel;
use app\models\base\CpicCaseScore;
use app\models\base\CpicCaseUserLog;
use app\models\base\CpicDept;
use app\models\base\CpicDeptAuditors;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserLine;
use app\models\base\CpicUserName;
use app\models\base\CpicUserRaterMore;
use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class ApiAudit extends ApiModel
{

    /**
     * wap端获取用户案例列表
     * @param $user_account_id
     * @return array
     */
    public function getCaseList($user_account_id, $page = 1, $pageSize = 10)
    {
        $model = CpicCase::find()
            ->andWhere(['user_account_id' => $user_account_id, 'disabled' => 0]);
        $caseList = $model->orderBy('create_time DESC')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->all();
        $list = [];
        $unreadNumber = 0;
        if ($caseList) {
            foreach ($caseList as $v) {
                //4为已评分
                if ($v['status'] == 4) {
                    $score = CpicCaseScore::findOne(['case_id' => $v['case_id']])['score'];
                } else {
                    $score = 0;
                }

                //$user = [];

                $item['case_id'] = $v['case_id'];
                $item['user_account_id'] = $v['user_account_id'];

                //$user = ApiUser::getUserInfo($v['user_account_id']);
                //$item['name'] = isset($user['fullname']) && $user['fullname'] ? $user['fullname'] : '';
                $item['name'] = CpicUserName::findOne(['user_account_id' => $v['user_account_id']])['name'];

                $item['title'] = $v['title'];
                $item['cover_img'] = $v['cover_img'] ? (Yii::$app->params['img_host'] . $v['cover_img']) : $v['content_url'];
                $item['content_url'] = $v['content_url'] ? (Yii::$app->params['img_host'] . $v['content_url']) : $v['content_url'];
                $item['line'] = $v['line'];
                $item['dept_id'] = $v['dept_id'];
                $item['status'] = $v['status'];
                $item['create_time'] = date('Y.m.d H:i:s', $v['create_time']);
                $item['score'] = $score;
                $item['is_read'] = $v['is_read'];
                if ($v['is_read']) $unreadNumber++;
                $list[] = $item;
            }
        }
        return [$list, $unreadNumber];
    }

    /**
     * wap端获取审核人员或评审人员的待审核案例列表
     * @param $audit_account_id
     * @return array
     */
    public function getAuditingCaseList($audit_account_id, $page = 1, $pageSize = 10)
    {
        //获取多人评分评委列表
        $rater = CpicUserRaterMore::findAll(['disabled' => 0]);
        //var_dump($rater);exit;
        $rater_arr = ArrayHelper::getColumn($rater, 'rater_account_id');
        $flag = in_array($audit_account_id, $rater_arr) ? 1 : 0;
        //var_dump();exit;

        $select = 't1.case_id, t1.user_account_id, t1.rater_account_id,  t1.title, t1.content_url, t1.content_name, IFNULL(t1.cover_img, \'\') AS cover_img, t1.line, t1.dept_id, t1.status, t1.create_time';
        $condition = 't1.disabled = 0 AND (t1.status = 1 AND t6.auditor_account_id = ' . $audit_account_id . ' 
            OR t1.status = 3 AND t1.rater_type IN (0,2,3) AND t1.rater_account_id = ' . $audit_account_id . '
            OR t1.status = 3 AND t1.rater_type = 1 AND ' . $flag . ')';

        $sql = 'SELECT ' . $select . ', IFNULL(t2.score, 0) AS score, t3.job AS job, t4.dept_name AS dept_name, t5.name AS name, t6.auditor_account_id AS audit_id' . ' FROM ' . CpicCase::tableName() .
            ' t1 LEFT JOIN ' . CpicCaseScore::tableName() . ' t2 ON t1.case_id = t2.case_id
            LEFT JOIN ' . CpicUserDept::tableName() . ' t3 ON t1.user_account_id = t3.user_account_id
            LEFT JOIN ' . CpicDept::tableName() . ' t4 ON t1.dept_id = t4.dept_id
            LEFT JOIN ' . CpicUserName::tableName() . ' t5 ON t1.user_account_id = t5.user_account_id
            LEFT JOIN ' . CpicDeptAuditors::tableName() . ' t6 ON t3.dept_id = t6.dept_id
            WHERE ' . $condition;


        //LEFT JOIN ' . CpicUserDept::tableName() . ' t3 ON t1.user_account_id = t3.user_account_id AND t1.dept_id = t3.dept_id
        //LEFT JOIN ' . CpicDeptAuditors::tableName() . ' t6 ON t1.dept_id = t6.dept_id
        $sql .= ' GROUP BY t1.case_id ORDER BY t1.create_time DESC ';
        //$sql .= ' LIMIT ' . ($page - 1) * $pageSize . ' , ' . $pageSize;
        $caseList = Yii::$app->db->createCommand($sql)->queryAll();
        return $this->listOutput($caseList);
    }


    /**
     * wap端获取审核人员或评审人员的已审核案例列表
     * @param $audit_account_id
     * @return array
     */
    public function getAuditedCaseList($audit_account_id, $page = 1, $pageSize = 10)
    {
        //获取多人评分评委列表
        $rater = CpicUserRaterMore::findAll(['disabled' => 0]);
        //var_dump($rater);exit;
        $rater_arr = ArrayHelper::getColumn($rater, 'rater_account_id');
        $flag = in_array($audit_account_id, $rater_arr) ? 1 : 0;
        //var_dump();exit;

        $select = 't1.case_id, t1.user_account_id, t1.rater_account_id,  t1.title, t1.content_url, t1.content_name, IFNULL(t1.cover_img, \'\') AS cover_img, t1.line, t1.dept_id, t1.status, t1.create_time';
        $condition = 't1.disabled = 0 AND (t1.status IN(3,4,5,6) AND t6.auditor_account_id = ' . $audit_account_id . ' 
            OR t1.status IN(4,6) AND t1.rater_type IN (0,2,3) AND t1.rater_account_id = ' . $audit_account_id . '
            OR t1.status IN(4,6) AND t1.rater_type = 1 AND ' . $flag . ')';

        $sql = 'SELECT ' . $select . ', IFNULL(t2.score, 0) AS score, t3.job AS job, t4.dept_name AS dept_name, t5.name AS name, t6.auditor_account_id AS audit_id' . ' FROM ' . CpicCase::tableName() .
            ' t1 LEFT JOIN ' . CpicCaseScore::tableName() . ' t2 ON t1.case_id = t2.case_id
            LEFT JOIN ' . CpicUserDept::tableName() . ' t3 ON t1.user_account_id = t3.user_account_id AND t1.dept_id = t3.dept_id
            LEFT JOIN ' . CpicDept::tableName() . ' t4 ON t1.dept_id = t4.dept_id
            LEFT JOIN ' . CpicUserName::tableName() . ' t5 ON t1.user_account_id = t5.user_account_id
            LEFT JOIN ' . CpicDeptAuditors::tableName() . ' t6 ON t1.dept_id = t6.dept_id
            WHERE ' . $condition;


        $sql .= ' ORDER BY t1.create_time DESC ';
        //$sql .= ' LIMIT ' . ($page - 1) * $pageSize . ' , ' . $pageSize;
        $caseList = Yii::$app->db->createCommand($sql)->queryAll();

        return $this->listOutput($caseList);
    }


    /**
     * wap端审核人员审核案例
     * @param $audit_account_id
     * @param $case_id
     * @param $status
     * @return boolean
     */
    public function auditCase($audit_account_id, $case_id, $status)
    {
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {
            //修改审核状态
            Yii::$app->db->createCommand()->update(
                CpicCase::tableName(),
                ['status' => $status, 'is_read' => 1, 'update_time' => time()],
                ['case_id' => $case_id]
            )->execute();

            //保存日志
            Yii::$app->db->createCommand()->insert(CpicCaseAuditorLog::tableName(),
                [
                    'case_id' => $case_id,
                    'deal_account_id' => $audit_account_id,
                    'last_status' => 1,//已提交
                    'cur_status' => $status,//3或5，通过或打回
                    'comment' => $status == 3 ? '案例通过初审' : '案例被合规人员打回',
                    'client_ip' => Yii::$app->request->userIP,
                    'create_time' => time()
                ]
            )->execute();

            $transaction->commit();
            return true;
        } catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * 数组处理
     * @param $arr
     * @return array
     */
    public function listOutput($arr)
    {
        $list = [];
        //var_dump($model);exit;
        if ($arr) {
            $userids = [];
            foreach ($arr as $u){
                $userids[] = $u['user_account_id'];
            }
            $userinfos = $this->getUserinfoByIds($userids);
            foreach ($arr as $v) {
                //$user = [];
                $item = $v;
                $user = isset($userinfos[$v['user_account_id']])?$userinfos[$v['user_account_id']]:[];//ApiUser::getUserInfo($v['user_account_id']);
                $item['image'] = isset($user['headimage']) && $user['headimage'] ? $user['headimage'] : '';
                $item['create_time'] = $v['create_time'] ? date('Y.m.d H:i:s', $v['create_time']) : 0;
                $list[] = $item;
            }
        }
        return $list;
    }

    public function getUserinfoByIds($userids){
        if (!is_array($userids) or empty($userids)){
            return [];
        }
        if (sizeof($userids)>10){
            $userinfos = array();
            foreach (array_chunk($userids,10) as $subids){
                foreach ((array)ApiUser::getUserInfos($subids) as $item){
                    array_push($userinfos ,$item );
                }

            }
        }else{
            $userinfos = ApiUser::getUserInfos($userids);
        }

        $list = [];
        if($userinfos){
            foreach ($userinfos as $v){
                $list[$v['accountid']] = $v;
            }
        }
        return $list;
    }


}