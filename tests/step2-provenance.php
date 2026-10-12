<?php
/**
 * Undo only the exact reviewed adapter spans before frozen-byte comparisons.
 * No path is generally excluded from existing provenance checks.
 */
function h5p_step2_baseline_bytes($path, $bytes) {
  $bytes = h5p_schema_update_hooks_baseline_bytes($path, $bytes);
  $bytes = h5p_content_metadata_baseline_bytes($path, $bytes);
  $bytes = h5p_metadata_null_baseline_bytes($path, $bytes);
  $bytes = h5p_step3_baseline_bytes($path, $bytes);
  static $patches;
  if ($patches === NULL) {
    $register = dirname(__DIR__) . '/docs/h5p-api-1.28/phase-3b-step2-patches.json';
    $patches = json_decode(file_get_contents($register), TRUE)['files'];
  }
  if (!isset($patches[$path])) {
    return $bytes;
  }
  $patch = $patches[$path];
  if (hash('sha256', $bytes) !== $patch['afterSha256']) {
    throw new RuntimeException('Unreviewed adapter bytes: ' . $path);
  }
  foreach (array_reverse($patch['replacements']) as $replacement) {
    if (substr_count($bytes, $replacement['after']) !== 1) {
      throw new RuntimeException('Adapter patch span is not unique: ' . $path);
    }
    $bytes = str_replace($replacement['after'], $replacement['before'], $bytes);
  }
  if (hash('sha256', $bytes) !== $patch['beforeSha256']) {
    throw new RuntimeException('Adapter baseline mismatch: ' . $path);
  }
  return $bytes;
}

/** Reverse only the six obsolete, empty Drupal 6 update declarations. */
function h5p_schema_update_hooks_baseline_bytes($path, $bytes) {
  if ($path !== 'h5p.install') { return $bytes; }
  $register = dirname(__DIR__) . '/docs/h5p-api-1.28/schema-update-hooks-patches.json';
  $patch = json_decode(file_get_contents($register), TRUE)['files'][$path];
  if (hash('sha256', $bytes) !== $patch['afterSha256']) {
    throw new RuntimeException('Unreviewed installation bytes: ' . $path);
  }
  foreach (array_reverse($patch['replacements']) as $replacement) {
    if (substr_count($bytes, $replacement['after']) !== 1) {
      throw new RuntimeException('Non-unique update-hook deletion: ' . $path);
    }
    $bytes = str_replace($replacement['after'], $replacement['before'], $bytes);
  }
  if (hash('sha256', $bytes) !== $patch['beforeSha256']) {
    throw new RuntimeException('Installation baseline mismatch: ' . $path);
  }
  return $bytes;
}

/**
 * Exact Step 3 adaptations; pinned vendor files are verified separately.
 */
function h5p_step3_baseline_bytes($path, $bytes) {
  static $patches;
  if ($patches === NULL) {
    $register = dirname(__DIR__) . '/docs/h5p-api-1.28/phase-3b-step3-patches.json';
    $patches = json_decode(file_get_contents($register), TRUE)['files'];
  }
  if (!isset($patches[$path])) { return $bytes; }
  $patch = $patches[$path];
  if (hash('sha256', $bytes) !== $patch['afterSha256']) {
    throw new RuntimeException('Unreviewed Step 3 adapter: ' . $path);
  }
  foreach (array_reverse($patch['replacements']) as $replacement) {
    if (substr_count($bytes, $replacement['after']) !== 1) {
      throw new RuntimeException('Non-unique Step 3 span: ' . $path);
    }
    $bytes = str_replace($replacement['after'], $replacement['before'], $bytes);
  }
  if (hash('sha256', $bytes) !== $patch['beforeSha256']) {
    throw new RuntimeException('Step 3 baseline mismatch: ' . $path);
  }
  return $bytes;
}

/**
 * Reverse only the recorded optional-metadata guards before historical checks.
 */
function h5p_metadata_null_baseline_bytes($path, $bytes) {
  static $patches;
  if ($patches === NULL) {
    $register = dirname(__DIR__) . '/docs/h5p-api-1.28/metadata-null-patches.json';
    $patches = json_decode(file_get_contents($register), TRUE)['files'];
  }
  if (!isset($patches[$path])) { return $bytes; }
  $patch = $patches[$path];
  if (hash('sha256', $bytes) !== $patch['afterSha256']) {
    throw new RuntimeException('Unreviewed metadata adapter bytes: ' . $path);
  }
  foreach (array_reverse($patch['replacements']) as $replacement) {
    if (substr_count($bytes, $replacement['after']) !== 1) {
      throw new RuntimeException('Non-unique metadata guard: ' . $path);
    }
    $bytes = str_replace($replacement['after'], $replacement['before'], $bytes);
  }
  if (hash('sha256', $bytes) !== $patch['beforeSha256']) {
    throw new RuntimeException('Metadata adapter baseline mismatch: ' . $path);
  }
  return $bytes;
}

/** Reverse only exact reviewed submission/import metadata adapter spans. */
function h5p_content_metadata_baseline_bytes($path, $bytes) {
  $bytes = h5p_node_cache_baseline_bytes($path, $bytes);
  static $patches;
  if ($patches === NULL) {
    $register = dirname(__DIR__) . '/docs/h5p-api-1.28/content-metadata-patches.json';
    $patches = json_decode(file_get_contents($register), TRUE)['files'];
  }
  if (!isset($patches[$path])) { return $bytes; }
  $patch = $patches[$path];
  if (hash('sha256', $bytes) !== $patch['afterSha256']) { throw new RuntimeException('Unreviewed content metadata adapter: ' . $path); }
  foreach (array_reverse($patch['replacements']) as $replacement) {
    if (substr_count($bytes, $replacement['after']) !== 1) { throw new RuntimeException('Non-unique content metadata span: ' . $path); }
    $bytes = str_replace($replacement['after'], $replacement['before'], $bytes);
  }
  if (hash('sha256', $bytes) !== $patch['beforeSha256']) { throw new RuntimeException('Content metadata baseline mismatch: ' . $path); }
  return $bytes;
}

/** Reverse only the exact targeted node-cache adapter patch. */
function h5p_node_cache_baseline_bytes($path, $bytes) {
  static $patches;
  if ($patches === NULL) {
    $register = dirname(__DIR__) . '/docs/h5p-api-1.28/node-cache-patches.json';
    $patches = json_decode(file_get_contents($register), TRUE)['files'];
  }
  if (!isset($patches[$path])) { return $bytes; }
  $patch = $patches[$path];
  if (hash('sha256', $bytes) !== $patch['afterSha256']) { throw new RuntimeException('Unreviewed node-cache adapter: ' . $path); }
  foreach (array_reverse($patch['replacements']) as $replacement) {
    if (substr_count($bytes, $replacement['after']) !== 1) { throw new RuntimeException('Non-unique node-cache span: ' . $path); }
    $bytes = str_replace($replacement['after'], $replacement['before'], $bytes);
  }
  if (hash('sha256', $bytes) !== $patch['beforeSha256']) { throw new RuntimeException('Node-cache baseline mismatch: ' . $path); }
  return $bytes;
}
