<?php

namespace Client\Tasks;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy\FileStrategy;
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

    public function fullUpdateViaFileAction()
    {
        (new ContentSynchronizer(new FileStrategy()))->fullUpdate();
    }
}