<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "statistics_day_listening".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property int $day_time 每日零点时间戳
 * @property int $question_type 题型id
 * @property int $duration 时长
 * @property int $total_num 总题数
 * @property int $total_correct 正确数
 * @property float $total_rate 平均正确率
 */
class StatisticsDayListening extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'statistics_day_listening';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'day_time', 'question_type', 'duration', 'total_num', 'total_correct'], 'integer'],
            [['total_rate'], 'number'],
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
            'day_time' => 'Day Time',
            'question_type' => 'Question Type',
            'duration' => 'Duration',
            'total_num' => 'Total Num',
            'total_correct' => 'Total Correct',
            'total_rate' => 'Total Rate',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDayListeningQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StatisticsDayListeningQuery(get_called_class());
    }
}
