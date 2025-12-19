<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_reading_record".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property int $relation_id 关联记录id
 * @property string $sub_answer 用户提交答案
 * @property int $correct 正确数
 * @property int $total 总数
 * @property float $score 得分
 * @property int $question_id 题目id
 * @property int $group_id 题目分组id
 * @property int $status 状态：1未批改 2已批改
 * @property int $all_status 多步状态：1未完成 2完成
 * @property int $step 当前所在步数
 * @property int $start_time 开始时间
 * @property int $correct_time 批改时间
 * @property string $result 批改结果
 * @property int $colour 批改结果展示：1对 2错 3得分偏低
 * @property string $analysis 批改解析
 * @property int $is_analysis 是否深度解析：1是 2否
 * @property int $collection_record_id 题集练习id
 * @property int $version 版本
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingReadingRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_reading_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'relation_id', 'correct', 'total', 'question_id', 'group_id', 'status', 'all_status', 'step', 'start_time', 'correct_time', 'colour', 'is_analysis', 'collection_record_id', 'version', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['score'], 'number'],
            [['sub_answer', 'analysis'], 'string', 'max' => 1000],
            [['result'], 'string', 'max' => 200],
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
            'relation_id' => 'Relation ID',
            'sub_answer' => 'Sub Answer',
            'correct' => 'Correct',
            'total' => 'Total',
            'score' => 'Score',
            'question_id' => 'Question ID',
            'group_id' => 'Group ID',
            'status' => 'Status',
            'all_status' => 'All Status',
            'step' => 'Step',
            'start_time' => 'Start Time',
            'correct_time' => 'Correct Time',
            'result' => 'Result',
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
     * @return BasicTrainingReadingRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingReadingRecordQuery(get_called_class());
    }
}
