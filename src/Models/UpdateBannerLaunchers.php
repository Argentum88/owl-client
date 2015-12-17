<?php

namespace Client\Models;

use Client\Tasks\SyncTask;

class UpdateBannerLaunchers extends Launchers
{
    public $task = self::UPDATE_BANNER;

    protected function launchTask()
    {
        $sync = new SyncTask();
        $sync->updateBannerAction();
    }
}
