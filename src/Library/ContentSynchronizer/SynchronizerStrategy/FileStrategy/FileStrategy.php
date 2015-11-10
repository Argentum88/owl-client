<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Urls;
use Client\Models\Contents;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\Provider\ImageUrlProvider;
use cURL\Exception;
use cURL\Robot;
use cURL\Event;

class FileStrategy extends SynchronizerStrategy
{
    protected $typeMap = [
        'content' => Urls::CONTENT,
        'image' => Urls::IMAGE,
        'common' => Urls::COMMON
    ];

    public function fullUpdate(array $params = [])
    {
        $handle = fopen("data.dat", "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $data = json_decode($line, true);

                if ($data['type'] != 'image' && $data['event'] == 'create') {
                    $this->createContent($data);
                } elseif ($data['type'] != 'image' && $data['event'] == 'delete') {

                } elseif ($data['type'] == 'image' && $data['event'] == 'create') {
                    $this->createUrl($data['url'], Urls::IMAGE);
                } elseif ($data['type'] == 'image' && $data['event'] == 'delete') {

                } else {
                    $this->log->error("операция не поддерживается");
                    continue;
                }
            }

            fclose($handle);
        } else {
            $this->log->error("не удалось открыть файл");
            exit();
        }

        $this->setReadyState();
        $this->deleteFirstVersion();
        $this->moveSecondVersionToFirst();

        $this->scrapeImageUrls();
    }

    public function update(array $params = [])
    {

        $this->setReadyState();
        $this->deleteFirstVersion(false);
        $this->moveSecondVersionToFirst();

        $this->scrapeImageUrls();
    }

    protected function createContent($data)
    {
        $oldContent = Contents::findFirst(
            [
                'url = :url: AND state = :state: AND type = :type:',
                'bind' => [
                    'url' => $data['url'],
                    'state' => Contents::READY,
                    'type' => $this->typeMap[$data['type']]
                ]
            ]
        );

        if ($oldContent) {
            $this->log->error("Дубль");
            return;
        }

        $content = new Contents();
        $content->url = $data['url'];
        $content->controller = $data['controller'];
        $content->action = $data['action'];
        $content->content = $data['content'];
        $content->type = $this->typeMap[$data['type']];
        $content->state = Contents::UPDATING;
        $content->version = 2;

        if (!$content->create()) {
            $messages = $content->getMessages();
            foreach ($messages as $message) {
                $this->log->error($message->getMessage());
            }
        }
    }
}