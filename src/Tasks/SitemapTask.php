<?php

namespace Client\Tasks;

use Client\Models\Contents;
use Client\Tasks;
use cURL\Request;
use Phalcon\CLI\Task;
use SitemapPHP\Sitemap;
use Client\Models\Owl;

class SitemapTask extends Task
{
    const TYPE_BOOKS = 2;

    public function byOwlAction()
    {
        $domain = rtrim($this->config->application->siteUri, '/');
        $sitemapsStorage = rtrim($this->config->application->sitemapsDir, '/');

        $sitemap = new Sitemap($domain);
        $sitemap->setPath($sitemapsStorage.'/');

        $response = (new Owl())->request('/');
        $response = (new Owl())->request($response['static_urls'][SitemapTask::TYPE_BOOKS]);

        $sitemap->addItem($response['static_urls'][SitemapTask::TYPE_BOOKS], 0.5, null, 'Today');

        foreach ($response['books'] as $book) {

            $sitemap->addItem($book['url'], 0.5, null, 'Today');
        }

        $classIds = array_keys($response['intersects']['classes']);
        foreach ($classIds as $classId) {
            $sitemap->addItem($response['classes'][$classId]['url'], 0.5, null, 'Today');
        }

        $subjectIds = array_keys($response['intersects']['subjects']);
        foreach ($subjectIds as $subjectId) {
            $sitemap->addItem($response['subjects'][$subjectId]['url'], 0.5, null, 'Today');
        }

        foreach ($response['intersects']['classes'] as $class) {
            foreach ($class as $ClassSubject) {
                $sitemap->addItem($ClassSubject['url'], 0.5, null, 'Today');
            }
        }
    }

    public function byMysqlAction()
    {
        $domain = rtrim($this->config->application->siteUri, '/');
        $sitemapsStorage = rtrim($this->config->application->sitemapsDir, '/');

        $sitemap = new Sitemap($domain);
        $sitemap->setPath($sitemapsStorage.'/');

        /** @var Contents[] $contents */
        $contents = Contents::find(
            [
                'conditions' => "(controller = 'books') AND (action = 'index' OR action = 'list' OR action = 'listByClass' OR action = 'booksBySubject' OR action = 'listByBoth' OR action = 'view')",
                'columns'    => "url, created_at",
                'group'      => "url",
                'order'      => "created_at DESC"
            ]
        );

        foreach ($contents as $content) {
            $sitemap->addItem($content->url, 0.5, null, $content->created_at);
        }
    }
}