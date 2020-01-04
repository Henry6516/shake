<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;


/**
 * This is the model class for table "userInfo".
 *
 * @property integer $id
 * @property string $name
 * @property string $status
 * @property string $creator
 * @property string $createDate
 * @property string $startTime
 * @property string $ready
 * @property string $last
 * @property string $endTime
 */
class Game extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'game';
    }

    public function behaviors()
    {
        return [[
            /**
             * TimestampBehavior：
             */
            'class' => TimestampBehavior::className(),
            'createdAtAttribute' => 'createDate',
            'updatedAtAttribute' => 'updateDate',
            'value' => new Expression('NOW()'),
        ],];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            //[['openid'], 'required'],
            [['creator','ready','last'], 'integer'],
            [['name', 'status'], 'string'],
            [[ 'createDate', 'startTime', 'endTime'],'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'status' => 'Status',
        ];
    }

}
