<?php

namespace Client\Library;

use Client\Models\Contents;
use Client\Models\Urls;
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

        /** @var Contents $content */
        $content = Contents::findFirst([
                'url = :url: AND state = :state: AND type = :type:',
                'bind' => [
                    'url' => $url,
                    'state' => Contents::READY,
                    'type' => Urls::CONTENT
                ],
                'order' => 'created_at DESC'
            ]);

        if (!$content) {
            return ['success' => false];
        }

        /** @var Contents $common */
        $common = Contents::findFirst([
                'url = :url: AND state = :state: AND type = :type:',
                'bind' => [
                    'url' => ' ',
                    'state' => Contents::READY,
                    'type' => Urls::COMMON
                ],
                'order' => 'created_at DESC'
            ]);

        $response = json_decode($content->content, true)  + json_decode($common->content, true);

        return $response;
    }

    public function getUrl($rawUrl, $type = 'content')
    {
        $clientId = $this->di->get('config')->clientId;
        $secretKey = $this->di->get('config')->secretKey;
        $string = "client_id=$clientId&url=$rawUrl$secretKey";

        $params = http_build_query([
                'client_id' => $clientId,
                'url' => $rawUrl,
                'sig' => md5($string)
            ]);

        return $this->di->get('config')->owl . "/api/1.0/$type/?$params";
    }
}
