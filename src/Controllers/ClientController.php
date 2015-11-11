<?php

namespace Client\Controllers;

use Client\Library\OwlRequester;
use Client\Library\Debugger;
use Phalcon\Mvc\View;

class ClientController extends ControllerBase
{
    public function forwardAction()
    {
        $uri = $this->request->get('_url') ? $this->request->get('_url') : '/';
        $response = (new OwlRequester())->request($uri, $this->config->useOwlServer);

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

    public function updateContentCacheAction()
    {
        $this->view->disable();


    }
}
