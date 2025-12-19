<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "course".
 *
 * @property int $id
 * @property string $name
 * @property string $name_en
 * @property int $unit 单元
 * @property int $subject 科目：1听力 2阅读 3写作 4口语
 * @property int $category 课程类目：1视频类 2阅读习题练习及水平测评
 * @property string $config 课程题目配置
 * @property int $duration 视频时长
 * @property int $status 状态：1正常 2禁用
 * @property string $answer 答案：视频时长裁剪结果
 * @property int $weight
 * @property int $online_time 上线时间
 * @property string $teacher_name 老师姓名
 * @property string $teacher_head 老师头像
 * @property string $source_id
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class Course extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'course';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['unit', 'subject', 'category', 'duration', 'status', 'weight', 'online_time', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 100],
            [['name_en', 'teacher_head'], 'string', 'max' => 500],
            [['config', 'answer'], 'string', 'max' => 2000],
            [['teacher_name', 'source_id'], 'string', 'max' => 50],
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
            'unit' => 'Unit',
            'subject' => 'Subject',
            'category' => 'Category',
            'config' => 'Config',
            'duration' => 'Duration',
            'status' => 'Status',
            'answer' => 'Answer',
            'weight' => 'Weight',
            'online_time' => 'Online Time',
            'teacher_name' => 'Teacher Name',
            'teacher_head' => 'Teacher Head',
            'source_id' => 'Source ID',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return CourseQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new CourseQuery(get_called_class());
    }
}
