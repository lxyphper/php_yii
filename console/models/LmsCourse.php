<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lms_course".
 *
 * @property int $id
 * @property string $name 名称
 * @property string $cover_path 封面地址
 * @property int $category 类别：1雅思 2托福
 * @property int $level 级别：1初级 2中级 3高级
 * @property string $introduction 简介
 * @property string $details 详情
 * @property int $price 价格
 * @property int $original_price 原价
 * @property string $url 跳转地址
 * @property int $status 状态：1未发布 2已发布
 * @property int $weight 排序权重
 * @property int $is_delete 是否删除：1是 2否
 * @property string $create_by
 * @property string $update_by
 * @property int $create_time
 * @property int $update_time
 */
class LmsCourse extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'lms_course';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['category', 'level', 'price', 'original_price', 'status', 'weight', 'is_delete', 'create_time', 'update_time'], 'integer'],
            [['details'], 'required'],
            [['details'], 'string'],
            [['name'], 'string', 'max' => 200],
            [['cover_path', 'introduction', 'url'], 'string', 'max' => 500],
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
            'name' => 'Name',
            'cover_path' => 'Cover Path',
            'category' => 'Category',
            'level' => 'Level',
            'introduction' => 'Introduction',
            'details' => 'Details',
            'price' => 'Price',
            'original_price' => 'Original Price',
            'url' => 'Url',
            'status' => 'Status',
            'weight' => 'Weight',
            'is_delete' => 'Is Delete',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LmsCourseQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new LmsCourseQuery(get_called_class());
    }
}
