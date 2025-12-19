<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_special_item_topic".
 *
 * @property int $id
 * @property string $name 名称
 * @property string $desc 描述
 * @property int $type 类型：1句子跟读 2段落跟读 3句子汉译英 4段落汉译英 5合并简单句
 * @property int $category 分类标签id
 * @property int $weight 排序权重
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingSpecialItemTopic extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_special_item_topic';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'category', 'weight', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name', 'desc'], 'string', 'max' => 100],
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
            'desc' => 'Desc',
            'type' => 'Type',
            'category' => 'Category',
            'weight' => 'Weight',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingSpecialItemTopicQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingSpecialItemTopicQuery(get_called_class());
    }
}
