<?php

namespace Client\Models;

use Client\Tasks\SyncTask;

class UpdateContentLaunchers extends Launchers
{
    public $task = self::UPDATE_CONTENT;

    protected function launchTask()
    {
        $sync = new SyncTask();
        return $sync->updateContentAction();
    }
}
