<?php

/**
 * Bridges editor library-array lookups to the pinned Core storage contract.
 */
class H5PBackdropFileStorage extends H5PDefaultStorage {
  private $backdropPath;

  public function __construct($path, $alteditorpath = NULL) {
    parent::__construct($path, $alteditorpath);
    $this->backdropPath = $path;
  }

  /**
   * Accepts the new editor's array or the existing three-argument Core call.
   *
   * Only locates an upgrade script; never executes it or renames a library.
   */
  public function getUpgradeScript($machineName, $majorVersion = NULL, $minorVersion = NULL) {
    if (!is_array($machineName)) {
      if (func_num_args() < 3) {
        throw new ArgumentCountError('Legacy upgrade-script lookup requires three arguments.');
      }
      return parent::getUpgradeScript($machineName, $majorVersion, $minorVersion);
    }

    $library = $machineName;
    $name = $library['machineName'] ?? $library['name'] ?? NULL;
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $name)) {
      throw new InvalidArgumentException('Invalid library name for upgrade-script lookup.');
    }
    $versions = array('majorVersion', 'minorVersion');
    if (!empty($library['patchVersionInFolderName'])) {
      $versions[] = 'patchVersion';
    }
    foreach ($versions as $version) {
      if (!isset($library[$version]) ||
          !(is_int($library[$version]) || is_string($library[$version])) ||
          !preg_match('/^[0-9]+$/D', (string) $library[$version])) {
        throw new InvalidArgumentException('Invalid library version for upgrade-script lookup.');
      }
    }

    $upgrades = '/libraries/' . H5PCore::libraryToFolderName($library) . '/upgrades.js';
    return file_exists($this->backdropPath . $upgrades) ? $upgrades : NULL;
  }
}
