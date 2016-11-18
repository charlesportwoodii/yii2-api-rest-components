<?php

namespace yrc\api\models;

use Base32\Base32;
use Yii;

/**
 * Class for generating and storing codes
 * @class Code
 */
class Code extends \yii\redis\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            'id',
            'hash',
            'user_id',
            'expires_at'
        ];
    }

    /**
     * Return true if the token is expired
     * @return boolean
     */
    public function isExpired()
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at < time();
    }
}