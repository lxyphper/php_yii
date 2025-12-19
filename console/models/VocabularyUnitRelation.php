<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "vocabulary_unit_relation".
 *
 * @property int $id
 * @property int $vocabulary_id 单词id
 * @property int $book_id 词书id
 * @property int $unit_id
 * @property int $order 排序
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class VocabularyUnitRelation extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vocabulary_unit_relation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['vocabulary_id', 'book_id', 'unit_id', 'order', 'status', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
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
            'book_id' => 'Book ID',
            'unit_id' => 'Unit ID',
            'order' => 'Order',
            'status' => 'Status',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return VocabularyUnitRelationQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VocabularyUnitRelationQuery(get_called_class());
    }
}
