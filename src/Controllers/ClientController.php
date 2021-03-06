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
        if (!$patch) {
            return $this->response->setStatusCode(406, 'Not Acceptable');
        }

        $clear = $this->request->getPost('clear');

        if ($clear == 1) {
            $updateContentEvents = Events::find([
                '(type = :type1: OR type = :type2:) AND state = :state:',
                'bind' => [
                    'type1'  => Events::UPDATE_CONTENT,
                    'type2'  => Events::FULL_UPDATE_CONTENT,
                    'state'  => Events::OPEN,
                ]
            ]);

            $updateContentEvents->delete();
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
        if (!$patch) {
            return $this->response->setStatusCode(406, 'Not Acceptable');
        }

        $event = new Events();
        $event->state = Events::OPEN;
        $event->type = Events::UPDATE_BANNER;
        $event->data = json_encode(['patch' => $patch]);
        $event->create();
        return $this->response->setStatusCode(200, 'OK');
    }
}
