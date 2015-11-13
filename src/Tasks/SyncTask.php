<?php

namespace Client\Tasks;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy\FileStrategy;
use Client\Models\Events;
use Phalcon\CLI\Task;
use Client\Library\ContentSynchronizer\ContentSynchronizer;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\ScraperStrategy;


class SyncTask extends Task
{
    public function fullUpdateViaScraperAction()
    {
        (new ContentSynchronizer(new ScraperStrategy()))->fullUpdate();
    }

    public function updateViaScraperAction()
    {
        $urls = ['/'];
        (new ContentSynchronizer(new ScraperStrategy()))->update(['urls' => $urls]);
    }

    public function fullUpdateViaFileAction($params)
    {
        $file = $params[3];
        (new ContentSynchronizer(new FileStrategy($file)))->fullUpdate();
    }

    public function mainAction()
    {
        $event = Events::findFirst([
                'state = :state:',
                'bind' => [
                    'state' => Events::PROCESSING,
                ]
            ]);

        if ($event) {
            return;
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

            $event->state = Events::PROCESSING;
            $event->save();

            $eventData = json_decode($event->data, true);
            $patch = $eventData['patch'];
            $time = time();
            $compressedFile = $this->config->tempDir . "/$time.bz2";
            file_put_contents($compressedFile, fopen($this->config->owl . $patch, 'r'));

            exec("bzip2 -d $compressedFile");
            $uncompressedFile = $this->config->tempDir . "/$time";

            $this->fullUpdateViaFileAction([3 => $uncompressedFile]);

            $event->state = Events::DONE;
            $event->save();
        }
    }
}