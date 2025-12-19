<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "simulate_exam_record".
 *
 * @property int $id
 * @property int $paper_group_id 试卷分组id
 * @property int $student_id 学生id
 * @property int $type 模考类型：1全科 2听力 3阅读 4写作 5口语
 * @property string $lang 语言
 * @property int $status 模考状态：1未完成 2已完成
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class SimulateExamRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'simulate_exam_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_group_id', 'student_id', 'type', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
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
            'paper_group_id' => 'Paper Group ID',
            'student_id' => 'Student ID',
            'type' => 'Type',
            'lang' => 'Lang',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SimulateExamRecordQuery(get_called_class());
    }
}
