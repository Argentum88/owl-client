<?php

namespace Client\Models;

use Client\Library\Debugger;
use cURL\Request;
use Phalcon\Debug;
use Phalcon\Di;
use Phalcon\Di\Injectable;

class Owl extends Injectable
{
    protected $di;

    public function __construct()
    {
        $this->di = Di::getDefault();
    }

    public function request($url, $useOwlServer = true)
    {
        $url = urldecode($url);
        PH_DEBUG ? Debugger::dumpBar($url, 'url') : null;

        if ($useOwlServer) {

            $url = $this->getUrl($url);
            $request = new Request($url);
            $request->getOptions()
                ->set(CURLOPT_TIMEOUT, 8)
                ->set(CURLOPT_RETURNTRANSFER, true)
                ->set(CURLOPT_USERAGENT, $this->di->get('config')->curlUserAgent);

            $response = $request->send();
            $content = json_decode($response->getContent(), true);

            $url = $this->getUrl('', 'common');
            $request = new Request($url);
            $request->getOptions()
                ->set(CURLOPT_TIMEOUT, 8)
                ->set(CURLOPT_RETURNTRANSFER, true)
                ->set(CURLOPT_USERAGENT, $this->di->get('config')->curlUserAgent);

            $response = $request->send();
            $common = json_decode($response->getContent(), true);

            return $content + $common;
        }

        $response = Contents::get($url);

        return $response;
    }

    public function getUrl($rawUrl, $type = 'content')
    {
        $clientId = $this->config->clientId;
        $secretKey = $this->config->secretKey;
        $string = "client_id=$clientId&url=$rawUrl$secretKey";

        $params = http_build_query([
                'client_id' => $clientId,
                'url' => $rawUrl,
                'sig' => md5($string)
            ]);

        return $this->config->owl . "/api/1.0/$type/?$params";
    }
}
