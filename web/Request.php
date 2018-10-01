<?php declare(strict_types=1);

namespace yrc\web;

use yii\base\InvalidConfigException;
use yii\web\RequestParserInterface;
use yrc\web\Response;
use Yii;

class Request extends \yii\web\Request
{
    /**
     * @var array $parsers
     */
    public $parsers = [ 
        'application/json' => \yii\web\JsonParser::class,
        'application/vnd.25519+json' => \yrc\web\ncryptf\JsonParser::class,
        'application/vnd.ncryptf+json' => \yrc\web\ncryptf\JsonParser::class,
    ];

    /**
     * @var array $_ncryptfContentTypes
     */
    private $_ncryptfContentTypes = [
        'application/vnd.25519+json',
        'application/vnd.ncryptf+json'
    ];

    /**
     * @var array $_bodyParams
     * @see yii\web\Request::_bodyParams
     */
    private $_bodyParams;

    /**
     * @var string $_decryptedBody
     */
    private $_decryptedBody;

    /**
     * Returns true if a given need is in an haystack array
     * @param array $haystack
     * @param string $needle
     * @return boolean
     */
    private function striposa($haystack, $needle)
    {
        foreach ($haystack as $element) {
            if (\stripos($element, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the decrypted body
     * @return string
     */
    public function getDecryptedBody() :? string
    {
        $rawContentType = $this->getContentType();

        if (!$this->striposa($this->_ncryptfContentTypes, $rawContentType)) {
            return $this->getRawBody();
        }

        $this->getBodyParams();
        return $this->_decryptedBody;
    }

    /**
     * @inheritdoc
     */
    public function getContentType()
    {
        return parent::getContentType() ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            if (isset($_POST[$this->methodParam])) {
                $this->_bodyParams = $_POST;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }
            $rawContentType = $this->getContentType();
            if (($pos = strpos($rawContentType, ';')) !== false) {
                // e.g. text/html; charset=UTF-8
                $contentType = substr($rawContentType, 0, $pos);
            } else {
                $contentType = $rawContentType;
            }
            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
                if ($this->striposa($this->_ncryptfContentTypes, $rawContentType)) {
                    $this->_decryptedBody = $parser->getDecryptedBody();
                }
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException('The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.');
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $_POST;
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }
}
