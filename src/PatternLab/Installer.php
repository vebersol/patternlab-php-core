<?php

/*!
 * Installer Class
 *
 * Copyright (c) 2014 Dave Olsen, http://dmolsen.com
 * Licensed under the MIT license
 *
 * Various functions to be run before and during composer package installs
 *
 */

namespace PatternLab;

use \Composer\Script\Event;
use \PatternLab\Config;
use \Symfony\Component\Filesystem\Filesystem;
use \Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class Installer {
	
	protected $types = array("mustachehelper" => "mustache helper", "patternengine" => "pattern engine", "plugin" => "plug-in", "starterkit" => "starterKit", "styleguidekit" => "styleguideKit");
	
	/**
	 * Make sure certain things are set-up before running composer's install
	 */
	public static function preInstallCmd(Event $event) {
		
		Config::init(false);
		
		if (!is_dir(Config::$options["sourceDir"])) {
			mkdir(Config::$options["sourceDir"]);
		}
		
		if (!is_dir(Config::$options["pluginDir"])) {
			mkdir(Config::$options["pluginDir"]);
		}
		
	}
	
	/**
	 * Handle some Pattern Lab specific tasks based on what's found in the package's composer.json file
	 */
	public static function postPackageInstall(Event $event) {
		
		// get package info
		$package = $event->getComposer()->getPackage();
		$path    = $event->getComposer()->getInstallationManager()->getInstallPath($package);
		$extra   = $package->getExtra();
		$name    = $package->getName();
		$type    = $package->getType();
		
		// move assets to the public directory
		if (isset($extra["assets"]["publicDir"])) {
			$this->parseFileList($path,Config::$options["publicDir"],$composerConfig["assets"]["publicDir"]);
		}
		
		// move assets to the source directory
		if (isset($extra["assets"]["sourceDir"])) {
			$this->parseFileList($path,Config::$options["sourceDir"],$composerConfig["assets"]["sourceDir"]);
		}
		
		// see if we need to modify the config
		if (isset($extra["config"]) {
			
			foreach ($extra["config"] as $optionInfo) {
				
				$option = key($optionInfo);
				$value  = $optionInfo[$option];
				
				// check if we should notify the user of a change
				if (isset(Config::$options[$option])) {
					$stdin = fopen("php://stdin", "r");
					print("update the config option '".$option."' with the value '".$value."'? Y/n\n");
					$answer = strtolower(trim(fgets($stdin)));
					fclose($stdin);
					if ($answer == "y") {
						Config::update($option,$value);
						print "config option '".$option."' updated...\n";
					} else {
						print "config option '".$option."' not  updated...\n";
					}
				} else {
					Config::update($option,$value);
				}
				
			}
			
		}
		
	}
	
	/**
	 * Move the files from the package to their location in the public dir or source dir
	 * @param  {String}    the base directory for the source of the files
	 * @param  {String}    the base directory for the destintation of the files (publicDir or sourceDir)
	 * @param  {Array}     the list of files to be moved
	 */
	protected function parseFileList($sourceBase,$destinationBase,$fileList) {
		
		$fs = new Filesystem();
		
		foreach ($fileList as $fileItem) {
			
			// retrieve the source & destination
			$source      = key($fileItem);
			$destination = $fileItem[$source];
			
			// depending on the source handle things differently. mirror if it ends in /*
			if (($source == "*") && ($destination == "*")) {
				$fs->mirror($sourceBase."/assets/",$destinationBase."/");
			} else if (($source == "*") && ($destination[strlen($source)-1] == "*")) {
				$destination = rtrim($destination,"/*");
				$fs->mirror($sourceBase."/assets/",$destinationBase."/".$destination);
			} else if ($source[strlen($source)-1] == "*") {
				$source      = rtrim($source,"/*");
				$destination = rtrim($destination,"/*");
				$fs->mirror($sourceBase."/assets/".$source,$destinationBase."/".$destination);
			} else {
				$pathInfo       = explode("/",$destination);
				$file           = array_pop($pathInfo);
				$destinationDir = implode("/",$pathInfo);
				if (!$fs->exists($destinationBase.$destinationDir)) {
					$fs->mkdir($destinationBase."/".$destinationDir);
				}
				$fs->copy($sourceBase."/assets/".$source,$destinationBase."/".$destination,true);
			}
			
		}
		
	}
	
}