<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "vocabulary".
 *
 * @property int $id
 * @property string $name 单词名称
 * @property string $translation
 * @property string $definition
 * @property int $weight 排序权重
 * @property int $status 状态：1正常 2禁用
 * @property string $card_info 卡片信息
 * @property string $uk_ipa 英式音标
 * @property string $us_ipa 美式音标
 * @property string $pronunciation 发音
 * @property string $breakdown 词汇分解
 * @property string $syllable_ipa_uk
 * @property string $syllable_ipa_us
 * @property int $generate_card_status 生成卡片信息状态：1未生成 2生成中 3已生成
 * @property int $generate_quiz_status 生成题目信息状态：1未生成 2生成中 3已生成
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class Vocabulary extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vocabulary';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['weight', 'status', 'generate_card_status', 'generate_quiz_status', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['name', 'translation', 'uk_ipa', 'us_ipa', 'syllable_ipa_uk', 'syllable_ipa_us'], 'string', 'max' => 100],
            [['definition'], 'string', 'max' => 300],
            [['card_info'], 'string', 'max' => 500],
            [['pronunciation'], 'string', 'max' => 200],
            [['breakdown'], 'string', 'max' => 1000],
            [['name'], 'unique'],
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
            'translation' => 'Translation',
            'definition' => 'Definition',
            'weight' => 'Weight',
            'status' => 'Status',
            'card_info' => 'Card Info',
            'uk_ipa' => 'Uk Ipa',
            'us_ipa' => 'Us Ipa',
            'pronunciation' => 'Pronunciation',
            'breakdown' => 'Breakdown',
            'syllable_ipa_uk' => 'Syllable Ipa Uk',
            'syllable_ipa_us' => 'Syllable Ipa Us',
            'generate_card_status' => 'Generate Card Status',
            'generate_quiz_status' => 'Generate Quiz Status',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return VocabularyQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VocabularyQuery(get_called_class());
    }
}
