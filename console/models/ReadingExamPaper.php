<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reading_exam_paper".
 *
 * @property int $id 主键id
 * @property string $title 标题
 * @property string $title_en 标题英文
 * @property string $complete_title 完整标题
 * @property string $complete_title_en 完整标题英文
 * @property string $essay_title 正文标题
 * @property string|null $content 正文json
 * @property int $weight 权重
 * @property string|null $topic 话题
 * @property int $unit 考试id
 * @property int $paper_group 试卷分组
 * @property int $difficulty 难易程度：1、易 2、中 3、难
 * @property string $analyze 正文解析
 * @property string $analyze_en 正文解析英文
 * @property string $essay_summary 正文描述
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ReadingExamPaper extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reading_exam_paper';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['content', 'topic'], 'safe'],
            [['weight', 'unit', 'paper_group', 'difficulty', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title'], 'string', 'max' => 50],
            [['title_en'], 'string', 'max' => 100],
            [['complete_title'], 'string', 'max' => 200],
            [['complete_title_en', 'essay_title'], 'string', 'max' => 500],
            [['analyze', 'analyze_en'], 'string', 'max' => 5000],
            [['essay_summary'], 'string', 'max' => 2000],
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
            'complete_title' => 'Complete Title',
            'complete_title_en' => 'Complete Title En',
            'essay_title' => 'Essay Title',
            'content' => 'Content',
            'weight' => 'Weight',
            'topic' => 'Topic',
            'unit' => 'Unit',
            'paper_group' => 'Paper Group',
            'difficulty' => 'Difficulty',
            'analyze' => 'Analyze',
            'analyze_en' => 'Analyze En',
            'essay_summary' => 'Essay Summary',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ReadingExamPaperQuery(get_called_class());
    }
}
