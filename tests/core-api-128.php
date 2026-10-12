<?php
/**
 * Dependency-free Core/Backdrop contract tests. Optional exports are read-only
 * sources: copies are validated in a private operating-system temp directory.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/step2-provenance.php';
require __DIR__ . '/export-fixtures.php';
$baselineMode = in_array('--baseline', $argv, TRUE);
$skipFixtureBaseline = in_array('--skip-fixture-baseline', $argv, TRUE);
$fixtureDirectory = NULL;
$fixtureManifest = NULL;
foreach ($argv as $argument) {
  if (strpos($argument, '--fixture-manifest=') === 0) {
    $fixtureManifest = substr($argument, strlen('--fixture-manifest='));
  }
  if (strpos($argument, '--fixtures=') === 0) {
    $fixtureDirectory = substr($argument, strlen('--fixtures='));
  }
}
$assertions = 0;
$failures = array();
function check($condition, $message) {
  global $assertions, $failures;
  $assertions++;
  if (!$condition) { $failures[] = $message; }
}
function same($expected, $actual, $message) {
  check($expected === $actual, $message . ' (expected ' . json_encode($expected) .
    ', got ' . json_encode($actual) . ')');
}
function testCase($name, $test) {
  global $failures;
  $before = count($failures);
  try { $test(); }
  catch (Throwable $error) { $failures[] = $name . ': ' . $error->getMessage(); }
  echo (count($failures) === $before ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
}
if (!class_exists('ZipArchive') || !extension_loaded('mbstring')) {
  fwrite(STDERR, "Tests require the existing zip and mbstring PHP extensions.\n");
  exit(2);
}
$snapshot = json_decode(file_get_contents(H5P_TEST_ROOT . '/docs/h5p-api-1.28/local-baseline.json'), TRUE);
$sourceLock = json_decode(file_get_contents(H5P_TEST_ROOT . '/docs/h5p-api-1.28/upstream-sources.lock.json'), TRUE);
$sandbox = new H5PTestSandbox();
$GLOBALS['h5p_test_sandbox'] = $sandbox;
$GLOBALS['h5p_test_snapshot'] = $snapshot;
register_shutdown_function(function () use ($sandbox) { $sandbox->close(); });

function packageCore($sandbox, $name, $snapshot) {
  $directory = $sandbox->directory($name);
  $framework = new H5PTestFramework();
  $framework->uploadDirectory = $directory . '/unpacked';
  mkdir($framework->uploadDirectory);
  $framework->uploadPath = $directory . '/upload.h5p';
  foreach ($snapshot['libraryCases'] as $index => $library) {
    $version = $library['manifest']['majorVersion'] . '.' . $library['manifest']['minorVersion'];
    $framework->installed[$library['machineName'] . ' ' . $version] = $index + 1;
  }
  $storage = new H5PDefaultStorage($directory . '/storage', $directory . '/editor');
  $core = new H5PCore($framework, $storage, '/test/', 'en', FALSE);
  return array($framework, $core);
}
function syntheticPackage($path, $api, $folder = 'H5P.BaselineFixture-1.0', $includeContent = TRUE) {
  $zip = new ZipArchive();
  if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    throw new RuntimeException('Cannot create synthetic package.');
  }
  $header = array('title' => 'Baseline fixture', 'language' => 'en',
    'mainLibrary' => 'H5P.BaselineFixture', 'embedTypes' => array('div'), 'license' => 'U',
    'preloadedDependencies' => array(array('machineName' => 'H5P.BaselineFixture',
      'majorVersion' => 1, 'minorVersion' => 0)));
  $library = array('title' => 'Baseline fixture', 'machineName' => 'H5P.BaselineFixture',
    'majorVersion' => 1, 'minorVersion' => 0, 'patchVersion' => 7, 'runnable' => 1,
    'coreApi' => $api, 'preloadedJs' => array(array('path' => 'fixture.js')));
  if ($api === NULL) { unset($library['coreApi']); }
  $zip->addFromString('h5p.json', json_encode($header));
  if ($includeContent) {
    $zip->addFromString('content/content.json', '{"text":"Preserve me","zero":0,"flag":false}');
  }
  $zip->addFromString($folder . '/library.json', json_encode($library));
  $zip->addFromString($folder . '/semantics.json', '[]');
  $zip->addFromString($folder . '/fixture.js', '// Synthetic fixture; never executed.');
  $zip->addFromString('content/.ignored.exe', 'ignored OS file');
  $zip->addFromString('content\\.ignored.exe', 'ignored Windows OS file');
  $zip->close();
}
function validatePackage($framework, $core) {
  return (new H5PValidator($framework, $core))->isValidPackage();
}
function errorCodes($framework) {
  return array_column($framework->errors, 'code');
}

testCase('Core API declaration and complete adapter contracts', function () use ($baselineMode) {
  same(array('majorVersion' => 1, 'minorVersion' => $baselineMode ? 24 : 28),
    H5PCore::$coreApi, 'Core API declaration');
  foreach (array('H5PBackdrop' => 'H5PFrameworkInterface',
      'H5PDefaultStorage' => 'H5PFileStorage',
      'H5peditorBackdropStorage' => 'H5peditorStorage',
      'H5PEditorBackdropAjax' => 'H5PEditorAjaxInterface') as $class => $interface) {
    $reflection = new ReflectionClass($class);
    check(!$reflection->isAbstract() && $reflection->implementsInterface($interface),
      $class . ' implements its complete interface');
    foreach ((new ReflectionClass($interface))->getMethods() as $method) {
      check($reflection->hasMethod($method->getName()) &&
        $reflection->getMethod($method->getName())->isPublic(),
        $class . ' exposes ' . $method->getName());
    }
  }
});

testCase('Compatible packages, newer APIs, malformed packages and directory formats',
  function () use ($sandbox, $snapshot, $baselineMode) {
    $cases = array(
      array(NULL, 'H5P.BaselineFixture-1.0', TRUE, TRUE, NULL),
      array(array('majorVersion' => 1, 'minorVersion' => 24), 'H5P.BaselineFixture-1.0', TRUE, TRUE, NULL),
      array(array('majorVersion' => 1, 'minorVersion' => 28), 'H5P.BaselineFixture-1.0', TRUE, !$baselineMode, 'api-version-unsupported'),
      array(array('majorVersion' => 1, 'minorVersion' => 29), 'H5P.BaselineFixture-1.0', TRUE, FALSE, 'api-version-unsupported'),
      array(array('majorVersion' => 2, 'minorVersion' => 0), 'H5P.BaselineFixture-1.0', TRUE, FALSE, 'api-version-unsupported'),
      array(array('majorVersion' => 1, 'minorVersion' => 24), 'H5P.BaselineFixture', TRUE, TRUE, NULL),
      array(array('majorVersion' => 1, 'minorVersion' => 24), 'H5P.Wrong-1.0', TRUE, FALSE, 'library-directory-name-mismatch'),
      array(array('majorVersion' => 1, 'minorVersion' => 24), 'H5P.BaselineFixture-1.0', FALSE, FALSE, 'invalid-content-folder'),
    );
    foreach ($cases as $index => $case) {
      list($api, $folder, $includeContent, $expected, $error) = $case;
      list($framework, $core) = packageCore($sandbox, 'package-' . $index, $snapshot);
      syntheticPackage($framework->uploadPath, $api, $folder, $includeContent);
      $valid = validatePackage($framework, $core);
      same($expected, $valid, 'Synthetic package case ' . $index);
      if (!$expected && $error) {
        check(in_array($error, errorCodes($framework), TRUE), 'Specific package rejection code ' . $error);
      }
      if ($valid) {
        same(array('text' => 'Preserve me', 'zero' => 0, 'flag' => FALSE),
          (array) $core->contentJsonData, 'Validation preserves content parameters');
      }
    }
  });

testCase('Legacy library folder names, dependency URLs and existing editor presave',
  function () use ($sandbox, $snapshot, $baselineMode) {
    list($framework, $core) = packageCore($sandbox, 'storage-contract', $snapshot);
    $library = array('machineName' => 'H5P.BaselineFixture', 'majorVersion' => 1,
      'minorVersion' => 0, 'patchVersion' => 7, 'preloadedJs' => 'fixture.js',
      'preloadedCss' => 'fixture.css');
    same('H5P.BaselineFixture 1.0', H5PCore::libraryToString($library), 'Display name');
    same('H5P.BaselineFixture-1.0', H5PCore::libraryToString($library, TRUE), 'Old editor folder call');
    if (!$baselineMode) {
      same('H5P.BaselineFixture-1.0', H5PCore::libraryToFolderName($library), 'Default excludes patch');
      $patched = $library + array('patchVersionInFolderName' => TRUE);
      same('H5P.BaselineFixture-1.0.7', H5PCore::libraryToFolderName($patched), 'Explicit patch flag');
      same('H5P.BaselineFixture-1.0', H5PCore::libraryToFolderName(
        array('name' => 'H5P.BaselineFixture', 'majorVersion' => 1, 'minorVersion' => 0)), 'Name alias');
      foreach ($snapshot['libraryCases'] as $installed) {
        same(basename($installed['directory']), H5PCore::libraryToFolderName($installed['manifest']),
          'Existing directory format for ' . $installed['machineName']);
      }
    }
    $assets = $core->getDependenciesFiles(array($library));
    same('/libraries/H5P.BaselineFixture-1.0/fixture.js', $assets['scripts'][0]->path, 'Dependency JS path');
    same('/libraries/H5P.BaselineFixture-1.0/fixture.css', $assets['styles'][0]->path, 'Dependency CSS path');
    $editor = (new ReflectionClass('H5peditor'))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty('H5peditor', 'h5p');
    $property->setAccessible(TRUE);
    $property->setValue($editor, $core);
    $editorAssets = array('scripts' => array());
    $editor->addPresaveFile($editorAssets, $library, '/prefix');
    same('/prefix/libraries/H5P.BaselineFixture-1.0/presave.js',
      $editorAssets['scripts'][0]->path, 'Unmodified editor presave URL');
    same('?ver=1.0.7', $editorAssets['scripts'][0]->version, 'Patch stays in cache-buster');
  });

if (!$baselineMode) {
  testCase('Backdrop platform version, option defaults and Hub organization reset', function () {
    $adapter = new H5PBackdrop();
    $platform = $adapter->getPlatformInfo();
    same(BACKDROP_VERSION, $platform['version'], 'Reports Backdrop version');
    same('backdrop', $platform['name'], 'Preserves platform name');
    same('test-module', $platform['h5pVersion'], 'Preserves module release metadata');
    same('fallback', $adapter->getOption('missing', 'fallback'), 'Unset option default');
    foreach (array(FALSE, 0, '', 'NULL') as $value) {
      $GLOBALS['h5p_test_config']['h5p.settings']['h5p_saved'] = $value;
      same($value, $adapter->getOption('saved', 'fallback'), 'Existing false/zero/empty values preserved');
    }
    $before = array('h5p_hub_secret' => 'test-secret', 'h5p_site_uuid' => 'test-uuid',
      'h5p_hub_is_enabled' => TRUE, 'h5p_whitelist' => 'json svg xml', 'unrelated' => 42);
    $GLOBALS['h5p_test_config']['h5p.settings'] = $before;
    $adapter->resetHubOrganizationData();
    $expected = $before;
    $expected['h5p_hub_secret'] = NULL;
    same($expected, $GLOBALS['h5p_test_config']['h5p.settings'], 'Reset changes only organization credential');
    $adapter->resetHubOrganizationData();
    same($expected, $GLOBALS['h5p_test_config']['h5p.settings'], 'Reset is idempotent');
    list($sandboxFramework, $core) = packageCore($GLOBALS['h5p_test_sandbox'], 'hub-contract',
      $GLOBALS['h5p_test_snapshot']);
    $sandboxFramework->options = array('site_uuid' => 'test-uuid', 'hub_secret' => 'test-secret');
    same(FALSE, $core->hubAccountInfo(), 'Mocked Hub 403 returns no account');
    same(NULL, $sandboxFramework->options['hub_secret'], 'Mocked 403 resets secret');
    same('test-uuid', $sandboxFramework->options['site_uuid'], 'Mocked 403 preserves site UUID');
  });

  testCase('Saved file policy preserved, upstream defaults used only when absent', function () {
    $adapter = new H5PBackdrop();
    $GLOBALS['h5p_test_config']['h5p.settings'] = array(
      'h5p_whitelist' => 'json svg xml', 'h5p_library_whitelist_extras' => 'js css');
    same('json svg xml', $adapter->getWhitelist(FALSE, 'new-default', 'new-extra'), 'Saved content policy');
    same('json svg xml js css', $adapter->getWhitelist(TRUE, 'new-default', 'new-extra'), 'Saved library policy');
    $GLOBALS['h5p_test_config']['h5p.settings'] = array();
    same('json', $adapter->getWhitelist(FALSE, 'json', 'js css svg xml'), 'Missing content default');
    same('json js css svg xml', $adapter->getWhitelist(TRUE, 'json', 'js css svg xml'), 'Missing library default');
    $GLOBALS['h5p_test_config']['h5p.settings'] = array('h5p_whitelist' => '', 'h5p_library_whitelist_extras' => '');
    same(' ', $adapter->getWhitelist(TRUE, 'json', 'js css'), 'Explicit empty policy preserved');
  });
}

testCase('Required assets and local CSS font/image references', function () use ($baselineMode, $sourceLock) {
  foreach (array_merge(H5PCore::$scripts, H5PCore::$styles, H5PCore::$adminScripts) as $asset) {
    check(is_file(H5P_TEST_ROOT . '/library/' . $asset), 'Core asset exists: ' . $asset);
  }
  if (!$baselineMode) {
    same($sourceLock['core']['assetEntryPoints']['scripts'], H5PCore::$scripts, 'Pinned script list');
    same($sourceLock['core']['assetEntryPoints']['styles'], H5PCore::$styles, 'Pinned style list');
  }
  foreach (H5PCore::$styles as $asset) {
    $path = H5P_TEST_ROOT . '/library/' . $asset;
    preg_match_all('~url\(\s*([\'"]?)(.*?)\1\s*\)~', file_get_contents($path), $matches);
    foreach ($matches[2] as $url) {
      if (preg_match('~^(data:|https?:|//|\#)~', $url)) { continue; }
      $relative = rawurldecode(preg_replace('~[?#].*$~', '', $url));
      check(is_file(dirname($path) . '/' . $relative), 'CSS resource exists: ' . $url);
    }
  }
});

testCase('Locked target editor and reviewed Backdrop adapter patches', function () use ($snapshot, $sourceLock) {
  $vendorRoot = H5P_TEST_ROOT . '/modules/h5peditor/h5peditor';
  $actualFiles = array();
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorRoot, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $entry) {
    if ($entry->isLink()) { throw new RuntimeException('Vendor symlink rejected'); }
    if ($entry->isFile()) { $actualFiles[] = str_replace('\\', '/', substr($entry->getPathname(), strlen($vendorRoot) + 1)); }
  }
  $expectedFiles = array_column($sourceLock['editor']['files'], 'path');
  sort($actualFiles); sort($expectedFiles);
  same($expectedFiles, $actualFiles, 'Exact vendor inventory, no conflicting legacy assets');
  foreach ($sourceLock['editor']['files'] as $file) {
    $bytes = file_get_contents($vendorRoot . '/' . $file['path']);
    same($file['gitBlob'], sha1('blob ' . strlen($bytes) . "\0" . $bytes), 'Exact editor blob: ' . $file['path']);
  }
  $changed = array();
  foreach ($snapshot['moduleFiles'] as $file) {
    // The entire replaced vendor tree is checked against its 214-file lock above.
    if (strpos($file['path'], 'modules/h5peditor/h5peditor/') === 0) { continue; }
    $bytes = h5p_step2_baseline_bytes($file['path'],
      file_get_contents(H5P_TEST_ROOT . '/' . $file['path']));
    if (strpos($file['path'], 'modules/h5peditor/') === 0 &&
        hash('sha256', $bytes) !== $file['sha256']) {
      $changed[] = $file['path'];
    }
  }
  same(array(), $changed, 'Editor vendor bytes and normalized adapter baselines match the frozen snapshot');
});

testCase('Existing editor asset alter hooks', function () {
  $GLOBALS['h5p_test_alters'] = array();
  $assets = array('scripts' => array(), 'styles' => array());
  $libraries = array(array('machineName' => 'H5P.BaselineFixture', 'majorVersion' => 1, 'minorVersion' => 0));
  (new H5peditorBackdropStorage())->alterLibraryFiles($assets, $libraries);
  same('h5p_scripts', $GLOBALS['h5p_test_alters'][0][0], 'Scripts hook called');
  same('h5p_styles', $GLOBALS['h5p_test_alters'][1][0], 'Styles hook called');
  same(array('H5P.BaselineFixture' => array('majorVersion' => 1, 'minorVersion' => 0)),
    $GLOBALS['h5p_test_alters'][0][1], 'Hook retains library context');
  same('test-h5p_scripts', $assets['scripts'][0]->path, 'Hook can alter scripts');
});

testCase('Backdrop factory preserves alternate editor storage and copied-media paths',
  function () use ($sandbox) {
    $public = $sandbox->directory('public-files');
    $GLOBALS['h5p_test_public_files'] = $public;
    $GLOBALS['language'] = (object) array('langcode' => 'fr');
    $GLOBALS['h5p_test_config']['h5p.settings'] = array(
      'h5p_default_path' => 'h5p', 'h5p_library_development' => 0);
    $core = _h5p_get_instance('core');
    check($core->fs instanceof H5PBackdropFileStorage, 'Factory uses the compatible Backdrop storage subclass');
    same($public . '/h5p', _h5p_get_h5p_path(), 'Configured content directory');
    same($core, _h5p_get_instance('core'), 'Factory keeps cached core');
    foreach (array('../../../h5peditor/images/test.png' => 'h5peditor',
        '../../content/17/images/test.png' => '17') as $path => $id) {
      same(1, preg_match($core->relativePathRegExp, $path, $matches), 'Copied-media path recognized');
      same($id, $matches[4], 'Copied-media source ID preserved');
      same('images/test.png', $matches[5], 'Copied-media relative filename preserved');
    }
    $sandbox->directory('public-files/h5peditor/images');
    file_put_contents($public . '/h5peditor/images/test.png', 'test media');
    $core->fs->cloneContentFile('images/test.png', 'editor', 17);
    $target = $core->fs->getContentFile('images/test.png', 17);
    check(is_file($target), 'Media cloned from alternate editor path');
    same('test media', file_get_contents($target), 'Media bytes preserved');
    check(is_file($public . '/h5peditor/images/test.png'), 'Source media retained');
    check(!is_dir($public . '/h5p/editor'), 'Default editor directory not used');
  });

if (!$baselineMode) {
  testCase('Pinned core provenance and reviewed platform/editor integration',
    function () use ($sourceLock, $snapshot) {
      $exceptions = array('h5p.classes.php', 'h5p-event-base.class.php', '.gitignore', '.travis.yml');
      foreach ($sourceLock['core']['files'] as $file) {
        if (in_array($file['path'], $exceptions, TRUE)) {
          if (isset($snapshot['coreVendorDifferences'][$file['path']])) {
            same($snapshot['coreVendorDifferences'][$file['path']]['sha256'],
              hash_file('sha256', H5P_TEST_ROOT . '/' . $file['targetPath']),
              'Exact documented Core difference: ' . $file['path']);
          }
          continue;
        }
        $bytes = file_get_contents(H5P_TEST_ROOT . '/' . $file['targetPath']);
        same($file['gitBlob'], sha1('blob ' . strlen($bytes) . "\0" . $bytes),
          'Exact upstream blob: ' . $file['targetPath']);
      }
      $changed = array();
      foreach ($snapshot['moduleFiles'] as $file) {
    // The entire replaced vendor tree is checked against its 214-file lock above.
    if (strpos($file['path'], 'modules/h5peditor/h5peditor/') === 0) { continue; }
        if (strpos($file['path'], 'library/') === 0 || $file['path'] === 'h5p.classes.inc') { continue; }
        $bytes = h5p_step2_baseline_bytes($file['path'],
          file_get_contents(H5P_TEST_ROOT . '/' . $file['path']));
        if ($file['path'] === 'h5p.module') {
          // Accept only the approved resizer property rename, then compare the
          // entire adapter against its original frozen hash.
          $oldOption = "'language' => (object) array('language' => FALSE)";
          $newOption = "'language' => (object) array('langcode' => FALSE)";
          same(1, substr_count($bytes, $newOption), 'Single approved resizer correction');
          $bytes = str_replace($newOption, $oldOption, $bytes);
        }
        if (hash('sha256', $bytes) !== $file['sha256']) {
          $changed[] = $file['path'];
        }
      }
      same(array(), $changed, 'All remaining platform and editor files match frozen baseline');
    });
}

testCase('Group validation preserves supported values and missing required fields', function () use ($sandbox, $snapshot) {
  list($framework, $core) = packageCore($sandbox, 'group-contract', $snapshot);
  $validator = new H5PContentValidator($framework, $core);
  $semantics = (object) array('fields' => array(
    (object) array('name' => 'label', 'type' => 'text'),
    (object) array('name' => 'flag', 'type' => 'boolean')));
  $group = (object) array('label' => 'PapiJo français', 'flag' => FALSE);
  $validator->validateGroup($group, $semantics);
  same(array('label' => 'PapiJo français', 'flag' => FALSE), (array) $group, 'Known values preserved');
  $empty = new stdClass();
  $validator->validateGroup($empty, $semantics);
  same(array(), (array) $empty, 'Port-only required-field loop did not reject missing fields');
  same(array(), $framework->errors, 'No new required-field errors');
});

testCase('Portable caller-supplied export selection and integrity', function () use ($sandbox, $snapshot, $baselineMode) {
  $directory = $sandbox->directory('portable-exports');
  $package = $directory . '/fixture.h5p';
  syntheticPackage($package, array('majorVersion' => 1, 'minorVersion' => $baselineMode ? 24 : 28));
  file_put_contents($directory . '/ignored.txt', 'Not a package');
  $hash = hash_file('sha256', $package);
  same(array(array('file' => 'fixture.h5p', 'sha256' => NULL)),
    h5p_test_export_fixtures($directory), 'Discover supplied packages without a site inventory');
  $records = array(array('file' => 'fixture.h5p', 'sha256' => $hash));
  $manifest = $directory . '/manifest.json';
  file_put_contents($manifest, json_encode($records));
  same($records, h5p_test_export_fixtures($directory, $manifest), 'External hash manifest preserved');
  foreach (array(NULL, array(), array(array('file' => '../fixture.h5p', 'sha256' => $hash)),
      array(array('file' => 'fixture.h5p', 'sha256' => 'bad')), array($records[0], $records[0]),
      array(array('file' => 'missing.h5p', 'sha256' => $hash))) as $bad) {
    file_put_contents($manifest, json_encode($bad));
    $rejected = FALSE;
    try { h5p_test_export_fixtures($directory, $manifest); }
    catch (RuntimeException $error) { $rejected = TRUE; }
    check($rejected, 'Reject malformed, duplicate, missing or escaping fixture entries');
  }
  list($framework, $core) = packageCore($sandbox, 'portable-export-validation', $snapshot);
  copy($package, $framework->uploadPath);
  check(validatePackage($framework, $core), 'Supplied synthetic export validates');
  same(array('text' => 'Preserve me', 'zero' => 0, 'flag' => FALSE),
    (array) $core->contentJsonData, 'Export parameter values retained');
  same($hash, hash_file('sha256', $package), 'Read-only export hash retained');
});

if ($fixtureDirectory !== NULL) {
  $exportFixtures = h5p_test_export_fixtures($fixtureDirectory, $fixtureManifest);
  if ($skipFixtureBaseline) {
    echo 'NOTE: External manifest hash comparison explicitly skipped; per-run source integrity remains checked.' . PHP_EOL;
  }
  else if ($fixtureManifest !== NULL) {
    testCase('Export integrity against the supplied portable manifest',
    function () use ($exportFixtures, $fixtureDirectory) {
      foreach ($exportFixtures as $fixture) {
        same(strtolower($fixture['sha256']), hash_file('sha256', $fixtureDirectory . '/' . $fixture['file']),
          'Supplied export hash: ' . $fixture['file']);
      }
    });
  }
  testCase('Supplied export packages validate without parameter changes',
    function () use ($sandbox, $snapshot, $exportFixtures, $fixtureDirectory) {
      foreach ($exportFixtures as $index => $fixture) {
        $path = $fixtureDirectory . '/' . $fixture['file'];
        $beforeHash = hash_file('sha256', $path);
        $zip = new ZipArchive();
        if ($zip->open($path) !== TRUE) { throw new RuntimeException('Cannot read fixture.'); }
        for ($entry = 0; $entry < $zip->numFiles; $entry++) {
          $name = str_replace('\\', '/', $zip->getNameIndex($entry));
          if (preg_match('~(^/|^[A-Za-z]:|(^|/)\.\.(/|$))~', $name)) {
            throw new RuntimeException('Unsafe fixture path; refused before extraction.');
          }
        }
        $parameters = json_decode($zip->getFromName('content/content.json'), TRUE);
        $header = json_decode($zip->getFromName('h5p.json'), TRUE);
        $zip->close();
        list($framework, $core) = packageCore($sandbox, 'export-' . $index, $snapshot);
        copy($path, $framework->uploadPath);
        $valid = validatePackage($framework, $core);
        check($valid, $fixture['file'] . ': ' . json_encode($framework->errors));
        if ($valid) {
          same($parameters, json_decode(json_encode($core->contentJsonData), TRUE), 'Fixture parameters unchanged');
          same($header['mainLibrary'], $core->mainJsonData['mainLibrary'], 'Fixture main library preserved');
          same($header['language'], $core->mainJsonData['language'], 'Fixture language preserved');
        }
        same($beforeHash, hash_file('sha256', $path), 'Original fixture untouched');
      }
    });
}
$sandbox->close();
foreach ($failures as $failure) { fwrite(STDERR, '  ' . $failure . PHP_EOL); }
echo $assertions . ' assertions; ' . count($failures) . ' failure(s).' . PHP_EOL;
exit(count($failures) ? 1 : 0);
