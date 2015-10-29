<?php

namespace Client\Library;

use Client\Library\Debugger;
use cURL\Request;
use Phalcon\Debug;
use Phalcon\Di;

class OwlRequester
{
    protected $di;

    public function __construct()
    {
        $this->di = Di::getDefault();
    }

    public function request($url, $useOwlServer = true)
    {
        PH_DEBUG ? Debugger::dumpBar($url, 'url') : null;

        if ($useOwlServer) {

            $url = $this->getUrl($url);
            $request = new Request($url);
            $request->getOptions()
                ->set(CURLOPT_TIMEOUT, 8)
                ->set(CURLOPT_RETURNTRANSFER, true)
                ->set(CURLOPT_USERAGENT, $this->di->get('config')->curlUserAgent);

            $response = $request->send();
            $response = json_decode($response->getContent(), true);

            return $response;
        }

        /** @var Urls $urls */
        $urls = Urls::findFirst([
                'url = :url:',
                'bind' => [
                    'url' => $url
                ]
            ]);

        return json_decode($urls->content, true);
    }

    public function getUrl($rawUrl)
    {
        $clientId = $this->di->get('config')->clientId;
        $secretKey = $this->di->get('config')->secretKey;
        $string = "client_id=$clientId&url=$rawUrl$secretKey";

        $params = http_build_query([
                'client_id' => $clientId,
                'url' => $rawUrl,
                'sig' => md5($string)
            ]);

        return $this->di->get('config')->owl . "/api/1.0/?$params";
    }
}
