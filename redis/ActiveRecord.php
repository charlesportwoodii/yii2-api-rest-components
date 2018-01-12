<?php

namespace yrc\redis;

use yii\redis\ActiveRecord as YiiRedisActiveRecord;
use Yii;

abstract class ActiveRecord extends YiiRedisActiveRecord
{
    /**
     * After find reconstitute the keypairs
     */
    public function afterFind()
    {
        // If the object is expired, delete it
        if ($this->isExpired()) {
            throw new \yii\base\Exception(Yii::t('yrc', 'Element has expired.'));
        }
        
        return parent::afterFind();
    }

    /**
     * Return true if the token is expired
     * @return boolean
     */
    public function isExpired()
    {
        if (empty($this->expires_at)) {
            return false;
        }
        
        // Handle token expiration by actually deleting the token
        if ($this->expires_at < time()) {
            $this->delete();

            return true;
        }

        return false;
    }

    /**
     * Before save, serialized the attributes array if it is not null
     * @param boolean $insert
     * @return boolean
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            $this->attributes = \json_encode($this->attributes);
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            // Log that the code was deleted
            Yii::info([
                'message' => 'Deleting redis object',
                'code_id' => $this->id
            ]);

            return true;
        }

        return false;
    }
}