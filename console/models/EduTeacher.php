<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "edu_teacher".
 *
 * @property int $id
 * @property int $user_id 用户id
 * @property string $name 老师名称
 * @property int $status 状态：1正常 2停用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class EduTeacher extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'edu_teacher';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'name' => 'Name',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return EduTeacherQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new EduTeacherQuery(get_called_class());
    }
}
