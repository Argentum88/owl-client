<?php

namespace Client\Tasks;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy\FileStrategy;
use Client\Models\Events;
use Phalcon\CLI\Task;
use Client\Library\ContentSynchronizer\ContentSynchronizer;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\ScraperStrategy;


class SyncTask extends Task
{
    public function updateContentViaScraperAction()
    {
        (new ContentSynchronizer(new ScraperStrategy()))->updateContent();
    }

    public function updateContentViaFileAction($params)
    {
        $file = $params[3];
        $fullUpdate = $params[4];
        (new ContentSynchronizer(new FileStrategy($file, $fullUpdate)))->updateContent();
    }

    public function updateBannerViaFileAction($params)
    {
        $file = $params[3];
        (new ContentSynchronizer(new FileStrategy($file)))->updateBanner();
    }

    public function scrapeImageAction()
    {
        (new ContentSynchronizer(new FileStrategy()))->scrapeImage();
    }

    public function updateBannerAction()
    {
        /** @var Events $event */
        $event = Events::findFirst([
            'type = :type: AND state = :state:',
            'bind' => [
                'type'  => Events::UPDATE_BANNER,
                'state' => Events::OPEN,
            ],
            'order' => 'created_at ASC'
        ]);

        if ($event) {
            $event->processUpdateBanner($this);
        }
    }

    public function updateContentAction()
    {
        /** @var Events $event */
        $event = Events::findFirst([
            '(type = :type1: OR type = :type2:) AND state = :state:',
            'bind' => [
                'type1' => Events::UPDATE_CONTENT,
                'type2' => Events::FULL_UPDATE_CONTENT,
                'state' => Events::OPEN,
            ],
            'order' => 'created_at ASC'
        ]);

        if ($event) {
            $event->processUpdateContent($this);
        }
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

            $this->updateContentViaFileAction([3 => $uncompressedFile]);
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