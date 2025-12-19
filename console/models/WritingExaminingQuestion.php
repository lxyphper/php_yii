<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "writing_examining_question".
 *
 * @property int $id
 * @property int $paper_id 试卷id
 * @property int $task_type 作文类型：1大作文 2小作文
 * @property string $title 题干
 * @property string $title_en 题干英文
 * @property string|null $option 选项
 * @property string|null $option_en 选项英文
 * @property int $answer 答案
 * @property string $summary 题干描述
 * @property int $number 题号
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class WritingExaminingQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'writing_examining_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'task_type', 'answer', 'number', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['option', 'option_en'], 'safe'],
            [['title', 'title_en', 'summary'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'paper_id' => 'Paper ID',
            'task_type' => 'Task Type',
            'title' => 'Title',
            'title_en' => 'Title En',
            'option' => 'Option',
            'option_en' => 'Option En',
            'answer' => 'Answer',
            'summary' => 'Summary',
            'number' => 'Number',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return WritingExaminingQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new WritingExaminingQuestionQuery(get_called_class());
    }
}
