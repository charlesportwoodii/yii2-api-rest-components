<?php

namespace yrc\web;

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
            
            $status = 200;

            // Pull the exception
            $exception = Yii::$app->errorHandler->exception;
            if ($exception) {
                if (is_a($exception, 'yii\web\HttpException')) {
                    $copy = $response->data;
                    $response->data = null;

                    if (isset($copy['message'])) {
                        $message = \json_decode($copy['message']);
                        if (\json_last_error() === JSON_ERROR_NONE) {
                            $copy['message'] = $message;
                        }
                    }

                    $response->data['error'] = [
                        'message'   => $copy['message'],
                        'code'      => $copy['code']
                    ];

                    $status = $copy['status'];
                } else {
                    
                    Yii::error([
                        'message' => 'A fatal uncaught error occured.',
                        'exception' => $exception
                    ]);
                    $status = 500;
                    $response->data = [
                        'error' => [
                            'message' => Yii::t('yrc', 'An unexpected error occured.'),
                            'code' => 0
                        ]
                    ];
                }
            }

            

            // If the data attribute isn't set, transfer everything into it and build the new response object
            if (\is_array($response->data) && !isset($response->data['data'])) {
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