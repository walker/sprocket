<?php
/**
 * @file
 * Provide basic concatenation, minifying, and caching of css & js.
 *
 * Created by Walker Hamilton on 2013-01-30.
 */

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
		'cache_directory' => './cache',
		'debug' => false
	);
	protected $full_file_path = '';
	protected $file_path = '';
	protected $file = '';
	
	protected $js = null;
	protected $css = null;
	protected $coffee = null;
	protected $file_contents = null;
	
	protected $constantsScanned = array();
	protected $constants = array();
	
	public function __construct($settings) {
		$this->settings = array_intersect_key($settings + $this->settings, $this->settings);
	}
	
	public function output($file, $type=null) {
		$return = true;
		
		/* Can this part be done better? */
		$this->file = $file[count($file)-1];
		$this->full_file_path = $file;
		unset($this->full_file_path[count($file)-1]);
		$this->full_file_path = implode('/', $this->full_file_path);
		$this->file_path = implode('/', $file);
		/* End file stuffs */
		
		switch($this->getType()) {
			case 'coffeescript':
				// find coffee script file
				// render coffee script to js
				$this->file_contents = $this->findFile($this->file_path);
				$this->coffee = $this->parseCoffeeScript($this->file_contents);
				if(!$this->coffee) {
					header('Content-type: application/x-javascript');
					echo '// couldn\'t find: '.$this->file_path;
					$return = false;
				}
				$this->js = $this->renderCoffeeScript($this->coffee);
				break;
			case 'javascript':
				// check if js already loaded, if not, get it.
				if(!$this->file_contents)
					$this->file_contents = $this->findFile();
				
				header('Content-type: application/x-javascript');
				if(!$this->file_contents) {
					$return = false;
					echo '// couldn\'t find: '.$this->file_path;
				} else {
					if($this->parseJS()) {
						if(count($this->constants) > 0) $this->swapConstants();
						// $this->stripComments();
						if(!isset($this->settings['debug'])) file_put_contents(APP.DS.'tmp'.DS.'cache'.str_replace('/', '_', $this->filePath).'.cache', $this->js);
						echo $this->js;
					} else {
						echo '// There was a problem parsing this file as js: '.$this->file_path;
					}
				}
				break;
			case 'stylesheet':
				$this->file_contents = $this->findFile();
				header('Content-type: text/css');
				if(!$this->file_contents) {
					$return = false;
					echo '/* couldn\'t find: '.$this->file_path.' */';
				} else {
					if($this->parseCSS()) {
						echo $this->css;
					} else {
						echo '/* There was a problem parsing this file as css: '.$this->file_path.' */';
					}
				}
				break;
		}
		
		$this->reset();
		return true;
	}
	
	protected function reset() {
		$this->js = null;
		$this->css = null;
		$this->coffee = null;
		$this->file = '';
		$this->file_path = '';
		$this->full_file_path = '';
	}
	
	public function findFile($file_path=false) {
		if(!$file_path) {
			foreach($this->settings[$this->getExt().'_directories'] as $dir) {
				if(file_exists($dir.$this->file_path)) {
					$this->full_file_path = $dir.$this->full_file_path;
					return file_get_contents($dir.$this->file_path);
				}
			}
		} else {
			foreach($this->settings[$this->getExt().'_directories'] as $dir) {
				if(file_exists($dir.$file_path)) {
					return file_get_contents($dir.$file_path);
				}
			}
		}
		return false;
	}
	
	public function renderCoffeeScript($file_contents) {
		// What to use?
	}
	
	protected function getType($file = null) {
		if($file) {
			$file = explode('.', $file);
		} else {
			$file = explode('.', $this->file);
		}
		switch(strtolower($file[count($file)-1])) {
			case 'js':
			case 'javascript':
				return 'javascript';
				break;
			case 'css':
				return 'stylesheet';
				break;
			case 'coffee':
			case 'coffeescript':
				return 'coffeescript';
				break;
		}
	}
	
	protected function getExt() {
		switch($this->getType()) {
			case 'javascript':
				return 'js';
				break;
			case 'stylesheet':
				return 'css';
				break;
			case 'coffeescript':
				return 'coffee';
				break;
		}
	}
	
	protected function checkCached() {
		if(is_file(APP.DS.'tmp'.DS.'cache'.str_replace('vendor/', '', $this->filePath).'.cache')) {
			echo file_get_contents(APP.DS.'tmp'.DS.'cache'.str_replace('vendor/', '', $this->filePath).'.cache');
			exit;
		} else return false;
	}
	
	protected function parseJS($file_contents=null) {
		// TODO: Fix this...what's going on here?
		// $link = $this->file_path.'/'.str_replace(basename($this->file), '', $this->file).'constants.yml';
		// if(!isset($this->constantsScanned[$link]) && is_file($link)) $this->parseConstants($link);
		
		// Find file returns false if it can't find it...so we have to differentiate none provided from false provided.
		if($file_contents===false) {
			return '';
		} else if($file_contents===null) {
			$this->js = $this->file_contents;
			preg_match_all('/\/\/= ([a-z]+) ([^\n]+)/', $this->js, $matches);
			
			foreach($matches[0] as $key => $match) {
				$method = $matches[1][$key].'_command';
				$this->js = str_replace($matches[0][$key], $this->$method(trim($matches[2][$key]), $this->full_file_path), $this->js);
			}
			return $this->js;
		} else {
			$js = $file_contents;
			$matches2 = array();
			preg_match_all('/\/\/= ([a-z]+) ([^\n]+)/', $js, $matches2);
			
			foreach($matches2[0] as $key => $match) {
				$method = $matches2[1][$key].'_command';
				$js = str_replace($matches2[0][$key], $this->$method(trim($matches2[2][$key]), $this->full_file_path), $js);
			}
			return $js;
		}
	}

	protected function parseCoffeeScript($file_contents=null) {
		// TODO: Fix this...what's going on here?
		// $link = $this->file_path.'/'.str_replace(basename($this->file), '', $this->file).'constants.yml';
		// if(!isset($this->constantsScanned[$link]) && is_file($link)) $this->parseConstants($link);
		if($file_contents===false) {
			return '';
		} else if($file_contents===null) {
			$this->coffee = $this->file_contents;
			preg_match_all('/\#= ([a-z]+) ([^\n]+)/', $this->coffee, $matches);
			
			foreach($matches[0] as $key => $match) {
				$method = $matches[1][$key].'_command';
				$this->coffee = str_replace($matches[0][$key], $this->$method(trim($matches[2][$key]), $this->full_file_path), $this->coffee);
			}
			return $this->coffee;
		} else {
			$coffee = $file_contents;
			$matches2 = array();
			preg_match_all('/\#= ([a-z]+) ([^\n]+)/', $coffee, $matches2);
			
			foreach($matches2[0] as $key => $match) {
				$method = $matches2[1][$key].'_command';
				$coffee = str_replace($matches2[0][$key], $this->$method(trim($matches2[2][$key]), $this->full_file_path), $coffee);
			}
			return $coffee;
		}

	}
	
	protected function parseCSS($file_contents=null) {
		// TODO: Fix this...what's going on here?
		// $link = $this->file_path.'/'.str_replace(basename($this->file), '', $this->file).'constants.yml';
		// if(!isset($this->constantsScanned[$link]) && is_file($link)) $this->parseConstants($link);
		if($file_contents===false) {
			return '';
		} else if($file_contents===null) {
			$this->css = $this->file_contents;
			preg_match_all('/\/\*= ([a-z]+) ([^\n]+)/', $this->css, $matches);
			
			foreach($matches[0] as $key => $match) {
				$method = $matches[1][$key].'_command';
				$this->css = str_replace($matches[0][$key], $this->$method(trim($matches[2][$key]), $this->full_file_path), $this->css);
			}
			return $this->css;
		} else {
			$css = $file_contents;
			$matches2 = array();
			preg_match_all('/\/\*= ([a-z]+) ([^\n]+)/', $css, $matches2);
			
			foreach($matches2[0] as $key => $match) {
				$method = $matches2[1][$key].'_command';
				$css = str_replace($matches2[0][$key], $this->$method(trim($matches2[2][$key]), $this->full_file_path), $css);
			}
			return $css;
		}
	}
	
	protected function require_command($file, $context) {
		$ext = $this->getExt();
		
		if(file_exists($context.str_replace('"', '', $file).'.'.$ext)) {
			return $this->parseJS(file_get_contents($context.str_replace('"', '', $file).'.'.$ext));
		} else {
			if(preg_match('/\"([^\"]+)\"/', $file, $match)) {
				if($ext=='js')
					return $this->parseJS($this->findFile($match[1].'.'.$ext));
				else if($ext=='css')
					return $this->parseCSS($this->findFile($match[1].'.'.$ext));
				else if($ext=='coffee')
					return $this->parseCoffeeScript($this->findFile($match[1].'.'.$ext));
			} else if(preg_match('/\<([^\>]+)\>/', $param, $match)) {
				if($ext=='js')
					return $this->parseJS($this->findFile($match[1].'.'.$ext));
				else if($ext=='css')
					return $this->parseCSS($this->findFile($match[1].'.'.$ext));
				else if($ext=='coffee')
					return $this->parseCoffeeScript($this->findFile($match[1].'.'.$ext));
			} else return '';
		}
	}
	
	protected function provide_command($param, $context) {
		preg_match('/\"([^\"]+)\"/', $param, $match);
		foreach(glob($context.'/'.$match[1].'/*') as $asset) {
			shell_exec('cp -r '.realpath($asset).' '.realpath($this->assetFolder));
		}
	}
	
	protected function parseConstants($file) {
		$contents = file_get_contents($file);
		preg_match_all('/^([A-Za-z][^\:]+)\:([^\n]+)/', $contents, $matches);
		foreach($matches[0] as $key => $val) {
			$this->constants[$matches[1][$key]] = $matches[2][$key];
		}
		$this->constantsScanned[$file] = true;
	}
	
	protected function swapConstants() {
		preg_match_all('/\<(\%|\?)\=\s*([^\s|\%|\?]+)\s*(\?|\%)\>/', $this->js, $matches);
		foreach($matches[0] as $key => $replace) {
			$this->js = str_replace($replace, $this->constants[$matches[2][$key]], $this->js);
		}
	}
	// die(shell_exec('NODE_PATH="/home/app/nodejs/node_modules/"; export NODE_PATH; uglifyjs -o /home/app/public_html/js/profile.min5.js /home/app/public_html/js/profile.js'));

}
