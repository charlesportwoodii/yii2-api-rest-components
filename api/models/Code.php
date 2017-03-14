<?php

namespace yrc\api\models;

use Base32\Base32;
use Yii;

/**
 * Class for generating and storing codes
 * @class Code
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
            'expires_at'
        ];
    }
}