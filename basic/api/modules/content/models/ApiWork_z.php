<?php
/**
 * Created by PhpStorm.
 * User: chindor
 * Date: 2017/6/28
 * Time: 11:16
 */

namespace app\api\modules\content\models;


use app\api\modules\user\models\ApiUser;
use app\models\base\CpicCase;
use app\models\base\CpicCaseFiles;
use app\models\base\CpicCaseLabel;
use app\models\base\CpicCaseScore;
use app\models\base\CpicDept;
use app\models\base\CpicDeptAuditors;
use app\models\base\CpicLabel;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserName;
use app\models\base\CpicUserRater;
use app\models\base\CpicUserRaterManagerChange;
use app\models\base\CpicUserRaterMemberChange;
use app\models\base\CpicUserRaterMore;
use yii\data\Pagination;
use yii\db\Query;
use yii\helpers\ArrayHelper;

class ApiWork_z
{
    /**案例详情的获取
     * @param int $case_id 案例id
     * @return array|mixed     数组
     */
    public static function detail($case_id, $c_account)
    {
        $model = CpicCase::findOne(['case_id' => $case_id]);
        if (!empty($model)) {

            //判断案例作者是不是分公司部门负责人
            $userDept = CpicUserDept::findOne(['user_account_id' => $model['user_account_id']]);
            //var_dump($userDept);exit;
            if(strpos($userDept['job'], '分公司部门负责人') !== false){
                $audit_account_id = CpicDeptAuditors::findOne(['dept_id' => $userDept['dept_id']])['auditor_account_id'];
            }else{
                $audit_account_id = CpicDeptAuditors::findOne(['dept_id' => $model['dept_id']])['auditor_account_id'];
            }

            //判断当前用户是否有权限查看

            if (($model['status'] == 0 || $model['status'] == 1) && $c_account != $audit_account_id && $c_account != $model['user_account_id']) return -1;

            //修改未读状态
            $model->is_read = 0;
            $res = $model->save();
            //$mes = $model->getErrors();
            //var_dump($model);exit;

            $data['label'] = self::label($case_id);
            $data['attachment'] = self::attachment($case_id);
            $data['button'] = self::button($model, $c_account);

            //判断案例是否可转派
            if ($data['button']['is_rater']) {
                $show_change = self::checkCaseIsChange($model, $c_account);
                $data['is_show_change'] = $show_change;
            } else {
                $data['is_show_change'] = 0;
            }

            $data['document'] = [
                'document_id' => $model['content_id'],
                'un_formated_url' => $model['content_url'],
                'url' => \yii::$app->params['img_host'] . $model['content_url'],
                'file_name' => $model['content_name'],
            ];
            $data['status'] = $model['status'];
            $data['title'] = $model['title'];
            $data['cover_img'] = $model['cover_img'];


            $data['user_account_id'] = $model['user_account_id'];
            $user = ApiUser::getUserInfo($model['user_account_id']);
            $data['name'] = isset($user['fullname']) && $user['fullname'] ? $user['fullname'] : '';
            //$data['name'] = CpicUserName::findOne(['user_account_id' => $model['user_account_id']])['name'];
            $data['image'] = isset($user['headimage']) && $user['headimage'] ? $user['headimage'] : '';
            $data['dept_name'] = CpicDept::findOne($model['dept_id'])['dept_name'];
            $data['job'] = CpicUserDept::findOne(['user_account_id' => $model['user_account_id'], 'dept_id' => $model['dept_id']])['job'];
            $data['line'] = $model['line'];
            $data['charge'] = $model['charge'];
            $data['agree_num'] = $model['agree_num'];
            $data['is_agree'] = ApiAgree::isAgree($c_account, $case_id);
            $data['is_fav'] = ApiFavorite::isFav($c_account, $case_id);
            $score = CpicCaseScore::findOne(['case_id' => $model['case_id']])['score'];
            $data['score'] = $model['status'] == 4 && $score ? $score : 0;
            $data['create_time'] = $model['create_time'] ? date('Y.m.d H:i:s', $model['create_time']) : 0;
            //var_dump($data);exit;
            return $data;
        }
        return [];
    }

    /**编辑详情
     * @param $case_id
     * @param $c_account
     * @return mixed|
     */
    public static function edit_detail($case_id, $c_account)
    {
        $model = CpicCase::findOne(['case_id' => $case_id]);
        if (!empty($model)) {
            $data['label'] = self::label($case_id);
            $data['attachment'] = self::attachment($case_id);
            $data['dept_name'] = self::getDetpName($model['dept_id']);
            $data['document'] = [
                'document_id' => $model['content_id'],
                'un_formated_url' => $model['content_url'],
                'url' => \yii::$app->params['img_host'] . $model['content_url'],
                'file_name' => $model['content_name'],
            ];
            $data['status'] = $model['status'];
            $data['dept_id'] = $model['dept_id'];
            $data['title'] = $model['title'];
            $data['line'] = $model['line'];
            $data['charge'] = $model['charge'];
            $data['cover_img'] = ['un_formated_url' => $model['cover_img'], 'url' => \yii::$app->params['img_host'] . $model['cover_img']];
            return $data;
        }
    }

    /**案例详情标签的获取
     * @param $case_id
     * @return array
     */
    public static function label($case_id)
    {
        if (empty($case_id)) {
            return [];
        }

        $data = (new Query())
            ->select('casel.label')
            ->from(CpicCaseLabel::tableName() . ' AS casel')
            ->where(['casel.case_id' => $case_id])
            ->all();

        return array_column($data, 'label');
    }

    /**普通案例列表的獲取
     * @return array
     */
    public static function commonCase($page)
    {
        $arr = self::getFourLineTopTen();
        $fourLineTopTenId = null;
        if (!empty($arr)) {
            $fourLineTopTenId = array_column($arr, 'case_id');
        }

        $query = (new Query())
            ->select(['case.title', 'case.line', 'score.score', 'case.case_id', 'dept.dept_name'])
            ->from(CpicCase::tableName() . ' AS case')
            ->innerJoin(CpicCaseScore::tableName() . ' AS score', 'score.case_id=case.case_id')
            ->innerJoin(CpicDept::tableName() . ' AS dept', 'dept.dept_id=case.dept_id')
            ->andfilterWhere(['not in', 'case.case_id', $fourLineTopTenId])
            ->andWhere(['case.status' => [3, 4]]);
        return $page == 0 ? $query->limit(20)->all() : $query->offset(($page - 1) * 10 + 20)->limit(10)->all();

    }

    /**优秀案例的获取
     * @return array
     */
    public static function fineCase()
    {
        $arr = self::getFourLineTopTen();

        //对数组进行排序
        usort($arr, function ($a, $b) {
            if ($a['score'] == $b['score']) {
                return 0;
            }
            return ($a['score'] < $b['score']) ? 1 : -1;
        });

        //对数组进行格式化
        array_walk($arr, function (&$v, $k) {
            $v['line'] = \yii::$app->params['line_name'][$v['line']];
            unset($v['score']);
        });

        return $arr;

    }

    /**取得四个条线中每个条线最优秀的前10个案例
     * @return array
     */
    private static function getFourLineTopTen()
    {
        return self::findTop10Byline(1)
            ->union(self::findTop10Byline(2))
            ->union(self::findTop10Byline(3))
            ->union(self::findTop10Byline(4))
            ->orderBy(['score' => SORT_DESC])
            ->all();
    }


    /*筛选出每个条线最优秀(得分最高)的10个案例
     * @param $line
     */
    private static function findTop10Byline($line)
    {
        return (new Query())
            ->select(['case.title', 'case.line', 'score.score', 'case.case_id'])
            ->from(CpicCase::tableName() . ' AS case')
            ->innerJoin(CpicCaseScore::tableName() . ' AS score', 'score.case_id=case.case_id')
//            -> innerJoin(CpicDept::tableName().' AS dept','dept.dept_id=case.dept_id')
            ->orderBy(['score.score' => SORT_DESC])
            ->where(['case.line' => $line])
            ->andWhere(['case.status' => [3, 4]])
            ->limit(10);
    }

    /**案例详情附件的获取
     * @param $case_id
     * @return array
     */
    public static function attachment($case_id)
    {

        if (!empty($case_id)) {
            $data = CpicCaseFiles::find()->select(['file_url', 'file_name'])->where(['case_id' => $case_id])->asArray()->all();
            $tmp = [];
            foreach ($data as $k => $v) {

                //检查该附件是否存在
                if (isset($v['file_name'])) {
                    $tmp[$k]['file_name'] = $v['file_name'];
                    $tmp[$k]['url'] = \yii::$app->params['img_host'] . $v['file_url'];
                    $tmp[$k]['un_formated_url'] = $v['file_url'];
                }
//                if(self::check_file_exists($v['file_url'])){

//                }
            }
            return ['data' => $tmp, 'count' => count($tmp)];
        }
        return ['data' => '', 'count' => 0];
    }

    /**按钮的获取
     * @param $model
     * @param $c_account
     * @return array
     */
    public static function button($model, $c_account)
    {
        //判断当前登录用户是不是案例作者
        $is_author = $model['user_account_id'] == $c_account ? 1 : 0;
        //判断当前登录用户是不是是不是合规人员

        //判断案例作者是不是分公司部门负责人
        $userDept = CpicUserDept::findOne(['user_account_id' => $model['user_account_id']]);
        //var_dump($userDept);exit;
        if(strpos($userDept['job'], '分公司部门负责人') !== false){
            $audit_account_id = CpicDeptAuditors::findOne(['dept_id' => $userDept['dept_id']])['auditor_account_id'];
        }else{
            $audit_account_id = CpicDeptAuditors::findOne(['dept_id' => $model['dept_id']])['auditor_account_id'];
        }

        $is_auditor = $audit_account_id == $c_account ? 1 : 0;
        //判断当前登录用户是不是是不是评审人员
        if ($model['rater_type'] == 1) {//多人审核
            $raterList = CpicUserRaterMore::findAll(['disabled' => 0]);
            $raterArr = ArrayHelper::getColumn($raterList, 'rater_account_id');
            $is_rater = in_array($c_account, $raterArr) ? 1 : 0;
        } else {
            $is_rater = $model['rater_account_id'] == $c_account ? 1 : 0;
        }
        switch ($model['status']) {
            case 0://待提交
                $is_show_button = $is_author ? 1 : 0;
                break;
            case 1:
                $is_show_button = $is_author ? 1 : ($is_auditor ? 1 : 0);
                break;
            case 2:
                $is_show_button = 0;
                break;
            case 3:
                $is_show_button = $is_rater ? 1 : 0;
                break;
            default:
                $is_show_button = 0;
                break;
        }


        /*//案例未审核并且不是自己看到
        $user_auditModel = CpicDeptAuditors::findOne(['dept_id' => $model['dept_id']]);
        if (empty($user_auditModel)) {
            return ['is_show_button' => 0];
        }

        if ($model['status'] == 1 && $c_account == $user_auditModel['auditor_account_id']) {
            return ['is_show_button' => 1];
        } else {
            return ['is_show_button' => 0];
        }*/
        return [
            'is_show_button' => $is_show_button,
            'is_author' => $is_author,
            'is_auditor' => $is_auditor,
            'is_rater' => $is_rater,
        ];
    }

    /**根据url来解析文件名
     * @param $url
     * @return mixed|string
     */
    private static function parseNameByUrl($url)
    {
        if (strripos($url, '/') !== false) {
            $tmp = explode('/', $url);
            $file_name = array_pop($tmp);
            return $file_name;
        }

        if (strripos($url, '.') && substr_count($url, '.') == 1 && mb_strlen($url, 'utf-8')) {
            return $url;
        }

        return '';
    }

    /**检查文件是否存在
     * @param $file_path   文件路径
     * @return bool        存在返回true,失败返回false
     */
    public static function check_file_exists($file_path)
    {
        $base_path = \yii::getAlias('@app');
        $real_path = $base_path . $file_path;
//        var_dump($real_path);
        if (file_exists($real_path) && is_file($real_path)) {
            return true;
        }

        return false;
    }

    /**
     * 获取所部部门名字
     */
    public static function getDetpName($dept_id)
    {
        if (empty($dept_id)) {
            return '';
        }
        $model = CpicDept::findOne($dept_id);
        if (empty($model)) {
            return '';
        } else {
            return $model['dept_name'];
        }
    }


    /**
     * 判断用户是否可转派当前案例
     */
    public static function checkCaseIsChange($model, $c_account)
    {
        switch ($model['rater_type']) {
            case 2:
                $userList = CpicUserRaterMemberChange::find()->andWhere(['line' => $model['line'], 'charge' => $model['charge'], 'is_change' => 1])->groupBy('line, charge')->asArray()->one();
                //$userIdArr = ArrayHelper::getColumn($userList, 'rater_account_id');
                $is_show_change = $c_account == $userList['rater_account_id'] ? 1 : 0;
                break;
            case 3:
                $userList = CpicUserRaterManagerChange::find()->andWhere(['line' => $model['line'], 'dept_id' => $model['dept_id'], 'is_change' => 1])->asArray()->one();
                $is_show_change = $userList && $c_account == $userList['rater_account_id'] ? 1 : 0;
                break;
            default:
                $is_show_change = 0;
                break;
        }
        return $is_show_change;
    }
}