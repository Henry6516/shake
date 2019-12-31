<?php

namespace app\api\components;

class Des
{
    const KEY = 'app.com_2015_1_!AAA%';


    /*
    *功能：对字符串进行加密处理
    *参数一：需要加密的内容
    *参数二：密钥
    */
    public static function encrypt($plainText, $key = self::KEY)
    {
        return base64_encode($plainText.md5($key));
        /*$ivSize = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($ivSize, MCRYPT_RAND);
        $encryptText = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $plainText, MCRYPT_MODE_ECB, $iv);
        return trim(base64_encode($encryptText));*/

    }

    /*
    *功能：对字符串进行解密处理
    *参数一：需要解密的密文
    *参数二：密钥
    */
    public static function decrypt($encryptedText, $key = self::KEY)
    {
        $str = base64_decode($encryptedText);
        return str_replace(md5($key),'',$str);
        /*$cryptText = base64_decode($encryptedText);
        $ivSize = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($ivSize, MCRYPT_RAND);
        $decryptText = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), $cryptText, MCRYPT_MODE_ECB, $iv);
        return trim($decryptText);*/
    }

    /*
    *辅助函数
    */
    public static function passport_key($str, $encrypt_key)
    {
        $encrypt_key = md5($encrypt_key);
        $ctr = 0;
        $tmp = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
            $tmp .= $str[$i] ^ $encrypt_key[$ctr++];
        }
        return $tmp;
    }
}

?>