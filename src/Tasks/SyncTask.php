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
        (new ContentSynchronizer(new ScraperStrategy()))->update();
    }

    public function updateViaFileAction($params)
    {
        $file = $params[3];
        (new ContentSynchronizer(new FileStrategy($file)))->update();
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
            $this->log->error("Не позволено");
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

            $event->state = Events::CONTENT_UPDATING;
            $event->save();

            $eventData = json_decode($event->data, true);
            $patch = $eventData['patch'];
            $time = time();
            $compressedFile = $this->config->tempDir . "/$time.bz2";
            file_put_contents($compressedFile, fopen($this->config->owl . $patch, 'r'));

            exec("bzip2 -d $compressedFile");
            $uncompressedFile = $this->config->tempDir . "/$time";

            $this->updateViaFileAction([3 => $uncompressedFile]);

            unlink($uncompressedFile);
            $event->state = Events::DONE;
            $event->save();
        }
    }
}