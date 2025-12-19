<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "exercises".
 *
 * @property int $id 主键ID
 * @property int $enter_by 录入人id
 * @property string $enter_lang 录入语言环境
 * @property string $title 标题
 * @property string $title_en 标题（英文）
 * @property string $content 题干
 * @property string $search_content
 * @property int $type 类型id
 * @property int $paper_group 试卷分组
 * @property int $category 分类id
 * @property string $category_content 题目类型说明
 * @property string $category_content_en 题目类型说明（英文）
 * @property string $topic 话题ids
 * @property int $year 年份
 * @property string|null $keywords 关键词
 * @property string|null $keywords_en 关键词（英文）
 * @property string $trap_content 题目陷阱
 * @property string $trap_content_en 题目陷阱（英文）
 * @property string $img 题目图片
 * @property int $weight 权重
 * @property int $is_analysis_done 是否分析完成
 * @property int $is_valid 是否有效
 * @property string $analysis_result 分析结果
 * @property string $analysis_result_en 分析结果（英文）
 * @property int $mine_type 录入题目类型：1自拟题 2真题 3机经题 4预测题
 * @property int $is_delete 是否删除：1是 2否
 * @property string $exam_date 考试日期
 * @property string $exam_points 考试地点
 * @property string $requirement 写作要求
 * @property string $statement_question 写作分析
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class Exercises extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'exercises';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['enter_by', 'type', 'paper_group', 'category', 'year', 'weight', 'is_analysis_done', 'is_valid', 'mine_type', 'is_delete', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['keywords', 'keywords_en'], 'safe'],
            [['enter_lang', 'title', 'title_en', 'topic'], 'string', 'max' => 50],
            [['content', 'search_content', 'img', 'analysis_result', 'analysis_result_en', 'requirement'], 'string', 'max' => 500],
            [['category_content', 'category_content_en', 'statement_question'], 'string', 'max' => 2000],
            [['trap_content'], 'string', 'max' => 1000],
            [['trap_content_en'], 'string', 'max' => 5000],
            [['exam_date'], 'string', 'max' => 20],
            [['exam_points'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键ID',
            'enter_by' => '录入人id',
            'enter_lang' => '录入语言环境',
            'title' => '标题',
            'title_en' => '标题（英文）',
            'content' => '题干',
            'search_content' => 'Search Content',
            'type' => '类型id',
            'paper_group' => '试卷分组',
            'category' => '分类id',
            'category_content' => '题目类型说明',
            'category_content_en' => '题目类型说明（英文）',
            'topic' => '话题ids',
            'year' => '年份',
            'keywords' => '关键词',
            'keywords_en' => '关键词（英文）',
            'trap_content' => '题目陷阱',
            'trap_content_en' => '题目陷阱（英文）',
            'img' => '题目图片',
            'weight' => '权重',
            'is_analysis_done' => '是否分析完成',
            'is_valid' => '是否有效',
            'analysis_result' => '分析结果',
            'analysis_result_en' => '分析结果（英文）',
            'mine_type' => '录入题目类型：1自拟题 2真题 3机经题 4预测题',
            'is_delete' => '是否删除：1是 2否',
            'exam_date' => '考试日期',
            'exam_points' => '考试地点',
            'requirement' => '写作要求',
            'statement_question' => '写作分析',
            'create_by' => '创建人',
            'update_by' => '更新人',
            'create_time' => '创建时间',
            'update_time' => '更新时间',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ExercisesQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ExercisesQuery(get_called_class());
    }
}
