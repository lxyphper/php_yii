<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[EduClassTeacher]].
 *
 * @see EduClassTeacher
 */
class EduClassTeacherQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return EduClassTeacher[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return EduClassTeacher|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
