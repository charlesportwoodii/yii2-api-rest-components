<?php

namespace yrc\models\redis;

use Base32\Base32;
use Yii;

/**
 * Represents a temporary single use consumable token
 * @property integer $id
 * @property string $hash
 * @property integer $user_id
 * @property mixed $type
 * @property mixed $data
 * @property integer $expires_at
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
