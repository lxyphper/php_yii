<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_writing_group".
 *
 * @property int $id
 * @property int $type 类型:1连词成句 2翻译练习 3语法纠错 4句子合并 5句子改写
 * @property int $sub_type 子类型
 * @property int $grammar 语法点
 * @property int $topic 话题
 * @property string $title 标题
 * @property int $status 状态：1正常 2禁用
 * @property int $difficulty 难度
 * @property int $weight 排序权重
 * @property string $source_id 数据来源id
 * @property int $done_num
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingWritingGroup extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_writing_group';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'sub_type', 'grammar', 'topic', 'status', 'difficulty', 'weight', 'done_num', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title', 'source_id'], 'string', 'max' => 200],
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
            'sub_type' => 'Sub Type',
            'grammar' => 'Grammar',
            'topic' => 'Topic',
            'title' => 'Title',
            'status' => 'Status',
            'difficulty' => 'Difficulty',
            'weight' => 'Weight',
            'source_id' => 'Source ID',
            'done_num' => 'Done Num',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingGroupQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingWritingGroupQuery(get_called_class());
    }
}
