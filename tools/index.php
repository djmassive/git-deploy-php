#!/usr/bin/env php
<?php

ini_set('memory_limit', '-1');
error_reporting(E_ALL ^ E_DEPRECATED);
Phar::mapPhar("git-deploy");

/**
 * PSR-4 autoloader.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(function ($class) {

    // project-specific namespace prefix
    $prefix = 'Brunodebarros\\Gitdeploy\\';

    // base directory for the namespace prefix
    $base_dir = 'phar://git-deploy/src/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

require 'phar://git-deploy/vendor/autoload.php';

$args = \Brunodebarros\Gitdeploy\Config::getArgs();
$servers = \Brunodebarros\Gitdeploy\Config::getServers($args['config_file']);
$git = new \Brunodebarros\Gitdeploy\Git($args['repo_path']);

exec("git log --pretty=format:'%h' -n 1", $githash);
exec("git log -1 --pretty=%B", $a);
exec("git rev-list --all --count" , $build);
exec("git describe" , $version);
foreach($a as $i) {
    if(!empty($i)) {
        if($i !== 'no message') {
            $gitlog[] = $i;
        }
    }
}
$build = $build[0];

$version = $version[0];
$version = preg_replace("/\-([0-9]+)\-([a-z0-9]+)/" , ".$1", $version);

if(file_get_contents(__DIR__ . "/version.ini") !== $version) {
    file_put_contents(__DIR__ . "/version.ini", $version);
    file_put_contents(__DIR__ . "/version.ini", $version);
    
    if(file_exists( __DIR__ . "/changelog.xml" )) {
        $content = file_get_contents("changelog.xml") . PHP_EOL;
        $xml = simplexml_load_string($content);
        $cl_count = count($xml->changelog) - 1;
        
        $changelog_data = $xml->changelog[$cl_count]['date'];
        $changelog_czas = $xml->changelog[$cl_count]['time'];
        $changelog_wersja = $xml->changelog[$cl_count]['version'];
        $changelog_opis = $xml->changelog[$cl_count]->description;
        
        if( date('Y-m-d') == $changelog_data && !empty($gitlog) ) {
        
            $changelog_opis = $changelog_opis . PHP_EOL . "- " . implode(PHP_EOL . "- " , $gitlog);
            
            $xml->changelog[$cl_count]['build'] = $build;
            $xml->changelog[$cl_count]['commit'] = $githash[0];
            $xml->changelog[$cl_count]['time'] = date('H:i:s');
            $xml->changelog[$cl_count]['version'] = $version;
            $xml->changelog[$cl_count]->description = $changelog_opis;
            
            file_put_contents(__DIR__ . "/changelog.xml", $xml->saveXML());
        } else {
            
            if(!empty($gitlog)) {
                $xml = file_get_contents(__DIR__ . "/changelog.xml");
                $xml = simplexml_load_string( $xml );
                
                $cl = $xml->addChild("changelog");
                $cl->addAttribute("build", $build);
                $cl->addAttribute("commit", $githash[0]);
                $cl->addAttribute("date", date('Y-m-d'));
                $cl->addAttribute("time", date('H:i:s'));
                $cl->addAttribute("version", $version);
                $cl->addChild("description", "- " . implode(PHP_EOL . "- " , $gitlog));
            
                file_put_contents(__DIR__ . "/changelog.xml", $xml->saveXML());
            }
        }
    }
}

foreach ($servers as $server) {
    if ($args['revert']) {
        $server->revert($git, $args['list_only']);
    } else {
        $server->deploy($git, $git->interpret_target_commit($args['target_commit'], $server->server['branch']), false, $args['list_only']);
    }
}

__HALT_COMPILER();
