<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "student_specify_account".
 *
 * @property int $id
 * @property string $name 账号名称
 * @property int $use_duration 有效使用时长（单位：天）
 * @property int $status 状态：1未使用 2已使用
 * @property int $create_time
 * @property int $update_time
 */
class StudentSpecifyAccount extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'student_specify_account';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['use_duration', 'status', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 50],
            [['name'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'use_duration' => 'Use Duration',
            'status' => 'Status',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return StudentSpecifyAccountQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StudentSpecifyAccountQuery(get_called_class());
    }
}
