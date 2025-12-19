<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_writing_question".
 *
 * @property int $id
 * @property int $type 题目类型
 * @property int $group_id 题目分组
 * @property int $step 训练步骤
 * @property string $stem 题干内容
 * @property string $answer 答案
 * @property string $content 选项内容
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingWritingQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_writing_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'group_id', 'step', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['answer', 'content'], 'required'],
            [['answer', 'content'], 'safe'],
            [['stem'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'group_id' => 'Group ID',
            'step' => 'Step',
            'stem' => 'Stem',
            'answer' => 'Answer',
            'content' => 'Content',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingWritingQuestionQuery(get_called_class());
    }
}
