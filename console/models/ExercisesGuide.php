<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "exercises_guide".
 *
 * @property int $id
 * @property int $exercises_id 题目ID
 * @property int $pid 父ID
 * @property int $level 层级（1标题 2观点 3角度 4论点 5论据）
 * @property int $group_id 分组ID
 * @property string $title 标题
 * @property string $title_en 英文标题
 * @property string $describe 描述
 * @property string $lang 语言类型
 * @property int $is_delete 是否删除：1是 2否
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class ExercisesGuide extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'exercises_guide';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['exercises_id', 'pid', 'level', 'group_id', 'is_delete', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title'], 'string', 'max' => 500],
            [['title_en'], 'string', 'max' => 1000],
            [['describe'], 'string', 'max' => 200],
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
            'exercises_id' => 'Exercises ID',
            'pid' => 'Pid',
            'level' => 'Level',
            'group_id' => 'Group ID',
            'title' => 'Title',
            'title_en' => 'Title En',
            'describe' => 'Describe',
            'lang' => 'Lang',
            'is_delete' => 'Is Delete',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ExercisesGuideQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ExercisesGuideQuery(get_called_class());
    }
}
