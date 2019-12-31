<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiAgree;
use app\api\modules\content\models\ApiFavorite;
use app\models\base\CpicCase;
use app\models\base\CpicCaseAgree;
use app\models\base\CpicCaseFavorite;
use app\models\UploadFile;
use dosamigos\qrcode\lib\Enum;
use dosamigos\qrcode\QrCode;
use Yii;

class FavoriteController extends ApiController
{
    /**
     * 获取收藏列表
     * @return array
     */
    public function actionIndex()
    {
        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 1;

        $apiFav = new ApiFavorite();
        $list = $apiFav->getFavList($user_account_id);
        //var_dump($list);exit;
        return ['code'=>parent::$CODE_SUC, 'msg' => '获取成功', 'data'=>$list];

    }
    /**
     * 收藏/取消收藏接口
     * @return array
     */
    public function actionFavorite()
    {
        $case_id = Yii::$app->request->get('case_id');
        $status = Yii::$app->request->get('status');
        if ($case_id === null) return parent::sendMessageJson('案例ID不能为空');
        if ($status != 0 && $status != 1) return parent::sendMessageJson('收藏参数错误');

        //验证用户是否登录
        $res = self::verifyAuth();
        if (!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;


        //判断用户当前收藏状态
        $caseFavLog = CpicCaseFavorite::findOne(['user_account_id' => $user_account_id, 'case_id' => $case_id]);
        if($caseFavLog && $status) {
            return ['code'=>parent::$CODE_ERR, 'msg' => '您已经收藏过此案例', 'data'=>[]];

        }

        $apiFav = new ApiFavorite();
        $result = $apiFav->doFav($user_account_id, $case_id, $status);
        //var_dump($result);exit;

        if($status){
            $msg = '收藏';
        }else{
            $msg = '取消收藏';
        }


        if($result) return ['code'=>parent::$CODE_SUC, 'msg' => $msg.'成功', 'data'=>[]];
        return ['code'=>parent::$CODE_ERR, 'msg' => $msg.'失败', 'data'=>[]];
    }
}
