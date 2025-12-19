<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_listening_record".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property string $sub_answer 用户提交答案
 * @property string $result 答案对错结果
 * @property int $is_correct 是否正确：1正确 2错误
 * @property int $correct
 * @property int $total
 * @property int $question_id 题目id
 * @property int $status 状态：1未批改 2已批改
 * @property string $result_str 批改结果
 * @property int $colour 批改结果展示：1对 2错
 * @property string $analysis 批改解析
 * @property int $is_analysis 是否深度解析：1是 2否
 * @property int $collection_record_id 题集练习记录id
 * @property int $version 版本
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingListeningRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_listening_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'is_correct', 'correct', 'total', 'question_id', 'status', 'colour', 'is_analysis', 'collection_record_id', 'version', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['sub_answer', 'analysis'], 'string', 'max' => 1000],
            [['result', 'result_str'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'student_id' => 'Student ID',
            'sub_answer' => 'Sub Answer',
            'result' => 'Result',
            'is_correct' => 'Is Correct',
            'correct' => 'Correct',
            'total' => 'Total',
            'question_id' => 'Question ID',
            'status' => 'Status',
            'result_str' => 'Result Str',
            'colour' => 'Colour',
            'analysis' => 'Analysis',
            'is_analysis' => 'Is Analysis',
            'collection_record_id' => 'Collection Record ID',
            'version' => 'Version',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingListeningRecordQuery(get_called_class());
    }
}
