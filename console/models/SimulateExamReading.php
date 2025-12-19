<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "simulate_exam_reading".
 *
 * @property int $id
 * @property int $record_id 模考记录id
 * @property int $student_id
 * @property int $paper_group_id 试卷分组id
 * @property int $is_confirm 是否确认：1是 2否
 * @property int $status 状态：1未开始 2未完成 3已完成 4已评分
 * @property float $score 得分
 * @property int $surplus_time 已使用时间
 * @property string $answer 提交答案json
 * @property string $correct 答对题号json
 * @property string $lang 语言
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class SimulateExamReading extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'simulate_exam_reading';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['record_id', 'student_id', 'paper_group_id', 'is_confirm', 'status', 'surplus_time', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['score'], 'number'],
            [['answer'], 'string', 'max' => 2000],
            [['correct'], 'string', 'max' => 500],
            [['lang'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'record_id' => 'Record ID',
            'student_id' => 'Student ID',
            'paper_group_id' => 'Paper Group ID',
            'is_confirm' => 'Is Confirm',
            'status' => 'Status',
            'score' => 'Score',
            'surplus_time' => 'Surplus Time',
            'answer' => 'Answer',
            'correct' => 'Correct',
            'lang' => 'Lang',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamReadingQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SimulateExamReadingQuery(get_called_class());
    }
}
