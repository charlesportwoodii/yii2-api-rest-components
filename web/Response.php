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
     * @const FORMAT_NCRYPTF_JSON
     */
    const FORMAT_NCRYPTF_JSON = 'vnd.ncryptf+json';

    /**
     * @return array the formatters that are supported by default
     */
    protected function defaultFormatters()
    {
        $formatters = parent::defaultFormatters();
        $formatters[self::FORMAT_JSON25519] = 'yrc\web\json25519\JsonResponseFormatter';
        $formatters[self::FORMAT_NCRYPTF_JSON] = 'yrc\web\ncryptf\JsonResponseFormatter';
        return $formatters;
    }
}
