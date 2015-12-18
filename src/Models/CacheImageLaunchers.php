<?php

namespace Client\Models;

use Client\Tasks\SyncTask;

class CacheImageLaunchers extends Launchers
{
    public $task = self::CACHE_IMAGE;

    protected function launchTask()
    {
        $sync = new SyncTask();
        return $sync->cacheImageAction();
    }

    public function getSource()
    {
        return "launchers";
    }
}
