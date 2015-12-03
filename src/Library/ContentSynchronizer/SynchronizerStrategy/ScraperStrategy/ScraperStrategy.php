<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Owl;
use cURL\Request;

class ScraperStrategy extends SynchronizerStrategy
{
    public function updateBanner()
    {
        $url = (new Owl())->getUrl('', 'banners');
        $request = new Request($url);
        $request->getOptions()
            ->set(CURLOPT_TIMEOUT, 8)
            ->set(CURLOPT_RETURNTRANSFER, true)
            ->set(CURLOPT_USERAGENT, $this->di->get('config')->curlUserAgent);

        $response = $request->send();

        $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);
        if ($response->hasError() || $httpCode != 200) {
            $error = $response->getError()->getMessage();
            $this->log->error("Ошибка!!! http code: $httpCode message: $error url: $url");
            exit;
        }

        $content = json_decode($response->getContent(), true);
        $banners = $content['content']['banners'];

        parent::createBanner($banners);
    }

    public function updateContent()
    {

    }
}