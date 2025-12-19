<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "writing_big_essay_record".
 *
 * @property int $id
 * @property int $paper_id 试卷id
 * @property string $subject
 * @property int $student_id 学生id
 * @property string $content 作文内容
 * @property int $status 状态：1未完成 2已完成 3已评分
 * @property string $score_file
 * @property int $equity_id
 * @property int $is_delete
 * @property string $lang
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class WritingBigEssayRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'writing_big_essay_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'student_id', 'status', 'equity_id', 'is_delete', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['subject'], 'string', 'max' => 2000],
            [['content'], 'string', 'max' => 5000],
            [['score_file'], 'string', 'max' => 1000],
            [['lang'], 'string', 'max' => 20],
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
            'subject' => 'Subject',
            'student_id' => 'Student ID',
            'content' => 'Content',
            'status' => 'Status',
            'score_file' => 'Score File',
            'equity_id' => 'Equity ID',
            'is_delete' => 'Is Delete',
            'lang' => 'Lang',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return WritingBigEssayRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new WritingBigEssayRecordQuery(get_called_class());
    }
}
