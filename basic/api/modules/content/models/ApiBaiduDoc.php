<?php
/**百度api文档云服务类
 * Created by PhpStorm.
 * User: chindor
 * Date: 2017/6/26
 * Time: 17:29
 */

namespace app\api\modules\content\models;

use app\api\modules\content\models\bce_doc_sdk_php\BcSign;  //签名类
use app\models\Helper\Curl;                                 //curl辅助类
use app\models\Helper\SessionMessage;
use BaiduBce\Services\Bos\BosClient;

include __DIR__.'/bce_doc_sdk_php/BaiduBce.phar';

class ApiBaiduDoc extends BcSign
{
    //提示信息常量
    const NOT_VALID_FILE = '不是一个有效的文件';
    const REGIST_BEFORE_PUSH_TO_BOS = '上传bos之前请先注册文档';
    const INVALID_FILE_PAH = '不是有效的文件路径';
    const REGIST_FIRST = '请先注册并上传至bos';
    const NOT_FIND_DOC_STATUS = '文档内容加载失败';
    const MISS_DOCUMENT_ID = '文档不能为空';

    //基本配置项
    public $config = [
        'ak'          => '',    //修改成自己的BCE AK
        'sk'          => '',    //修改成自己的BCE SK
        'endpoint'    => 'doc.baidubce.com',
        'method'      => 'POST',
        'path'        => '/v2/document',
    ];

    //上传到百度云存储的各个状态
    private $STATUS = [
      'UPLOADING'  => 1,
      'PROCESSING' => 2,
      'PUBLISHED'  => 3,
      'FAILED'     => -1
    ];

    //文档和bucket信息存储
    private $documentId = '';    //文档id
    private $bucket = '';    //bucket
    private $object = '';    //百度云存储对象
    private $bosEndpoint=''; //百度云存储地点
    private $file_path = ''; //文件路径 包含路径的文件名(/images/doc/1/测试文档.docx)

    public function init($config=[])
    {
        $config = array_merge($this -> config,$config);
        $config['ak'] = empty($config['ak']) ? \yii::$app->params['baiduAccessKeyID']:$config['ak'];
        $config['sk'] = empty($config['sk']) ? \yii::$app->params['baiduSecretAccessKey']:$config['sk'];
        $this -> config = $config;
        parent::__init();
        return $this;
    }


    /**
     * 上传文档到百度云的入口方法
     */
    public function uploadToBc($file_name='')
    {
        if(empty($file_name)){
            SessionMessage::setMess(self::NOT_VALID_FILE);
            return false;
        }

        $this -> file_path = $file_name;

        //第一步创建文档
        if(!$this -> registDoc($file_name)){
            return false;
        }
        //第二步上传到bos
        if(!$this -> pushBos()){
            return false;
        }

        //第三步发布文档
        $this -> publishDoc();
        return $this -> documentId;
    }

    /**将注册过后的文档上传至bos
     * @return bool|mixed
     */
    public function pushBos()
    {
        if(!$path = self::parse_path($this->file_path)){
            SessionMessage::setMess(self::INVALID_FILE_PAH);
            return false;
        }

        if(empty($this -> documentId) || empty($this->bucket) || empty($this->object) || empty($this->bosEndpoint)){
            SessionMessage::setMess(self::REGIST_BEFORE_PUSH_TO_BOS);
            return false;
        }

        date_default_timezone_set('UTC');
        $BOS_TEST_CONFIG =
               array(
                    'credentials'     => array(
                        'accessKeyId'     =>  $this -> config['ak'],
                        'secretAccessKey' =>  $this -> config['sk'],
                     ),
                'endpoint' => 'http://bj.bcebos.com',
            );
        $STDERR = fopen('php://stderr', 'w+');
        $__handler = new \Monolog\Handler\StreamHandler($STDERR, \Monolog\Logger::DEBUG);
        $__handler->setFormatter(
            new \Monolog\Formatter\LineFormatter(null, null, false, true)
        );
        \BaiduBce\Log\LogFactory::setInstance(
            new \BaiduBce\Log\MonoLogFactory(array($__handler))
        );
        \BaiduBce\Log\LogFactory::setLogLevel(\Psr\Log\LogLevel::DEBUG);

        $client = new BosClient($BOS_TEST_CONFIG);
        $res = $client -> putObjectFromFile($this->bucket,$this->object,$path);
        return $res;
    }

    /**注册文档
     * @param $file_name   文件名（可以含路径）
     * @return bool|mixed  失败返回false,成功返回数组格式的数据
     */
    public function registDoc($file_name)
    {
        if(!self::parse_path($file_name)){
            SessionMessage::setMess(self::INVALID_FILE_PAH);
            return false;
        }

        $parms = array("register"=>"");
        date_default_timezone_set('UTC');
        $timestamp = date("Y-m-d") . "T" . date("H:i:s") . "Z";
        $host = $this -> config['endpoint'];
        $path = $this -> config['path'];
        $Authorization = $this -> getSigner($host,$this -> config['method'],$path, $parms, $timestamp);
        $parms = $this -> getCanonicalQueryString($parms);
        $url = "http://".$host.$path."?".$parms;
        $head = array(
            "Content-Type:application/json",
            "Authorization:{$Authorization}",
            "x-bce-date:{$timestamp}",
        );

        //传入需要注册的文档格式和名称
        if(!$arr = $this -> getNameByPath($file_name)){
            SessionMessage::setMess(self::NOT_VALID_FILE);
            return false;
        }

        $this -> file_path = $file_name;
        $data = array("title"=>$arr['file_name'],"format"=>$arr['extension']);
        $res = Curl::request($url,$this->config['method'],json_encode($data),$head);
        //如果失败返回false，并存储错误信息
        if(!empty($res['requestId'])){
            SessionMessage::setMess($res['message']);
            return false;
        }


        foreach($res as $k => $v){
            if(isset($this -> $k)){
                $this -> $k = $v;
            }
        }
        return true;
    }

    /**将注册并上传至bos的文档，进行发布
     * @return bool
     */
    public function publishDoc()
    {
        if(empty($this -> documentId) || empty($this->bucket) || empty($this->object) || empty($this->bosEndpoint)){
            SessionMessage::setMess(self::REGIST_FIRST);
            return false;
        }

        $documentId = $this -> documentId;
        $host = $this -> config['endpoint'];
        $path = "/v2/document/".$documentId;
        $method = "PUT";
        $parms = array("publish"=>"");
        date_default_timezone_set('UTC');
        $timestamp = date("Y-m-d") . "T" . date("H:i:s") . "Z";
        $Authorization = $this -> getSigner($host,$method,$path, $parms, $timestamp);

        $parms = $this->getCanonicalQueryString($parms);
        $url = "http://".$host.$path."?".$parms;

        $head = array(
            "Content-Type:text/plain",
            "Authorization:{$Authorization}",
            "x-bce-date:{$timestamp}",
        );

        $res = Curl::request($url,$method,json_encode([]),$head);
        return $res;

    }

    /**取得文档状态的接口
     * @param $documentId   文档id(百度云上的文档id)
     * @return bool|mixed   成功返回文档状态，失败返回false
     */
    public function getDocStatus($documentId='')
    {
        $documentId = empty($documentId) ? (!empty($this -> documentId) ? $this -> documentId: ''): $documentId;
        if(empty($documentId)){
            SessionMessage::setMess(self::MISS_DOCUMENT_ID);
            return false;
        }

        $host = $this -> config['endpoint'];
        $path = "/v2/document/".$documentId;
        $method = $this -> config['method'];
        $parms = array();
        date_default_timezone_set('UTC');
        $timestamp = date("Y-m-d") . "T" . date("H:i:s") . "Z";
        $Authorization = $this -> getSigner($host,$method,$path, $parms, $timestamp);
        $parms = $this->getCanonicalQueryString($parms);
        $url = "http://".$host.$path."?".$parms;

        $head = array(
            "Content-Type:text/plain",
            "Authorization:{$Authorization}",
            "x-bce-date:{$timestamp}",
        );


        $data = array();
        $data_string = json_encode($data);
        $res =  Curl::request($url,$method,$data_string,$head);
        if(empty($res)){
            SessionMessage::setMess(self::NOT_FIND_DOC_STATUS);
            return false;
        }

        if(!empty($res['status'])){
            return $this -> STATUS[$res['status']];
        }
        
        SessionMessage::setMess('文档已损坏');
        return false;

    }

    public function getNameByPath($path)
    {
        if(!empty($path)){
            if(strpos($path,'\\') !== false || strpos($path,'/') !== false){
                $path = str_replace('\\','/',$path);
                $tmp = explode('/',$path);
                $file_name = array_pop($tmp);
            }else{
                $file_name = $path;
            }

            if(strpos($path,'.') !== false){
                $tmp = explode('.',$file_name);
                $name = array_shift($tmp);
                return ['extension'=> $tmp[0] , 'file_name'=>$name];
            }else{
                return false;
            }

        }else{
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
            if(DIRECTORY_SEPARATOR != '/'){
                $file_path =  mb_convert_encoding($file_path,'GB2312','GB2312,UTF-8,GBK');
            }
        }

        if(file_exists($file_path) && is_file($file_path)){
            return $file_path;
        }
        return false;
    }

    public function get()
    {
        $this -> config['method'] = 'GET';
        return $this;
    }

    public function post()
    {
        $this -> config['method'] = 'POST';
        return $this;
    }

    public function __set($name,$val)
    {
        $property_arr = get_class_vars(get_class($this));
        if(!empty($property_arr)){
            if(array_key_exists($name,$property_arr)){
                $this -> $name = $val;
            }
        }
    }
}