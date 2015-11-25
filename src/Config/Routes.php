<?php

namespace Client\Config;

use Phalcon\Mvc\Router\Group as RouterGroup;

class Routes extends RouterGroup
{
    public function initialize()
    {
        $defaultPaths = [
            'controller' => 'Client'
        ];
        $this->setPaths($defaultPaths);

        $this->addGet('/{path:.*}', ['action' => 'forward']);
        $this->add('/cache/update', ['action' => 'updateCache']);
        $this->add('/cache/banners/update', ['action' => 'updateBanners']);
    }
}
