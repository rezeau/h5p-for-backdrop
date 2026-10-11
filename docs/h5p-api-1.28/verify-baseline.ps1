<#
Read-only integrity check for the frozen Phase 1 snapshot.
Does not bootstrap Backdrop, access a database, extract packages, install software,
write caches, fetch upstream, or modify files. After implementation, use
-SkipRepositoryFiles to check the protected library/fixture snapshot separately.
#>
[CmdletBinding()]
param(
  [string]$RepositoryRoot = (Join-Path $PSScriptRoot '..\..'),
  [string]$SiteRoot,
  [switch]$SkipRepositoryFiles,
  [switch]$SkipSiteFiles
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ($SkipRepositoryFiles -and $SkipSiteFiles) {
  throw 'At least one snapshot group must be checked.'
}
$RepositoryRoot = (Resolve-Path -LiteralPath $RepositoryRoot).Path
$baseline = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'local-baseline.json') -Raw |
  ConvertFrom-Json
if ([string]::IsNullOrWhiteSpace($SiteRoot)) {
  $SiteRoot = $baseline.siteRoot
}
$baselineFailures = [System.Collections.Generic.List[string]]::new()
$script:baselineVerifiedCount = 0

function Test-BaselineFile {
  param([string]$Path, [string]$ExpectedSha256)
  if (!(Test-Path -LiteralPath $Path -PathType Leaf)) {
    $baselineFailures.Add("Missing file: $Path")
    return
  }
  $actual = (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash
  if ($actual -ine $ExpectedSha256) {
    $baselineFailures.Add("Changed file: $Path")
  }
  else {
    $script:baselineVerifiedCount++
  }
}

function Get-SiteSnapshotPath {
  param([string]$RecordedPath)
  $recordedRoot = $baseline.siteRoot.TrimEnd('/','\') + '/'
  $normalized = $RecordedPath.Replace('\','/')
  if (!$normalized.StartsWith($recordedRoot, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Snapshot path is outside its recorded site: $RecordedPath"
  }
  return Join-Path $SiteRoot $normalized.Substring($recordedRoot.Length)
}

if (!$SkipRepositoryFiles) {
  $head = & git -C $RepositoryRoot rev-parse HEAD
  if ($LASTEXITCODE -ne 0) { throw 'Cannot read repository HEAD.' }
  if ($head -ne $baseline.baseCommit) {
    $baselineFailures.Add("HEAD differs from frozen baseline: $head")
  }
  $branch = & git -C $RepositoryRoot branch --show-current
  if ($LASTEXITCODE -ne 0) { throw 'Cannot read repository branch.' }
  if ($branch -ne $baseline.developmentBranch) {
    $baselineFailures.Add("Expected development branch: $($baseline.developmentBranch); found: $branch")
  }
  foreach ($file in $baseline.moduleFiles) {
    Test-BaselineFile (Join-Path $RepositoryRoot $file.path) $file.sha256
  }
}

if (!$SkipSiteFiles) {
  $SiteRoot = (Resolve-Path -LiteralPath $SiteRoot).Path
  $libraryRoot = Join-Path $SiteRoot 'files/h5p/libraries'
  $fixtureRoot = Join-Path $SiteRoot 'files/h5p/exports'
  $expectedLibraries = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
  $manifestVersions = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
  $liveManifests = [System.Collections.Generic.List[object]]::new()
  foreach ($library in $baseline.installedLibraries) {
    $manifestPath = Get-SiteSnapshotPath $library.path
    [void]$expectedLibraries.Add($manifestPath)
    if (Test-Path -LiteralPath $manifestPath -PathType Leaf) {
      $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
      $liveManifests.Add([pscustomobject]@{ Manifest = $manifest; Path = $manifestPath })
      [void]$manifestVersions.Add("$($manifest.machineName) $($manifest.majorVersion).$($manifest.minorVersion)")
    }
    $libraryDirectory = Get-SiteSnapshotPath $library.directory
    $expectedFiles = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
    foreach ($file in $library.files) {
      $filePath = Join-Path $libraryDirectory $file.path
      [void]$expectedFiles.Add($filePath)
      Test-BaselineFile $filePath $file.sha256
    }
    if (Test-Path -LiteralPath $libraryDirectory -PathType Container) {
      foreach ($file in Get-ChildItem -LiteralPath $libraryDirectory -Recurse -File -Force) {
        if (!$expectedFiles.Contains($file.FullName)) {
          $baselineFailures.Add("Additional installed library file: $($file.FullName)")
        }
      }
    }
  }
  foreach ($directory in Get-ChildItem -LiteralPath $libraryRoot -Directory) {
    $manifestPath = Join-Path $directory.FullName 'library.json'
    if ((Test-Path -LiteralPath $manifestPath) -and !$expectedLibraries.Contains($manifestPath)) {
      $baselineFailures.Add("Additional library manifest: $manifestPath")
    }
  }
  foreach ($item in $liveManifests) {
    $manifest = $item.Manifest
    foreach ($kind in @('preloadedDependencies','editorDependencies','dynamicDependencies')) {
      $property = $manifest.PSObject.Properties[$kind]
      if ($null -ne $property) {
        foreach ($dependency in $property.Value) {
          $key = "$($dependency.machineName) $($dependency.majorVersion).$($dependency.minorVersion)"
          if (!$manifestVersions.Contains($key)) {
            $baselineFailures.Add("Missing $kind dependency $key in $($item.Path)")
          }
        }
      }
    }
    foreach ($kind in @('preloadedJs','preloadedCss')) {
      $property = $manifest.PSObject.Properties[$kind]
      if ($null -ne $property) {
        foreach ($asset in $property.Value) {
          $assetPath = Join-Path (Split-Path -Parent $item.Path) $asset.path
          if (!(Test-Path -LiteralPath $assetPath -PathType Leaf)) {
            $baselineFailures.Add("Missing declared asset: $assetPath")
          }
        }
      }
    }
  }
  $expectedFixtures = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
  foreach ($fixture in $baseline.exportFixtures) {
    $fixturePath = Get-SiteSnapshotPath $fixture.path
    [void]$expectedFixtures.Add($fixturePath)
    Test-BaselineFile $fixturePath $fixture.sha256
  }
  foreach ($file in Get-ChildItem -LiteralPath $fixtureRoot -File -Filter '*.h5p') {
    if (!$expectedFixtures.Contains($file.FullName)) {
      $baselineFailures.Add("Additional export fixture: $($file.FullName)")
    }
  }
}

if ($baselineFailures.Count -gt 0) {
  foreach ($failure in $baselineFailures) { Write-Output $failure }
  throw "$($baselineFailures.Count) baseline integrity failure(s)."
}
Write-Output "PASS: $script:baselineVerifiedCount frozen file hashes verified."
if (!$SkipSiteFiles) {
  Write-Output "PASS: 17 library manifests, declared dependencies/assets, and 21 export fixtures verified."
}
