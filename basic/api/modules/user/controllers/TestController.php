<?php

namespace app\api\modules\user\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiScore;
use app\api\modules\user\models\ApiUser;
use app\models\base\CpicDept;
use app\models\base\CpicDeptAuditors;
use app\models\base\UserSheet;
use app\models\Upload;
use app\models\UploadFile;
use Yii;

class TestController extends ApiController
{
    public function init()
    {
        parent::init();
    }


    /**
     * 获取登陆用户信息
     */
    public function actionTest()
    {
        $list = CpicDeptAuditors::findAll(['auditor_account_id' => null, 'disabled' => 0]);
        //var_dump(count($list));
        foreach ($list as $v){
            $dept = CpicDept::findOne(['dept_code' => $v['dept_code']]);
            $user = UserSheet::findOne(['user_account' => $v['auditor_account']]);
            $v->dept_id = $dept['dept_id'];
            $v->auditor_account_id = $user['user_account_id'];
            $v->create_time = time();
            $res = $v->save();
            var_dump($res?1:$v->id.'失败！');
        }



    }


}
