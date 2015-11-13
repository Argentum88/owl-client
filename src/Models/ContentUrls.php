<?php

namespace Client\Models;

use cURL\RequestProviderInterface;
use cURL\Request;

class ContentUrls extends Urls implements RequestProviderInterface
{
    protected $urls = [];

    public function nextRequest()
    {
        $rawUrl = array_pop($this->urls);
        if (!$rawUrl) {

            $this->selectUrls();
            $rawUrl = array_pop($this->urls);
        }

        if (!$rawUrl) {

            return false;
        }

        $url = (new Owl())->getUrl($rawUrl['url']);

        $request = new Request($url);
        $request->url = $rawUrl['url'];
        $request->urlId = $rawUrl['urlId'];
        return $request;
    }

    protected function selectUrls()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find([
                'state = :state: AND type = :type:',
                'limit' => 1000,
                'bind' => [
                    'state' => Urls::OPEN,
                    'type'  => Urls::CONTENT
                ]
            ]);

        foreach ($urls as $url) {
            $url->state = Urls::LOCK;
            $url->update();

            $this->urls[] = ['url' => $url->url, 'urlId' => $url->id];
        }
    }
}
