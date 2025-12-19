<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "writing_essay".
 *
 * @property int $id
 * @property string $title 标题
 * @property string $title_en 英文标题
 * @property int $category 类别：1剑雅 2机经 3预测 4自建
 * @property string $content 题干
 * @property string $search_content
 * @property int $type 类型
 * @property string $img_desc 图片描述
 * @property string $img_url 图片地址
 * @property int $img_type 图片分类：1动态图 2静态图 3地图 4流程图
 * @property int $paper_group 试卷分组id
 * @property int $weight
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class WritingEssay extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'writing_essay';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['category', 'type', 'img_type', 'paper_group', 'weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title'], 'string', 'max' => 100],
            [['title_en', 'img_url'], 'string', 'max' => 500],
            [['content', 'search_content'], 'string', 'max' => 2000],
            [['img_desc'], 'string', 'max' => 5000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'title_en' => 'Title En',
            'category' => 'Category',
            'content' => 'Content',
            'search_content' => 'Search Content',
            'type' => 'Type',
            'img_desc' => 'Img Desc',
            'img_url' => 'Img Url',
            'img_type' => 'Img Type',
            'paper_group' => 'Paper Group',
            'weight' => 'Weight',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return WritingEssayQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new WritingEssayQuery(get_called_class());
    }
}
