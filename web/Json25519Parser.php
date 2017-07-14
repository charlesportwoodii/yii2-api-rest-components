<?php

namespace yrc\web;

use yii\helpers\Json;
use yii\web\JsonParser;
use yrc\api\models\EncryptionKey;

use yii\web\BadRequestHttpException;
use yii\base\InvalidParamException;
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

    /**
     * Parses a HTTP request body.
     * @param string $rawBody the raw HTTP request body.
     * @param string $contentType the content type specified for the request body.
     * @return array parameters parsed from the request body
     * @throws BadRequestHttpException if the body contains invalid json and [[throwException]] is `true`.
     */
    public function parse($rawBody, $contentType)
    {
        $key = self::getKeyFromHash(Yii::$app->request->getHeaders()->get(self::HASH_HEADER, null));
        $nonce = Yii::$app->request->getHeaders()->get(self::NONCE_HEADER, null);
        $public = Yii::$app->request->getHeaders()->get(self::PUBLICKEY_HEADER, null);

        try {
            $rawBody = $this->getRawBodyFromTokenAndNonce($key, $nonce, $public, $rawBody);

            if ($rawBody === false) {
                throw new \Exception;
            }

        } catch (\Exception $e) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Invalid security headers.'));
        }

        try {
            $parameters = Json::decode($rawBody, $this->asArray);
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
    private function getRawBodyFromTokenAndNonce($key, $nonce, $public, $rawBody)
    {
        if ($key === null || empty($nonce) || empty($public)) {
            throw new HttpException(400, Yii::t('yrc', 'Invalid security headers.'));
        }

        // Construct a keypair from the client_public and the server private key
        $kp = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            \base64_decode($key->secret),
            \base64_decode($public)
        );

        // Decrypt the raw body
        $rawBody = sodium_crypto_box_open(
            \base64_decode($rawBody),
            \base64_decode($nonce),
            $kp
        );
        
        // The key is deleted upon consumption
        $key->delete();

        return $rawBody;
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

        $token = EncryptionKey::find()->where(['hash' => $hash])->one();

        if ($token === null) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Invalid x-hashid header.'));
        }

        return $token;
    }
}