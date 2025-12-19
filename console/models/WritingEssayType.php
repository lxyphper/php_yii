<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "writing_essay_type".
 *
 * @property int $id
 * @property string $name 标题
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class WritingEssayType extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'writing_essay_type';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => '标题',
            'create_by' => '创建人',
            'update_by' => '更新人',
            'create_time' => '创建时间',
            'update_time' => '更新时间',
        ];
    }

    /**
     * {@inheritdoc}
     * @return WritingEssayTypeQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new WritingEssayTypeQuery(get_called_class());
    }
}
