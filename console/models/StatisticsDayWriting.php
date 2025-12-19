<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "statistics_day_writing".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property int $day_time 每日零点时间戳
 * @property int $task1_duration 小作文总时长
 * @property int $task2_duration 大作文总时长
 * @property int $task1_num 小作文总题数
 * @property int $task2_num 大作文总题数
 * @property float $task1_ta 小作文ta预估成绩
 * @property float $task1_cc 小作文cc预估成绩
 * @property float $task1_lr 小作文lr预估成绩
 * @property float $task1_gra 小作文gra预估成绩
 * @property float $task2_ta 大作文ta预估成绩
 * @property float $task2_cc 大作文cc预估成绩
 * @property float $task2_lr 大作文lr预估成绩
 * @property float $task2_gra 大作文gra预估成绩
 */
class StatisticsDayWriting extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'statistics_day_writing';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'day_time', 'task1_duration', 'task2_duration', 'task1_num', 'task2_num'], 'integer'],
            [['task1_ta', 'task1_cc', 'task1_lr', 'task1_gra', 'task2_ta', 'task2_cc', 'task2_lr', 'task2_gra'], 'number'],
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
            'task1_duration' => 'Task1 Duration',
            'task2_duration' => 'Task2 Duration',
            'task1_num' => 'Task1 Num',
            'task2_num' => 'Task2 Num',
            'task1_ta' => 'Task1 Ta',
            'task1_cc' => 'Task1 Cc',
            'task1_lr' => 'Task1 Lr',
            'task1_gra' => 'Task1 Gra',
            'task2_ta' => 'Task2 Ta',
            'task2_cc' => 'Task2 Cc',
            'task2_lr' => 'Task2 Lr',
            'task2_gra' => 'Task2 Gra',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDayWritingQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StatisticsDayWritingQuery(get_called_class());
    }
}
