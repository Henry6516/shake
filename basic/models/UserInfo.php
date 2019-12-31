<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;


/**
 * This is the model class for table "userInfo".
 *
 * @property integer $id
 * @property string $openid
 * @property string $session_key
 * @property string $unionid
 * @property string $avatar
 * @property string $nickName
 * @property string $gender
 * @property string $country
 * @property string $province
 * @property string $city
 * @property string $language
 * @property string $num
 * @property string $createDate
 * @property string $updateDate
 */
class UserInfo extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'userInfo';
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
            [['openid'], 'required'],
            [['gender','num'], 'integer'],
            [['openid', 'session_key','unionid','avatar','nickName','country','province','city','language'], 'string'],
            [[ 'createDate', 'updateDate',],'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'openid' => 'openid',
            'session_key' => 'session_key',
            'unionid' => 'unionid'
        ];
    }

}
