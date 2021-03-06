<?php

class CRM_Core_CodeGen_Util_File {
  static function createDir($dir, $perm = 0755) {
    if (!is_dir($dir)) {
      mkdir($dir, $perm, TRUE);
    }
  }

  static function removeDir($dir) {
    foreach (glob("$dir/*") as $tempFile) {
      unlink($tempFile);
    }
    rmdir($dir);
  }

  static function createTempDir($prefix) {
    if (isset($_SERVER['TMPDIR'])) {
      $tempDir = $_SERVER['TMPDIR'];
    }
    else {
      $tempDir = '/tmp';
    }

    $newTempDir = $tempDir . '/' . $prefix . rand(1, 10000);
    if (function_exists('posix_geteuid')) {
      $newTempDir .= '_' . posix_geteuid();
    }

    if (file_exists($newTempDir)) {
      self::removeDir($newTempDir);
    }
    self::createDir($newTempDir);

    return $newTempDir;
  }

  /**
   * Calculate a cumulative digest based on a collection of files
   *
   * @param array $files list of file names (strings)
   * @param callable|string $digest a one-way hash function (string => string)
   *
   * @return string
   */
  static function digestAll($files, $digest = 'md5') {
    $buffer = '';
    foreach ($files as $file) {
      $buffer .= $digest(file_get_contents($file));
    }
    return $digest($buffer);
  }

  /**
   * Find the path to the main Civi source tree
   *
   * @return string
   * @throws RuntimeException
   */
  static function findCoreSourceDir() {
    $path = str_replace(DIRECTORY_SEPARATOR, '/', __DIR__);
    if (!preg_match(':(.*)/CRM/Core/CodeGen/Util:', $path, $matches)) {
      throw new RuntimeException("Failed to determine path of code-gen");
    }

    return $matches[1];
  }

  /**
   * Find files in several directories using several filename patterns
   *
   * @param array $pairs each item is an array(0 => $searchBaseDir, 1 => $filePattern)
   * @return array of file paths
   */
  static function findManyFiles($pairs) {
    $files = array();
    foreach ($pairs as $pair) {
      list ($dir, $pattern) = $pair;
      $files = array_merge($files, CRM_Utils_File::findFiles($dir, $pattern));
    }
    return $files;
  }
}
