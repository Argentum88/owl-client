<?php
/**
 * Created by PhpStorm.
 * User: argentum
 * Date: 29.03.16
 * Time: 13:29
 */

namespace Client\Library\ContentSynchronizer;


interface UrlCreatableInterface
{
    public function createUrl($url, $type, $action);
}