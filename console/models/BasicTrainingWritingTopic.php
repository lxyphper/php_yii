<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_writing_topic".
 *
 * @property int $id
 * @property string $name 名称
 * @property string $name_en 英文名称
 * @property int $status 状态：1正常 2禁用
 * @property int $weight 排序权重
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingWritingTopic extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_writing_topic';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status', 'weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 100],
            [['name_en'], 'string', 'max' => 500],
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
            'name_en' => 'Name En',
            'status' => 'Status',
            'weight' => 'Weight',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingTopicQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingWritingTopicQuery(get_called_class());
    }
}
