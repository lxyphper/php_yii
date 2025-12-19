<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[LmsSection]].
 *
 * @see LmsSection
 */
class LmsSectionQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return LmsSection[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return LmsSection|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
