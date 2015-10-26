<?php

namespace Client\Controllers;

use Client\Library\OwlRequester;
use Core\Debugger;
use Phalcon\Mvc\View;

class ClientController extends ControllerBase
{
    public function forwardAction()
    {
        $uri = $this->request->getURI();
        $response = (new OwlRequester())->request($uri);

        PH_DEBUG ? Debugger::dumpBar($response) : null;

        if ($response['success']) {

            $this->dispatcher->forward([
                    'controller' => $response['controller'],
                    'action'     => $response['action'],
                    'params'     => [$response]
                ]);

        } else {

            $this->dispatcher->forward([
                    'controller' => 'Error',
                    'action'     => 'error404'
                ]);

        }
    }

    public function clearNginxCacheAction()
    {
        $this->view->disable();
        //touch($this->config->clearCacheFile);
    }

    public function clearImagesCacheAction()
    {
        $this->view->disable();
        //$imagesCache = $this->config->pathToImagesCache;
        //$res = $this->delTree($imagesCache . 'pathToDir');

        //var_dump($res);
    }

    private function delTree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
