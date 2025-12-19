<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lms_lesson".
 *
 * @property int $id
 * @property int $course_id 课程id
 * @property string $name 名称
 * @property int $status 状态：1未发布 2已发布
 * @property int $weight 排序权重
 * @property int $is_delete 是否删除：1是 2否
 * @property int $is_copy 是否是复制数据：1是 2否
 * @property string $create_by
 * @property string $update_by
 * @property int $create_time
 * @property int $update_time
 */
class LmsLesson extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'lms_lesson';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['course_id', 'status', 'weight', 'is_delete', 'is_copy', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 200],
            [['create_by', 'update_by'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'course_id' => 'Course ID',
            'name' => 'Name',
            'status' => 'Status',
            'weight' => 'Weight',
            'is_delete' => 'Is Delete',
            'is_copy' => 'Is Copy',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LmsLessonQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new LmsLessonQuery(get_called_class());
    }
}
