<?php

namespace LithiumHosting\MarkdownImporter;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Facades\Storage;
use Parsedown;
use FrontMatter;

class MarkdownImporter
{
    public $parsedown;
    public $markdown_folder = '';
    public $site_domain = 'kbdemo.test';
    public $site_url = 'http://kbdemo.test';
    public $blog_folder = ''; // example /blog (NO TRAILING SLASH!)
    public $categories = [];

    public function __construct(array $config)
    {
        foreach ($config as $opt) {
            $this->site_domain = $config['site_domain'];
            $this->site_url = $config['site_url'];
            $this->blog_folder = $config['blog_folder'];
            $this->markdown_folder = $config['markdown_folder'];
        }

        $this->parsedown = new Parsedown();

        // Setup Database Connection
        $this->initDatabase();

        // OPTIONAL: Delete all posts beforehand - useful for tweaking this script to get it just right until it works
        if ($config['reset_database']) {
            $this->deleteAllPosts();
        }

        if ( ! is_dir($this->markdown_folder)) {
            throw new \Exception('Invalid path to markdown files');
        }

        $categories = $this->parseDirectory($this->markdown_folder);

        foreach ($categories as $category) {
            $articles = $this->parseDirectory($this->markdown_folder . DIRECTORY_SEPARATOR . $category);
            foreach ($articles as $article) {
                $mdFile = $this->markdown_folder . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $article . DIRECTORY_SEPARATOR . 'index.md';
                $this->importMarkdown($category, $article, $mdFile);
            }
        }
    }

    private function deleteAllPosts()
    {
        DB::table('posts')->truncate();
        DB::table('postmeta')->truncate();
        DB::table('terms')->truncate();
        DB::table('term_taxonomy')->truncate();
        DB::table('term_relationships')->truncate();
    }

    private function initDatabase()
    {
        $capsule = new DB;

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => DB_HOST,
            'database'  => DB_NAME,
            'username'  => DB_USER,
            'password'  => DB_PASSWORD,
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => DB_PREFIX,
        ]);

        $capsule->setAsGlobal();
    }

    private function importMarkdown($category, $article, $mdFile)
    {
        $md = file_get_contents($mdFile);
        $post = new FrontMatter($md);

        $title = trim($post->fetch('title'), "'\" ");
        $date = $post->fetch('date');
        $date = date('Y-m-d H:i:s', strtotime($date));
        $body = $post->fetch('content');
        $tags = explode(',', str_replace(["'", '[', ']'], '', $post->fetch('tags')));

        // Slug is filename - remove date from beginning, and extensions from end
        $slug = str_slug($article);

        // Build full permalink
        $permalink = $this->site_url . '/' . $category . '/' . $this->blog_folder . $slug . "\n";

        // Replace 'READMORE' with WordPress equivalent
        $body = str_replace('READMORE', '<!--more-->', $body);
        $body = str_replace('kb.apiscp.com', $this->site_domain, $body);

        $post_id = DB::table('posts')
            ->insertGetId([
                'post_author'           => 1,
                'post_date'             => $date,
                'post_date_gmt'         => $date,
                'post_content'          => str_replace(["\r\n", "\r", "\n"], " ", $this->parsedown->text($body)),
                'post_content_filtered' => $body,
                'post_title'            => $title,
                'post_status'           => 'publish',
                'comment_status'        => 'closed',
                'ping_status'           => 'closed',
                'post_name'             => $slug,
                'post_modified'         => $date,
                'post_modified_gmt'     => $date,
                'post_parent'           => 0,
                'post_type'             => 'post',
                'post_excerpt'          => '',
                'to_ping'               => '',
                'pinged'                => '',
            ]);

        wp_set_post_tags($post_id, $tags, false);

        $term_id = $this->getTermId($category);
        $this->addTermRelationship($term_id, $post_id);

        DB::table('postmeta')
            ->insert(['post_id' => $post_id, 'meta_key' => '_sd_is_markdown', 'meta_value' => 1]);
    }

    private function parseDirectory($directory)
    {
        return array_diff(scandir($directory), ['..', '.']);
    }

    private function getTermId($category)
    {
        $check = DB::table('terms')->where('name', $category)->first();

        if ( ! $check) {
            $term_id = DB::table('terms')
                ->insertGetId(['name' => $category, 'slug' => str_slug($category), 'term_group' => 0]);

            DB::table('term_taxonomy')
                ->insert(['term_id' => $term_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 0]);

            return $term_id;
        }

        return $check->term_id;
    }

    private function addTermRelationship($term_id, $post_id)
    {
        DB::table('term_taxonomy')->where('term_id', $term_id)->increment('count');
        $result = DB::table('term_taxonomy')->where('term_id', $term_id)->first();

        if ( ! $result) {
            dd($term_id, $post_id);
        }
        DB::table('term_relationships')->insert(['object_id' => $post_id, 'term_taxonomy_id' => $result->term_taxonomy_id]);
    }
}