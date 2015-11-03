<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\Provider;

use Client\Models\Urls;
use cURL\RequestProviderInterface;
use cURL\Request;
use Phalcon\Di;

class ImageUrlProvider implements RequestProviderInterface
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

        $url = Di::getDefault()->get('config')->imgHost . $rawUrl;

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
                    'type'  => Urls::IMAGE
                ]
            ]);

        foreach ($urls as $url) {
            $url->state = Urls::LOCK;
            $url->update();

            $this->urls[] = $url->url;
        }
    }
}
