<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiSearch;
use app\models\base\CpicSearch;
use Yii;

class SearchController extends ApiController
{
    /**
     * 搜索案例
     * @return array
     */
    public function actionIndex()
    {
        $keywords = Yii::$app->request->get('keywords', '');
        $line = Yii::$app->request->get('line', 0);
        $path = Yii::$app->request->get('path', '');
        $page = Yii::$app->request->get('page', 1);

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269674;

        $apiSearch = new ApiSearch();
        list($number, $list) = $apiSearch->getSearchCaseList($user_account_id, $keywords, $line, $path, $page);

        return ['code' => parent::$CODE_SUC, 'msg' => 'successful', 'number' => $number, 'data' => $list];
    }


    /**
     * 搜索历史
     * @return array
     */
    public function actionHistory()
    {
        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269660;

        $apiSearch = new ApiSearch();

        $res = $apiSearch->getHistoryList($user_account_id);

        return ['code' => parent::$CODE_SUC, 'data' => $res, 'msg' => 'successful'];
    }

    /**
     * 删除搜索历史
     * @return array
     */
    public function actionDelete()
    {
        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 1;

        $res = CpicSearch::deleteAll(['user_account_id' => $user_account_id]);

        if(!$res) return ['code' => parent::$CODE_ERR, 'msg' => '删除失败'];
        return ['code' => parent::$CODE_SUC, 'msg' => 'successful'];
    }


}
