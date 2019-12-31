<?php
/**  案例控制器类
 * Created by PhpStorm.
 * User: chindor
 * Date: 2017/6/23
 * Time: 15:45
 */

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiBaiduDoc;
use app\api\modules\content\models\ApiMessage;
use app\api\modules\content\models\ApiWenzhi;
use app\api\modules\content\models\ApiWork_z;
use app\models\base\CpicCase;
use app\models\base\CpicDeptAuditors;
use app\models\Helper\File;
use app\models\Helper\SessionMessage;
use app\models\Helper\Upload_helper;
use Yii;

class CaseZController extends ApiController
{

    /**(没有点击提交之前的上传)
     * 上传文档
     * @return array   返回提示信息
     */
    public function actionUpload_doc()
    {
        $post = \yii::$app -> request->post();
        
        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        // $user_account_id = '00001';

        $upload = new Upload_helper();
        if(!$upload -> upload_doc($user_account_id)){
            return self::sendMessageJson(SessionMessage::getMess());
        }else{

            //根据文智api获取标签
            $label = ApiWenzhi::getLabel(SessionMessage::getMess('doc_name')[0]['file']);
            if($label===false){
                return self::sendMessageJson(SessionMessage::getMess());
            }

            return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC,['url'=>File::conver_name_to_utf8(SessionMessage::getMess('doc_name')[0]['file']),'label'=>$label,'origin_name'=>SessionMessage::getMess('doc_name')[0]['origin_name']]);
        }
    }

    /**删除文档
     * @return array
     */
    public function actionDeleteFile()
    {
        $post = \yii::$app -> request->post();

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        Upload_helper::delete_file($post['url']);
        return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC);
    }

    /**重新上傳文檔
     * @return array
     */
    public function actionReuploadFile()
    {
        $post = \yii::$app -> request->post();

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        if(!(new Upload_helper()) -> reuploadFile($post['old_file_path'],$user_account_id)){
            return self::sendMessageJson(SessionMessage::getMess());
        }else{

            if(!$label = ApiWenzhi::getLabel(SessionMessage::getMess('doc_name')[0]['file'])){
                return self::sendMessageJson(SessionMessage::getMess());
            }

            return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC,['url'=>File::conver_name_to_utf8(SessionMessage::getMess('doc_name')[0]['file']),'label'=>$label,'origin_name'=>SessionMessage::getMess('doc_name')[0]['origin_name']]);
        }
    }

    /**案例详情的获取
     * @return array|mixed
     */
    public function actionCaseDetail()
    {
        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;
        //$user_account_id = 269673;

        $get = \yii::$app -> request -> get();

        if(empty($get['case_id'])){
            return self::sendMessageJson(ApiMessage::EMPTY_CASE_ID);
        }
        $res = ApiWork_z::detail($get['case_id'],$user_account_id);
        if($res == -1) return parent::sendMessageJson(ApiMessage::CHECK_MSG,self::$CODE_ERR);
        return parent::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC,$res);
    }

    /**
     *案例详情的获取
     *
     */
    public function actionEdit_detail()
    {
        $get = \yii::$app -> request -> get();

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        if(empty($get['case_id'])){
            return self::sendMessageJson(ApiMessage::EMPTY_CASE_ID);
        }

        return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC,ApiWork_z::edit_detail($get['case_id'],$user_account_id));
    }


    /**文档状态的获取
     * @return array
     */
    public function actionGetDocStatus()
    {
        //需要传 document_id(文档id)
        $get = \yii::$app->request->get();
        if(empty($get['document_id'])){
            if(isset($get['document_id']) && $get['document_id'] == 0){
                return self::sendMessageJson('文档内容加载失败!');
            }
            return self::sendMessageJson('文档不能为空!');
        }

        $res = (new ApiBaiduDoc())->init() -> get()->getDocStatus($get['document_id']);
        if(!$res){
            return self::sendMessageJson(SessionMessage::getMess());
        }

        //文档状态
        //1-上传中 2-处理中 3-已经发布(可以预览) -1-发布失败(不能预览需要重新上传)
        return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC,['status'=>$res]);
    }

    /**优秀案例列表的获取
     * @return array
     */
    public function actionExcellent_case()
    {
       $data = ApiWork_z::fineCase();
       return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC,empty($data)?[]:$data);
    }

    /**普通列表的获取
     * @return array
     */
    public function actionCommon_case()
    {
        $page = \yii::$app->request->get('page',0);
        $data =  ApiWork_z::commonCase($page);
        return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS,self::$CODE_SUC,empty($data)?[]:$data);
    }

}