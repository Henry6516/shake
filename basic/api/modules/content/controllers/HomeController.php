<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/7/17
 * Time: 11:53
 */

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiHome;
use app\models\base\CpicBanner;
use app\models\base\CpicShowCase;
use Yii;

class HomeController extends ApiController
{
    /**
     * 获取首页四个模块相关内容
     * @return array
     */
    public function actionIndex(){
        $list = CpicBanner::find()->andWhere(['disabled' => 0])->all();
        return ['code' => parent::$CODE_SUC, 'data' => $list, 'msg' => 'successful'];
    }

    /**
     * 获取展示案例
     * @return array
     */
    public function actionShowList(){
        $flag = Yii::$app->request->get('flag', 0);
        $page = Yii::$app->request->get('page', 1);
        $pageSize = Yii::$app->request->get('pageSize', 20);

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiHome = new ApiHome();
        $list = $apiHome->getShowCaseList($flag, $page, $pageSize);
        return ['code' => parent::$CODE_SUC, 'data' => $list, 'msg' => 'successful'];
    }

    /**
     * 获取展示案例详情
     * @return array
     */
    public function actionShowDetail(){
        $id = Yii::$app->request->get('id');
        if (!$id) return ['code' => self::$CODE_ERR, 'msg' => '展示案例ID不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $showCase = CpicShowCase::findOne($id);
        //$showCase['create_time'] = date('Y.m.d H:i:s', $showCase['create_time']);
        return ['code' => parent::$CODE_SUC, 'data' => $showCase, 'msg' => 'successful'];
    }

    /**
     * 获取优秀案例(显示大于等于90分的案例，按打分时间倒序)
     * @return array
     */
    public function actionGoodList(){
        $flag = Yii::$app->request->get('flag', 0);
        $page = Yii::$app->request->get('page', 1);
        $pageSize = Yii::$app->request->get('pageSize', 20);

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiHome = new ApiHome();
        $list = $apiHome->getGoodCaseList($user_account_id, $flag, $page, $pageSize);
        return ['code' => parent::$CODE_SUC, 'data' => $list, 'msg' => 'successful'];
    }

    /**
     * 获取人气案例（按点赞数倒序）
     * @return array
     */
    public function actionPopList(){
        $flag = Yii::$app->request->get('flag', 0);
        $page = Yii::$app->request->get('page', 1);
        $pageSize = Yii::$app->request->get('pageSize', 20);

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiHome = new ApiHome();
        $list = $apiHome->getPopCaseList($user_account_id, $flag, $page, $pageSize);
        return ['code' => parent::$CODE_SUC, 'data' => $list, 'msg' => 'successful'];
    }

    /**
     * 获取普通案例（用户所在条线案例时间倒序）
     * @return array
     */
    public function actionList(){
        $page = Yii::$app->request->get('page', 1);
        $pageSize = Yii::$app->request->get('pageSize', 20);

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiHome = new ApiHome();
        $list = $apiHome->getCaseList($user_account_id, $page, $pageSize);
        return ['code' => parent::$CODE_SUC, 'data' => $list, 'msg' => 'successful'];
    }


    /**
     * 获取分公司贡献率列表
     * @return array
     */
    public function actionConList(){
        $page = Yii::$app->request->get('page', 1);
        $pageSize = Yii::$app->request->get('pageSize', 20);

        //验证用户是否登录
        $res = self::verifyAuth();
        //if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiHome = new ApiHome();
        $list = $apiHome->getConCaseList($page, $pageSize);
        return ['code' => parent::$CODE_SUC, 'data' => $list, 'msg' => 'successful'];
    }







}