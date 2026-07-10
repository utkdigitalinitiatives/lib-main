<?php

declare(strict_types=1);

namespace Drupal\custom_news_migrate;

use Drupal\Core\Site\Settings;
use Drupal\migrate\MigrateException;

/**
 * Utilities for resolving source file locations during migration.
 */
final class MigratePathHelper
{

    /**
     * Converts a public:// URI to a full source path/URL from settings.php.
     *
     * @param string $uri
     *   The source file URI, typically public://path/to/file.ext.
     *
     * @return string
     *   Absolute source path or URL for use with file_copy.
     *
     * @throws \Drupal\migrate\MigrateException
     *   Thrown when migrate_file_public_path is not configured.
     */
    public static function publicUriToSourcePath(string $uri): string
    {
        if (!str_starts_with($uri, 'public://')) {
            return $uri;
        }

        $sourcePublicPath = Settings::get('migrate_file_public_path');
        if (empty($sourcePublicPath) || !is_string($sourcePublicPath)) {
            throw new MigrateException('Missing required $settings["migrate_file_public_path"] for news file migrations.');
        }

        $relativePath = ltrim(substr($uri, strlen('public://')), '/');

        // Preserve query parameters (for example Azure SAS tokens) after appending
        // the relative file path.
        if (str_contains($sourcePublicPath, '?')) {
            [$basePath, $query] = explode('?', $sourcePublicPath, 2);
            return rtrim($basePath, '/') . '/' . $relativePath . '?' . $query;
        }

        return rtrim($sourcePublicPath, '/') . '/' . $relativePath;
    }
}
