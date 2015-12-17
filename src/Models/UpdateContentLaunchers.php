<?php

namespace Client\Models;

use Client\Tasks\SyncTask;

class UpdateContentLaunchers extends Launchers
{
    public $task = self::UPDATE_CONTENT;

    public function launch()
    {
        $startTime = time();

        $this->actualizeLaunchIndex();

        while (time() < ($startTime + 58)) {
            if ($this->index == 1) {
                $sync = new SyncTask();

                try {
                    $sync->updateContentAction();
                } catch (\Exception $e) {
                    $this->log->error($e->getMessage());
                }

            } else {
                $this->actualizeLaunchIndex();
            }
            sleep(1);
        }

        $this->delete();
    }
}
