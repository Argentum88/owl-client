<?php

namespace Client\Controllers;

use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{
    public function returnJson($data, $params = null)
    {

        $this->view->disable();
        $this->response->setContentType('application/json');
        $this->response->setJsonContent($data, $params ?: JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK);

        return $this->response;
    }

    public function redirect($url, $code = 302)
    {
        $this->view->disable();

        if ($this->request->isAjax()) {
            return $this->returnJson([
                'type'     => 'redirect',
                'code'     => $code,
                'location' => $url
            ]);
        }

        return $this->response->redirect($url, true, $code);
    }

    public function redirectAction()
    {
        $url  = $this->dispatcher->getParam('location');
        $code = $this->dispatcher->getParam('code') ?: 302;

        return $this->redirect($url, $code);
    }

}
