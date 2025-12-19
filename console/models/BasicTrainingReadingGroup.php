<?php

namespace app\models;

use Yii;
use yii\log\Logger;

/**
 * This is the model class for table "basic_training_reading_group".
 *
 * @property int $id
 * @property int $type 类型
 * @property int $grammar 语法点
 * @property int $difficulty 难度：1初级 2中级 3高级
 * @property string $title 标题
 * @property int $status 状态：1正常 2禁用
 * @property int $weight 排序权重
 * @property string $source_id 数据来源id
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingReadingGroup extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_reading_group';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'grammar', 'difficulty', 'status', 'weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title', 'source_id'], 'string', 'max' => 200],
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
            'grammar' => 'Grammar',
            'difficulty' => 'Difficulty',
            'title' => 'Title',
            'status' => 'Status',
            'weight' => 'Weight',
            'source_id' => 'Source ID',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingReadingGroupQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingReadingGroupQuery(get_called_class());
    }

    public function getByName($source_id, $title): int
    {
        if (empty($group_id) || empty($title)) {
            return 0;
        }
        $data = self::findOne(['title'=>$title, 'source_id'=>$source_id]);
        if (empty($data)) {
            //新增
            $new = new self();
            $new->title = $title;
            $new->source_id = $source_id;
            try {
                $new->insert();
            } catch (\Throwable $e) {
                Yii::getLogger()->log('保存题组失败，err：'.$e->getMessage(),Logger::LEVEL_ERROR);
            }
            return $new->id;
        }
        return $data->id;
    }
}
