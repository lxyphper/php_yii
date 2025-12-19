<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "simulate_exam_speaking".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property int $record_id 模考id
 * @property int $paper_group_id 模考试卷id
 * @property int $status 当前状态：1、part1 2、part2 3、part3 4、完成 5、生成报告中 6、已出报告
 * @property int $duration 练习时长
 * @property string $part1_topic part1话题ids
 * @property int $part2_topic part2&3话题id
 * @property string $part1_asr_info part1腾讯评分结果
 * @property string $part2_asr_info part2腾讯评分结果
 * @property string $part3_asr_info part3腾讯评分结果
 * @property string $report_score 报告得分信息
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class SimulateExamSpeaking extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'simulate_exam_speaking';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'record_id', 'paper_group_id', 'status', 'duration', 'part2_topic', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['part1_topic'], 'string', 'max' => 100],
            [['part1_asr_info', 'part2_asr_info', 'part3_asr_info'], 'string', 'max' => 500],
            [['report_score'], 'string', 'max' => 1000],
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
            'record_id' => 'Record ID',
            'paper_group_id' => 'Paper Group ID',
            'status' => 'Status',
            'duration' => 'Duration',
            'part1_topic' => 'Part1 Topic',
            'part2_topic' => 'Part2 Topic',
            'part1_asr_info' => 'Part1 Asr Info',
            'part2_asr_info' => 'Part2 Asr Info',
            'part3_asr_info' => 'Part3 Asr Info',
            'report_score' => 'Report Score',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamSpeakingQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SimulateExamSpeakingQuery(get_called_class());
    }
}
