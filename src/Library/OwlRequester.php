<?php

namespace Client\Library;

use Core\Debugger;
use Phalcon\Debug;
use Phalcon\Di;

class OwlRequester extends CurlRequester
{
    protected $di;

    public function __construct()
    {
        $this->di = Di::getDefault();
        parent::__construct();
    }

    public function request($url, $curlOpts = [])
    {
        $clientId = $this->di->get('config')->clientId;
        $secretKey = $this->di->get('config')->secretKey;
        $string = "client_id=$clientId&url=$url$secretKey";

        $params = http_build_query([
            'client_id' => $clientId,
            'url' => $url,
            'sig' => md5($string)
        ]);

        $url = $this->di->get('config')->owl . "/api/1.0/?$params";
        $response = parent::request($url, $curlOpts);

        PH_DEBUG ? Debugger::dumpBar($url,'url') : null;

        return $response;
    }
}
