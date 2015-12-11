<?php

namespace Client\Models;

use cURL\RequestProviderInterface;

class ImageUrlsForReplaceWatermark extends ImageUrlsForPutWatermark implements RequestProviderInterface
{
    protected function selectUrls()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find([
                'type = :type: AND action = :action: AND state = :state:',
                'limit' => 1,
                'bind' => [
                    'type'  => Urls::IMAGE,
                    'action' => Urls::FOR_REPLACE_WATERMARK,
                    'state' => Urls::OPEN,
                ]
            ]);

        foreach ($urls as $url) {
            unlink($this->getDI()->get('config')->imagesCacheDir . $url->url);

            $this->urls[] = ['url' => $url->url, 'urlId' => $url->id];
        }

        $urls->delete();
    }
}
