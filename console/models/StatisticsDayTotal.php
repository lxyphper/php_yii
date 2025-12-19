<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "statistics_day_total".
 *
 * @property int $id
 * @property int $student_id 用户id
 * @property int $day_time 每日零点时间戳
 * @property int $all_duration 总时长
 * @property int $all_num 总题数
 * @property int $listening_special_duration 听力专项总时长
 * @property int $listening_special_num 听力专项总题数
 * @property float $listening_special_rate 听力专项平均正确率
 * @property int $listening_paper_duration 听力真题总时长
 * @property int $listening_paper_num 听力真题总题数
 * @property int $listening_paper_correct 听力真题正确数
 * @property float $listening_paper_rate 听力真题平均正确率
 * @property int $speaking_special_duration 口语专项总时长
 * @property int $speaking_special_num 口语专项总题数
 * @property float $speaking_special_rate 口语专项平均正确率
 * @property int $speaking_paper_duration 口语真题总时长
 * @property int $speaking_paper_num 口语真题总题数
 * @property float $speaking_paper_rate 口语真题平均正确率
 * @property int $reading_special_duration 阅读专项总时长
 * @property int $reading_special_num 阅读专项总题数
 * @property float $reading_special_rate 于都专项平均正确率
 * @property int $reading_paper_duration 阅读真题总时长
 * @property int $reading_paper_num 阅读真题总题数
 * @property int $reading_paper_correct 阅读真题正确数
 * @property float $reading_paper_rate 阅读真题平均正确率
 * @property int $writing_special_duration 写作专项总时长
 * @property int $writing_special_num 写作专项总题数
 * @property float $writing_special_rate 写作专项平均正确率
 * @property int $writing_paper_duration 写作真题总时长
 * @property int $writing_paper_num 写作真题总题数
 * @property float $writing_paper_score 写作真题平均得分
 */
class StatisticsDayTotal extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'statistics_day_total';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'day_time', 'all_duration', 'all_num', 'listening_special_duration', 'listening_special_num', 'listening_paper_duration', 'listening_paper_num', 'listening_paper_correct', 'speaking_special_duration', 'speaking_special_num', 'speaking_paper_duration', 'speaking_paper_num', 'reading_special_duration', 'reading_special_num', 'reading_paper_duration', 'reading_paper_num', 'reading_paper_correct', 'writing_special_duration', 'writing_special_num', 'writing_paper_duration', 'writing_paper_num'], 'integer'],
            [['listening_special_rate', 'listening_paper_rate', 'speaking_special_rate', 'speaking_paper_rate', 'reading_special_rate', 'reading_paper_rate', 'writing_special_rate', 'writing_paper_score'], 'number'],
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
            'all_duration' => 'All Duration',
            'all_num' => 'All Num',
            'listening_special_duration' => 'Listening Special Duration',
            'listening_special_num' => 'Listening Special Num',
            'listening_special_rate' => 'Listening Special Rate',
            'listening_paper_duration' => 'Listening Paper Duration',
            'listening_paper_num' => 'Listening Paper Num',
            'listening_paper_correct' => 'Listening Paper Correct',
            'listening_paper_rate' => 'Listening Paper Rate',
            'speaking_special_duration' => 'Speaking Special Duration',
            'speaking_special_num' => 'Speaking Special Num',
            'speaking_special_rate' => 'Speaking Special Rate',
            'speaking_paper_duration' => 'Speaking Paper Duration',
            'speaking_paper_num' => 'Speaking Paper Num',
            'speaking_paper_rate' => 'Speaking Paper Rate',
            'reading_special_duration' => 'Reading Special Duration',
            'reading_special_num' => 'Reading Special Num',
            'reading_special_rate' => 'Reading Special Rate',
            'reading_paper_duration' => 'Reading Paper Duration',
            'reading_paper_num' => 'Reading Paper Num',
            'reading_paper_correct' => 'Reading Paper Correct',
            'reading_paper_rate' => 'Reading Paper Rate',
            'writing_special_duration' => 'Writing Special Duration',
            'writing_special_num' => 'Writing Special Num',
            'writing_special_rate' => 'Writing Special Rate',
            'writing_paper_duration' => 'Writing Paper Duration',
            'writing_paper_num' => 'Writing Paper Num',
            'writing_paper_score' => 'Writing Paper Score',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StatisticsDayTotalQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StatisticsDayTotalQuery(get_called_class());
    }
}
