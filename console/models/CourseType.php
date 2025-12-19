<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "course_type".
 *
 * @property int $id
 * @property string $name 名称
 * @property string $name_en 名称英文
 * @property int $status 状态：1正常 2禁用
 * @property int $weight 排序权重
 * @property string $source_id
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class CourseType extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'course_type';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status', 'weight', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 100],
            [['name_en'], 'string', 'max' => 500],
            [['source_id'], 'string', 'max' => 50],
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
            'name_en' => 'Name En',
            'status' => 'Status',
            'weight' => 'Weight',
            'source_id' => 'Source ID',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return CourseTypeQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new CourseTypeQuery(get_called_class());
    }
}
