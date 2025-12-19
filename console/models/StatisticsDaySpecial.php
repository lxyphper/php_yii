<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "statistics_day_special".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property int $day_time 每日零点时间戳
 * @property int $course 科目：1写作 2阅读 3听力 4口语 5大作文审题练习 6小作文审题练习
 * @property int $question_type 题型id
 * @property int $grammar 知识点id
 * @property int $duration 时长
 * @property int $num 总题数
 * @property int $correct 正确题数
 * @property float $rate 平均/总正确率
 */
class StatisticsDaySpecial extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'statistics_day_special';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'day_time', 'course', 'question_type', 'grammar', 'duration', 'num', 'correct'], 'integer'],
            [['rate'], 'required'],
            [['rate'], 'number'],
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
            'course' => 'Course',
            'question_type' => 'Question Type',
            'grammar' => 'Grammar',
            'duration' => 'Duration',
            'num' => 'Num',
            'correct' => 'Correct',
            'rate' => 'Rate',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDaySpecialQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StatisticsDaySpecialQuery(get_called_class());
    }
}
