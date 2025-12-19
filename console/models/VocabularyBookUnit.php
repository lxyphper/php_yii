<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "vocabulary_book_unit".
 *
 * @property int $id
 * @property string $name 名称
 * @property int $book_id
 * @property string $desc
 * @property int $status 状态：1正常 2禁用
 * @property string $cover_image_url
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 * @property float $sort_order 排序字段
 */
class VocabularyBookUnit extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vocabulary_book_unit';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['book_id', 'status', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['sort_order'], 'number'],
            [['name'], 'string', 'max' => 100],
            [['desc', 'cover_image_url'], 'string', 'max' => 500],
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
            'book_id' => 'Book ID',
            'desc' => 'Desc',
            'status' => 'Status',
            'cover_image_url' => 'Cover Image Url',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
            'sort_order' => 'Sort Order',
        ];
    }

    /**
     * {@inheritdoc}
     * @return VocabularyBookUnitQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VocabularyBookUnitQuery(get_called_class());
    }
}
