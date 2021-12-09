<?php

require '..\wp-config.php';
require 'vendor\autoload.php';

use LithiumHosting\MarkdownImporter\MarkdownImporter;

define('DB_PREFIX', $table_prefix);

$config = [
    'site_url'        => 'http://kbdemo.test',
    'site_domain'     => 'kbdemo.test',
    'blog_folder'     => '', // example /blog (NO TRAILING SLASH!) Optional
    'markdown_folder' => __DIR__ . DIRECTORY_SEPARATOR . 'docs',
    'reset_database' => false, // set to true if this is a new install, the DB tables will be truncated
];

$importer = new MarkdownImporter($config);