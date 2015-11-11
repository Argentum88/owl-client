<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\Provider\ImageUrlProvider;
use Client\Models\Contents;
use Client\Models\Urls;
use cURL\Event;
use cURL\Exception;
use cURL\Robot;
use Phalcon\Di\Injectable;

abstract class SynchronizerStrategy extends Injectable
{
    abstract public function fullUpdate();

    abstract public function update();

    protected function setReadyState()
    {
        $this->db->execute("UPDATE contents SET state = ? WHERE state = ?", [Contents::READY, Contents::UPDATING]);
        $this->log->info('Сброс статуса обновления');
    }

    protected function deleteFirstVersion($all = true)
    {
        if ($all) {
            $this->db->execute("DELETE FROM contents WHERE version = ?", [1]);
            $this->log->info('Удалена первая версия');

            return;
        }

        /** @var Contents[] $contents */
        $contents = Contents::find([
                'version = :version:',
                'bind' => [
                    'version' => 2
                ]
            ]);

        $count = 0;
        $this->db->begin();
        foreach ($contents as $content) {
            if ($count > 100) {
                $this->db->commit();
                $this->db->begin();
                $count = 0;
                $this->log->info('применили транзакцию удаления первой версии');
            }

            $contentOfFirstVersion  = Contents::findFirst([
                    'version = :version: AND url = :url:',
                    'bind' => [
                        'version' => 1,
                        'url' => $content->url
                    ]
                ]);

            if ($contentOfFirstVersion) {
                $contentOfFirstVersion->delete();

                $count++;
            }
        }
        $this->db->commit();

        $this->log->info('Удалена первая версия');
    }

    protected function moveSecondVersionToFirst()
    {
        $this->db->execute("UPDATE contents SET version = ? WHERE version = ?", [1, 2]);
        $this->log->info('Сброс версии');
    }

    protected function createUrl($url, $type = Urls::CONTENT)
    {
        $oldUrls = Urls::findFirst(
            [
                'url = :url:',
                'bind' => [
                    'url' => $url
                ]
            ]
        );

        if ($oldUrls) {
            $this->log->error("Дубль: $url");
            return false;
        }

        $urls        = new Urls();
        $urls->url   = $url;
        $urls->state = Urls::OPEN;
        $urls->type  = $type;

        try {
            if ($urls->create()) {
                return true;
            }

            $messages = $urls->getMessages();
            foreach ($messages as $message) {
                $this->log->error($message->getMessage());
            }

            return false;
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());

            return false;
        }
    }

    protected function scrapeImageUrls()
    {
        $robot = new Robot();
        $robot->setRequestProvider(new ImageUrlProvider());

        $robot->setQueueSize(3);
        $robot->setMaximumRPM(6000);

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

                if ($count > 100) {

                    $this->db->commit();
                    $this->db->begin();
                    $count = 0;
                    $this->log->info('применили транзакцию');
                }

                $response = $event->response;
                $urlId    = $event->request->urlId;
                $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);

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
                    $urls->state = Urls::CLOSE;
                    $urls->save();

                    $this->log->info('соханили картинку');
                    $count++;
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
                    $this->db->commit();
                    exit();
                }
            }
        );

        try {
            $robot->run();
        } catch (Exception $e) {
            $this->log->notice($e->getMessage());
        }

        $this->db->commit();
    }
}