<?php

namespace Client\Tasks;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy\FileStrategy;
use Client\Models\Events;
use Phalcon\CLI\Task;
use Client\Library\ContentSynchronizer\ContentSynchronizer;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\ScraperStrategy;


class SyncTask extends Task
{
    public function updateViaScraperAction()
    {
        (new ContentSynchronizer(new ScraperStrategy()))->updateContent();
    }

    public function updateViaFileAction($params)
    {
        $file = $params[3];
        (new ContentSynchronizer(new FileStrategy($file)))->updateContent();
    }

    public function scrapeImageAction()
    {
        (new ContentSynchronizer(new FileStrategy()))->scrapeImage();
    }

    public function mainAction()
    {
        $event = Events::findFirst([
                'state = :state1: OR state = :state2:',
                'bind' => [
                    'state1' => Events::CONTENT_UPDATING,
                    'state2' => Events::ERROR,
                ]
            ]);

        if ($event) {
            $this->log->error("Предыдущие обновление не завершено, либо завершено с ошибкой");
            exit();
        }

        /** @var Events $event */
        $event = Events::findFirst([
            'state = :state:',
            'bind' => [
                'state' => Events::OPEN,
            ],
            'order' => 'created_at ASC'
        ]);

        if ( $event && ($event->type == Events::UPDATE_CONTENT) ) {
            $this->log->info('начали синхронизацию контента');

            $event->state = Events::CONTENT_UPDATING;
            $event->save();

            $eventData = json_decode($event->data, true);
            $patch = $eventData['patch'];
            $time = time();
            $compressedFile = $this->config->tempDir . "/$time.bz2";

            $handle = fopen($this->config->owl . $patch, 'r');
            if ($handle) {
                file_put_contents($compressedFile, fopen($this->config->owl . $patch, 'r'));
            } else {
                $this->log->error("не удалось открыть файл " . $this->config->owl . $patch);
                $event->state = Events::ERROR;
                $event->save();
                exit;
            }

            exec("bzip2 -d $compressedFile");
            $uncompressedFile = $this->config->tempDir . "/$time";

            $this->updateViaFileAction([3 => $uncompressedFile]);
            unlink($uncompressedFile);

            $this->log->info('закончили синхронизацию контента');

            $imageUpdatingEvent = Events::findFirst(
                [
                    'state = :state:',
                    'bind' => [
                        'state' => Events::IMAGE_UPDATING,
                    ]
                ]
            );

            if (!$imageUpdatingEvent) {

                $event->state = Events::IMAGE_UPDATING;
                $event->save();

                $this->scrapeImageAction();
            }

            $event->state = Events::DONE;
            $event->save();
        }
    }
}