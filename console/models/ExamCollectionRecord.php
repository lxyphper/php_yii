<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "exam_collection_record".
 *
 * @property int $id
 * @property int $collection_id 题集id
 * @property int $student_id 用户id
 * @property int $start_time 开始时间
 * @property int $end_time 结束时间
 * @property int $total 题目总数
 * @property int $correct 正确数
 * @property int $status 状态：1未完成 2已完成
 * @property string $rate 正确率
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ExamCollectionRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'exam_collection_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['collection_id', 'student_id', 'start_time', 'end_time', 'total', 'correct', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['rate'], 'string', 'max' => 50],
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
            'student_id' => 'Student ID',
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'total' => 'Total',
            'correct' => 'Correct',
            'status' => 'Status',
            'rate' => 'Rate',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ExamCollectionRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ExamCollectionRecordQuery(get_called_class());
    }
}
