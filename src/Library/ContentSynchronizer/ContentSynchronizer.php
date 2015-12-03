<?php

namespace Client\Library\ContentSynchronizer;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Phalcon\Di\Injectable;
use cURL\Event;
use cURL\Exception;
use cURL\Robot;
use Client\Models\ImageUrls;
use Client\Models\Urls;

class ContentSynchronizer extends Injectable
{
    /** @var SynchronizerStrategy */
    protected $synchronizerStrategy;

    public function __construct(SynchronizerStrategy $synchronizerStrategy)
    {
        $this->synchronizerStrategy = $synchronizerStrategy;
    }

    public function scrapeImage()
    {
        $robot = new Robot();
        $robot->setRequestProvider(new ImageUrls());

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
                $startCallbackTime = microtime(true);

                $response = $event->response;
                $url    = $event->request->url;
                $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);
                $info = $response->getInfo();

                if ($httpCode == 200) {
                    $urls = Urls::find(
                        [
                            'url = :url:',
                            'bind' => [
                                'url' => $url
                            ]
                        ]
                    );
                    $urls->delete();

                    $finishCallbackTame = microtime(true);
                    $callbackExecutedTime = $finishCallbackTame - $startCallbackTime;

                    $this->log->info("Соханили картинку: TOTAL_TIME={$info['total_time']} NAME_LOOKUP_TIME = {$info['namelookup_time']} CONNECT_TIME={$info['connect_time']} PRE_TRANSFER_TIME={$info['pretransfer_time']} START_TRANSFER_TIME={$info['starttransfer_time']} CALLBACK=$callbackExecutedTime");
                } else {
                    $urls = Urls::find(
                        [
                            'url = :url:',
                            'bind' => [
                                'url' => $url
                            ]
                        ]
                    );
                    $urls->delete();

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