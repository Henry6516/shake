<?php

namespace app\api\components;

class Auth
{
    private static $_instance = NULL;
    private static $_access_code = "app.com_2015_1_AAA(";//访问密钥
    private static $_encrypt_code = "app.com_2015_1_!AAA%";//用户密钥

    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /*
     * 生成用户登陆标识token
     * @param $userExists array 登录用户信息
     * return string
     */
    public function createToken($userExists)
    {
        if (empty($userExists)) {
            return false;
        }
        return md5(sha1($userExists . self::$_encrypt_code));
    }

    /*
     * 验证用户
     *
     */
    public function verifyToken($userExists, $token = '')
    {
        return md5(sha1($userExists . self::$_encrypt_code)) == $token ? true : false;
    }



}