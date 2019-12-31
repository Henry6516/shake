<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/7/17
 * Time: 14:52
 */

namespace app\api\modules\content\models;


use app\api\modules\user\models\ApiUser;
use app\models\base\CpicCase;
use app\models\base\CpicCaseNum;
use app\models\base\CpicDept;
use app\models\base\CpicShowCase;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserName;

class ApiHome
{
    /**
     * 获取展示案例
     * @param $user_id string 登录用户ID
     * @param $page
     * @param int $pageSize
     * @return array
     */
    public function getShowCaseList($flag, $page = 1, $pageSize = 20)
    {
        $model = CpicShowCase::find()
            ->andWhere(['disabled' => 0])
            ->orderBy('order ASC');
        //$model->offset(($page - 1) * $pageSize);
        if($flag){
            $model->limit(6);
        }else{
            //$model->limit($pageSize);
        }
        $modelList = $model->asArray()->all();
        $list = [];
        if($modelList){
            foreach ($modelList as $v){
                $item = $v;
                $item['create_time'] = $v['create_time']?date('Y.m.d H:i:s', $v['create_time']):0;
                $list[] = $item;
            }

        }
        return $list;
    }



    /**
     * 获取优秀案例（90分以上）
     * @param $user_id string 登录用户ID
     * @param $flag int 0-所有列表 1-首页列表（10个）
     * @param $page
     * @param int $pageSize
     * @return array
     */
    public function getGoodCaseList($user_id, $flag = 0, $page = 1, $pageSize = 20)
    {
        $model = CpicCase::find()->joinWith('score')
            ->andWhere(['status' => 4, '{{%case}}.disabled' => 0])
            ->andWhere(['>=', '{{%case_score}}.score', 90])
            ->orderBy('{{%case_score}}.score DESC, {{%case_score}}.create_time DESC');

        if ($flag) {
            $model->limit(10);
        } else {
            $model->offset(($page - 1) * $pageSize);
            $model->limit($pageSize);
        }
        $modelList = $model->asArray()->all();

        return $this->listOutput($modelList, $user_id);
    }

    /**
     * 获取人气案例（点赞倒序）
     * @param $user_id string 登录用户ID
     * @param $flag int 0-所有列表 1-首页列表（10个）
     * @param $page
     * @param int $pageSize
     * @return array
     */
    public function getPopCaseList($user_id, $flag = 0, $page = 1, $pageSize = 20)
    {
        $model = CpicCase::find()->joinWith('score')
            ->andWhere(['{{%case}}.disabled' => 0])
            ->andWhere([
                'or',
                ['status' => 3],
                ['status' => 4],
            ])
            ->orderBy('agree_num DESC');

        if ($flag) {
            $model->limit(10);
        } else {
            $model->offset(($page - 1) * $pageSize);
            $model->limit($pageSize);
        }
        $modelList = $model->asArray()->all();

        return $this->listOutput($modelList, $user_id);
    }

    /**
     * 获取普通案例列表（创建时间倒序）
     * @param $user_id string 登录用户ID
     * @param $flag int 0-所有列表 1-首页列表（10个）
     * @param $page
     * @param int $pageSize
     * @return array
     */
    public function getCaseList($user_id, $page = 1, $pageSize = 20)
    {
        $model = CpicCase::find()->joinWith('score')
            ->andWhere(['{{%case}}.disabled' => 0])
            ->andWhere([
                'or',
                ['status' => 3],
                ['status' => 4],
            ])
            ->orderBy('agree_num DESC');
        $model->offset(($page - 1) * $pageSize);
        $model->limit($pageSize);
        $modelList = $model->asArray()->all();

        return $this->listOutput($modelList, $user_id);
    }


    /**
     * 获取分公司贡献率列表
     * @param $page
     * @param int $pageSize
     * @return array
     */
    public function getConCaseList($page = 1, $pageSize = 20)
    {
        $model = CpicCaseNum::find()->joinWith('dept')
            ->andWhere(['cpic_case_num.disabled' => 0])
            ->andWhere(['cpic_dept.parent_code' => 'CPIC_SX'])
            ->orderBy('contri_num DESC');
        //$model->offset(($page - 1) * $pageSize);
        //$model->limit($pageSize);
        $modelList = $model->asArray()->all();
        $list = [];
        if($modelList){
            foreach ($modelList as $v){
                //$dept = CpicDept::findOne(['dept_id' => $v['dept_id']]);
                $item['dept_id'] = $v['dept_id'];
                //$item['dept_name'] = $dept['dept_name'];
                $item['dept_name'] = $v['dept']['dept_name'];
                $item['case_num'] = $v['case_num'];
                $item['case_real_num'] = $v['case_real_num'];
                $item['contri_num'] = $v['contri_num'];
                $list[] = $item;
            }
        }
        return $list;
    }


    /**
     * 数组处理
     * @param $arr
     * @param $user_id string 登录用户ID
     * @return array
     */
    public function listOutput($arr, $user_id)
    {
        $list = [];
        //var_dump($model);exit;
        if ((array)$arr) {
            $userids = [];
            foreach ($arr as $u){
                $userids[] = $u['user_account_id'];
            }
            $userinfos = $this->getUserinfoByIds($userids);
            $userdepts = $this->getUserDeptByIds($userids);
//            var_dump($userinfos) ;
            foreach ($arr as $v) {
                //$user = [];
                $userinfo = isset($userinfos[$v['user_account_id']])?$userinfos[$v['user_account_id']]:[];//ApiUser::getUserInfo($v['user_account_id']);
                $userdept = isset($userdepts[$v['user_account_id']])?$userdepts[$v['user_account_id']]:[];
                $item['case_id'] = $v['case_id'];
                $item['user_account_id'] = $v['user_account_id'];
                $item['name'] = isset($userinfo['fullname']) && $userinfo['fullname'] ? $userinfo['fullname'] : '';
                $item['title'] = $v['title'];
                $item['cover_img'] = $v['cover_img'];
                $item['content_url'] = $v['content_url'];
                $item['content_name'] = $v['content_name'];
                $item['status'] = $v['status'];
                $item['agree_num'] = $v['agree_num'];
                $item['dept_name'] = CpicDept::findOne($v['dept_id'])['dept_name'];
                $item['job'] =  isset($userdept['job']) && $userdept['job'] ? $userdept['job'] : '';//CpicUserDept::findOne(['user_account_id' => $v['user_account_id']])['job'];
                //$item['job'] = CpicUserDept::findOne(['user_account_id' => $v['user_account_id'], 'dept_id' => $v['dept_id']])['job'];
                $item['score'] = $v['score']['score'];

                $item['is_agree'] = ApiAgree::isAgree($user_id, $v['case_id']);//判断当前用户是否点赞

                $list[] = $item;

            }
        }
        return $list;
    }

    public function getUserinfoByIds($userids){
        if (!is_array($userids) or empty($userids)){
            return [];
        }

        $model =  CpicUserName::find()
            ->select(["user_account_id","name"])
            ->where(['user_account_id'=>$userids]);

        $modelList = $model->asArray()->all();
        $list = [];
        if($modelList){
            foreach ($modelList as $v){
                $list[$v['user_account_id']] = $v;
            }
        }
        return $list;
    }
    public function getUserDeptByIds($userids){
        if (!is_array($userids) or empty($userids)){
            return [];
        }

        $model =  CpicUserDept::find()
            ->select(["user_account_id","job"])
            ->where(['user_account_id'=>$userids]);

        $modelList = $model->asArray()->all();
        $list = [];
        if($modelList){
            foreach ($modelList as $v){
                $list[$v['user_account_id']] = $v;
            }
        }
        return $list;
    }
}