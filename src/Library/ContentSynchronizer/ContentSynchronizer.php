<?php

namespace Client\Library\ContentSynchronizer;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use FilesystemIterator;
use Phalcon\Di\Injectable;
use cURL\Event;
use cURL\Exception;
use cURL\Robot;
use Client\Models\ImageUrlsForPutWatermark;
use Client\Models\ImageUrlsForReplaceWatermark;
use Client\Models\Urls;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ContentSynchronizer extends Injectable implements SynchronizableInterface
{
    /** @var SynchronizableInterface | null */
    protected $synchronizerStrategy;

    public function __construct(SynchronizerStrategy $synchronizerStrategy = null)
    {
        $this->synchronizerStrategy = $synchronizerStrategy;
    }

    public function putWatermark()
    {
        $this->scrapeImage(new ImageUrlsForPutWatermark());
    }

    public function fetchExistingImages()
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->config->imagesCacheDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
        $urls = new Urls();
        $urls->init();
        
        foreach($files as $file => $info){
            
            $url = str_replace($this->config->imagesCacheDir, '', $file);
            $urls->insert($url, Urls::IMAGE, Urls::FOR_REPLACE_WATERMARK);
        }

        $urls->flash();
        Urls::find("url = '/favicon.ico'")->delete();
        Urls::find("url = '/.gitignore'")->delete();
    }

    public function replaceWatermark()
    {
        $this->scrapeImage(new ImageUrlsForReplaceWatermark());
    }

    protected function scrapeImage($provider)
    {
        $robot = new Robot();
        $robot->setRequestProvider($provider);

        $robot->setQueueSize(1);
        $robot->setMaximumRPM(20);
        $robot->setSpeedMeterFrame(1);

        $queue = $robot->getQueue();
        $queue->getDefaultOptions()->set(
            [
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY         => true,
                CURLOPT_USERAGENT      => $this->di->get('config')->curlUserAgent,
            ]
        );

        $queue->addListener(
            'complete',
            function (Event $event) {

                $response = $event->response;
                $url    = $event->request->url;
                $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);
                $info = $response->getInfo();

                if ($httpCode == 200) {

                    $this->log->info("Соханили картинку: TOTAL_TIME={$info['total_time']} NAME_LOOKUP_TIME = {$info['namelookup_time']} CONNECT_TIME={$info['connect_time']} PRE_TRANSFER_TIME={$info['pretransfer_time']} START_TRANSFER_TIME={$info['starttransfer_time']}");
                } else {

                    $error = '';
                    if ($response->hasError()) {
                        $error = $response->getError()->getMessage();
                    }

                    $this->log->error("Ошибка!!! http code: $httpCode message: $error url: $url");
                }
            }
        );

        try {
            $robot->run();
        } catch (Exception $e) {
            $this->log->notice($e->getMessage());
        }
    }

    public function updateContent()
    {
        $this->synchronizerStrategy->updateContent();
    }

    public function updateBanner()
    {
        $this->synchronizerStrategy->updateBanner();
    }
}