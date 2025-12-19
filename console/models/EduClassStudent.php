<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "edu_class_student".
 *
 * @property int $id
 * @property int $student_id 学生id
 * @property int $class_id 班级id
 * @property string $student_name 学生姓名
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class EduClassStudent extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'edu_class_student';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['student_id', 'class_id', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['student_name'], 'string', 'max' => 100],
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
            'class_id' => 'Class ID',
            'student_name' => 'Student Name',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return EduClassStudentQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new EduClassStudentQuery(get_called_class());
    }
}
