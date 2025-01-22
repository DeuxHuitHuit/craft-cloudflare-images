<?php

namespace deuxhuithuit\cfimages\fs;

use craft\base\Fs;
use craft\elements\Asset;
use craft\errors\FsException;
use craft\helpers\UrlHelper;
use craft\models\FsListing;
use deuxhuithuit\cfimages\client\CloudflareImagesClient;
use deuxhuithuit\cfimages\Filename;

class CloudflareImagesFs extends Fs
{
    /**
     * @inheritdoc
     */
    protected static bool $showHasUrlSetting = false;

    /**
     * @inheritdoc
     */
    protected static bool $showUrlSetting = false;

    /** @var \deuxhuithuit\cfimages\models\Settings */
    private $settings;

    /** @var CloudflareImagesClient */
    private $client;

    private array $recentFiles = [];

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        /** @var \deuxhuithuit\deuxhuithuit\cfimages\Plugin  */
        $plugin = \deuxhuithuit\cfimages\Plugin::getInstance();
        $this->settings = $plugin->settings;
        $this->client = new CloudflareImagesClient($this->settings);
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return \Craft::t('cloudflare-images', 'Cloudflare Images');
    }

    /**
     * @inheritdoc
     * Note: This is a hack to make the 'view' button work in the asset manager.
     */
    public function getRootUrl(): ?string
    {
        return UrlHelper::baseCpUrl() . '/' . UrlHelper::prependCpTrigger('') . '/actions/cloudflare-images/view?file=';
    }

    /**
     * @inheritdoc
     */
    public function getFileList(string $directory = '', bool $recursive = true): \Generator
    {
        if ($directory === '') {
            $directory = '.';
        }
        try {
            $continueToken = null;
            do {
                $list = $this->client->listImages(100, $continueToken);
                $images = $list['images'];
                $continueToken = $list['continuation_token'];

                foreach ($images as $image) {
                    $dirname = isset($image['meta']['folder']) ? $image['meta']['folder'] : '';
                    $filename = isset($image['meta']['path']) ? basename($image['meta']['path']) : $image['filename'];

                    if ($recursive) {
                        if ($directory != '.' && !\str_starts_with("$dirname/", $directory)) {
                            continue;
                        }
                    } else {
                        if ($dirname !== $directory && "$dirname/" !== $directory) {
                            continue;
                        }
                    }

                    yield new FsListing([
                        'basename' => Filename::fromParts($this->settings->getAccountHash(), $image['id'], $filename),
                        'dirname' => $dirname,
                        'type' => 'file',
                        'fileSize' => isset($image['meta']['size']) ? $image['meta']['size'] : 0,
                        'dateModified' => isset($image['meta']['updated'])
                            ? $image['meta']['updated']
                            : (isset($image['meta']['created']) ? $image['meta']['created'] : 0),
                    ]);
                }
            } while ($continueToken);
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function getFileSize(string $uri): int
    {
        try {
            $imageId = Filename::toId($uri);
            $image = $this->client->getImage($imageId);
            return $image['meta']['size'];
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function getDateModified(string $uri): int
    {
        try {
            $imageId = Filename::toId($uri);
            $image = $this->client->getImage($imageId);
            return isset($image['meta']['updated']) ? $image['meta']['updated'] : $image['meta']['created'];
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function read(string $path): string
    {
        try {
            return $this->client->getImageStream($path)->getContents();
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function write(string $path, string $contents, array $config = []): void
    {
        $this->writeFileFromStream($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        try {
            $result = $this->client->uploadImageStream($stream, $path, $config);
            $this->recentFiles[$path] = $result['id'];
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * This function will save the id and hash of the file into the asset's filename.
     *
     * @param Asset $asset
     */
    public function saveAsset(Asset $asset): void
    {
        // Make sure we have a recent id for this path
        $recentId = $this->recentFiles[$asset->getPath()] ?? null;
        if (!$recentId) {
            return;
        }
        // Compute the proper filename, containing the id and hash
        $properFilename = Filename::fromParts(
            $this->settings->getAccountHash(),
            $recentId,
            Filename::cleanParts($asset->filename)
        );
        // If the filename is already correct, we are done.
        // Somehow, we need to break the loop here, otherwise the asset will be saved again and again.
        if ($properFilename === $asset->filename) {
            return;
        }

        // Update in-memory values
        $asset->filename = $properFilename;
        
        // We need to bypass Craft's logic to rename the asset because it will try
        // to manipulate the FileSystem's representation of the asset, which we don't want.
        $result = \Craft::$app->getDb()
            ->createCommand()
            ->update('{{%assets}}', ['filename' => $properFilename], ['id' => $asset->id])
            ->execute();
 
        if (!$result) {
            throw new \Exception('Failed to rename Cloudflare Images asset.');
        }
 
        // Then we need to update the asset's indexes to make Craft happy
        \Craft::$app->getSearch()->indexElementAttributes($asset, ['filename']);
    }

    /**
     * @inheritdoc
     */
    public function fileExists(string $path): bool
    {
        // There are no way for us to know if an image exists without its id
        if (!$path) {
            return false;
        }

        // Check if the file exists in the cloudflare images
        try {
            $imageId = Filename::toId(\basename($path));
            if (!$imageId) {
                throw new \Exception('Failed to parse filename');
            }
            $image = $this->client->getImage($imageId);
            if (isset($image['id'])) {
                // The file exists, so let's make sure it is still in the same folder as the one we know about.
                // If not, it means the file has been moved to a different folder
                // and we need to tell Craft the new path is available.
                $dirname = isset($image['meta']['folder']) ? $image['meta']['folder'] : '';

                return $dirname === \dirname($path);
            }
        } catch (\Exception $e) {
            // ignore, must not exist...
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function deleteFile(string $path): void
    {
        $imageId = null;
        try {
            $imageId = Filename::toId($path);
            if (!$imageId) {
                // Found an empty filename, let Craft handle it.
                return;
            }
        } catch (\Exception $e) {
            // Can not parse the filename, let Craft handle it.
            return;
        }
        try {
            $this->client->deleteImage($imageId);
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function renameFile(string $path, string $newPath): void
    {
        try {
            $imageId = Filename::toId($path);
            $image = $this->client->getImage($imageId);
            // Make sure we get rid of any parts in the new paths.
            // This happens when the file is moved to another folder in the asset manager.
            $newCleanedPath = \dirname($newPath) . '/' . Filename::cleanParts(\basename($newPath));
            $this->client->moveImage($newCleanedPath, $imageId, $image['meta']);
            $this->recentFiles[$newCleanedPath] = $imageId;
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function copyFile(string $path, string $newPath): void
    {
        throw new FsException('Cloudflare Images does not support copying files');
    }

    /**
     * @inheritdoc
     */
    public function getFileStream(string $uriPath)
    {
        try {
            return $this->client->getImageStream(Filename::toId($uriPath))->detach();
        } catch (\Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    // #region Directory operations
    /**
     * @inheritdoc
     */
    public function directoryExists(string $path): bool
    {
        // Left empty: Cloudflare do not support directories, let Craft handle it.
        return true;
    }

    /**
     * @inheritdoc
     */
    public function createDirectory(string $path, array $config = []): void
    {
        // Left empty: Cloudflare do not support directories, let Craft handle it.
    }

    /**
     * @inheritdoc
     */
    public function deleteDirectory(string $path): void
    {
        // Left empty: Cloudflare do not support directories, let Craft handle it.
    }

    public function renameDirectory(string $path, string $newName): void
    {
        // Left empty: Cloudflare do not support directories, let Craft handle it.
    }
    // #endregion
}
