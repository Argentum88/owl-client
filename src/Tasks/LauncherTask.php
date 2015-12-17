<?php

namespace Client\Tasks;

use Client\Models\UpdateContentLaunchers;
use Phalcon\CLI\Task;

class LauncherTask extends Task
{
    public function launchUpdateContentAction()
    {
        $launcher = new UpdateContentLaunchers();
        $launcher->launch();
    }
}