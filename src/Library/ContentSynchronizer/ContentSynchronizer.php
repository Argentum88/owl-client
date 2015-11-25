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

    public function scrapeImage()
    {
        $this->synchronizerStrategy->scrapeImage();
    }

    public function updateContent()
    {
        $this->synchronizerStrategy->updateContent();
    }

    public function updateBanner()
    {
        $this->synchronizerStrategy->updateBanner();
    }
}