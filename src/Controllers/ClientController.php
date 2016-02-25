<?php

namespace Client\Controllers;

use Client\Models\Owl;
use Client\Library\Debugger;
use Client\Models\Events;
use Phalcon\Mvc\View;

class ClientController extends ControllerBase
{
    public function forwardAction()
    {
        $uri = $this->request->getURI();

        $pos = strpos($uri, '#');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
            return $this->response->redirect($uri, false, 301);
        }

        $response = (new Owl())->request($uri, $this->config->useOwlServer);

        PH_DEBUG ? Debugger::dumpBar($response) : null;

        if ($response['success']) {

            if ($response['controller'] == 'redirect') {
                return $this->response->redirect($response['location'], false, $response['code']);
            }

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

    public function updateCacheAction()
    {
        $this->view->disable();

        $patch = $this->request->getPost('patch');
        $clear = $this->request->getPost('clear');

        if ($clear == 1) {
            $fullUpdateEvent = Events::findFirst([
                'type = :type: AND state = :state:',
                'bind' => [
                    'type'  => Events::FULL_UPDATE_CONTENT,
                    'state' => Events::OPEN,
                ]
            ]);

            if ($fullUpdateEvent) {
                $fullUpdateEvent->delete();
            }
        }

        $event = new Events();
        $event->state = Events::OPEN;
        $event->type = $clear == 1 ? Events::FULL_UPDATE_CONTENT : Events::UPDATE_CONTENT;
        $event->data = json_encode(['patch' => $patch]);
        $event->create();
        return $this->response->setStatusCode(200, 'OK');
    }

    public function updateBannersAction()
    {
        $this->view->disable();

        $patch = $this->request->getPost('patch');

        $event = new Events();
        $event->state = Events::OPEN;
        $event->type = Events::UPDATE_BANNER;
        $event->data = json_encode(['patch' => $patch]);
        $event->create();
        return $this->response->setStatusCode(200, 'OK');
    }
}
