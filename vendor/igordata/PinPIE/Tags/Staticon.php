<?php
/**
 * Created by PhpStorm.
 * User: igors
 * Date: 2016-08-28
 * Time: 22:41
 */

namespace igordata\PinPIE\Tags;

use \igordata\PinPIE\PP as PP;


class Staticon extends Tag {
  private
    $dimensions = [],
    $gzip = false,
    $gzipLevel = 1,
    $minifie = false,
    $minifiedPath = false,
    $minifiedURL = false,
    $staticHash = false,
    $staticPath = false,
    $staticType = false,
    $url = false;

  private $c = [];

  public function __construct(PP $pinpie, $fulltag, $type, $placeholder, $template, $cachetime, $fullname, Tag $parentTag, $priority, $depth) {
    parent::__construct($pinpie, $fulltag, $type, $placeholder, $template, $cachetime, $fullname, $parentTag, $priority, $depth);

    if (!isset($this->pinpie->inCa['static'])) {
      $this->pinpie->inCa['static'] = [];
    }
    $this->c = &$this->pinpie->inCa['static'];


    $this->staticType = $this->name;
    $this->staticPath = $this->value . (!empty($this->params) ? '?' . $this->params : '');

    if (empty($this->staticPath)) {
      $this->error($fulltag . ' static file path is empty');
    } else {
      if ($this->staticPath{0} !== '/') {
        $this->staticPath = $this->pinpie->url['path'] . '/' . $this->staticPath;
      }
    }

    $this->minifie = in_array($this->staticType, $this->pinpie->conf->pinpie['static minify types']);
    $this->gzip = in_array($this->staticType, $this->pinpie->conf->pinpie['static gzip types']);

    $this->filename = $this->getStaticPath();

    if (!empty($this->filename)) {
      if ($this->minifie) {
        $this->getMinified();
      }

      if ($this->gzip) {
        $this->checkAndRunGzip();
      }

      if (in_array($this->staticType, $this->pinpie->conf->pinpie['static dimensions types'])) {
        $this->dimensions = $this->getDimensions();

      }
      $this->filetime = $this->pinpie->filemtime($this->filename);
      $this->staticHash = md5($this->pinpie->conf->random_stuff . '*' . $this->filename . '*' . $this->filetime);
      $this->url = $this->getStaticUrl();
    }
  }

  public function getStaticUrl() {
    if (!isset($this->c['getStaticPath'])) {
      $this->c['getStaticPath'] = [];
    }
    if (!isset($this->c['getStaticPath'][$this->filename])) {
      if ($this->minifie AND $this->minifiedURL) {
        $file = $this->minifiedURL;
      } else {
        $file = $this->staticPath;
      }
      $this->c['getStaticPath'][$this->filename] = $this->getServer() . ($file[0] == '/' ? '' : '/') . $file;
    }
    return $this->c['getStaticPath'][$this->filename];
  }


  private function getStaticPath() {
    if (!isset($this->c['getStaticPath'])) {
      $this->c['getStaticPath'] = [];
    }
    if (isset($this->c['getStaticPath'][$this->staticPath])) {
      return $this->c['getStaticPath'][$this->staticPath];
    }
    $this->c['getStaticPath'][$this->staticPath] = $this->getStaticPathReal();
    return $this->c['getStaticPath'][$this->staticPath];
  }

  private function getStaticPathReal() {
    $path = $this->pinpie->conf->pinpie['static folder'] . DIRECTORY_SEPARATOR . $this->staticPath;
    if ($this->pinpie->conf->pinpie['static realpath check']) {
      $path = $this->pinpie->checkPathIsInFolder($path, $this->pinpie->conf->pinpie['static folder']);
    }
    if (!file_exists($path)) {
      // no such file
      return false;
    }
    return $path;
  }

  public function getServer() {
    if (!$this->filename) {
      return false;
    }
    if (!isset($this->c['getServer'])) {
      $this->c['getServer'] = [];
    }
    if (isset($this->c['getServer'][$this->filename])) {
      return $this->c['getServer'][$this->filename];
    }
    if (empty($this->pinpie->conf->static_servers)) {
      $this->url = '//' . $this->pinpie->conf->pinpie['site url'];
    } else {
      $a = abs(crc32($this->filename)) % count($this->pinpie->conf->static_servers);
      $this->url = '//' . $this->pinpie->conf->static_servers[$a];
    }
    $this->c['getServer'][$this->filename] = $this->url;
    return $this->url;
  }

  private function checkAndRunGzip() {
    $r = false;
    if (!$this->checkMTime($this->filename, $this->filename . '.gz')) {
      $this->pinpie->times['#gzipping start ' . $this->filename] = microtime(true);
      if (is_file($this->filename)) {
        $fp = fopen($this->filename, 'r');
        if ($fp !== false AND flock($fp, LOCK_EX | LOCK_NB)) {
          $gz = gzopen($this->filename . '.gz', 'w' . (int)$this->gzipLevel);
          if ($gz !== false) {
            gzwrite($gz, fread($fp, filesize($this->filename)));
            $r = true;
          }
          flock($fp, LOCK_UN);
          fclose($fp);
        }
      }
      $this->pinpie->times['#gzipping done ' . $this->filename] = microtime(true);
    }
    return $r;
  }

  private function checkAndRunMinifier() {
    if (!$this->minifie) {
      return false;
    }
    if (empty($this->pinpie->conf->pinpie['static minify function'])) {
      return false;
    }
    $fp = fopen($this->filename, 'r');
    if (empty($fp)) {
      return false;
    }

    /*
   * We can't lock file for writing, external minifiers like Yahoo YUI Compressor or Google Closure Compiler will have no access in that case.
   * Locking file for reading will prevent file from any modifications.
   * So if we will attempt to lock it for writing, we will success if file is not locked for reading in *another* process.
   */
    if (flock($fp, LOCK_SH) === false) {
      return false;
    }
    if (flock($fp, LOCK_EX | LOCK_NB) === false) {
      return false;
    }
    // Switching back to reading lock to make file readable by any external processes
    if (flock($fp, LOCK_SH) === false) {
      return false;
    }
    // Calling user function, where minification is made
    $func = $this->pinpie->conf->pinpie['static minify function'];
    $ufuncr = $func($this);
    // Releasing lock
    flock($fp, LOCK_UN);
    fclose($fp);
    if (!$ufuncr) {
      $this->pinpie->times['#minify func cancels use of min path by returning false ' . $this->filename] = microtime(true);
      return false;
    }
    return $this->checkMTime($this->filename, $this->minifiedPath);
  }

  /** Return true if $older is older or equal than $newer.
   * @param $older
   * @param $newer
   * @return bool
   */
  private function checkMTime($older, $newer) {
    if ($this->pinpie->filemtime($older) !== false AND $this->pinpie->filemtime($newer) !== false AND $this->pinpie->filemtime($older) <= $this->pinpie->filemtime($newer)) {
      return true;
    }
    return false;
  }


  /**
   * Looks for minified version of the file in the static folder.
   */
  private function getMinified() {
    if (!isset($this->c['getMinified'])) {
      $this->c['getMinified'] = [];
    }
    if (isset($this->c['getMinified'][$this->staticPath])) {
      $this->minifiedURL = $this->c['getMinified'][$this->staticPath]['url'];
      $this->minifiedPath = $this->c['getMinified'][$this->staticPath]['path'];
    }
    $pi = pathinfo('/' . trim($this->staticPath, '/\\'));
    $this->minifiedURL = trim($pi['dirname'], '/\\') . DS . 'min.' . $pi['basename'];
    $this->minifiedPath = $this->pinpie->conf->pinpie['static folder'] . DS . trim($this->minifiedURL, '/\\');
    if ($this->checkMTime($this->filename, $this->minifiedPath)) {
      $useminify = true;
    } else {
      $useminify = $this->checkAndRunMinifier();
    }
    if (!$useminify) {
      $this->minifiedURL = false;
    }
    $this->c['getMinified'][$this->staticPath]['url'] = $this->minifiedURL;
    $this->c['getMinified'][$this->staticPath]['path'] = $this->minifiedPath;
  }


  public function getDimensions() {
    if (!isset($this->c['getDimensions'])) {
      $this->c['getDimensions'] = [];
    }
    if (!isset($this->c['getDimensions'][$this->filename])) {
      $this->c['getDimensions'][$this->filename] = $this->measureDimensions();
    }
    return $this->c['getDimensions'][$this->filename];
  }

  /**
   * @param $path
   * @return array|bool
   */
  public function measureDimensions() {
    if (empty($this->filename)) return false;
    $imginfo = getimagesize($this->filename);
    $r = [];
    $r['type'] = $imginfo['mime'];
    $r['width'] = $imginfo[0];
    $r['height'] = $imginfo[1];
    return $r;
  }

  public function getOutput() {
    $this->content = $this->getContent();
    //Apply template to tag content
    if (!empty($this->template)) {
      $this->output = $this->applyTemplate();
    } else {
      $this->output = $this->content;
    }
    if ($this->placeholder) {
      $this->varPut();
    }
    return $this->output;
  }

  public function getContent() {
    if (!empty($this->pinpie->conf->pinpie['static draw function'])) {
      $this->content = $this->pinpie->conf->pinpie['static draw function']($this);
      return $this->content;
    }
    if ($this->cachetime) {
      /* exclamation mark AKA cachetime = return path only */
      $this->content = $this->url . '?time=' . $this->staticHash;
    } else {
      $this->content = $this->draw();
    }
    if (!empty($this->template)) {
      $this->varsLocal['content'][0][] = $this->content;
      if (isset($this->dimensions['width'])) {
        $this->varsLocal['width'][0][] = $this->dimensions['width'];
      }
      if (isset($this->dimensions['height'])) {
        $this->varsLocal['height'][0][] = $this->dimensions['height'];
      }
      $this->varsLocal['file path'][0][] = $this->filename;
      $this->varsLocal['time'][0][] = $this->filetime;
      $this->varsLocal['time getHash'][0][] = $this->staticHash;
      $this->varsLocal['url'][0][] = $this->url;
    }
    return $this->content;
  }

  protected function draw() {
    if ($this->url !== false) {
      switch ($this->staticType) {
        case 'js':
          return '<script type="text/javascript" src="' . $this->url . '?time=' . $this->staticHash . '"></script>';
        case 'css':
          return '<link rel="stylesheet" type="text/css" href="' . $this->url . '?time=' . $this->staticHash . '">';
        case 'img':
          return '<img src="' . $this->url . '?time=' . $this->staticHash . '"' . (isset($this->dimensions['width']) ? ' width="' . $this->dimensions['width'] . '"' : '') . (isset($this->dimensions['height']) ? ' height="' . $this->dimensions['height'] . '"' : '') . '>';
      }
    }
    if ($this->pinpie->conf->debug) {
      return $this->fulltag;
    }
    return '';
  }

}