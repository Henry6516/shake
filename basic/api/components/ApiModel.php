<?php
/**
 * 基础数据处理类
 *
 * @author chenfenghua <843958575@qq.com>
 * version v2.0
 */

namespace app\api\components;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;


class ApiModel extends Model
{
	const LIMIT = 10;
    public $connection;
    public function init()
    {
        parent::init();
        $this->connection = Yii::$app->db;
    }


}