<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Urls;
use Client\Models\Contents;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\Provider\ContentUrlProvider;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\Provider\ImageUrlProvider;
use cURL\Exception;
use cURL\Robot;
use cURL\Event;

class ScraperStrategy extends SynchronizerStrategy
{
    const TYPE_BOOKS = 2;

    public function initiallyFill(array $params = [])
    {
        $this->getUrls();
        $this->scrapeContentUrls();
        $this->deleteFilledUrls();
        $this->setReadyState();
        $this->deleteFirstVersion();
        $this->moveSecondVersionToFirst();

        $this->scrapeImageUrls();
    }

    public function fullUpdate(array $params = [])
    {
        $this->getUrls();
        $this->scrapeContentUrls();
        $this->deleteFilledUrls();
        $this->setReadyState();
        $this->deleteFirstVersion();
        $this->moveSecondVersionToFirst();

        $this->scrapeImageUrls();
    }

    public function update(array $params = [])
    {
        $this->db->begin();
        foreach ($params['urls'] as $url) {
            $urls = new Urls();
            $urls->url = $url;
            $urls->state = Urls::OPEN;
            $urls->type = Urls::CONTENT;
            $urls->save();
        }
        $this->db->commit();

        $this->scrapeContentUrls();
        $this->deleteFilledUrls();
        $this->setReadyState();
        $this->deleteFirstVersion(false);
        $this->moveSecondVersionToFirst();
    }

    protected function getUrls()
    {
        $this->log->info('Начали');

        $response = (new \Client\Library\OwlRequester())->request('/');
        $response = (new \Client\Library\OwlRequester())->request($response['static_urls'][ScraperStrategy::TYPE_BOOKS]);

        $urls = new Urls();
        $urls->url = '/';
        $urls->state = Urls::OPEN;
        $urls->type = Urls::CONTENT;
        $urls->save();
        $this->log->info('сохранили урл главной');

        $urls = new Urls();
        $urls->url = $response['static_urls'][ScraperStrategy::TYPE_BOOKS];
        $urls->state = Urls::OPEN;
        $urls->type = Urls::CONTENT;
        $urls->save();
        $this->log->info('сохранили урл списка книг');

        $classIds = array_keys($response['intersects']['classes']);
        foreach ($classIds as $classId) {
            $urls = new Urls();
            $urls->url = $response['classes'][$classId]['url'];
            $urls->state = Urls::OPEN;
            $urls->type = Urls::CONTENT;
            $urls->save();
            $this->log->info('сохранили урл класса');
        }

        $subjectIds = array_keys($response['intersects']['subjects']);
        foreach ($subjectIds as $subjectId) {
            $urls = new Urls();
            $urls->url = $response['subjects'][$subjectId]['url'];
            $urls->state = Urls::OPEN;
            $urls->type = Urls::CONTENT;
            $urls->save();
            $this->log->info('сохранили урл предмета');
        }

        foreach ($response['intersects']['classes'] as $class) {
            foreach ($class as $ClassSubject) {
                $urls = new Urls();
                $urls->url = $ClassSubject['url'];
                $urls->state = Urls::OPEN;
                $urls->type = Urls::CONTENT;
                $urls->save();
                $this->log->info('сохранили урл класса/предмета');
            }
        }

        $this->saveBookImages($response['books']);
        //return;

        foreach ($response['books'] as $book) {
            $bookUrls = new Urls();
            $bookUrls->url = $book['url'];
            $bookUrls->state = Urls::OPEN;
            $bookUrls->type = Urls::CONTENT;
            $bookUrls->save();
            $this->log->info('сохранили урл книги');

            $bookResponse = (new \Client\Library\OwlRequester())->request($book['url']);

            $this->db->begin();
            $this->saveTaskUrls($bookResponse['structure']);
            $this->db->commit();
            $this->log->info('сохранили урлы тасков');
        }

        $this->log->info('Закончили');
    }

    protected function saveTaskUrls($structure)
    {
        foreach ($structure as $topic) {
            foreach ($topic['tasks'] as $task) {
                $taskUrls = new Urls();
                $taskUrls->url = $task['url'];
                $taskUrls->state = Urls::OPEN;
                $taskUrls->type = Urls::CONTENT;
                $taskUrls->save();
            }

            if (!empty($topic['topics'])) {
                $this->saveTaskUrls($topic['topics']);
            }
        }
    }

    protected function saveBookImages($books)
    {
        foreach ($books as $book) {
            /** @var Urls $oldBookImage */
            $oldBookImage = Urls::findFirst([
                    'url = :url:',
                    'bind' => [
                        'url' => $book['cover']
                    ]
                ]);

            if ($oldBookImage) {
                //$oldBookImage->checked = 1;
                //$oldBookImage->update();
                $this->log->info('урл картинки книги существует');
                continue;
            }

            $urls = new Urls();
            $urls->url = $book['cover'];
            $urls->state = Urls::OPEN;
            $urls->type = Urls::IMAGE;
            //$urls->checked = 1;
            $urls->save();
            $this->log->info('сохранили урл картинки книги');
        }
    }

    protected function saveTaskImages($tasks)
    {
        foreach ($tasks as $edition) {
            foreach ($edition['task']['images'] as $image) {
                /** @var Urls $oldBookImage */
                $oldBookImage = Urls::findFirst([
                        'url = :url:',
                        'bind' => [
                            'url' => $image['url']
                        ]
                    ]);

                if ($oldBookImage) {
                    //$oldBookImage->checked = 1;
                    //$oldBookImage->update();
                    $this->log->info('урл картинки таска существует');
                    continue;
                }

                $urls = new Urls();
                $urls->url = $image['url'];
                $urls->state = Urls::OPEN;
                $urls->type = Urls::IMAGE;
                //$urls->checked = 1;
                $urls->save();
                $this->log->info('сохранили урл картинки таска');
            }
        }
    }

    protected function scrapeContentUrls()
    {
        $robot = new Robot();
        $robot->setRequestProvider(new ContentUrlProvider());

        $robot->setQueueSize(10);
        $robot->setMaximumRPM(600);

        $queue = $robot->getQueue();
        $queue->getDefaultOptions()->set([
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => $this->di->get('config')->curlUserAgent,
        ]);

        $count = 0;
        $this->db->begin();
        $queue->addListener('complete', function (Event $event) use (&$count) {

            if ($count > 100) {

                $this->db->commit();
                $this->db->begin();
                $count = 0;
                $this->log->info('применили транзакцию');
            }

            $response = $event->response;
            $rawUrl = $event->request->url;
            $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);

            if ($httpCode == 200) {
                $json = $response->getContent();
                $decodedJson = json_decode($json, true);

                if ($decodedJson['action'] == 'viewTask') {
                    $this->saveTaskImages($decodedJson['tasks']);
                }

                $content = new Contents();
                $content->url = $rawUrl;
                $content->content = $json;
                $content->state = Contents::UPDATING;
                $content->version = 2;
                $content->save();

                /** @var Urls $urls */
                $urls = Urls::findFirst([
                    'url = :url:',
                    'bind' => [
                        'url' => $rawUrl
                    ]
                ]);
                $urls->state = Urls::CLOSE;
                $urls->save();

                $this->log->info('соханили контент');
                $count++;
            } else {
                /** @var Urls $urls */
                $urls = Urls::findFirst([
                    'url = :url:',
                    'bind' => [
                        'url' => $rawUrl
                    ]
                ]);
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
        });

        try {
            $robot->run();
        } catch (Exception $e) {
            $this->log->notice($e->getMessage());
        }

        $this->db->commit();
    }

    protected function scrapeImageUrls()
    {
        $robot = new Robot();
        $robot->setRequestProvider(new ImageUrlProvider());

        $robot->setQueueSize(3);
        $robot->setMaximumRPM(6000);

        $queue = $robot->getQueue();
        $queue->getDefaultOptions()->set([
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY         => true,
                CURLOPT_USERAGENT      => $this->di->get('config')->curlUserAgent,
            ]);

        $count = 0;
        $this->db->begin();
        $queue->addListener('complete', function (Event $event) use (&$count) {

                if ($count > 100) {

                    $this->db->commit();
                    $this->db->begin();
                    $count = 0;
                    $this->log->info('применили транзакцию');
                }

                $response = $event->response;
                $rawUrl = $event->request->url;
                $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);

                if ($httpCode == 200) {
                    /** @var Urls $urls */
                    $urls = Urls::findFirst([
                            'url = :url:',
                            'bind' => [
                                'url' => $rawUrl
                            ]
                        ]);
                    $urls->state = Urls::CLOSE;
                    $urls->save();

                    $this->log->info('соханили картинку');
                    $count++;
                } else {
                    /** @var Urls $urls */
                    $urls = Urls::findFirst([
                            'url = :url:',
                            'bind' => [
                                'url' => $rawUrl
                            ]
                        ]);
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
            });

        try {
            $robot->run();
        } catch (Exception $e) {
            $this->log->notice($e->getMessage());
        }

        $this->db->commit();
    }

    protected function deleteFilledUrls()
    {
        $this->db->execute("DELETE FROM urls WHERE state = ? AND type = ?", [Urls::CLOSE, Urls::CONTENT]);
        $this->log->info('Удалены обработанные урлы');
    }
}