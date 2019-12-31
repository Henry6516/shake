<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiAudit;
use app\api\modules\content\models\ApiBaiduDoc;
use app\api\modules\content\models\ApiCase;
use app\api\modules\content\models\ApiOfficeToText;
use app\models\base\CpicCase;
use app\models\base\CpicUserDept;
use app\models\base\CpicUserRater;
use app\models\Helper\SessionMessage;
use Yii;

class DefaultController extends ApiController
{
    public function init()
    {
        parent::init();
    }

    /**PC端我的案例列表
     * @return array
     */
    public function actionIndex()
    {
        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiCase = new ApiCase();
        $caseList = $apiCase->getCaseList($user_account_id);
        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $caseList];
    }


    /**PC创建案例
     * @return array
     */
    public function actionCreate()
    {
        $title = Yii::$app->request->get('title');
        $content_url = Yii::$app->request->get('content_url');
        $content_name = Yii::$app->request->get('content_name');
        $cover_img = Yii::$app->request->get('cover_img');
        $line = Yii::$app->request->get('line');
        $charge = Yii::$app->request->get('charge','');
        $dept_id = Yii::$app->request->get('dept_id');
        $status = Yii::$app->request->get('status', 0);
        $files = Yii::$app->request->get('files', []);
        $labels = Yii::$app->request->get('labels', []);
        if(!$title) return  ['code' => self::$CODE_ERR, 'msg' => '案例标题不能为空'];
        if(!$content_url) return  ['code' => self::$CODE_ERR, 'msg' => '案例文档地址不能为空'];
        if(!$content_name) return  ['code' => self::$CODE_ERR, 'msg' => '案例文档名称不能为空'];
        if(!$cover_img) return  ['code' => self::$CODE_ERR, 'msg' => '案例封面不能为空'];
        if(!$line) return  ['code' => self::$CODE_ERR, 'msg' => '案例条线不能为空'];
        if(!$dept_id) return  ['code' => self::$CODE_ERR, 'msg' => '案例部门不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        //var_dump($labels);exit;
        //文档保存在百度云的iD
        $content_id = 0;
        $baiduDoc = new ApiBaiduDoc();
        if(!$content_id = $baiduDoc -> init() -> uploadToBc($content_url)){
            return self::sendMessageJson(SessionMessage::getMess());
        }

        //解析文档内容
        $text = ApiOfficeToText::OfficeToText($content_url);
        if(mb_strlen($text,'utf-8') > 20000){
            $text = mb_substr($text,0,20000,'utf-8');
        }

        $apiCase = new ApiCase();

        //判断案例评审类型/获取评委
        list($rater_type, $rater_account_id) = $apiCase->checkCaseReviewType($user_account_id, $dept_id, $line, $charge);

        $params = [
            'user_account_id' => $user_account_id,
            'rater_account_id' => $rater_account_id,
            'rater_type' => $rater_type,
            'title' => $title,
            'content_url' => $content_url,
            'content_name' => $content_name,
            'content_id' => $content_id,
            'content' => $text?$text:'',
            'cover_img' => $cover_img,
            'line' => $line,
            'charge' => $charge,
            'dept_id' => $dept_id,
            'status' => $status,
        ];

        $result = $apiCase->saveCase($params, $files, $labels);
        //var_dump($caseList);exit;
        $successMsg = $status?'案例提交成功':'案例保存成功';
        $failedMsg = $status?'案例提交失败':'案例保存失败';

        if($result) return parent::sendMessageJson($successMsg, parent::$CODE_SUC);
        return parent::sendMessageJson($failedMsg);
    }

    /**PC编辑案例
     * @return array
     */
    public function actionUpdate()
    {
        $case_id = Yii::$app->request->get('case_id');
        $title = Yii::$app->request->get('title');
        $content_url = Yii::$app->request->get('content_url');
        $content_name = Yii::$app->request->get('content_name');
        $cover_img = Yii::$app->request->get('cover_img');
        $line = Yii::$app->request->get('line');
        $charge = Yii::$app->request->get('charge','');
        $dept_id = Yii::$app->request->get('dept_id');
        $status = Yii::$app->request->get('status', 0);
        $files = Yii::$app->request->get('files', []);
        $labels = Yii::$app->request->get('labels', []);
        if(!$case_id) return  ['code' => self::$CODE_ERR, 'msg' => '案例ID不能为空'];
        if(!$title) return  ['code' => self::$CODE_ERR, 'msg' => '案例标题不能为空'];
        if(!$content_url) return  ['code' => self::$CODE_ERR, 'msg' => '案例文档地址不能为空'];
        if(!$content_name) return  ['code' => self::$CODE_ERR, 'msg' => '案例文档名称不能为空'];
        if(!$cover_img) return  ['code' => self::$CODE_ERR, 'msg' => '案例封面图片不能为空'];
        if(!$line) return  ['code' => self::$CODE_ERR, 'msg' => '案例条线不能为空'];
        if(!$dept_id) return  ['code' => self::$CODE_ERR, 'msg' => '案例部门不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        //文档保存在百度云的iD
        $baiduDoc = new ApiBaiduDoc();
        if(!$content_id = $baiduDoc -> init() -> uploadToBc($content_url)){
            return self::sendMessageJson(SessionMessage::getMess());
        }
        //解析文档内容
        $text = ApiOfficeToText::OfficeToText($content_url);
        if(mb_strlen($text,'utf-8') > 20000){
            $text = mb_substr($text,0,20000,'utf-8');
        }

        $apiCase = new ApiCase();
        //判断案例评审类型/获取评委
        list($rater_type, $rater_account_id) = $apiCase->checkCaseReviewType($user_account_id, $dept_id, $line, $charge);
        //var_dump($rater_type);
        //var_dump($rater_account_id);exit;
        $params = [
            'rater_account_id' => $rater_account_id,
            'rater_type' => $rater_type,
            'title' => $title,
            'content_url' => $content_url,
            'content_name' => $content_name,
            'content_id' => $content_id,
            'content' => $text?$text:'',
            'cover_img' => $cover_img,
            'line' => $line,
            'charge' => $charge,
            'dept_id' => $dept_id,
            'status' => $status,
        ];

        $result = $apiCase->editCase($case_id, $user_account_id, $params, $files, $labels);
        if($result) return parent::sendMessageJson('编辑案例成功', parent::$CODE_SUC);
        return parent::sendMessageJson('编辑案例失败');
    }



    /**PC获取用户所属条线
     * @return
     */
    public function actionLine()
    {
        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiCase = new ApiCase();
        $list = $apiCase->getLineList($user_account_id);
        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $list];
    }

    /**PC获取用户所部门
     * @return string
     */
    public function actionDept()
    {
        $line = Yii::$app->request->get('line');
        if(!$line) return  ['code' => self::$CODE_ERR, 'msg' => '案例条线不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $apiCase = new ApiCase();
        $list = $apiCase->getDeptList($user_account_id, $line);
        //var_dump(1111);exit;
        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $list];
    }

    /**PC获取领域/分管
     * @return string
     */
    public function actionCharge()
    {
        $line = Yii::$app->request->get('line');
        if(!$line) return  ['code' => self::$CODE_ERR, 'msg' => '案例条线不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269675;


        //if($line == 2 || $line == 3) return  ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => []];

        $apiCase = new ApiCase();
        //判断用户类型
        $dept = CpicUserDept::find()->andWhere(['user_account_id' => $user_account_id, 'disabled' => 0])->groupBy('job')->asArray()->all();
        if($dept && count($dept) == 1 && strpos($dept[0]['job'], '分公司班子成员') !== false && $line != 2){
            $list = $apiCase->getChargeList($line);
        }else{
            $list = [];
        }
        //var_dump(1111);exit;
        return ['code' => self::$CODE_SUC, 'msg' => 'successful', 'data' => $list];
    }


    /**用户撤回案例/提交案例
     * @return array
     */
    public function actionRecall()
    {
        $case_id = Yii::$app->request->get('case_id');
        $status = Yii::$app->request->get('status');
        if(!$case_id) return  ['code' => self::$CODE_ERR, 'msg' => '案例ID不能为空'];
        if(!$status) return  ['code' => self::$CODE_ERR, 'msg' => '案例状态不能为空'];

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;


        //判断案例能否撤回
        if($status == 2){
            $case = CpicCase::findOne($case_id);
            if($case['status'] != 1) return ['code' => self::$CODE_ERR, 'msg' => '该案例不能撤回'];
            $msg = '案例撤回';
            $new_status = 0;
        }else{
            $msg = '案例提交';
            $new_status = $status;
        }

        $apiCase = new ApiCase();
        $result = $apiCase->auditCase($user_account_id, $case_id, $new_status);
        if($result) return parent::sendMessageJson($msg.'成功', parent::$CODE_SUC);
        return parent::sendMessageJson($msg.'失败');
    }


}
