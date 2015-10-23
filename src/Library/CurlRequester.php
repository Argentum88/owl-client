<?php

namespace Client\Library;

class CurlRequester
{
    protected $defaultCurlOpts = [];

    protected $di;

    public function __construct()
    {
        $this->di = \Phalcon\DI::getDefault();

        $this->defaultCurlOpts = [
            CURLOPT_USERAGENT => $this->di->get('config')->curlUserAgent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ];
    }

    protected function log($ch, $response)
    {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode != 200) {

            $logger = new \Logger();
            $logger->log($ch, $response);
        }
    }

    protected function request($url, $curlOpts = [])
    {

        $curl = curl_init();

        curl_setopt_array($curl, [CURLOPT_URL => $url] + $this->defaultCurlOpts);

        $response = curl_exec($curl);
        $items = json_decode($response, true);

        //$this->log($curl, $response);

        curl_close($curl);

        return $items;
    }
}
