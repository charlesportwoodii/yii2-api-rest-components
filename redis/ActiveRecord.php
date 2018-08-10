<?php

namespace yrc\redis;

use yii\redis\ActiveRecord as YiiRedisActiveRecord;
use Yii;
use yii\helpers\Json;

abstract class ActiveRecord extends YiiRedisActiveRecord
{
    public $isExpired = false;

    /**
     * After find reconstitute the keypairs
     */
    public function afterFind()
    {
        // If the object is expired, delete it
        if ($this->isExpired()) {
            $this->isExpired = true;
        }
        
        if ($this->hasAttribute('data')) {
            $this->data = Json::decode($this->data);
        }

        return parent::afterFind();
    }

    /**
     * Return true if the token is expired
     * @return boolean
     */
    public function isExpired()
    {
        if ($this->isExpired) {
            return true;
        }
        
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
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // If an expiration date is set in the model attributes, tell Redis to automatically handle it
        if (!empty($this->expires_at)) {
            $db = static::getDb();
            $pk = [];
            foreach ($this->primaryKey() as $key) {
                $pk[$key] = $this->getAttribute($key);
            }

            $pk = static::buildKey($pk);
            $key = static::keyPrefix() . ':a:' . $pk;
            $result = $db->executeCommand('EXPIREAT', [$key, $this->expires_at]);
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($this->hasAttribute('data')) {
                $this->data = Json::encode($this->data);
            }

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
            Yii::debug([
                'message' => 'Deleting redis object',
                'code_id' => $this->id
            ], 'yrc/redis/ActiveRecord:delete');

            return true;
        }

        return false;
    }
}
