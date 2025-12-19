<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_writing_grammar".
 *
 * @property int $id
 * @property int $type 题组分类：1连词成句 2翻译练习
 * @property string $name 名称
 * @property string $name_en 英文名称
 * @property string $key 对应key
 * @property int $status 状态：1正常 2禁用
 * @property int $weight 排序权重
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingWritingGrammar extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_writing_grammar';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'status', 'weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name', 'key'], 'string', 'max' => 100],
            [['name_en'], 'string', 'max' => 500],
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
            'name' => 'Name',
            'name_en' => 'Name En',
            'key' => 'Key',
            'status' => 'Status',
            'weight' => 'Weight',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingWritingGrammarQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingWritingGrammarQuery(get_called_class());
    }
}
