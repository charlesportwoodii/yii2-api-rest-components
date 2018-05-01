<?php

namespace yrc\components;

use yii\base\BaseObject;
use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\httpclient\RequestEvent;
use Yii;

/**
 * Class HttpClientComponent
 * Abstracts HTTP requests with request & response logging
 * @package app\components
 */
final class HttpClientComponent extends BaseObject
{
    /**
     * Options to pass to yii\httpclient\Client;
     * @var string $clientOptions
     */
    public $clientOptions;
    
    /**
     * HttpOptions
     * @var array $options
     */
    public $options;

    /**
     * The HTTP client
     * @var Client $client
     */
    private $client;

    /**
     * Returns the client instance
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Initializes the HttpClient with a transport and a given set of options, and pre-loads events
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->client = new Client($this->clientOptions);

        $this->client->on(Client::EVENT_BEFORE_SEND, function(RequestEvent $e) {
            Yii::info([
                'message' => sprintf('Sending HTTP request [%s] %s', $e->request->getMethod(), $e->request->getUrl()),
                'data' => $e->request->getData(),
                'user_id' => Yii::$app->user->id ?? null
            ], 'httpclient');
        });

        $this->client->on(Client::EVENT_AFTER_SEND, function(RequestEvent $e) {
            Yii::info([
                'message' => sprintf('Recieved HTTP response HTTP [%s] | [%s] %s', $e->response->getStatusCode(), $e->request->getMethod(), $e->request->getUrl()),
                'data' => (function () use ($e) {
                    $content = $e->response->getContent();
                    if (preg_match('~[^\x20-\x7E\t\r\n]~', $content) > 0) {
                        return '[binary data]';
                    }
                    return $content;
                })(),
                'user_id' => Yii::$app->user->id ?? null
            ], 'httpclient');
        });
    }
}