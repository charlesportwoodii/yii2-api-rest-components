<?php

namespace yrc\web;

use yii\helpers\Json;
use yii\web\JsonParser;
use yrc\api\models\TokenKeyPair;

use yii\web\BadRequestHttpException;
use yii\base\InvalidParamException;
use Yii;

/**
 * Allows for requests to be encrypted and signed via Curve/Ed 25519 cryptography via libsodium
 * @class Json25519 Parser
 */
class Json25519Parser extends JsonParser
{
    const HASH_HEADER = 'x-hashid';

    const PUBLICKEY_HEADER = 'x-pubkey';

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
        $token = self::getTokenFromHash(Yii::$app->request->getHeaders()->get(self::HASH_HEADER, null));

        try {
            $nonce = Yii::$app->request->getHeaders()->get(self::NONCE_HEADER, null);

            // If a nonce is not provided, use crypto_box_seal
            if ($nonce === null) {
                $rawBody = \Sodium\crypto_box_seal_open(
                    \base64_decode($rawBody),
                    $token->getBoxKeyPair()
                );

                // If this is a One Time Use Token, delete it to prevent it from being reused
                if ($token->type === TokenKeyPair::OTK_TYPE) {
                    $token->delete();
                }
            } else {
                // Otherwise, decrypt the box using the nonce and key
                // Construct a keypair from the client_public and the server private key
                $kp = \Sodium\crypto_box_keypair_from_secretkey_and_publickey(
                    \base64_decode($token->secret_box_kp),
                    \base64_decode($token->client_public)
                );

                // Decrypt the raw body
                $rawBody = \Sodium\crypto_box_open(
                    \base64_decode($rawBody),
                    \base64_decode($nonce),
                    $kp
                );
            }

            if ($rawBody === false) {
                throw new \Exception;
            }

        } catch (\Exception $e) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Invalid x-hashid header. The provided header is either invalid or expired.'));
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
     * Helper method to retrieve and valdiate the token
     * @return Token
     */
    public static function getTokenFromHash($hash)
    {
        // Fetch the hash from the header
        if ($hash === null) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Missing x-hashid header'));
        }

        $token = TokenKeyPair::find()->where(['hash' => $hash])->one();

        if ($token === null) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Invalid x-hashid header. The provided header is either invalid or expired.'));
        }

        return $token;
    }
}