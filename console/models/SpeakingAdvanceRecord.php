<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_advance_record".
 *
 * @property int $id
 * @property int $student_id
 * @property int $topic_id 主问题话题id
 * @property int $question_id 主问题id
 * @property int $status 状态：1主问题 2子问题 3完成
 * @property int $step 当前所在步骤：1、起始页 2、句式模板页 3、单句评价页 4、总串模板页 5、总串评价页
 * @property string $tips 最新提示
 * @property string $total_template 总串模板
 * @property int $duration 练习时长
 * @property int $latest_mode 最后使用模式
 * @property string $radar_chart 五维图数据
 * @property string $improvements 提升建议
 * @property int $course_type 练习课程类型：1、ielts 2、ket 3、pet
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingAdvanceRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_advance_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'topic_id', 'question_id', 'status', 'step', 'duration', 'latest_mode', 'course_type', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['tips', 'improvements'], 'required'],
            [['tips', 'improvements'], 'string'],
            [['total_template'], 'string', 'max' => 500],
            [['radar_chart'], 'string', 'max' => 1000],
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
            'topic_id' => 'Topic ID',
            'question_id' => 'Question ID',
            'status' => 'Status',
            'step' => 'Step',
            'tips' => 'Tips',
            'total_template' => 'Total Template',
            'duration' => 'Duration',
            'latest_mode' => 'Latest Mode',
            'radar_chart' => 'Radar Chart',
            'improvements' => 'Improvements',
            'course_type' => 'Course Type',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingAdvanceRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingAdvanceRecordQuery(get_called_class());
    }
}
