<?php

namespace yrc\components\log;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;
use Yii;

final class PsrTarget extends Target
{
    use LoggerAwareTrait;

    private $_psrLevels = [
        Logger::LEVEL_ERROR => LogLevel::ERROR,
        Logger::LEVEL_WARNING => LogLevel::WARNING,
        Logger::LEVEL_INFO => LogLevel::INFO,
        Logger::LEVEL_TRACE => LogLevel::DEBUG,
        Logger::LEVEL_PROFILE => LogLevel::DEBUG,
        Logger::LEVEL_PROFILE_BEGIN => LogLevel::DEBUG,
        Logger::LEVEL_PROFILE_END => LogLevel::DEBUG,
    ];

    /**
     * @return LoggerInterface
     * @throws InvalidConfigException
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            throw new InvalidConfigException('Logger should be configured with Psr\Log\LoggerInterface.');
        }
        return $this->logger;
    }

    /**
     * @inheritdoc
     */
    public function export()
    {
        foreach ($this->messages as $message) {
            $context = [];
            if (isset($message[4])) {
                $context['trace'] = $message[4];
            }

            if (isset($message[5])) {
                $context['memory'] = $message[5];
            }

            if (isset($message[2])) {
                $context['category'] = $message[2];
            }

            $text = $message[0];

            if (!is_string($text)) {
                // exceptions may not be serializable if in the call stack somewhere is a Closure
                if ($text instanceof \Throwable || $text instanceof \Exception) {
                    $text = (string)$text;
                } elseif (\is_array($text)) {
                    $ctx = $text;
                    if (isset($ctx['message'])) {
                        $text = $ctx['message'];
                        unset($ctx['message']);

                        if (isset($ctx['exception'])) {
                            $e = $ctx['exception'];
                            unset($ctx['exception']);
                            $context['exception'] = [
                                'message' => $e->getMessage(),
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'code' => $e->getCode(),
                                'trace' => $e->getTrace()
                            ];
                        }
                        foreach ($ctx as $k => $v) {
                            $context[$k] = $v;
                        }
                    } else {
                        $text = VarDumper::export($text);
                    }
                } else {
                    $text = VarDumper::export($text);
                }
            }
            
            // If the user_id is not passed, dynamically set it from the user identity object
            if (!isset($context['user_id'])) {
                $context['user_id'] = Yii::$app->has('user') && Yii::$app->user->id != null ? Yii::$app->user->id : 'system';
            }

            // If the user_id of the event isn't the system, pre-load the policy number
            if (!\in_array($context['user_id'], ['system', null])) {
                if (Yii::$app->has('user') && Yii::$app->user->id) {
                    $model = Yii::$app->user->identity;
                } else {
                    $model = Yii::$app->user->identityClass::find()->where(['id' => $context['user_id']])->one();
                }
            }
            
            if (!isset($context['timestamp'])) {
                $context['timestamp'] = \microtime(true);
            }

            $this->getLogger()->log($this->_psrLevels[$message[1]], $text, $context);
        }
    }
}
