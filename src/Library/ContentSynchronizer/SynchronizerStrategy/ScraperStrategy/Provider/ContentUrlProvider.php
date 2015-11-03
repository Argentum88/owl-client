<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\Provider;

use Client\Models\Urls;
use Client\Library\OwlRequester;
use cURL\RequestProviderInterface;
use cURL\Request;

class ContentUrlProvider implements RequestProviderInterface
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

        $url = (new OwlRequester())->getUrl($rawUrl);

        $request = new Request($url);
        $request->url = $rawUrl;
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

            $this->urls[] = $url->url;
        }
    }
}
