<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\api\modules\content\models\ApiMessage;
use app\models\Helper\SessionMessage;
use app\models\Helper\Upload_helper;
use app\models\UploadFile;
use Yii;

class UploadController extends ApiController
{
    public function init()
    {
        parent::init();
    }

    /**上传封面图片
     * @return array
     */
    public function actionUpload_img()
    {
        $post = \yii::$app -> request->post();

        if (isset($_FILES['image']) == false) return parent::sendMessageJson('上传图片不能为空');

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $data['cover_img'] = UploadFile::file($_FILES['image'], 'image', $user_account_id);
        //var_dump($res);exit;

        return ['code' => parent::$CODE_SUC, 'data' => $data, 'msg' => '上传成功'];


        /*$upload = new Upload_helper();
        //配置上传属性
        $upload->config(['allow_doc_type' => ['jpg', 'png', 'gif']]);
        if(!$upload -> upload_doc($user_account_id, 'image')){
            return self::sendMessageJson(SessionMessage::getMess());
        }else{
            return self::sendMessageJson(
                ApiMessage::OPERATE_SUCCESS,
                    self::$CODE_SUC,
                    ['cover_img'=>mb_convert_encoding(SessionMessage::getMess('doc_name'),'utf-8','GB2312,UTF-8,GBK')]
            );
        }*/
    }

    /**上传附件
     * @return array
     */
    public function actionUpload_file()
    {
        $post = \yii::$app -> request->post();

        //验证用户是否登录
        $res = self::verifyAuth();
        if(!$res) return ['code' => self::$CODE_GRANT_FAILED, 'msg' => '授权验证未通过'];
        $user_account_id = $res;

        $upload = new Upload_helper();
        //配置上传属性
        $upload->config(
            [
                'allow_doc_type' => [],
                'maxsize' => 100 * 1024 * 1024,
                'upload_max_size' => 100,
                'post_max_size' => 100,
            ]
        );
        if(!$upload -> upload_doc($user_account_id, 'file')){
            return self::sendMessageJson(SessionMessage::getMess());
        }else{
            $file_url = SessionMessage::getMess('doc_name');
            //var_dump($file_url);exit;
            if(is_array($file_url) && $file_url){
                foreach ($file_url as &$v){
                    $v['origin_name'] = mb_convert_encoding($v['origin_name'], 'utf-8', 'GB2312, UTF-8, GBK');
                    //$v['file'] = mb_convert_encoding($v['origin_name'], 'utf-8', 'GB2312, UTF-8, GBK');
                }
            }else{
                $file_url = mb_convert_encoding($file_url, 'utf-8', 'GB2312, UTF-8, GBK');
            }
            //var_dump($file_url);exit;
            return self::sendMessageJson(ApiMessage::OPERATE_SUCCESS, self::$CODE_SUC, ['file'=>$file_url]);
        }
    }








}
