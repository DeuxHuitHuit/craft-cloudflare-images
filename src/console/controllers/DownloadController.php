<?php

namespace deuxhuithuit\cfimages\console\controllers;

use craft\console\Controller;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\models\Volume;
use deuxhuithuit\cfimages\Filename;
use deuxhuithuit\cfimages\fs\CloudflareImagesFs;
use deuxhuithuit\cfimages\Plugin;
use yii\console\ExitCode;

class DownloadController extends Controller
{
    /**
     * @var string|null Custom directory to save downloaded images into.
     */
    public ?string $destination = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'destination';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function optionAliases(): array
    {
        return [
            'd' => 'destination',
        ];
    }

    /**
     * Download all images from a Cloudflare Images volume.
     *
     * @param string $volume The volume handle, UID, or name.
     */
    public function actionIndex(string $volume): int
    {
        $resolvedVolume = $this->resolveVolume($volume);
        if ($resolvedVolume === null) {
            $this->stderr("Volume not found: {$volume}\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$resolvedVolume->getFs() instanceof CloudflareImagesFs) {
            $this->stderr("Volume \"{$resolvedVolume->handle}\" is not a Cloudflare Images volume.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $destination = $this->resolveDestination($resolvedVolume);
        FileHelper::createDirectory($destination);

        $plugin = Plugin::getInstance();
        $accountHash = $plugin->getSettings()->getAccountHash();
        /** @var CloudflareImagesFs $fs */
        $fs = $resolvedVolume->getFs();

        $assetQuery = Asset::find()
            ->volumeId($resolvedVolume->id)
            ->status(null);

        $total = (clone $assetQuery)->count();
        if ($total === 0) {
            $this->stdout("No assets found in volume \"{$resolvedVolume->handle}\".\n");

            return ExitCode::OK;
        }

        $this->stdout("Downloading {$total} asset(s) from volume \"{$resolvedVolume->handle}\" to {$destination}\n");

        $downloaded = 0;
        $failed = 0;

        foreach ($assetQuery->each() as $asset) {
            /** @var Asset $asset */
            try {
                $originalFilename = ltrim(
                    Filename::toParts($asset->filename, $accountHash)['filename'],
                    '/\\'
                );
                $folderPath = trim($asset->getFolder()->path, '/\\');
                $relativePath = $folderPath === ''
                    ? $originalFilename
                    : $folderPath . '/' . $originalFilename;
                $localPath = FileHelper::normalizePath($destination . DIRECTORY_SEPARATOR . $relativePath);

                FileHelper::createDirectory(\dirname($localPath));

                $stream = $fs->getFileStream($asset->getPath());
                $handle = \fopen($localPath, 'wb');
                if ($handle === false) {
                    throw new \RuntimeException("Unable to open {$localPath} for writing.");
                }

                \stream_copy_to_stream($stream, $handle);
                \fclose($handle);
                if (\is_resource($stream)) {
                    \fclose($stream);
                }

                $downloaded++;
                $this->stdout("  [{$downloaded}/{$total}] {$relativePath}\n");
            } catch (\Throwable $e) {
                $failed++;
                $this->stderr("  Failed to download {$asset->filename}: {$e->getMessage()}\n");
            }
        }

        $this->stdout("Downloaded {$downloaded} asset(s)");
        if ($failed > 0) {
            $this->stderr(", {$failed} failed");
        }
        $this->stdout(".\n");

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    private function resolveVolume(string $identifier): ?Volume
    {
        $volumes = \Craft::$app->getVolumes();

        $volume = $volumes->getVolumeByHandle($identifier);
        if ($volume !== null) {
            return $volume;
        }

        $volume = $volumes->getVolumeByUid($identifier);
        if ($volume !== null) {
            return $volume;
        }

        foreach ($volumes->getAllVolumes() as $candidate) {
            if (\strcasecmp($candidate->name, $identifier) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveDestination(Volume $volume): string
    {
        if ($this->destination !== null && $this->destination !== '') {
            return FileHelper::normalizePath(\Craft::getAlias($this->destination, false) ?: $this->destination);
        }

        return FileHelper::normalizePath(
            \Craft::getAlias('@storage') . '/cloudflare-images-' . $volume->handle
        );
    }
}
