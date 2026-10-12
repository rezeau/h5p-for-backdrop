<?php
/** Select caller-supplied packages without any recorded site inventory. */
function h5p_test_export_fixtures($directory, $manifest = NULL) {
  $root = realpath($directory);
  if ($root === FALSE || !is_dir($root)) {
    throw new RuntimeException('Export fixture directory does not exist.');
  }
  if ($manifest === NULL) {
    $paths = glob($root . '/*.h5p');
    sort($paths);
    $records = array_map(function ($path) {
      return array('file' => basename($path), 'sha256' => NULL);
    }, $paths);
  }
  else {
    $records = json_decode(file_get_contents($manifest), TRUE);
    if (!is_array($records) || array_keys($records) !== range(0, count($records) - 1)) {
      throw new RuntimeException('Fixture manifest must be a nonempty JSON list.');
    }
  }
  if (!$records) { throw new RuntimeException('No export fixtures selected.'); }
  $seen = array();
  foreach ($records as $record) {
    if (!is_array($record) || !isset($record['file']) || !is_string($record['file']) ||
        preg_match('~[/\\\\:]~', $record['file']) || substr($record['file'], -4) !== '.h5p' ||
        isset($seen[$record['file']])) {
      throw new RuntimeException('Fixture names must be unique .h5p basenames.');
    }
    if ($manifest !== NULL && (!isset($record['sha256']) || !is_string($record['sha256']) ||
        !preg_match('/^[a-f0-9]{64}$/i', $record['sha256']))) {
      throw new RuntimeException('Manifest entries require a SHA-256 hash.');
    }
    $path = $root . DIRECTORY_SEPARATOR . $record['file'];
    if (!is_file($path) || is_link($path) || realpath(dirname($path)) !== $root) {
      throw new RuntimeException('Fixture must be a regular file within its supplied directory.');
    }
    $seen[$record['file']] = TRUE;
  }
  return $records;
}
