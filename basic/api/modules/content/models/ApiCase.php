<?php
namespace app\api\modules\content\models;

use app\api\components\ApiModel;
use app\api\modules\user\models\ApiUser;
use app\models\base\CpicCase;
use app\models\base\CpicCaseFiles;
use app\models\base\CpicCaseLabel;
use app\models\base\CpicCaseScore;
use app\models\base\CpicCaseUserLog;
use app\models\base\CpicDept;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserLine;
use app\models\base\CpicUserRater;
use app\models\base\CpicUserRaterManagerChange;
use app\models\base\CpicUserRaterMemberChange;
use Yii;
use yii\base\Exception;

class ApiCase extends ApiModel
{

    /**
     * pc端获取用户案例列表
     * @param $user_account_id
     * @return array
     */
    public function getCaseList($user_account_id)
    {
        $model = CpicCase::find()
            ->andWhere(['user_account_id' => $user_account_id, 'disabled' => 0]);
        $caseList = $model->orderBy('create_time DESC')->all();
        $list = [];
        if ($caseList) {
            foreach ($caseList as $v) {
                //4为已评分
                if ($v['status'] == 4) {
                    $score = CpicCaseScore::findOne($v['case_id'])['score'];
                } else {
                    $score = 0;
                }

                $user = [];//ApiUser::getUserInfo($v['user_account_id']);

                $item['case_id'] = $v['case_id'];
                $item['user_account_id'] = $v['user_account_id'];
                $item['name'] = isset($user['fullname']) && $user['fullname'] ? $user['fullname'] : '';
                $item['title'] = $v['title'];
                $item['cover_img'] = $v['cover_img'] ? (Yii::$app->params['img_host'] . $v['cover_img']) : $v['content_url'];
                $item['content_url'] = $v['content_url'] ? (Yii::$app->params['img_host'] . $v['content_url']) : $v['content_url'];
                $item['line'] = $v['line'];
                $item['dept_id'] = $v['dept_id'];
                $item['status'] = $v['status'];
                $item['create_time'] = date('Y.m.d H:i:s', $v['create_time']);
                $item['score'] = $score;
                $list[] = $item;
            }
        }
        return $list;
    }


    /**
     * 创建案例
     * @param array $params 案例参数数组
     * @param array $files 案例附件数组
     * @param array $labels 案例标签数组
     * @return boolean
     */
    public function saveCase($params, $files, $labels)
    {
        $params['client_ip'] = Yii::$app->request->userIP;
        $params['create_time'] = time();
        //var_dump($params);exit;
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {
            Yii::$app->db->createCommand()->insert(CpicCase::tableName(), $params)->execute();
            $case_id = $command->lastInsertID;

            //保存附件
            if ($files) {
                $file_row = [];
                foreach ($files as $v) {
                    $file_row[] = [$case_id, $v['file_url'], $v['file_name'], time()];
                }
                Yii::$app->db->createCommand()->batchInsert(CpicCaseFiles::tableName(), ['case_id', 'file_url', 'file_name', 'create_time'], $file_row)->execute();
            }

            //保存标签
            if ($labels) {
                $label_row = [];
                foreach ($labels as $v) {
                    $label_row[] = [$case_id, $v, time()];
                }
                Yii::$app->db->createCommand()->batchInsert(CpicCaseLabel::tableName(), ['case_id', 'label', 'create_time'], $label_row)->execute();
            }

            //保存日志
            Yii::$app->db->createCommand()->insert(CpicCaseUserLog::tableName(),
                [
                    'case_id' => $case_id,
                    'deal_account_id' => $params['user_account_id'],
                    'last_status' => 0,
                    'cur_status' => $params['status'],
                    'comment' => $params['status']?'创建并提交案例':'创建并保存案例',
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
     * 编辑案例
     * @param int $case_id 案例ID
     * @param int $user_account_id 用户ID
     * @param array $params 案例参数数组
     * @param array $files 案例附件数组
     * @param array $labels 案例标签数组
     * @return boolean
     */
    public function editCase($case_id, $user_account_id, $params, $files, $labels)
    {
        //$params['client_ip'] = Yii::$app->request->userIP;
        $params['update_time'] = time();
        //var_dump($params);exit;
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {
            Yii::$app->db->createCommand()->update(CpicCase::tableName(), $params, ['case_id' => $case_id])->execute();

            //var_dump($files);
            // 先删除现有所有附件，再保存附件
            if ($files) {
                $file_row = [];
                foreach ($files as $v) {
                    $file_row[] = [$case_id, $v['file_url'], $v['file_name'], time()];
                }
                //var_dump($file_row);exit;
                Yii::$app->db->createCommand()->delete(CpicCaseFiles::tableName(), ['case_id' => $case_id])->execute();
                Yii::$app->db->createCommand()->batchInsert(CpicCaseFiles::tableName(), ['case_id', 'file_url', 'file_name', 'create_time'], $file_row)->execute();
            }

            // 先删除现有所有标签，再保存标签
            if ($labels) {
                $label_row = [];
                foreach ($labels as $v) {
                    $label_row[] = [$case_id, $v, time()];
                }
                Yii::$app->db->createCommand()->delete(CpicCaseLabel::tableName(), ['case_id' => $case_id])->execute();
                Yii::$app->db->createCommand()->batchInsert(CpicCaseLabel::tableName(), ['case_id', 'label', 'create_time'], $label_row)->execute();
            }

            //保存日志
            Yii::$app->db->createCommand()->insert(CpicCaseUserLog::tableName(),
                [
                    'case_id' => $case_id,
                    'deal_account_id' => $user_account_id,
                    'last_status' => 0,
                    'cur_status' => $params['status'],
                    'comment' => '编辑案例',
                    'client_ip' => Yii::$app->request->userIP,
                    'create_time' => time()
                ]
            )->execute();

            $transaction->commit();
            return true;
        } catch (Exception $e) {
            //var_dump($e->getMessage());exit;
            $transaction->rollBack();
            return false;
        }

    }


    /**
     * pc端获取用户条线列表
     * @param $user_account_id
     * @return array
     */
    public function getLineList($user_account_id)
    {
        $lineList = CpicUserLine::find()
            ->andWhere(['user_account_id' => $user_account_id, 'disabled' => 0])
            ->groupBy('user_account_id,line')
            ->asArray()->all();
        $list = [];
        if ($lineList) {
            foreach ($lineList as $v) {
                $item['user_account_id'] = $v['user_account_id'];
                $item['line'] = $v['line'];
                $item['line_name'] = Yii::$app->params['line_name'][$v['line']];
                $list[] = $item;
            }
        }
        return $list;

    }

    /**
     * pc端获取用户部门列表
     * @param $user_account_id
     * @param $line
     * @return array
     */
    public function getDeptList($user_account_id, $line)
    {
        $job = CpicUserDept::findOne(['user_account_id' => $user_account_id, 'disabled' => 0]);
        //判断当前用户是不是部门负责人
        if(strpos($job['job'], '分公司部门负责人') !== false){
            $deptList = CpicUserRaterManagerChange::find()
                ->andWhere(['line' => $line, 'disabled' => 0])->asArray()->all();
        }else{
            $deptList = CpicUserDept::find()
                ->andWhere(['user_account_id' => $user_account_id, 'disabled' => 0])
                ->groupBy('user_account_id,dept_id')
                ->asArray()->all();
        }
        $list = [];
        if ($deptList) {
            foreach ($deptList as $v) {
                $dept = CpicDept::findOne($v['dept_id']);
                $item['dept_id'] = $v['dept_id'];
                $item['dept_code'] = $dept['dept_code'];
                $item['dept_name'] = $dept['dept_name'];
                $list[] = $item;
            }
        }
        return $list;
    }


    /**
     * 撤回案例/提交案例
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
            //修改案例状态
            Yii::$app->db->createCommand()->update(
                CpicCase::tableName(),
                ['status' => $status, 'update_time' => time()],
                ['case_id' => $case_id]
            )->execute();

            //保存日志
            Yii::$app->db->createCommand()->insert(CpicCaseUserLog::tableName(),
                [
                    'case_id' => $case_id,
                    'deal_account_id' => $audit_account_id,
                    'last_status' => $status == 1 ? 0 : 1,
                    'cur_status' => $status == 1 ? 1 : 0,//撤回
                    'comment' => $status == 1 ? '案例提交' : '案例撤回',
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
     * pc端获取分管列表
     * @param $line
     * @return array
     */
    public function getChargeList($line)
    {
        return CpicUserRaterMemberChange::find()
            ->select('line, charge')
            ->andWhere(['line' => $line, 'disabled' => 0])
            ->groupBy('line, charge')
            ->asArray()->all();
    }

    /**
     * 判断当前创建的案例评审类型
     * @param $user_id string 用户ID
     * @param $dept_id int 用户部门ID
     * @return array
     */
    public function checkCaseReviewType($user_id, $dept_id, $line, $charge)
    {
        $rater = $rater_type = 0;
        //$userDept = CpicUserDept::findOne(['user_account_id' => $user_id, 'dept_id' => $dept_id]);
        $userDept = CpicUserDept::findOne(['user_account_id' => $user_id]);

        $rater_user = CpicUserRater::findOne(['user_account_id' => $user_id]);
        //if($userDept['path'] == 2){
        if(!$rater_user){
            if(strpos($userDept['job'] , '分公司总经理') !== false){
                $rater_type = 1;
            }elseif (strpos($userDept['job'], '分公司班子成员') !== false){
                $rater_type = 2;
                if($line == 2){
                    $raterArr = CpicUserRaterMemberChange::find()->andWhere(['line' => $line])->groupBy('line')->asArray()->one();
                }else{
                    $raterArr = CpicUserRaterMemberChange::find()->andWhere(['line' => $line, 'charge' => $charge])->groupBy('line,charge')->asArray()->one();
                }
                $rater = $raterArr['rater_account_id'];
            }elseif(strpos($userDept['job'], '分公司部门负责人') !== false){
                $rater_type = 3;
                $raterArr = CpicUserRaterManagerChange::find()->andWhere(['line' => $line, 'dept_id' => $dept_id])->asArray()->one();
                $rater = $raterArr['rater_account_id'];
            }
        }else{
            $rater = $rater_user ? $rater_user['rater_account_id'] : 0;
        }


        return [$rater_type, $rater];
    }



}