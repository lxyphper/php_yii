<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "vocabulary_quiz_option".
 *
 * @property int $id
 * @property int $quiz_id
 * @property string $source_word
 * @property string $pos
 * @property string $definition
 * @property int $is_correct 是否正确：1正确 2错误
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class VocabularyQuizOption extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vocabulary_quiz_option';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['quiz_id', 'is_correct', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['source_word', 'definition'], 'string', 'max' => 200],
            [['pos'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'quiz_id' => 'Quiz ID',
            'source_word' => 'Source Word',
            'pos' => 'Pos',
            'definition' => 'Definition',
            'is_correct' => 'Is Correct',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return VocabularyQuizOptionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VocabularyQuizOptionQuery(get_called_class());
    }
}
