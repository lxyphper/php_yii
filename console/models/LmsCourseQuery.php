<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[LmsCourse]].
 *
 * @see LmsCourse
 */
class LmsCourseQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return LmsCourse[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return LmsCourse|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
