<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lms_resource".
 *
 * @property int $id
 * @property string $name 文件名称
 * @property string $ali_resource_id 阿里资源id
 * @property string $resource_path 资源地址
 * @property int $duration 视频时长
 * @property int $status 转码状态：1未完成 2已完成
 * @property string $create_by
 * @property string $update_by
 * @property int $create_time
 * @property int $update_time
 */
class LmsResource extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'lms_resource';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['resource_path'], 'required'],
            [['duration', 'status', 'create_time', 'update_time'], 'integer'],
            [['name', 'create_by', 'update_by'], 'string', 'max' => 255],
            [['ali_resource_id'], 'string', 'max' => 100],
            [['resource_path'], 'string', 'max' => 500],
            [['ali_resource_id'], 'unique'],
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
            'ali_resource_id' => 'Ali Resource ID',
            'resource_path' => 'Resource Path',
            'duration' => 'Duration',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LmsResourceQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new LmsResourceQuery(get_called_class());
    }
}
