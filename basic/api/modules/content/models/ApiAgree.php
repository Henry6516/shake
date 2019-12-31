<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/7/14
 * Time: 13:59
 */

namespace app\api\modules\content\models;


use app\models\base\CpicCase;
use app\models\base\CpicCaseAgree;
use app\models\base\CpicCaseAgreeLog;
use Yii;
use yii\base\Exception;

class ApiAgree
{

    /**
     * 点赞
     * @param $user_id
     * @param $case_id
     * @param $status
     * @return int|mixed
     */
    public function doAgree($user_id, $case_id, $status){
        $caseAgreeLog = CpicCaseAgree::findOne(['user_account_id' => $user_id, 'case_id' => $case_id]);

        //保存数据
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {
            if($caseAgreeLog) {
                $caseAgreeLog->status = $status;
                $caseAgreeLog->update_time = time();
                $caseAgreeLog->save();
            }else{
                $model = new CpicCaseAgree();
                $model->user_account_id = $user_id;
                $model->case_id = $case_id;
                $model->status = $status;
                $model->create_time = time();
                $model->save();
            }

            //修改案例点赞数量
            if($status){
                Yii::$app->db->createCommand('UPDATE '.CpicCase::tableName(). ' SET agree_num = agree_num + 1,update_time = unix_timestamp(now()) WHERE case_id = '.$case_id)->execute();
            }else{
                Yii::$app->db->createCommand('UPDATE '.CpicCase::tableName(). ' SET agree_num = IF(agree_num < 1, 0,  agree_num - 1),update_time = unix_timestamp(now())  WHERE case_id = '.$case_id)->execute();
            }

            //保存点赞日志
            Yii::$app->db->createCommand()->insert(CpicCaseAgreeLog::tableName(),
                [
                    'user_account_id' => $user_id,
                    'case_id' => $case_id,
                    'status' => $status,
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
     * 判断当前用户是否点过赞
     * @param $user_id
     * @param $case_id
     * @return int
     */
    public static function isAgree($user_id, $case_id){
        $res = CpicCaseAgree::findOne(['user_account_id' => $user_id, 'case_id' => $case_id, 'status' => 1]);
        return $res?1:0;
    }


}