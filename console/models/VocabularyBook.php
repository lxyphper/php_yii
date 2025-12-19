<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "vocabulary_book".
 *
 * @property int $id
 * @property string $name 名称
 * @property string $description 描述
 * @property int $total_words 总数量
 * @property string $cover_image_url 封面图地址
 * @property int $status 状态：1正常 2禁用
 * @property int $weight
 * @property int $category
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class VocabularyBook extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vocabulary_book';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['total_words', 'status', 'weight', 'category', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 200],
            [['description', 'cover_image_url'], 'string', 'max' => 500],
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
            'description' => 'Description',
            'total_words' => 'Total Words',
            'cover_image_url' => 'Cover Image Url',
            'status' => 'Status',
            'weight' => 'Weight',
            'category' => 'Category',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return VocabularyBookQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new VocabularyBookQuery(get_called_class());
    }
}
