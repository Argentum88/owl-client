<?php

namespace Client\Controllers;

class RedirectController extends ControllerBase
{
    public function indexAction($owlResponse)
    {
        return $this->response->redirect($owlResponse['location'], false, $owlResponse['code']);
    }
} 