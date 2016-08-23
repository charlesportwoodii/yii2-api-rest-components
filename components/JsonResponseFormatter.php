<?php

namespace yrc\components;

use yii\helpers\Json;
use yii\web\JsonResponseFormatter as YiiJsonResponseFormatter;

class JsonResponseFormatter extends YiiJsonResponseFormatter
{
    /**
     * Formats response data in JSON format.
     * @param Response $response
     */
    protected function formatJson($response)
    {
        $response->getHeaders()->set('Content-Type', 'application/json; charset=UTF-8');
        if ($response->data !== null) {
            $options = $this->encodeOptions;
            if ($this->prettyPrint) {
                $options |= JSON_PRETTY_PRINT;
            }

            if (!isset($response->data['data'])) {
                $copy = $response->data;
                $response->data = [];
                $response->data['data'] = $copy;
            }

            $response->content = Json::encode($response->data, $options);
        }
    }
}