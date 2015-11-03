<?php

namespace Client\Library\ContentSynchronizer;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Phalcon\Di\Injectable;

class ContentSynchronizer extends Injectable
{
    /** @var SynchronizerStrategy */
    protected $synchronizerStrategy;

    public function __construct(SynchronizerStrategy $synchronizerStrategy)
    {
        $this->synchronizerStrategy = $synchronizerStrategy;
    }

    public function initiallyFill(array $params = [])
    {
        $this->synchronizerStrategy->initiallyFill($params);
    }

    public function fullUpdate(array $params = [])
    {
        $this->synchronizerStrategy->fullUpdate($params);
    }

    public function update(array $params = [])
    {
        $this->synchronizerStrategy->update($params);
    }
}