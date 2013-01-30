<?php
namespace Walker\Sprocket;
/**
 * @file
 * Provide basic concatenation, minifying, and caching of css & js.
 *
 * Created by Walker Hamilton on 2013-01-30.
 */
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

/**
 * This class provides javascript and css concatenation, minifying, and caching.
 *
 * @see https://github.com/sstephenson/sprockets
 *
 * @author Walker Hamilton <myself@walkerhamilton.com>
 * @copyright MIT License
 * @version 1.0
 */
class Sprocket {
	
	protected $settings = array(
		'js_directories' => array(),
		'css_directories' => array(),
		'asset_directories' => array(),
		'cache_directory' => dirname(__FILE__).DS.'cache',
		'file_path' => null,
	);
	
	protected $constantsScanned = array();
	protected $constants = array();
	
	public function __construct($settings) {
		$this->settings = array_intersect_key($settings + $this->settings, $this->settings);
	}
	
	
	// die(shell_exec('NODE_PATH="/home/app/nodejs/node_modules/"; export NODE_PATH; uglifyjs -o /home/app/public_html/js/profile.min5.js /home/app/public_html/js/profile.js'));

}