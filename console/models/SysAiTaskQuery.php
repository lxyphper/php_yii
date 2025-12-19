<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SysAiTask]].
 *
 * @see SysAiTask
 */
class SysAiTaskQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SysAiTask[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SysAiTask|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
