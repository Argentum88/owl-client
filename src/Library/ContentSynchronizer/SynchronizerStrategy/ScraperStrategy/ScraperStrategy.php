<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy;

use Client\Library\ContentSynchronizer\BannerUpdatableInterface;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Owl;
use cURL\Request;

class ScraperStrategy extends SynchronizerStrategy implements BannerUpdatableInterface
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
            throw new \Exception("Ошибка!!! http code: $httpCode message: $error url: $url");
        }

        $content = json_decode($response->getContent(), true);
        parent::createBanner($content);
    }

    public function updateContent()
    {

    }
}