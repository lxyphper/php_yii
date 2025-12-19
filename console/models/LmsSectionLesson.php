<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lms_section_lesson".
 *
 * @property int $id
 * @property int $course_id 课程id
 * @property int $section_id 小节id
 * @property int $lesson_id 章节id
 * @property int $weight 排序权重
 * @property int $is_freeze 是否冻结：1是 2否
 * @property int $is_delete 是否删除：1是 2否
 * @property string $create_by
 * @property string $update_by
 * @property int $create_time
 * @property int $update_time
 */
class LmsSectionLesson extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'lms_section_lesson';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['course_id', 'section_id', 'lesson_id', 'weight', 'is_freeze', 'is_delete', 'create_time', 'update_time'], 'integer'],
            [['create_by', 'update_by'], 'string', 'max' => 255],
            [['course_id', 'section_id'], 'unique', 'targetAttribute' => ['course_id', 'section_id']],
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
            'section_id' => 'Section ID',
            'lesson_id' => 'Lesson ID',
            'weight' => 'Weight',
            'is_freeze' => 'Is Freeze',
            'is_delete' => 'Is Delete',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LmsSectionLessonQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new LmsSectionLessonQuery(get_called_class());
    }
}
