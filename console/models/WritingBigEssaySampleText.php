<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "writing_big_essay_sample_text".
 *
 * @property int $id 主键ID
 * @property int $paper_id 题目ID
 * @property int $student_id 用户id
 * @property string $requirement 定制要求
 * @property string $thought 思路
 * @property string $content 范文内容
 * @property string $subject 自定义题目内容
 * @property string|null $subject_thought 自定义题目思路
 * @property int $status 状态：1未完成 2已完成
 * @property int $equity_id
 * @property int $is_delete
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class WritingBigEssaySampleText extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'writing_big_essay_sample_text';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'student_id', 'status', 'equity_id', 'is_delete', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['requirement', 'thought'], 'required'],
            [['subject_thought'], 'string'],
            [['requirement', 'subject'], 'string', 'max' => 500],
            [['thought'], 'string', 'max' => 1000],
            [['content'], 'string', 'max' => 5000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'paper_id' => 'Paper ID',
            'student_id' => 'Student ID',
            'requirement' => 'Requirement',
            'thought' => 'Thought',
            'content' => 'Content',
            'subject' => 'Subject',
            'subject_thought' => 'Subject Thought',
            'status' => 'Status',
            'equity_id' => 'Equity ID',
            'is_delete' => 'Is Delete',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return WritingBigEssaySampleTextQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new WritingBigEssaySampleTextQuery(get_called_class());
    }
}
