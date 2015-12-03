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

        $url = $this->getDI()->get('config')->imgHost . $rawUrl['url'];

        $request = new Request($url);
        $request->urlId = $rawUrl['urlId'];
        $request->url = $rawUrl['url'];
        return $request;
    }

    protected function selectUrls()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find([
                'type = :type: AND action = :action: AND state = :state:',
                'limit' => 1,
                'bind' => [
                    'type'  => Urls::IMAGE,
                    'action' => Urls::FOR_UPDATING,
                    'state' => Urls::OPEN,
                ]
            ]);

        foreach ($urls as $url) {
            $url->state = Urls::LOCK;
            $url->update();

            $this->urls[] = ['url' => $url->url, 'urlId' => $url->id];
        }
    }
}
