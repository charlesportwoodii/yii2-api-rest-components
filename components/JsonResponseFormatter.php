<?php

namespace yrc\components;

use yii\helpers\Json;
use yii\web\JsonResponseFormatter as YiiJsonResponseFormatter;

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
            
            $status = 200;
            // Pull the exception
            $exception = \Yii::$app->errorHandler->exception;
            if ($exception && is_subclass_of($exception, 'yii\web\HttpException'))
            {
                $copy = $response->data;
                $response->data = null;
                $response->data['error'] = [
                    'message'   => $copy['message'],
                    'code'      => $copy['code']
                ];

                $status = $copy['status'];

                /**
                $response->data['error'] = [
                    'message'   => $exception->getMessage(),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                    'trace'     => $exception->getTraceAsString()
                ];
                **/
            }

            // If the data attribute isn't set, transfer everything into it
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
            }

            $response->content = Json::encode($response->data, $options);
        }
    }
}