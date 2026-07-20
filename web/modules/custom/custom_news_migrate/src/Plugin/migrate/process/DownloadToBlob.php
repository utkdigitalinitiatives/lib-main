<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\process;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Downloads a remote file and writes it to the destination stream via
 * FileSystem::saveData() (temp file + move()) -- the path normal uploads use --
 * avoiding the az_blob_fs feof() crash that core file_copy hits when Guzzle
 * streams directly into an azblob:// sink.
 *
 * Usage (replaces the `file_copy` step in the news_file_* migrations):
 *   uri:
 *     plugin: download_to_blob
 *     source:
 *       - '@source_full_path'   # remote http(s) URL to fetch
 *       - '@destination_uri'    # destination uri, e.g. azblob://path/file.jpg
 *
 * Verified on prod (Drupal 11.3.13, az_blob_fs) 2026-07-15: files land in
 * azblob:// with real bytes, readable back, zero errors.
 */
#[MigrateProcess(id: 'download_to_blob')]
class DownloadToBlob extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value) || count($value) < 2) {
      throw new MigrateException('download_to_blob expects a [source_url, destination_uri] array.');
    }
    [$source_url, $destination_uri] = array_values($value);
    if (empty($source_url) || empty($destination_uri)) {
      throw new MigrateSkipRowException('download_to_blob: empty source URL or destination URI.');
    }

    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');

    // Fetch bytes into memory (NOT streamed into the blob sink -> avoids the bug).
    try {
      $data = (string) \Drupal::httpClient()->get($source_url)->getBody();
    }
    catch (\Throwable $e) {
      throw new MigrateSkipRowException(sprintf('download_to_blob: fetch failed for %s (%s)', $source_url, $e->getMessage()));
    }

    // Best-effort ensure the destination directory exists (no-op for flat blob).
    $fs->prepareDirectory($fs->dirname($destination_uri), FileSystemInterface::CREATE_DIRECTORY);

    // Write via saveData() = temp file + move(), the working upload path.
    try {
      return $fs->saveData($data, $destination_uri, FileExists::Replace);
    }
    catch (\Throwable $e) {
      throw new MigrateException(sprintf('download_to_blob: saveData to %s failed (%s)', $destination_uri, $e->getMessage()));
    }
  }

}
