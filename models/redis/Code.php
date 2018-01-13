<?php

namespace yrc\models\redis;

use Base32\Base32;
use Yii;

/**
 * Represents a temporary, (ideally) single use code for operations where passwords cannot be used
 */
class Code extends \yrc\redis\ActiveRecord
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
            'type',
            'data',
            'expires_at'
        ];
    }
}