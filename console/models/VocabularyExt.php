<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "vocabulary_ext".
 *
 * @property int $id
 * @property int $vocabulary_id
 * @property string|null $core_meanings
 * @property string|null $collocations
 * @property string|null $example_sentences
 * @property string|null $synonyms
 * @property string $etymology
 * @property string|null $nearby_words
 * @property string|null $rhyming_words
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class VocabularyExt extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vocabulary_ext';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['vocabulary_id', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['core_meanings', 'collocations', 'example_sentences', 'synonyms', 'nearby_words', 'rhyming_words'], 'safe'],
            [['etymology'], 'string', 'max' => 500],
            [['vocabulary_id'], 'unique'],
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
            'core_meanings' => 'Core Meanings',
            'collocations' => 'Collocations',
            'example_sentences' => 'Example Sentences',
            'synonyms' => 'Synonyms',
            'etymology' => 'Etymology',
            'nearby_words' => 'Nearby Words',
            'rhyming_words' => 'Rhyming Words',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return VocabularyExtQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VocabularyExtQuery(get_called_class());
    }
}
