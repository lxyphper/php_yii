<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "exam_collection_relation".
 *
 * @property int $id
 * @property int $collection_id 题集id
 * @property int $question_id 题目id
 * @property int $status 状态：1正常 2禁用
 * @property int $weight 排序权重
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ExamCollectionRelation extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'exam_collection_relation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['collection_id', 'question_id', 'status', 'weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'collection_id' => 'Collection ID',
            'question_id' => 'Question ID',
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
     * @return ExamCollectionRelationQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ExamCollectionRelationQuery(get_called_class());
    }
}
