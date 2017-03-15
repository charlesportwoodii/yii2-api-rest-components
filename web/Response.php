<?php

namespace yrc\web;

use yii\web\Response as YiiResponse;
use Yii;

class Response extends YiiResponse
{
    /**
     * @const FORMAT_JSON25519
     */
    const FORMAT_JSON25519 = 'json+25519';

    /**
     * @return array the formatters that are supported by default
     */
    protected function defaultFormatters()
    {
        $formatters = parent::defaultFormatters();
        $formatters[self::FORMAT_JSON25519] = 'yrc\web\Json25519ResponseFormatter';

        return $formatters;
    }
}