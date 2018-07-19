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
            if ($exception = Yii::$app->errorHandler->exception) {
                if (is_a($exception, 'yii\web\HttpException')) {
                    $copy = $response->data;

                    if (isset($copy['message'])) {
                        $message = \json_decode($copy['message']);
                        if (\json_last_error() === JSON_ERROR_NONE) {
                            $copy['message'] = $message;
                        }
                    }

                    $response->data = [
                        'data' => null,
                        'error' => [
                            'message'   => $copy['message'],
                            'code'      => $copy['code']
                        ]
                    ];

                    $status = $copy['status'];
                } else {
                    Yii::error([
                        'message' => 'A fatal uncaught error occured.',
                        'exception' => $exception
                    ]);
                    $status = 500;
                    $response->data = [
                        'data' => null,
                        'error' => [
                            'message' => Yii::t('yrc', 'An unexpected error occured.'),
                            'code' => 0
                        ]
                    ];
                }
            }

            if (\is_object($response->data)) {
                $copy = $response->data;
                $response->data = null;
                $response->data['data'] = $copy;
            }

            if (!\is_array($response->data) || (is_array($response->data) && !array_key_exists('data', $response->data))) {
                $copy = $response->data;

                $error = $copy['error'] ?? null;
                unset($copy['error']);
                if ($error !== null) {
                    $response->data['error'] = $error;
                }

                $response->data = [
                    'data' => $copy,
                    'error' => null
                ];
            }

            $response->data['status'] = $status;
            $response->content = Json::encode($response->data, $options);
        }
    }
}
