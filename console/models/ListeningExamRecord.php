<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "listening_exam_record".
 *
 * @property int $id 主键id
 * @property int $paper_id 试卷id
 * @property int $student_id 做题人id
 * @property int $tourist_id 游客id
 * @property int $status 完成状态：1、未完成 2、已完成
 * @property string $essay 正文区json
 * @property string $question 答题区json
 * @property string|null $answer 用户答案json
 * @property string|null $correct_info 答题正确数据
 * @property int $correct 正确数量
 * @property int $total 题目总数
 * @property float $rate 正确率
 * @property int $finished_time 完成时间
 * @property int $duration 做题时长
 * @property int $question_type 题型
 * @property int $total_num 做题总数
 * @property int $correct_num 正确题目总数
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ListeningExamRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'student_id', 'tourist_id', 'status', 'correct', 'total', 'finished_time', 'duration', 'question_type', 'total_num', 'correct_num', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['answer', 'correct_info'], 'safe'],
            [['rate'], 'number'],
            [['essay', 'question'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'paper_id' => 'Paper ID',
            'student_id' => 'Student ID',
            'tourist_id' => 'Tourist ID',
            'status' => 'Status',
            'essay' => 'Essay',
            'question' => 'Question',
            'answer' => 'Answer',
            'correct_info' => 'Correct Info',
            'correct' => 'Correct',
            'total' => 'Total',
            'rate' => 'Rate',
            'finished_time' => 'Finished Time',
            'duration' => 'Duration',
            'question_type' => 'Question Type',
            'total_num' => 'Total Num',
            'correct_num' => 'Correct Num',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamRecordQuery(get_called_class());
    }
}
