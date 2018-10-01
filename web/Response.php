<?php

namespace yrc\web;

use yii\web\Response as YiiResponse;
use Yii;

class Response extends YiiResponse
{
    /**
     * @const FORMAT_JSON25519
     */
    const FORMAT_JSON25519 = 'vnd.json+25519';

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

        foreach ([self::FORMAT_JSON25519, self::FORMAT_NCRYPTF_JSON] as $format) {
            $formatters[$format] = [
                'class'         => \yrc\web\ncryptf\JsonResponseFormatter::class,
                'prettyPrint'   => YII_DEBUG,
                'encodeOptions' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            ];
        }

        $formatters[self::FORMAT_JSON] = [
            'class'         => \yrc\web\JsonResponseFormatter::class,
            'prettyPrint'   => YII_DEBUG,
            'encodeOptions' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        ];
        
        return $formatters;
    }
}
