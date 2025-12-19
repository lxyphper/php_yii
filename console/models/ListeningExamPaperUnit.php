<?php

namespace app\models;

use Yii;
use yii\log\Logger;

/**
 * This is the model class for table "listening_exam_paper_unit".
 *
 * @property int $id
 * @property string $name 考试名称
 * @property string $name_en 考试名称英文
 * @property int $type 试卷类型
 * @property int $status 状态：1正常 2停用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ListeningExamPaperUnit extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_paper_unit';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
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
            'name' => '考试名称',
            'name_en' => '考试名称英文',
            'type' => '试卷类型',
            'status' => '状态：1正常 2停用',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamPaperUnitQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamPaperUnitQuery(get_called_class());
    }

    public function getByName($type, $name): int
    {
        if ($type <= 0 || empty($name)) {
            return 0;
        }
        $data = self::findOne(['type'=>$type, 'name'=>$name]);
        if (empty($data)) {
            //新增
            $new = new self();
            $new->type = $type;
            $new->name = $name;
            try {
                $new->insert();
            } catch (\Throwable $e) {
                Yii::getLogger()->log('保存考试信息失败，err：'.$e->getMessage(),Logger::LEVEL_ERROR);
            }
            return $new->id;
        }
        return $data->id;
    }
}
