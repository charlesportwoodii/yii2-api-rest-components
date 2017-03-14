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
}