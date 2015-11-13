<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Urls;
use Client\Models\Contents;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\Provider\ContentUrlProvider;
use cURL\Exception;
use cURL\Robot;
use cURL\Event;

class ScraperStrategy extends SynchronizerStrategy
{
    const TYPE_BOOKS = 2;

    public function fullUpdate()
    {
        $this->getUrls();
        $this->scrapeContentUrls();
        $this->deleteFilledUrls();
        $this->setReadyState();
        $this->deleteFirstVersion();
        $this->moveSecondVersionToFirst();

        $this->scrapeImageUrls();
    }

    public function update()
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
        $response = (new \Client\Library\OwlRequester())->request('/');
        $response = (new \Client\Library\OwlRequester())->request($response['static_urls'][ScraperStrategy::TYPE_BOOKS]);

        if ($this->createUrl('/')) {
            $this->log->info('сохранили урл главной');
        }

        if ($this->createUrl($response['static_urls'][ScraperStrategy::TYPE_BOOKS])) {
            $this->log->info('сохранили урл списка книг');
        }

        $classIds = array_keys($response['intersects']['classes']);
        foreach ($classIds as $classId) {
            if ($this->createUrl($response['classes'][$classId]['url'])) {
                $this->log->info('сохранили урл класса');
            }
        }

        $subjectIds = array_keys($response['intersects']['subjects']);
        foreach ($subjectIds as $subjectId) {
            if ($this->createUrl($response['subjects'][$subjectId]['url'])) {
                $this->log->info('сохранили урл предмета');
            }
        }

        foreach ($response['intersects']['classes'] as $class) {
            foreach ($class as $classSubject) {
                if ($this->createUrl($classSubject['url'])) {
                    $this->log->info('сохранили урл класса/предмета');
                }
            }
        }

        $this->createBookImages($response['books']);
        //return;

        foreach ($response['books'] as $book) {
            if ($this->createUrl($book['url'])) {
                $this->log->info('сохранили урл книги');
            }

            $bookResponse = (new \Client\Library\OwlRequester())->request($book['url']);

            $this->db->begin();
            $this->createTaskUrls($bookResponse['structure']);
            $this->db->commit();
            $this->log->info('сохранили урлы тасков');
        }
    }

    protected function createTaskUrls($structure)
    {
        foreach ($structure as $topic) {
            foreach ($topic['tasks'] as $task) {
                $this->createUrl($task['url']);
            }

            if (!empty($topic['topics'])) {
                $this->createTaskUrls($topic['topics']);
            }
        }
    }

    protected function createBookImages($books)
    {
        foreach ($books as $book) {
            if ($this->createUrl($book['cover'], Urls::IMAGE)) {
                $this->log->info('сохранили урл картинки книги');
            }
        }
    }

    protected function createTaskImages($tasks)
    {
        foreach ($tasks as $edition) {
            foreach ($edition['task']['images'] as $image) {
                if ($this->createUrl($image['url'], Urls::IMAGE)) {
                    $this->log->info('сохранили урл картинки таска');
                }
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
            $urlId = $event->request->urlId;
            $httpCode = $response->getInfo(CURLINFO_HTTP_CODE);

            if ($httpCode == 200) {
                $json = $response->getContent();
                $decodedJson = json_decode($json, true);

                if ($decodedJson['action'] == 'viewTask') {
                    $this->createTaskImages($decodedJson['tasks']);
                }

                $content = new Contents();
                $content->url = $rawUrl;
                $content->controller = $decodedJson['controller'];
                $content->action = $decodedJson['action'];
                $content->content = $json;
                $content->state = Contents::UPDATING;
                $content->version = 2;

                try {
                    if ($content->create()) {
                        $this->log->info('соханили контент');

                        /** @var Urls $urls */
                        $urls = Urls::findFirst([
                                'id = :id:',
                                'bind' => [
                                    'id' => $urlId
                                ]
                            ]);
                        $urls->state = Urls::CLOSE;
                        $urls->save();
                    } else {
                        $messages = $content->getMessages();
                        foreach ($messages as $message) {
                            $this->log->error($message->getMessage());
                        }

                        /** @var Urls $urls */
                        $urls = Urls::findFirst([
                                'id = :id:',
                                'bind' => [
                                    'id' => $urlId
                                ]
                            ]);
                        $urls->state = Urls::ERROR;
                        $urls->save();
                    }
                } catch (\Exception $e) {
                    $this->log->error($e->getMessage());

                    /** @var Urls $urls */
                    $urls = Urls::findFirst([
                            'id = :id:',
                            'bind' => [
                                'id' => $urlId
                            ]
                        ]);
                    $urls->state = Urls::ERROR;
                    $urls->save();
                }

                $count++;
            } else {
                /** @var Urls $urls */
                $urls = Urls::findFirst([
                    'id = :id:',
                    'bind' => [
                        'id' => $urlId
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