<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "vocabulary_quiz".
 *
 * @property int $id
 * @property int $vocabulary_id 词汇id
 * @property int $quiz_type 类型：1、基础题目 2、练习题目-选择题 3、练习题目-填空题 4、练习题目-听力选择题 5、练习题目-听写题
 * @property string $quiz_question
 * @property string $quiz_answer
 * @property string $quiz_translation
 * @property string $quiz_options
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class VocabularyQuiz extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vocabulary_quiz';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['vocabulary_id', 'quiz_type', 'status', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['quiz_translation', 'quiz_options'], 'required'],
            [['quiz_options'], 'safe'],
            [['quiz_question', 'quiz_answer'], 'string', 'max' => 200],
            [['quiz_translation'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'vocabulary_id' => 'Vocabulary ID',
            'quiz_type' => 'Quiz Type',
            'quiz_question' => 'Quiz Question',
            'quiz_answer' => 'Quiz Answer',
            'quiz_translation' => 'Quiz Translation',
            'quiz_options' => 'Quiz Options',
            'status' => 'Status',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return VocabularyQuizQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VocabularyQuizQuery(get_called_class());
    }
}
