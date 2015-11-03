<?php

namespace Client\Tasks;

use Phalcon\CLI\Task;
use Client\Library\ContentSynchronizer\ContentSynchronizer;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\ScraperStrategy;


class SyncTask extends Task
{
    public function initiallyFillAction()
    {
        (new ContentSynchronizer(new ScraperStrategy()))->initiallyFill();
    }

    public function fullUpdateAction()
    {
        (new ContentSynchronizer(new ScraperStrategy()))->fullUpdate();
    }

    public function updateAction()
    {
        $urls = ['/'];
        (new ContentSynchronizer(new ScraperStrategy()))->update(['urls' => $urls]);
    }
}