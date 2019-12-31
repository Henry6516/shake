<?php
/**
 * Created by PhpStorm.
 * User: 许先生
 * Date: 2017/8/15
 * Time: 10:53
 */

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\models\base\CpicMsg;
use Yii;

class MessageController extends ApiController
{
    /**
     * 获取消息列表
     * @return array
     */
    public function actionIndex()
    {
        $flag = Yii::$app->request->get('flag', 0);
        $page = Yii::$app->request->get('page', 1);
        $pageSize = Yii::$app->request->get('pageSize', 20);

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;


        $model = CpicMsg::find()
            ->andWhere(['disabled' => 0]);
        if($flag){
            $model->andWhere(['marketable' => 1]);
        }
        $list = $model->orderBy('update_time DESC')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)->all();
        //$list = [];
        if ($list) {
            foreach ($list as $v) {
                $v['create_time'] = date('Y-m-d H:i', $v['create_time']);
            }
        }
        return ['code' => parent::$CODE_SUC, 'msg' => 'successful', 'data' => $list];
    }

    /**
     * 获取消息详情
     * @return array
     */
    public function actionInfo()
    {
        $id = Yii::$app->request->get('id', 0);
        if (!$id) return ['code' => self::$CODE_ERR, 'msg' => '消息ID不能为空'];


        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;


        $info = CpicMsg::findOne($id);
        $info['create_time'] = date('Y-m-d H:i', $info['create_time']);
        return ['code' => parent::$CODE_SUC, 'msg' => 'successful', 'data' => $info];
    }
}