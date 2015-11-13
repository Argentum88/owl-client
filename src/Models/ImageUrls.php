<?php

namespace Client\Models;

use cURL\RequestProviderInterface;
use cURL\Request;

class ImageUrls extends Urls implements RequestProviderInterface
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

        $url = $this->config->imgHost . $rawUrl['url'];

        $request = new Request($url);
        $request->urlId = $rawUrl['urlId'];
        return $request;
    }

    protected function selectUrls()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find([
                'state = :state: AND type = :type: AND action = :action:',
                'limit' => 1000,
                'bind' => [
                    'state' => Urls::OPEN,
                    'type'  => Urls::IMAGE,
                    'action' => Urls::FOR_UPDATING
                ]
            ]);

        foreach ($urls as $url) {
            $url->state = Urls::LOCK;
            $url->update();

            $this->urls[] = ['url' => $url->url, 'urlId' => $url->id];
        }
    }
}
