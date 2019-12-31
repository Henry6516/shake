<?php
/**腾讯文智api类
 *
 * 调用示例
 * (new ApiWenzhi()) -> post() -> load() -> TextClassify(["content"=>'helo大家好我是船只博客']);
 * Created by PhpStorm.
 * User: chindor
 * Date: 2017/6/24
 * Time: 15:22
 */

namespace app\api\modules\content\models;

use app\models\Helper\SessionMessage;

include __DIR__.'/qcloudapi-sdk-php-master/src/QcloudApi/QcloudApi.php';


class ApiWenzhi
{
   const MAX_TXT_LENGTH = 20000;  //文智解析支持的最多字数为22000字
   public $config = [
       'SecretId'       => '',
       'SecretKey'      => '',
       'RequestMethod'  => 'GET',
       'DefaultRegion'  => 'gz',
   ];

   public $wenzhi;



    /**根据文本内容调用文智sdk返回 标签列表
     * @param $text            文本内容
     * @return array|bool      成功返回标签列表 失败返回false
     */
    public static function getLabel($doc)
    {
        //解析pdf或者doc中的文本内容
        $text = ApiOfficeToText::OfficeToText($doc);
        if($text === false){
            return false;
        }else if(empty($text)){
            $text = 'a';
            // SessionMessage::setMess(ApiMessage::EMPTY_CONTENT);
            // return false;
        }

        //用文智api处理文本内容 返回分类标签
        // $res = (new self()) -> post() -> load() -> TextClassify(["content"=>self::check_text($text)]);
        // var_dump($res);
        return  self::format_label((new self()) -> post() -> load() -> TextClassify(["content"=>self::check_text($text)]));
    }

    /**进行初始化配置
     * ApiWenzhi constructor.
     * @param $config
     */
   public function __construct($config=[])
   {
       if(!empty($config)){
           foreach($config as $k => $v){
               if(isset($this -> config[$k])){
                   $this -> config[$k] = $v;
               }
           }
       }
       $this -> config['SecretId'] = empty($this->config['SecretId'])?\yii::$app->params['tencentSecretId']:'';
       $this -> config['SecretKey'] = empty($this->config['SecretKey'])?\yii::$app->params['tencentSecretKey']:'';
       return $this;
   }

    /**修改为post提交
     * @return $this
     */
   public function post()
   {
       $this -> config['RequestMethod'] = 'post';
       return $this;
   }

    /**修改为get提交
     * @return $this
     */
   public function get()
   {
       $this -> config['RequestMethod'] = 'get';
       return $this;
   }

    /**加载文智类
     * @return bool
     */
   public function load()
   {
       return $this -> wenzhi = \QcloudApi::load(\QcloudApi::MODULE_WENZHI, $this -> config);
   }

    /**检查从pdf或者doc解析出来的字数是否超出了MAX_TXT_LENGTH的值,并做相应的处理
     * @param $txt
     * @return bool|string
     */
    public static function check_text($txt)
    {
        $txt = preg_replace('/\s+|[a-zA-Z\/\*\=\r\n]||/','',$txt);
        if(mb_strlen($txt,'utf-8') >= self::MAX_TXT_LENGTH){
            return mb_substr($txt,0,self::MAX_TXT_LENGTH,'utf-8');
        }
        return $txt;
    }

    /**解析从文智api分类标签获取过来的数组
     * @param $data
     * Array
    (
        [codeDesc] => Success
        [classes] => Array
        (
            [1] => Array
            (
                [class] => 幽默搞笑
                [class_num] => 47
                [conf] => 0.298
            )
        )

    )
     * @return array|bool    返回格式化的标签 ['a','b','c']或者false
     */
   public static function format_label($data)
   {
        if(empty($data)){
            return [];
        }

        if($data['codeDesc'] == 'Success'){
            if(!empty($data['classes'])){
                $arr = array_column($data['classes'],'class');

                if($index = array_search('未分类',$arr)){
                    if($index == 0){
                        array_shift($arr);
                    }else{
                        $tmp = [];
                        foreach($arr as $k => $v){
                            if($v != '未分类'){
                                $tmp[] = $v;
                            }
                        }
                        $arr = $tmp;
                    }
                }else if($index == 0){
                    array_shift($arr);
                }

                return $arr;
            }
            return [];
        }else{
            return [];
        }
   }


}