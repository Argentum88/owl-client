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
        $robot->setMaximumRPM(60);
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

        $count = 0;
        $this->db->begin();
        $queue->addListener(
            'complete',
            function (Event $event) use (&$count) {
                $startCallbackTime = microtime(true);

                if ($count >= 100) {

                    $this->db->commit();
                    $this->db->begin();
                    $count = 0;
                    $this->log->info('применили транзакцию синхронизации картинок');
                }

                $response = $event->response;
                $urlId    = $event->request->urlId;
                $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);
                $info = $response->getInfo();

                if ($httpCode == 200) {
                    /** @var Urls $urls */
                    $urls        = Urls::findFirst(
                        [
                            'id = :id:',
                            'bind' => [
                                'id' => $urlId
                            ]
                        ]
                    );
                    $urls->delete();

                    $finishCallbackTame = microtime(true);
                    $callbackExecutedTime = $finishCallbackTame - $startCallbackTime;

                    $this->log->info("Соханили картинку: TOTAL_TIME={$info['total_time']} NAME_LOOKUP_TIME = {$info['namelookup_time']} CONNECT_TIME={$info['connect_time']} PRE_TRANSFER_TIME={$info['pretransfer_time']} START_TRANSFER_TIME={$info['starttransfer_time']} CALLBACK=$callbackExecutedTime");
                } else {
                    /** @var Urls $urls */
                    $urls        = Urls::findFirst(
                        [
                            'id = :id:',
                            'bind' => [
                                'id' => $urlId
                            ]
                        ]
                    );
                    $urls->state = Urls::ERROR;
                    $urls->save();

                    $error = '';
                    if ($response->hasError()) {
                        $error = $response->getError()->getMessage();
                    }

                    $this->log->error("Ошибка!!! http code: $httpCode message: $error");
                }
                $count++;
            }
        );

        try {
            $robot->run();
        } catch (Exception $e) {
            $this->log->notice($e->getMessage());
        }

        $this->db->commit();
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