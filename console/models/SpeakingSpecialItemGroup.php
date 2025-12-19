<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_special_item_group".
 *
 * @property int $id
 * @property int $type 分类：1、app 2、lms
 * @property string $title 标题
 * @property string $desc 描述
 * @property string $tips 提示信息
 * @property int $topic 话题id
 * @property int $weight 排序权重
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingSpecialItemGroup extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_special_item_group';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'topic', 'weight', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title'], 'string', 'max' => 100],
            [['desc'], 'string', 'max' => 500],
            [['tips'], 'string', 'max' => 1000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'title' => 'Title',
            'desc' => 'Desc',
            'tips' => 'Tips',
            'topic' => 'Topic',
            'weight' => 'Weight',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingSpecialItemGroupQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingSpecialItemGroupQuery(get_called_class());
    }
}
