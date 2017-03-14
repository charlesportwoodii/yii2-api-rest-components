<?php

namespace yrc\web;

use yii\helpers\Json;
use yii\web\JsonParser;
use yrc\api\models\TokenKeyPair;

use yii\web\BadRequestHttpException;
use Yii;

/**
 * Allows for requests to be encrypted and signed via Curve/Ed 25519 cryptography via libsodium
 * @class Json25519 Parser
 */
class Json25519Parser extends JsonParser
{
    const HASH_HEADER = 'x-hashid';

    const PUBLICKEY_HEADER = 'x-pubkey';

    /**
     * Parses a HTTP request body.
     * @param string $rawBody the raw HTTP request body.
     * @param string $contentType the content type specified for the request body.
     * @return array parameters parsed from the request body
     * @throws BadRequestHttpException if the body contains invalid json and [[throwException]] is `true`.
     */
    public function parse($rawBody, $contentType)
    {
        // If the content type isn't application/json+25519 try the parent validator
        if ($contentType !== 'application/json+25519') {
            return parent::parse($rawBody, $contentType);
        }

        // Fetch the hash from the header
        $hash = Yii::$app->request->getHeaders()->get(self::HASH_HEADER, null);
        if ($hash === null) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Missing x-hashid header'));
        }

        $token = TokenKeyPair::find(['hash' => $hash])->one();

        if ($token === null) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Invalid x-hashid header. The provided header is either invalid or expired.'));
        }
        try {
            // We are using an anonymous box so as to not identity the customer
            $rawBody = \Sodium\crypto_box_seal_open(
                \base64_decode($rawBody),
                $token->getBoxKeyPair()
            );

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
}