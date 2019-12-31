<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/7/19
 * Time: 17:28
 */

namespace app\api\modules\content\models;


use app\api\modules\user\models\ApiUser;
use app\models\base\CpicCase;
use app\models\base\CpicCaseRaterLog;
use app\models\base\CpicCaseScore;
use app\models\base\CpicDept;
use app\models\base\CpicScoreMoreLog;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserRaterChangeLog;
use app\models\base\CpicUserRaterManagerChange;
use app\models\base\CpicUserRaterMemberChange;
use app\models\base\CpicUserRaterMore;
use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class ApiScore
{
    /**
     * 获取案例评委转派列表
     * @param $user_account_id
     * @param $case_id
     * @return array
     */
    public function getCaseRaterChangeList($user_account_id, $case_id)
    {
        $case = CpicCase::findOne($case_id);
        switch ($case['rater_type']) {
            case 2:
                $list = CpicUserRaterMemberChange::find()->andWhere(['line' => $case['line'], 'charge' => $case['charge'], 'is_change' => 1])->asArray()->all();
                break;
            case 3:
                $list = CpicUserRaterManagerChange::find()->andWhere(['line' => $case['line'], 'dept_id' => $case['dept_id'], 'is_change' => 1])->asArray()->all();
                break;
            default:
                $list = [];
                break;
        }
        $arr = [];
        if($list){
            foreach ($list as $v){
                $user = ApiUser::getUserInfo($v['new_rater_account_id']);
                $dept = CpicUserDept::findOne(['user_account_id' => $v['new_rater_account_id']]);
                $dept_name = CpicDept::findOne($dept['dept_id'])['dept_name'];
                $item['new_rater_account_id'] = $v['new_rater_account_id'];
                $item['new_rater_name'] = isset($user['fullname']) && $user['fullname'] ? $user['fullname'] : '';
                $item['new_rater_img'] = isset($user['headimage']) && $user['headimage'] ? $user['headimage'] : '';
                $item['dept_id'] = $dept['dept_id'] ? $dept['dept_id'] : 0;
                $item['dept_name'] = $dept_name ? $dept_name : '';
                $item['job'] = $dept['job'] ? $dept['job'] : '';
                $arr[] = $item;
            }
        }
        return $arr;
    }

    /**
     * 案例评委转派
     * @param $user_account_id
     * @param $new_rater_account_id
     * @param $case_id
     * @return mixed
     */
    public function caseRaterChange($user_account_id, $new_rater_account_id, $case_id)
    {
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {

            //修改案例评分人
            Yii::$app->db->createCommand()->update(
                CpicCase::tableName(),
                ['rater_account_id' => $new_rater_account_id, 'update_time' => time()],
                ['case_id' => $case_id]
            )->execute();

            //保存评分人转派日志
            Yii::$app->db->createCommand()->insert(CpicUserRaterChangeLog::tableName(),
                [
                    'case_id' => $case_id,
                    'rater_account_id' => $user_account_id,
                    'new_rater_account_id' => $new_rater_account_id,
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
     * 案例评分
     * @param $rater_account_id
     * @param $case_id
     * @param $params
     * @return array|mixed
     */
    public function doCaseScore($rater_account_id, $case_id, $params)
    {
        $case = CpicCase::findOne($case_id);
        if ($case['rater_type'] == 1) {//多人打分
            return $this->doCaseMoreRaterScore($rater_account_id, $case_id, $params);
        } else {
            return $this->doCaseToOneRaterScore($rater_account_id, $case_id, $params);
        }
    }

    /**
     * 案例打回
     * @param $rater_account_id
     * @param $case_id
     * @param $reason
     * @return array|mixed
     */
    public function doCaseRefuse($rater_account_id, $case_id, $reason)
    {
        $case = CpicCase::findOne($case_id);
        if ($case['rater_type'] == 1) {//多人打分
            return $this->doCaseMoreRaterRefuse($rater_account_id, $case_id, $reason);
        } else {
            return $this->doCaseToOneRaterRefuse($rater_account_id, $case_id, $reason);
        }
    }


    /**
     * 多评委案例评分保存数据
     * @param $rater_account_id
     * @param $case_id
     * @param $reason
     * @return array|mixed
     */
    public function doCaseMoreRaterScore($rater_account_id, $case_id, $params)
    {
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {
            //保存多人评分评分日志
            Yii::$app->db->createCommand()->insert(CpicScoreMoreLog::tableName(),
                [
                    'case_id' => $case_id,
                    'user_account_id' => $rater_account_id,
                    'part_1' => $params['part_1'],
                    'part_2' => $params['part_2'],
                    'part_3' => $params['part_3'],
                    'score' => $params['part_1'] + $params['part_2'] + $params['part_3'],
                    'reason' => '',
                    'client_ip' => Yii::$app->request->userIP,
                    'create_time' => time()
                ]
            )->execute();

            //所有评委评完分计算平均分
            $raterScoreLog = CpicScoreMoreLog::findAll(['case_id' => $case_id, 'disabled' => 0]);
            //var_dump(count($raterScoreLog));exit;
            if (count($raterScoreLog) == 13) {
                //计算平均分
                $scoreArr = $this->getCaseAverageScore($raterScoreLog);

                //保存最终评分结果
                Yii::$app->db->createCommand()->insert(CpicCaseScore::tableName(),
                    [
                        'case_id' => $case_id,
                        'part_1' => $scoreArr['part_1'],
                        'part_2' => $scoreArr['part_2'],
                        'part_3' => $scoreArr['part_3'],
                        'score' => $scoreArr['score'],
                        'client_ip' => '',
                        'create_time' => time()
                    ]
                )->execute();

                //修改案例状态
                Yii::$app->db->createCommand()->update(
                    CpicCase::tableName(),
                    ['status' => 4, 'is_read' => 1, 'update_time' => time()],
                    ['case_id' => $case_id]
                )->execute();

                //保存操作日志
                Yii::$app->db->createCommand()->insert(CpicCaseRaterLog::tableName(),
                    [
                        'case_id' => $case_id,
                        'deal_account_id' => 0,//多人评分
                        'last_status' => 3,//已审核
                        'cur_status' => 4,//已评分
                        'comment' => '案例评分完成',
                        'client_ip' => '',
                        'create_time' => time()
                    ]
                )->execute();


            }

            $transaction->commit();
            return true;
        } catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * 一对一评委案例评分保存数据
     * @param $rater_account_id
     * @param $case_id
     * @param $params
     * @return array|mixed
     */
    public function doCaseToOneRaterScore($rater_account_id, $case_id, $params)
    {
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {

            //修改案例状态
            Yii::$app->db->createCommand()->update(
                CpicCase::tableName(),
                ['status' => 4, 'is_read' => 1, 'update_time' => time()],
                ['case_id' => $case_id]
            )->execute();

            //保存打分结果
            Yii::$app->db->createCommand()->insert(CpicCaseScore::tableName(),
                [
                    'case_id' => $case_id,
                    'part_1' => $params['part_1'],
                    'part_2' => $params['part_2'],
                    'part_3' => $params['part_3'],
                    'score' => $params['part_1'] + $params['part_2'] + $params['part_3'],
                    'client_ip' => Yii::$app->request->userIP,
                    'create_time' => time()
                ]
            )->execute();

            //保存操作日志
            Yii::$app->db->createCommand()->insert(CpicCaseRaterLog::tableName(),
                [
                    'case_id' => $case_id,
                    'deal_account_id' => $rater_account_id,
                    'last_status' => 3,//已审核
                    'cur_status' => 4,//已评分
                    'comment' => '案例被评委打回',
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
     * 一对一评委打回案例保存数据
     * @param $rater_account_id
     * @param $case_id
     * @return array|mixed
     */
    public function doCaseToOneRaterRefuse($rater_account_id, $case_id, $reason)
    {
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {

            //修改案例状态
            Yii::$app->db->createCommand()->update(
                CpicCase::tableName(),
                ['status' => 6, 'is_read' => 1, 'update_time' => time()],
                ['case_id' => $case_id]
            )->execute();

            //保存打回日志
            Yii::$app->db->createCommand()->insert(CpicCaseRaterLog::tableName(),
                [
                    'case_id' => $case_id,
                    'deal_account_id' => $rater_account_id,
                    'last_status' => 3,//已审核
                    'cur_status' => 6,//评委打回
                    'comment' => $reason,
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
     * 多评委评分打回案例保存数据
     * @param $rater_account_id
     * @param $case_id
     * @param $reason
     * @return array|mixed
     */
    public function doCaseMoreRaterRefuse($rater_account_id, $case_id, $reason)
    {
        $raterScoreLog = CpicScoreMoreLog::findAll(['case_id' => $case_id, 'disabled' => 0]);

        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {
            if ($raterScoreLog) {
                //有打分记录，计算打分平均分

                //修改案例状态
                Yii::$app->db->createCommand()->update(
                    CpicCase::tableName(),
                    ['status' => 4, 'is_read' => 1, 'update_time' => time()],
                    ['case_id' => $case_id]
                )->execute();

                //保存评分日志
                Yii::$app->db->createCommand()->insert(CpicCaseRaterLog::tableName(),
                    [
                        'case_id' => $case_id,
                        'deal_account_id' => 0,//多人评分
                        'last_status' => 3,//已审核
                        'cur_status' => 4,//已评分
                        'comment' => '案例评分完成',
                        'client_ip' => '',
                        'create_time' => time()
                    ]
                )->execute();

                //计算平均分
                $scoreArr = $this->getCaseAverageScore($raterScoreLog);
                //保存最终评分结果
                Yii::$app->db->createCommand()->insert(CpicCaseScore::tableName(),
                    [
                        'case_id' => $case_id,
                        'part_1' => $scoreArr['part_1'],
                        'part_2' => $scoreArr['part_2'],
                        'part_3' => $scoreArr['part_3'],
                        'score' => $scoreArr['score'],
                        'client_ip' => '',
                        'create_time' => time()
                    ]
                )->execute();
            } else {
                //当前评委第一个评分且是打回，案例就打回

                //修改案例状态
                Yii::$app->db->createCommand()->update(
                    CpicCase::tableName(),
                    ['status' => 6, 'is_read' => 1, 'update_time' => time()],
                    ['case_id' => $case_id]
                )->execute();

                //保存打回日志
                Yii::$app->db->createCommand()->insert(CpicCaseRaterLog::tableName(),
                    [
                        'case_id' => $case_id,
                        'deal_account_id' => $rater_account_id,
                        'last_status' => 3,//已审核
                        'cur_status' => 6,//评委打回
                        'comment' => '案例被评委打回',
                        'client_ip' => Yii::$app->request->userIP,
                        'create_time' => time()
                    ]
                )->execute();
            }

            //保存多人评分打回日志
            Yii::$app->db->createCommand()->insert(CpicScoreMoreLog::tableName(),
                [
                    'case_id' => $case_id,
                    'user_account_id' => $rater_account_id,
                    'part_1' => 0,
                    'part_2' => 0,
                    'part_3' => 0,
                    'score' => 0,
                    'reason' => $reason,
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
     * 判断用户是否有权限打分/打回
     * @param $rater_account_id
     * @param $case_id
     * @return array|mixed
     */
    public function checkScorePermissions($rater_account_id, $case_id)
    {
        $case = CpicCase::findOne($case_id);
        if ($case['rater_type'] == 1) {
            //多人打分
            $raterList = CpicUserRaterMore::findAll(['disabled' => 0]);
            $raterArr = ArrayHelper::getColumn($raterList, 'rater_account_id');
            return in_array($rater_account_id, $raterArr) ? true : false;
        } else {
            return $case['rater_account_id'] == $rater_account_id ? true : false;
        }
    }

    /**
     * 判断多人评分的案例是否可打回/评分
     * @param $rater_account_id
     * @param $case_id
     * @return array|mixed
     */
    public function checkMoreScorePermissions($rater_account_id, $case_id)
    {
        $case = CpicCase::findOne($case_id);
        //多人打分
        if ($case['rater_type'] == 1) {
            //判断当前案例评分截止时间
            $time = Yii::$app->params['score_time'];
            if($time && strtotime($time) < time()){
                //案例处于待评分状态，评分时间到期，定时执行脚本计算平均分，更新案例状态（console）
                return false;
            }
            //当前评委打分记录
            $raterScoreLog = CpicScoreMoreLog::findOne(['user_account_id' => $rater_account_id, 'case_id' => $case_id, 'disabled' => 0]);
            return $raterScoreLog ? false : true;
        }
        return true;
    }


    /**
     * 计算当前案例的平均分
     * @param $list
     * @return array
     */
    public function getCaseAverageScore($list)
    {
        $part_1 = $part_2 = $part_3 = $score = $k = 0;
        $total_part_1 = $total_part_2 = $total_part_3 = $total_score = 0;
        //$list = CpicScoreMoreLog::findOne(['case_id' => $case_id, 'disabled' => 0]);
        if ($list) {
            foreach ($list as $v) {
                if ($v['score']) {
                    $total_part_1 += $v['part_1'];
                    $total_part_2 += $v['part_2'];
                    $total_part_3 += $v['part_3'];
                    $total_score += $v['score'];
                    $k++;
                }
            }
            $part_1 = round($total_part_1 / $k, 1);
            $part_2 = round($total_part_2 / $k, 1);
            $part_3 = round($total_part_3 / $k, 1);
            $score = round($total_score / $k, 1);
        }
        return [
            'part_1' => $part_1,
            'part_2' => $part_2,
            'part_3' => $part_3,
            'score' => $score,
        ];
    }


}