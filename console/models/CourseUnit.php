<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "course_unit".
 *
 * @property int $id
 * @property string $name
 * @property string $name_en
 * @property int $type
 * @property int $status
 * @property int $weight
 * @property string $source_id
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class CourseUnit extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'course_unit';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'status', 'weight', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
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
            'type' => 'Type',
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
     * @return CourseUnitQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new CourseUnitQuery(get_called_class());
    }
}
