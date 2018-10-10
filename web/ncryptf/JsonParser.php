<?php declare(strict_types=1);

namespace yrc\web\ncryptf;

use InvalidArgumentException;
use ncryptf\Request;
use ncryptf\Response;
use ncryptf\exceptions\DecryptionFailedException;
use ncryptf\exceptions\InvalidSignatureException;
use ncryptf\exceptions\InvalidChecksumException;
use yrc\models\redis\EncryptionKey;
use yrc\web\Request as YiiRequest;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use Yii;

/**
 * Parses vnd.ncryptf+json
 * @class Ncryptf JsonParser
 */
class JsonParser extends \yii\web\JsonParser
{
    private $decryptedBody;

    /**
     * Returns the decrypted response
     *
     * @return string
     */
    public function getDecryptedBody() :? string
    {
        return $this->decryptedBody;
    }

    /**
     * Parses vnd.ncryptf+json
     *
     * @param string  $rawBody
     * @param string $contentType
     * @return mixed
     */
    public function parse($rawBody, $contentType)
    {
        if ($contentType === 'application/vnd.25519+json') {
            Yii::warning([
                'message' => '`application/vnd.25519+json` content type is deprecated. Migrate to `application/vnd.ncryptf+json'
            ]);
        }

        if ($rawBody === '') {
            $this->decryptedBody = '';
            return [];
        }

        $request = Yii::$app->request;
        $version = Response::getVersion(\base64_decode($rawBody));
        $key = $this->getEncryptionKey($request);

        try {
            $this->decryptedBody = $this->decryptRequest($key, $request, $rawBody, $version);
        } catch (DecryptionFailedException | InvalidArgumentException | InvalidSignatureException | InvalidChecksumException $e) {
            throw new BadRequestHttpException(Yii::t('yrc', 'Unable to decrypt response.'));
        } catch (\Exception $e) {
            Yii::warning([
                'message' => 'An unexpected error occured when decryption the response. See attached exception',
                'exception' => $e
            ]);

            throw new BadRequestHttpException(Yii::t('yrc', 'Unable to decrypt response.'));
        }

        try {
            $parameters = Json::decode($this->decryptedBody, $this->asArray);
            return $parameters ?? [];
        } catch (InvalidParamException $e) {
            if ($this->throwException) {
                throw new BadRequestHttpException('Invalid JSON data in request body: ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Decrypts the request using a given encryption key and request parameters
     *
     * @param EncryptionKey $key
     * @param \yrc\web\Request $request
     * @param string $rawBody
     * @param string $version
     * @return string
     */
    private function decryptRequest(EncryptionKey $key, \yrc\web\Request $request, string $rawBody, int $version)
    {
        static $response = null;
        static $nonce = null;
        static $publicKey = null;

        $response = new Response(
            \base64_decode($key->secret)
        );

        if ($version === 1) {
            $publicKey = $request->headers->get('x-pubkey', null);
            $nonce = $request->headers->get('x-nonce', null);

            if ($publicKey === null || $nonce === null) {
                throw new Exception(Yii::t('yrc', 'Missing nonce or public key header. Unable to decrypt request.'));
            }
            $nonce = \base64_decode($nonce);
            $publicKey = \base64_decode($publicKey);
        }

        $decryptedRequest = $response->decrypt(
            \base64_decode($rawBody),
            $publicKey,
            $nonce
        );

        if ($key->is_single_use) {
            $key->delete();
        }

        return $decryptedRequest;
    }

    /**
     * Fetches the local encryption key from the data provided in the request
     *
     * @param \yrc\web\Request $request
     * @param string $rawBody
     * @param integer $version
     * @return EncryptionKey
     */
    private function getEncryptionKey(\yrc\web\Request $request) : EncryptionKey
    {
        $lookup = $request->headers->get('x-hashid', null);
        if ($lookup === null) {
            Yii::warning([
                'message' => 'X-HashId missing on request. Unable to decrypt response.'
            ]);
            throw new Exception(Yii::t('yrc', 'Unable to decrypt response.'));
        }

        $key = EncryptionKey::find()->where([
            'hash' => $lookup
        ])->one();

        if ($key === null) {
            throw new Exception(Yii::t('yrc', 'Unable to decrypt response.'));
        }

        return $key;
    }
}
