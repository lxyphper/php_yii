<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_reading_question".
 *
 * @property int $id
 * @property int $type 题目类型
 * @property int $group_id 题目分组
 * @property int $step 训练步骤
 * @property int $answer_count 答案的位置个数
 * @property string $stem 题干内容
 * @property string $answer 答案/答案句
 * @property string $content 选项内容/段落
 * @property string $locating_words 关键词，同义词
 * @property string $translation
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingReadingQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_reading_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'group_id', 'step', 'answer_count', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['answer', 'content', 'locating_words'], 'required'],
            [['answer', 'content', 'locating_words'], 'safe'],
            [['stem', 'translation'], 'string', 'max' => 1000],
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
            'answer_count' => 'Answer Count',
            'stem' => 'Stem',
            'answer' => 'Answer',
            'content' => 'Content',
            'locating_words' => 'Locating Words',
            'translation' => 'Translation',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingReadingQuestionQuery(get_called_class());
    }
}
