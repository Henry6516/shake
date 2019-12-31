<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/8/10
 * Time: 19:12
 */

namespace app\api\modules\content\models;

use app\models\base\CpicCase;
use app\models\base\CpicCaseFavorite;
use app\models\base\CpicCaseScore;
use app\models\base\CpicDept;
use app\models\base\CpicUserDept;
use Yii;
use yii\base\Exception;

class ApiFavorite
{
    /**
     * 获取用户收藏列表
     * @param $user_id
     * @return array|mixed
     */
    public function getFavList($user_id){
        $caseFavList = CpicCaseFavorite::find()->andWhere(['user_account_id' => $user_id])->orderBy('create_time DESC')->asArray()->all();
        $list = [];
        if($caseFavList){
            foreach ($caseFavList as $v){
                $case = CpicCase::findOne(['case_id' => $v['case_id']]);
                $item['case_id'] = $v['case_id'];
                $item['title'] = $case['title'];
                $item['cover_img'] = $case['cover_img'];
                $item['content_url'] = $case['content_url'];
                $item['content_name'] = $case['content_name'];
                $item['line'] = $case['line'];
                $item['charge'] = $case['charge'];
                $item['dept_id'] = $case['dept_id'];
                $item['status'] = $case['status'];
                $item['agree_num'] = $case['agree_num'];
                if($case['status'] == 4){
                    $item['score'] = CpicCaseScore::findOne(['case_id' => $v['case_id']])['score'];
                }else{
                    $item['score'] = 0;
                }
                $userDept = CpicUserDept::findOne(['user_account_id' => $case['user_account_id']]);
                $item['job'] = $userDept['job'];
                $item['dept_name'] = CpicDept::findOne(['dept_id' => $userDept['dept_id']])['dept_name'];
                //$item['is_fav'] = self::isFav($user_id, $case['case_id']);
                $item['is_agree'] = ApiAgree::isAgree($user_id, $case['case_id']);

                $list[] = $item;
            }
        }
        //var_dump($caseFavList);exit;
        return $list;
    }


    /**
     * 收藏/取消收藏
     * @param $user_id
     * @param $case_id
     * @param $status
     * @return int|mixed
     */
    public function doFav($user_id, $case_id, $status){
        $command = Yii::$app->db;
        $transaction = $command->beginTransaction();
        try {
            if($status) {
                //收藏添加数据
                Yii::$app->db->createCommand()->insert(CpicCaseFavorite::tableName(),
                    [
                        'user_account_id' => $user_id,
                        'case_id' => $case_id,
                        'create_time' => time()
                    ]
                )->execute();
            }else{
                //取消收藏删除数据
                Yii::$app->db->createCommand()->delete(CpicCaseFavorite::tableName(),
                    [
                        'user_account_id' => $user_id,
                        'case_id' => $case_id,
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
     * 判断当前用户是否收藏过
     * @param $user_id
     * @param $case_id
     * @return int
     */
    public static function isFav($user_id, $case_id){
        $res = CpicCaseFavorite::findOne(['user_account_id' => $user_id, 'case_id' => $case_id]);
        return $res ? 1 : 0;
    }

}