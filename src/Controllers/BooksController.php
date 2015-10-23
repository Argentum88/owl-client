<?php

namespace Client\Controllers;

class BooksController extends ControllerBase
{
    public function indexAction($owlResponse)
    {
        $this->view->setVars([
                'message' => $owlResponse['message'],
                'response' => $owlResponse,
            ]);
    }

    public function listAction($owlResponse)
    {
        $this->view->setVars([
                'message' => $owlResponse['message'],
                'response' => $owlResponse,
            ]);
    }

    public function listByClassAction($owlResponse)
    {
        $this->view->setVars([
                'message' => $owlResponse['message'],
                'response' => $owlResponse,
            ]);
    }

    public function listBySubjectAction($owlResponse)
    {
        $this->view->setVars([
                'message' => $owlResponse['message'],
                'response' => $owlResponse,
            ]);
    }

    public function listByBothAction($owlResponse)
    {
        $this->view->setVars([
                'message' => $owlResponse['message'],
                'response' => $owlResponse,
            ]);
    }

    public function viewAction($owlResponse)
    {
        $this->view->setVars([
                'message' => $owlResponse['message'],
                'response' => $owlResponse,
            ]);
    }

    public function viewTaskAction($owlResponse)
    {
        $this->view->setVars([
                'message' => $owlResponse['message'],
                'response' => $owlResponse,
            ]);
    }
} 