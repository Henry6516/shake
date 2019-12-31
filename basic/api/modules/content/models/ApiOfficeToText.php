<?php
/**解析pdf ,doc 中的内容为根本的类
 * Created by PhpStorm.
 * User: chindor
 * Date: 2017/6/24
 * Time: 17:33
 */

namespace app\api\modules\content\models;


use app\models\Helper\SessionMessage;
use yii\web\Session;

class ApiOfficeToText
{

    const NOT_AN_VALID_PATH = '不是有效的文件!';
    const PDF_TO_TXT = 'pdf转换为txt失败';
    const DOC_TO_TXT = '解析doc时出错';
    const NO_FOUND_XPDF = '没有在服务器上发现xpdf组件';
    const NO_FOUND_ANTIWORD = '没有在服务器上发现antiword组件';
    const NOT_SUPPORT_FILE = '暂不支持该文件类型';
    public static $DOC_EXTENSION = ['docx'];
    public static $PDF_EXTENSION = ['pdf'];

    /**pdf和text的公共調用方法
     * @param string $file   文件名或者路径(含文件名)
     * @return bool|mixed    false或者文件名
     */
    public static function OfficeToText($file = '')
    {
       $arr = explode('.',$file);
       $extension = array_pop($arr);
       if(in_array(strtolower($extension),self::$DOC_EXTENSION)){
           return self::docToText($file);
       }else if(in_array($extension,self::$PDF_EXTENSION )){
           return self::PdfToText($file);
       }else if(strtolower($extension) == 'doc'){
            return self::doc2003ToText($file);
       }else{
           SessionMessage::setMess(self::NOT_AN_VALID_PATH);
           return false;
       }
    }

    /**将word2003 解析word2003中的文档内容
     * [doc2003ToText description]
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public static function doc2003ToText($file)
    {
        if(!$file = self::parse_path($file)){
            SessionMessage::setMess(self::NOT_AN_VALID_PATH);
            return false;
        }

        try{
            if(file_exists('/usr/local/bin/antiword')){
                $content = shell_exec('/usr/local/bin/antiword -m UTF-8.txt '.self::filter_ilegal_charater($file));
            }else{
                throw new \Exception(self::NO_FOUND_ANTIWORD);
                return false;
            }

            if(!empty($content)){

                //说明转换成功了
                $content = preg_replace('/\s+/','',$content);
                return $content;
            }else{
                SessionMessage::setMess(self::DOC_TO_TXT);
                return false;
            }
        }catch(\Exception $e){
            SessionMessage::setMess($e -> getMessage());
            return false;
        }


    }

    /**将doc文件中的内容转为文本
     * @param $file      文件名或者路径(含文件名)
     * @return mixed     返回提取出来的文本
     */
    public static function docToText($file)
    {
        if(!$file = self::parse_path($file)){
            SessionMessage::setMess(self::NOT_AN_VALID_PATH);
            return false;
        }

        $doc = new DocToText();

        //加载docx文件
        if(!$doc -> setDocx($file)){
            SessionMessage::setMess(self::NOT_SUPPORT_FILE);
            return false;
        }

        // 将内容存入$docx变量中
        $text = $doc -> extract();
        return  preg_replace('/\s+/','',$text);
    }

    /**将pdf中的内容解析成文字
     * @param $file         文件名或者路径(含文件名)
     * @return bool|mixed   返回提取出来的文本
     */
    public static function PdfToText($file)
    {
        if(!$file = self::parse_path($file)){
            SessionMessage::setMess(self::NOT_AN_VALID_PATH);
            return false;
        }

        if(DIRECTORY_SEPARATOR == '\\'){

            //在windows上
            try{
                if(file_exists('C:\xpdftest\xpdf\bin32\pdftotext.exe')){
                    shell_exec('C:\xpdftest\xpdf\bin32\pdftotext '.self::filter_ilegal_charater($file));
                    $txt_path = self::changeExtension($file,'txt');
                }else{
                    throw new \Exception(self::NO_FOUND_XPDF);
                }
            }catch(\Exception $e){
                SessionMessage::setMess($e -> getMessage());
                return false;
            }



        }else{

            //在linux上
            try{
                if(file_exists('/usr/local/bin/pdftotext')){
                    shell_exec('/usr/local/bin/pdftotext '.self::filter_ilegal_charater($file));
                    $txt_path = self::changeExtension($file,'txt');
                }else{
                    throw new \Exception(self::NO_FOUND_XPDF);
                    return false;
                }
            }catch(\Exception $e){
                SessionMessage::setMess($e -> getMessage());
                return false;
            }

        }

 
        if(file_exists($txt_path)){

            //说明转换成功了
            $txt = preg_replace('/\s+/','',file_get_contents($txt_path));
            unlink($txt_path);
            return $txt;
        }else{

            SessionMessage::setMess(self::PDF_TO_TXT);
            return false;
        }

    }

    /**解析文件路径的方法
     * @param $file_path   文件名或者文件路径(含文件名)
     * @return bool|string
     */
    public static function parse_path($file_path)
    {
        $base_path = \yii::getAlias('@app');
        if(mb_substr($file_path,0,mb_strlen($base_path,'utf-8'),'utf-8') == $base_path){

            //绝对路径下的文件路径
            $file_path = $file_path;
        }else{

            //相对路径
            $file_path = $base_path.$file_path;
//            $file_path =  mb_convert_encoding($file_path,'GB2312','GB2312,UTF-8,GBK');
        }

        if(file_exists($file_path) && is_file($file_path)){
            return $file_path;
        }
        return false;
    }

    /**切换文件后缀名的方法
     * @param $file               文件名或者文件路径(含文件名)
     * @param string $extension   要切换的后缀
     * @return string
     */
    public static function changeExtension($file,$extension='txt')
    {
        $lastDotIndex = mb_strripos($file,'.',0,'utf-8');
        if($lastDotIndex){
            return mb_substr($file,0,$lastDotIndex+1).$extension;
        }
        return false;
    }

    public static function filter_ilegal_charater($str)
    {
        return quotemeta($str);
    }
}