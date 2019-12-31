<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/7/12
 * Time: 15:35
 */

namespace app\api\modules\content\models;


use app\api\components\ApiModel;
use app\api\modules\user\models\ApiUser;
use app\models\base\CpicCase;
use app\models\base\CpicCaseLabel;
use app\models\base\CpicCaseScore;
use app\models\base\CpicDept;
use app\models\base\CpicSearch;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserName;
use app\models\base\CpicUserPath;
use Yii;

class ApiSearch extends ApiModel
{

    private $select = 'DISTINCT(t1.case_id), t1.user_account_id, t1.rater_account_id,  t1.title, t1.content_url, t1.content_name, IFNULL(t1.cover_img, \'\') AS cover_img, t1.agree_num, t1.line, t1.dept_id, t1.status, t1.create_time';
    //private $select = 't1.*';

    /**
     * 获取搜索案例列表
     * @param $user_account_id string 用户ID
     * @param $keywords string 关键词
     * @param $line int 条线
     * @param $path string 层级
     * @param $page int 当前页数
     * @param $pageSize int 每页显示数据条数
     * @return array
     */
    public function getSearchCaseList($user_account_id, $keywords, $line, $path, $page, $pageSize = 10){
        $condition = 't1.disabled = 0 AND (t1.status = 3 OR t1.status = 4)';

        if ($line) $condition .= ' AND t1.line='.$line;
        if ($path) $condition .= ' AND t4.path='.$path;
        if ($keywords) {
            //$condition .= ' AND (t1.title LIKE  "%'.$keywords.'%" OR label LIKE  "%'.$keywords.'%" OR job LIKE  "%'.$keywords.'%" OR dept_name LIKE  "%'.$keywords.'%" OR name LIKE  "%'.$keywords.'%")';
            $condition .= ' AND CONCAT(IFNULL(label, ""),IFNULL(dept_name, ""), IFNULL(t1.title, ""), IFNULL(job, ""), IFNULL(name, "")) LIKE  "%'.$keywords.'%"';
            //保存搜索记录
            $params = [
                'user_account_id' => $user_account_id,
                'keywords' => $keywords,
                'client_ip' => Yii::$app->request->userIP,
                'create_time' => time(),
            ];
            Yii::$app->db->createCommand()->insert(CpicSearch::tableName(), $params)->execute();
        }

        $sql = 'SELECT ' . $this->select . ', t2.score AS score, group_concat(t3.label) AS label, t4.job AS job, t4.path AS path, t5.dept_name AS dept_name, t6.name AS name ' . ' FROM ' . CpicCase::tableName() .
            ' t1 LEFT JOIN ' . CpicCaseScore::tableName() . ' t2 ON t1.case_id = t2.case_id
            LEFT JOIN ' . CpicCaseLabel::tableName() . ' t3 ON t1.case_id = t3.case_id 
            LEFT JOIN ' . CpicUserDept::tableName() . ' t4 ON t1.user_account_id = t4.user_account_id AND t1.dept_id = t4.dept_id
            LEFT JOIN ' . CpicDept::tableName() . ' t5 ON t1.dept_id = t5.dept_id
            LEFT JOIN ' . CpicUserName::tableName() . ' t6 ON t1.user_account_id = t6.user_account_id
                WHERE ' . $condition;
        $sql .= ' GROUP BY t1.case_id ORDER BY t1.create_time DESC';
        $numList = Yii::$app->db->createCommand($sql)->queryAll();
        $number = count($numList);//获取搜索结果数量
        $sql .= ' LIMIT ' . ($page - 1) * $pageSize . ' , ' . $pageSize;
        $modelList = Yii::$app->db->createCommand($sql)->queryAll();
        //var_dump($modelList);exit;
        $list = [];
        if($modelList){
            foreach ($modelList as $v){
                //$user = ApiUser::getUserInfo($v['user_account_id']);
                $item = $v;
                //$item['name'] = isset($user['fullname']) && $user['fullname'] ? $user['fullname'] : '';
                $labelArr = explode(',', $v['label']);
                $label = '';
                if($labelArr){
                    foreach($labelArr as $val){
                        if(stripos($val, $keywords) !== false){
                            $label = $val;
                            break;
                        }
                    }
                }
                $item['label'] = $label;
                $list[] = $item;
            }
        }
        return [$number, $list];
        //return [$number, $modelList];
    }


    /**
     * 获取用户搜索历史纪录
     * @param $user_account_id string 用户ID
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getHistoryList($user_account_id){
        return CpicSearch::find()
            ->andWhere(['user_account_id' => $user_account_id])
            ->groupBy('keywords')
            ->orderBy('create_time DESC')
            ->limit(15)
            ->asArray()->all();
    }

}