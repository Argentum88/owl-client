<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

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

    protected $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function fullUpdate()
    {
        $count = 0;
        $this->db->begin();
        $handle = fopen($this->file, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $count++;
                if ($count > 1000) {
                    $this->db->commit();
                    $this->db->begin();
                    $count = 1;
                    $this->log->info('применили транзакцию');
                }

                $data = json_decode($line, true);

                if ($data['type'] != 'image' && $data['event'] == 'update') {
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
            $this->db->commit();
            exit();
        }
        $this->db->commit();

        $this->setReadyState();
        $this->deleteFirstVersion();
        $this->moveSecondVersionToFirst();

        $this->scrapeImageUrls();
    }

    public function update()
    {

        $this->setReadyState();
        $this->deleteFirstVersion(false);
        $this->moveSecondVersionToFirst();

        $this->scrapeImageUrls();
    }

    protected function createContent($data)
    {
        /** @var Contents $oldContent */
        $oldContent = Contents::findFirst(
            [
                'url = :url: AND state = :state: AND type = :type:',
                'bind' => [
                    'url' => $data['url'],
                    'state' => Contents::UPDATING,
                    'type' => $this->typeMap[$data['type']]
                ]
            ]
        );

        if ($oldContent) {
            $oldContentId = $oldContent->id;
            $this->log->error("Дубль. Контент с id: $oldContentId существует");
            return;
        }

        $decodedContent = !empty($data['content'][1]) ? json_decode($data['content'][1], true) : null;

        $content = new Contents();
        $content->url = !empty($data['url']) ? $data['url'] : ' ';
        $content->controller = !empty($decodedContent['controller']) ? $decodedContent['controller'] : ' ';
        $content->action = !empty($decodedContent['action']) ? $decodedContent['action'] : ' ';
        $content->content = !empty($data['content'][1]) ? $data['content'][1] : ' ';
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