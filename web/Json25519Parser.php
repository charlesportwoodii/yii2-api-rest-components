<?php

namespace yrc\web;

use ncryptf\Request;
use ncryptf\Response;
use yrc\models\redis\EncryptionKey;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\helpers\Json;
use yii\web\JsonParser;
use yii\web\BadRequestHttpException;
use Yii;

/**
 * Allows for requests to be encrypted and signed via Curve/Ed 25519 cryptography via libsodium
 * @class Json25519 Parser
 */
class Json25519Parser extends JsonParser
{
    /**
     * @const HASH_HEADER
     */
    const HASH_HEADER = 'x-hashid';

    /**
     * @const PUBLICKEY_HEADER
     */
    const PUBLICKEY_HEADER = 'x-pubkey';

    /**
     * @const NONCE_HEADER
     */
    const NONCE_HEADER = 'x-nonce';

    private $decryptedBody;

    /**
     * Returns the decrypted box, if it was encrypted
     *
     * @return string
     */
    public function getDecryptedBody()
    {
        return $this->decryptedBody;
    }
    
    /**
     * Parses a HTTP request body.
     * @param string $rawBody the raw HTTP request body.
     * @param string $contentType the content type specified for the request body.
     * @return array parameters parsed from the request body
     * @throws BadRequestHttpException if the body contains invalid json and [[throwException]] is `true`.
     */
    public function parse($rawBody, $contentType)
    {
        if ($rawBody === '') {
            return '';
        }
        
        $key = self::getKeyFromHash(Yii::$app->request->getHeaders()->get(self::HASH_HEADER, null));
        $nonce = Yii::$app->request->getHeaders()->get(self::NONCE_HEADER, null);
        $public = Yii::$app->request->getHeaders()->get(self::PUBLICKEY_HEADER, null);

        try {
            $decryptedBody = $this->getRawBodyFromTokenAndNonce(
                $key,
                \base64_decode($nonce),
                \base64_decode($public),
                \base64_decode($rawBody)
            );

            if ($rawBody === false) {
                throw new Exception(Yii::t('yrc', 'Unable to decrypt request.'));
            }
        } catch (\Exception $e) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Invalid security headers.'));
        }

        try {
            $this->decryptedBody = $decryptedBody;
            $parameters = Json::decode($decryptedBody, $this->asArray);
            return $parameters === null ? [] : $parameters;
        } catch (InvalidParamException $e) {
            if ($this->throwException) {
                throw new BadRequestHttpException('Invalid JSON data in request body: ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Decrypts the raw body using EncryptionKey, the client submitted nonce and crypto_box_open
     * @param EncryptionKey $key
     * @param string $nonce
     * @param string $rawBody
     * @return string
     */
    private function getRawBodyFromTokenAndNonce(EncryptionKey $key, string $nonce, string $public, string $rawBody)
    {
        try {
            $response = new Response(
                \base64_decode($key->secret),
                $public
            );

            $rawBody = $response->decrypt(
                $rawBody,
                $nonce
            );

            return $rawBody;
        } catch (\Exception $e) {
            Yii::error('Unable to decrypt request.', 'yrc');
            throw new Exception(Yii::t('yrc', 'Unable decrypt response with provided data.'));
        }
    }

    /**
     * Helper method to retrieve and valdiate the token
     * @return Token
     */
    public static function getKeyFromHash($hash = null)
    {
        if ($hash === null) {
            $hash = Yii::$app->request->getHeaders()->get(self::HASH_HEADER, null);
        }

        // Fetch the hash from the header
        if ($hash === null) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Missing x-hashid header'));
        }

        $token = EncryptionKey::find()
            ->where([
                'hash' => $hash
            ])->one();

        if ($token === null) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Invalid x-hashid header.'));
        }

        return $token;
    }
}
