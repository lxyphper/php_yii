<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_exam_paper".
 *
 * @property int $id
 * @property string $name 话题内容
 * @property int $part 1、Part 1 2、Part2 3、Part 3 
 * @property string $time_tag 日期标识
 * @property int $category 分类
 * @property int $flag 标识：1新题 2保留题
 * @property int $is_high_frequency 是否是高频题：1是 2否
 * @property int $group_id 分组id
 * @property string $base_id 原始id
 * @property int $status 状态：1正常 2禁用
 * @property int $passed_num 考过人数
 * @property string $img_url 图片地址
 * @property int $is_lms 是否是lms使用：1是 2否
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingExamPaper extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_exam_paper';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['part', 'category', 'flag', 'is_high_frequency', 'group_id', 'status', 'passed_num', 'is_lms', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 200],
            [['time_tag', 'base_id'], 'string', 'max' => 100],
            [['img_url'], 'string', 'max' => 500],
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
            'part' => 'Part',
            'time_tag' => 'Time Tag',
            'category' => 'Category',
            'flag' => 'Flag',
            'is_high_frequency' => 'Is High Frequency',
            'group_id' => 'Group ID',
            'base_id' => 'Base ID',
            'status' => 'Status',
            'passed_num' => 'Passed Num',
            'img_url' => 'Img Url',
            'is_lms' => 'Is Lms',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingExamPaperQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingExamPaperQuery(get_called_class());
    }
}
