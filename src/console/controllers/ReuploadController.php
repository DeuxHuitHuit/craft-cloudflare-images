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

class ReuploadController extends Controller
{
    /**
     * @var string|null Custom directory to read images from.
     */
    public ?string $source = null;

    /**
     * @var bool Whether to validate files without uploading or updating the database.
     */
    public bool $dryRun = false;

    /**
     * @var bool Whether to re-upload assets already on the current Cloudflare account.
     */
    public bool $force = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'source';
        $options[] = 'dryRun';
        $options[] = 'force';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function optionAliases(): array
    {
        return [
            's' => 'source',
            'f' => 'force',
        ];
    }

    /**
     * Re-upload local images to a Cloudflare Images volume and update Craft filenames.
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

        $source = $this->resolveSource($resolvedVolume);
        if (!is_dir($source)) {
            $this->stderr("Source directory not found: {$source}\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $plugin = Plugin::getInstance();
        $accountHash = $plugin->getSettings()->getAccountHash();
        $client = $this->dryRun ? null : $plugin->client();

        $assetQuery = Asset::find()
            ->volumeId($resolvedVolume->id)
            ->status(null);

        $total = (clone $assetQuery)->count();
        if ($total === 0) {
            $this->stdout("No assets found in volume \"{$resolvedVolume->handle}\".\n");

            return ExitCode::OK;
        }

        $mode = $this->dryRun ? 'Validating' : 'Reuploading';
        $this->stdout("{$mode} {$total} asset(s) from {$source} to volume \"{$resolvedVolume->handle}\"");
        if ($this->dryRun) {
            $this->stdout(' (dry-run)');
        }
        $this->stdout("\n");

        $processed = 0;
        $failed = 0;

        foreach ($assetQuery->each() as $asset) {
            /** @var Asset $asset */
            try {
                $relativePath = $this->buildRelativePath($asset);

                if (!$this->force && $this->isAlreadyOnCurrentAccount($asset, $accountHash)) {
                    $processed++;
                    $this->stdout("  [{$processed}/{$total}] {$relativePath} (skipped, already on current account)\n");

                    continue;
                }

                $localPath = FileHelper::normalizePath($source . DIRECTORY_SEPARATOR . $relativePath);

                if (!is_file($localPath)) {
                    throw new \RuntimeException("File not found: {$localPath}");
                }

                $stream = \fopen($localPath, 'rb');
                if ($stream === false) {
                    throw new \RuntimeException("Unable to open {$localPath} for reading.");
                }

                try {
                    if (!$this->dryRun) {
                        $result = $client->uploadImageStream($stream, $relativePath, [
                            'mimetype' => $asset->getMimeType(),
                        ]);
                        $this->updateAssetFilename($asset, $result['id'], $accountHash);
                    }
                } finally {
                    \fclose($stream);
                }

                $processed++;
                $this->stdout("  [{$processed}/{$total}] {$relativePath}\n");
            } catch (\Throwable $e) {
                $failed++;
                $action = $this->dryRun ? 'validate' : 'reupload';
                $this->stderr("  Failed to {$action} {$asset->filename}: {$e->getMessage()}\n");
            }
        }

        $summary = $this->dryRun ? 'Validated' : 'Reuploaded';
        $this->stdout("{$summary} {$processed} asset(s)");
        if ($failed > 0) {
            $this->stderr(", {$failed} failed");
        }
        $this->stdout(".\n");

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    private function buildRelativePath(Asset $asset): string
    {
        $originalFilename = ltrim(
            Filename::toParts($asset->filename)['filename'],
            '/\\'
        );
        $folderPath = trim($asset->getFolder()->path, '/\\');

        return $folderPath === ''
            ? $originalFilename
            : $folderPath . '/' . $originalFilename;
    }

    private function isAlreadyOnCurrentAccount(Asset $asset, string $accountHash): bool
    {
        try {
            return Filename::toParts($asset->filename)['account'] === $accountHash;
        } catch (\Throwable) {
            return false;
        }
    }

    private function updateAssetFilename(Asset $asset, string $imageId, string $accountHash): void
    {
        $properFilename = Filename::fromParts(
            $accountHash,
            $imageId,
            Filename::cleanParts($asset->filename)
        );

        if ($properFilename === $asset->filename) {
            return;
        }

        $asset->filename = $properFilename;

        $result = \Craft::$app->getDb()
            ->createCommand()
            ->update('{{%assets}}', ['filename' => $properFilename], ['id' => $asset->id])
            ->execute();

        if (!$result) {
            throw new \RuntimeException('Failed to update Cloudflare Images asset filename.');
        }

        \Craft::$app->getSearch()->indexElementAttributes($asset, ['filename']);
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

    private function resolveSource(Volume $volume): string
    {
        if ($this->source !== null && $this->source !== '') {
            return FileHelper::normalizePath(\Craft::getAlias($this->source, false) ?: $this->source);
        }

        return FileHelper::normalizePath(
            \Craft::getAlias('@storage') . '/cloudflare-images-' . $volume->handle
        );
    }
}
