<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "statistics_day_speaking".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property int $day_time 每日凌晨时间戳
 * @property int $part 环节id
 * @property int $duration 总时长
 * @property int $num 总题数
 * @property float $grammar 语法预估成绩
 * @property float $vocabulary 词汇丰富度预估成绩
 * @property float $proficient 熟练度预估成绩
 * @property float $pron_fluency 流利度预估成绩
 * @property float $pron_accuracy 发音准确度预估成绩
 */
class StatisticsDaySpeaking extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'statistics_day_speaking';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'day_time', 'part', 'duration', 'num'], 'integer'],
            [['grammar', 'vocabulary', 'proficient', 'pron_fluency', 'pron_accuracy'], 'number'],
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
            'part' => 'Part',
            'duration' => 'Duration',
            'num' => 'Num',
            'grammar' => 'Grammar',
            'vocabulary' => 'Vocabulary',
            'proficient' => 'Proficient',
            'pron_fluency' => 'Pron Fluency',
            'pron_accuracy' => 'Pron Accuracy',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDaySpeakingQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StatisticsDaySpeakingQuery(get_called_class());
    }
}
