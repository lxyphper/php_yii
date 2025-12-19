<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "listening_exam_paper".
 *
 * @property int $id 主键id
 * @property string $title 标题
 * @property string $title_en
 * @property int $part 环节
 * @property string $complete_title 完整标题
 * @property string $complete_title_en
 * @property string $file_url 听力文件url
 * @property string $file_json_url 音频波形数据
 * @property string|null $content 正文json
 * @property string|null $content_text 正文json，去除角色或人名
 * @property int $weight 权重
 * @property string|null $topic 话题
 * @property int $unit 考试id
 * @property int $paper_group 试卷分组
 * @property int $difficulty 难易程度：1、易 2、中 3、难
 * @property string $analyze 正文解析
 * @property string $essay_summary 正文描述
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ListeningExamPaper extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_paper';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['part', 'weight', 'unit', 'paper_group', 'difficulty', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['content', 'content_text', 'topic'], 'safe'],
            [['title'], 'string', 'max' => 50],
            [['title_en', 'complete_title'], 'string', 'max' => 200],
            [['complete_title_en'], 'string', 'max' => 300],
            [['file_url', 'file_json_url'], 'string', 'max' => 500],
            [['analyze'], 'string', 'max' => 5000],
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
            'part' => 'Part',
            'complete_title' => 'Complete Title',
            'complete_title_en' => 'Complete Title En',
            'file_url' => 'File Url',
            'file_json_url' => 'File Json Url',
            'content' => 'Content',
            'content_text' => 'Content Text',
            'weight' => 'Weight',
            'topic' => 'Topic',
            'unit' => 'Unit',
            'paper_group' => 'Paper Group',
            'difficulty' => 'Difficulty',
            'analyze' => 'Analyze',
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
     * @return ListeningExamPaperQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamPaperQuery(get_called_class());
    }
}
