<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[EduClassStudent]].
 *
 * @see EduClassStudent
 */
class EduClassStudentQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return EduClassStudent[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return EduClassStudent|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
