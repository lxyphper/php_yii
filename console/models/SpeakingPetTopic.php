<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_pet_topic".
 *
 * @property int $id
 * @property string $name 名称
 * @property string $en_name 英文名称
 * @property int $part part：1、B1
 * @property string $img_url 图片地址
 * @property int $create_by 创建人
 * @property int $create_time 创建时间
 * @property int $update_by 更新人
 * @property int $update_time 更新时间
 */
class SpeakingPetTopic extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_pet_topic';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['part', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 100],
            [['en_name', 'img_url'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'en_name' => 'En Name',
            'part' => 'Part',
            'img_url' => 'Img Url',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingPetTopicQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingPetTopicQuery(get_called_class());
    }
}
