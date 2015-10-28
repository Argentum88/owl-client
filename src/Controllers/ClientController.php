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
        $result = touch($this->config->clearCacheFlag);

        if (!$result) {
            return $this->response->setStatusCode(500, 'Internal Server Error');
        }

        return $this->response->setStatusCode(200, 'OK');
    }

    public function clearImagesCacheAction()
    {
        $this->view->disable();
        /*$imagesCache = $this->config->pathToImagesCache;

        $urls = $this->request->getPost('url');
        foreach ($urls as $url) {
            $result = unlink($imagesCache . $url);

            if (!$result) {
                return $this->response->setStatusCode(500, 'Internal Server Error');
            }
        }

        return $this->response->setStatusCode(200, 'OK');*/
    }
}
