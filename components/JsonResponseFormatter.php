<?php

namespace yrc\components;

use yii\helpers\Json;
use yii\web\JsonResponseFormatter as YiiJsonResponseFormatter;
use Yii;

/**
 * Handles formatting of the response
 * @class JsonResponseFormatter
 */
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

            // Preserve floating precision values
            $options |= JSON_PRESERVE_ZERO_FRACTION;
            
            $status = 200;

            // Pull the exception
            $exception = Yii::$app->errorHandler->exception;
            if ($exception && is_subclass_of($exception, 'yii\web\HttpException')) {
                $copy = $response->data;
                $response->data = null;
                $response->data['error'] = [
                    'message'   => $copy['message'],
                    'code'      => $copy['code']
                ];

                $status = $copy['status'];
            }

            // If the data attribute isn't set, transfer everything into it and build the new response object
            if (!isset($response->data['data'])) {
                $copy = $response->data;

                $error = $copy['error'] ?? null;
                unset($copy['error']);

                $response->data = null;
                $response->data['data'] = $copy;
                if ($error !== null) {
                    $response->data['error'] = $error;
                }

                $response->data['status'] = $status;

                if ($response->data['data'] === [] || $response->data['data'] === null) {
                    $response->data['data'] =  null;
                }
            }

            $response->content = Json::encode($response->data, $options);
        }
    }
}