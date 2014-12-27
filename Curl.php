<?php

namespace pvlg\yii2\curl;

use Yii;
use yii\base\Component;
use yii\helpers\Json;

class Curl extends Component
{

    public $resource;
    
    public $connectionTimeout;
    
    public $dataTimeout;

    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    public function get($url, $options = [], $body = null, $raw = true)
    {
        return $this->httpRequest('GET', $url, $body, $raw);
    }

    protected function httpRequest($method, $url, $requestBody = null, $raw = false)
    {
        $method = strtoupper($method);

        // response body
        $body = '';

        $options = [
            CURLOPT_USERAGENT => 'Yii Framework 2 ' . __CLASS__,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            // http://www.php.net/manual/en/function.curl-setopt.php#82418
            CURLOPT_HTTPHEADER => ['Expect:'],
            CURLOPT_WRITEFUNCTION => function ($curl, $data) use (&$body) {
        $body .= $data;

        return mb_strlen($data, '8bit');
    },
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($this->connectionTimeout !== null) {
            $options[CURLOPT_CONNECTTIMEOUT] = $this->connectionTimeout;
        }
        if ($this->dataTimeout !== null) {
            $options[CURLOPT_TIMEOUT] = $this->dataTimeout;
        }
        if ($requestBody !== null) {
            $options[CURLOPT_POSTFIELDS] = $requestBody;
        }
        if ($method == 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
            unset($options[CURLOPT_WRITEFUNCTION]);
        }

        $profile = $method . ' ' . $url . '#' . md5(serialize($requestBody));
        Yii::trace("Sending request: $url\n" . Json::encode($requestBody), __METHOD__);
        Yii::beginProfile($profile, __METHOD__);

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);
        if (curl_exec($curl) === false) {
            throw new Exception('curl request failed: ' . curl_error($curl), curl_errno($curl));
        }

        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        Yii::endProfile($profile, __METHOD__);

        if ($responseCode >= 200 && $responseCode < 300) {
            if ($method == 'HEAD') {
                return true;
            } else {
                return $raw ? $body : Json::decode($body);
            }
        } elseif ($responseCode == 404) {
            return false;
        } else {
            throw new HttpException($responseCode, $body);
        }
    }

}
