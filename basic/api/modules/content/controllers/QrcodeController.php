<?php

namespace app\api\modules\content\controllers;


use app\api\components\ApiController;
use app\models\UploadFile;
use dosamigos\qrcode\lib\Enum;
use dosamigos\qrcode\QrCode;
use Yii;

class QrcodeController extends ApiController
{
    /**
     * 生成二维码
     *
     * @return array
     */
    public function actionCreate()
    {
        $case_id = Yii::$app->request->get('case_id');
        if ($case_id === null) return parent::sendMessageJson('案例ID不能为空');

        $code = '9'.str_pad($case_id, 5, 0, STR_PAD_LEFT);
        //var_dump($code);exit;
        $text = Yii::$app->params['img_host'].'/wap/preview.html?value='.base64_encode('info_code='.$code);

        $path_model = 'qrcode';
        $file_name = $code.'.png';

        $path_arr = UploadFile::savePath($path_model);

        //var_dump($path_arr);exit;
        QrCode::png($text, $path_arr['path'].$file_name, Enum::QR_ECLEVEL_H, 18, 2);

        return ['code'=>parent::$CODE_SUC, 'data'=>['qrcode'=>Yii::$app->params['img_host'].$path_arr['file'].'/'.$file_name], 'msg' => ''];
    }
}
