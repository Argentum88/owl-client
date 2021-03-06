<?php

namespace Client\Tasks;

use Client\Models\CacheImageLaunchers;
use Client\Models\UpdateBannerLaunchers;
use Client\Models\UpdateContentLaunchers;
use Phalcon\CLI\Task;

class LauncherTask extends Task
{
    public function launchUpdateContentAction()
    {
        $launcher = new UpdateContentLaunchers();
        $launcher->start();
    }

    public function launchUpdateBannerAction()
    {
        $launcher = new UpdateBannerLaunchers();
        $launcher->start();
    }

    public function launchCacheImageAction()
    {
        $launcher = new CacheImageLaunchers();
        $launcher->start();
    }
}