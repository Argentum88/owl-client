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

    public function byOwlAction($param = null)
    {
        $domain = rtrim($this->config->application->siteUri, '/');
        $sitemapsStorage = rtrim($this->config->application->sitemapsDir, '/');

        $sitemap = new Sitemap($domain);
        $sitemap->setPath($sitemapsStorage.'/');

        $response = (new Owl())->request('/');
        $response = (new Owl())->request($response['static_urls'][SitemapTask::TYPE_BOOKS]);

        if (isset($param[3]) && $param[3] == 'full') {
            $sitemap->addItem($response['static_urls'][SitemapTask::TYPE_BOOKS], 0.5, null, 'Today');
        }

        foreach ($response['books'] as $book) {

            $sitemap->addItem($book['url'], 0.5, null, 'Today');

            if (!$book['is_single_page']) {
                $this->addTasks($sitemap, $book['structure']);
            }
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


        $sitemap->createSitemapIndex($domain . '/sitemaps/', 'Today');
    }

    protected function addTasks(Sitemap $sitemap, $structure)
    {
        foreach ($structure['tasks'] as $task) {
            $sitemap->addItem($task['url'], 0.5, null, 'Today');
        }

        if (!empty($structure['topics'])) {
            $this->addTasks($sitemap, $structure['topics']);
        }
    }

    public function byMysqlAction($param = null)
    {
        $domain = rtrim($this->config->application->siteUri, '/');
        $sitemapsStorage = rtrim($this->config->application->sitemapsDir, '/');

        $sitemap = new Sitemap($domain);
        $sitemap->setPath($sitemapsStorage.'/');

        /** @var Contents[] $contents */
        $contents = Contents::find(
            [
                'columns'    => "url, content, created_at",
                'group'      => "url",
                'order'      => "created_at DESC"
            ]
        );

        $count = 0;
        foreach ($contents as $content) {

            $count++;
            if ($count % 1000 == 0) {
                $this->log->info("обрабатано $count строк");
            }

            $data = json_decode($content->content, true);

            if (isset($param[3]) && $param[3] == 'full') {
                $condition = $data['controller'] == 'books' && ($data['action'] == 'listByClass' || $data['action'] == 'booksBySubject' || $data['action'] == 'listByBoth' || $data['action'] == 'view'|| $data['action'] == 'list');
            } else {
                $condition = $data['controller'] == 'books' && ($data['action'] == 'listByClass' || $data['action'] == 'booksBySubject' || $data['action'] == 'listByBoth' || $data['action'] == 'view'|| $data['action'] == 'list');

            }

            if ($condition) {
                $sitemap->addItem($content->url, 0.5, null, $content->created_at);
                continue;
            }

            if ($data['controller'] == 'books' && $data['action'] == 'viewTask' && $data['book']['is_single_page'] == false) {
                $sitemap->addItem($content->url, 0.5, null, $content->created_at);
            }
        }

        $sitemap->createSitemapIndex($domain . '/sitemaps/', 'Today');
    }
}