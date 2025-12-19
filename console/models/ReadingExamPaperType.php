<?php

namespace app\models;

use Yii;
use yii\log\Logger;

/**
 * This is the model class for table "reading_exam_paper_type".
 *
 * @property int $id
 * @property int $p_id 父级id
 * @property string $name 考试类型名称
 * @property string $name_en 考试类型名称英文
 * @property int $weight 排序权重
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ReadingExamPaperType extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reading_exam_paper_type';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['p_id', 'weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name', 'name_en'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'p_id' => '父级id',
            'name' => '考试类型名称',
            'name_en' => '考试类型名称英文',
            'weight' => '排序权重',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperTypeQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ReadingExamPaperTypeQuery(get_called_class());
    }

    public function getByName($pid, $name): int
    {
        if ($pid <= 0 || empty($name)) {
            return 0;
        }
        $data = self::findOne(['p_id'=>$pid, 'name'=>$name]);
        if (empty($data)) {
            //新增
            $new = new self();
            $new->p_id = $pid;
            $new->name = $name;
            try {
                $new->insert();
            } catch (\Throwable $e) {
                Yii::getLogger()->log('保存考试类型信息失败，err：'.$e->getMessage(),Logger::LEVEL_ERROR);
            }
            return $new->id;
        }
        return $data->id;
    }
}
