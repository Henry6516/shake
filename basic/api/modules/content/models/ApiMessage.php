<?php
/**
 * Created by PhpStorm.
 * User: chindor
 * Date: 2017/6/23
 * Time: 18:50
 */

namespace app\api\modules\content\models;


class ApiMessage
{
    const OPERATE_SUCCESS = '操作成功';
    const OPERATE_FAIL    = '操作失败';

    //文件上传
    const EMPTY_FILE =  '请上传文件';
    const EMPTY_CONTENT = '文本内容为空';

    //案例详情
    const EMPTY_ACCOUNT_ID = '用户id不能为空';
    const EMPTY_CASE_ID = '请选择你要查看的案例';
    const CHECK_MSG = '案例正在审核中';
}