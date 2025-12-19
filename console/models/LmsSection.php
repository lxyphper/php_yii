<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lms_section".
 *
 * @property int $id
 * @property int $type 类型：1视频 2基础练习 3强化练习-写作 4强化练习-听力 5强化练习-阅读 6口语-进阶练习 7写作审题练习 8写作思路拓展 9写作分段练习
 * @property int $sub_type 子类型：1大作文 2小作文；具体值根据type值确定对应数据
 * @property int $resource_id 关联资源id：视频id/题集id/强化练习id
 * @property string $name 名称
 * @property string $cover_path 封面地址
 * @property int $auth 观看权限：1会员可看 2全部可看
 * @property string $desc 介绍
 * @property int $weight 排序权重
 * @property int $status 状态：1未发布 2已发布
 * @property int $is_auto 是否自动发布：1是 2否
 * @property string $content
 * @property int $is_copy 是否是复制数据：1是 2否
 * @property string $create_by
 * @property string $update_by
 * @property int $create_time
 * @property int $update_time
 */
class LmsSection extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'lms_section';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'sub_type', 'resource_id', 'auth', 'weight', 'status', 'is_auto', 'is_copy', 'create_time', 'update_time'], 'integer'],
            [['desc'], 'required'],
            [['desc'], 'string'],
            [['name'], 'string', 'max' => 200],
            [['cover_path', 'content'], 'string', 'max' => 500],
            [['create_by', 'update_by'], 'string', 'max' => 255],
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
            'sub_type' => 'Sub Type',
            'resource_id' => 'Resource ID',
            'name' => 'Name',
            'cover_path' => 'Cover Path',
            'auth' => 'Auth',
            'desc' => 'Desc',
            'weight' => 'Weight',
            'status' => 'Status',
            'is_auto' => 'Is Auto',
            'content' => 'Content',
            'is_copy' => 'Is Copy',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LmsSectionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new LmsSectionQuery(get_called_class());
    }
}
