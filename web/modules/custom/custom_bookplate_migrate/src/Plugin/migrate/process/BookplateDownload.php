<?php

declare(strict_types=1);

namespace Drupal\custom_bookplate_migrate\Plugin\migrate\process;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Downloads through saveData(), supporting public and Azure blob storage.
 */
#[MigrateProcess(id: 'bookplate_download')]
final class BookplateDownload extends ProcessPluginBase
{

    /**
     * {@inheritdoc}
     */
    public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
    {
        if (!is_string($value) || !str_starts_with($value, 'public://')) {
            throw new MigrateException('Bookplate downloads require a legacy public:// file URI.');
        }
        $prefix = Settings::get('custom_bookplate_migrate_source_public_prefix', 'https://lib.utk.edu/sites/default/files/');
        $relative = substr($value, strlen('public://'));
        $url = rtrim($prefix, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
        $scheme = \Drupal::config('system.file')->get('default_scheme');
        $directory = $scheme . '://bookplate-migrate/' . $row->getSourceProperty('fid');
        $destination = $directory . '/' . basename($value);
        $file_system = \Drupal::service('file_system');
        $timeout = Settings::get('custom_bookplate_migrate_http_timeout', 300);
        if (!is_numeric($timeout) || !is_finite((float) $timeout) || (float) $timeout <= 0 || (float) $timeout >= 840) {
            throw new MigrateException('Bookplate download timeout must be greater than zero and less than 840 seconds.');
        }
        $timeout = (float) $timeout;

        try {
            $data = (string) \Drupal::httpClient()->get($url, [
                'connect_timeout' => min(10, $timeout),
                'timeout' => $timeout,
            ])->getBody();
            if ($data === '') {
                throw new MigrateException(sprintf('Downloaded bookplate file is empty: %s', $url));
            }
            if (!$file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
                throw new MigrateException(sprintf('Cannot prepare bookplate file directory: %s', $directory));
            }
            // Avoid streaming HTTP directly into azblob:// (see DownloadToBlob).
            return $file_system->saveData($data, $destination, FileExists::Replace);
        } catch (GuzzleException | FileException $e) {
            throw new MigrateException(sprintf('Bookplate download failed for %s: %s', $url, $e->getMessage()), 0, $e);
        }
    }
}
