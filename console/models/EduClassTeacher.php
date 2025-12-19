<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "edu_class_teacher".
 *
 * @property int $id
 * @property int $class_id
 * @property int $teacher_id
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class EduClassTeacher extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'edu_class_teacher';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['class_id', 'teacher_id', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'class_id' => 'Class ID',
            'teacher_id' => 'Teacher ID',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return EduClassTeacherQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new EduClassTeacherQuery(get_called_class());
    }
}
