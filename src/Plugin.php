<?php

namespace deuxhuithuit\cfimages;

use craft\elements\Asset;
use craft\events\DefineBehaviorsEvent;
use craft\events\GenerateTransformEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\ElementHelper;
use craft\models\ImageTransform;
use craft\services\Fs;
use craft\services\ImageTransforms;
use craft\web\View;
use deuxhuithuit\cfimages\client\CloudflareImagesClient;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';

    public function __construct($id, $parent = null, array $config = [])
    {
        \Craft::setAlias('@plugin/cloudflare-images', $this->getBasePath());
        \Craft::setAlias('@plugin/cloudflare-images/resources', $this->getBasePath() . DIRECTORY_SEPARATOR . 'resources');
        $this->controllerNamespace = 'deuxhuithuit\cfimages\controllers';

        // Base template directory
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                    $e->roots[$this->id] = $baseDir;
                }
            }
        );

        // Set this as the global instance of this module class
        static::setInstance($this);

        parent::__construct($id, $parent, $config);
    }

    public function init(): void
    {
        \Craft::$app->onInit(function() {
            // Fs
            Event::on(
                Fs::class,
                Fs::EVENT_REGISTER_FILESYSTEM_TYPES,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = \deuxhuithuit\cfimages\fs\CloudflareImagesFs::class;
                }
            );

            // ImageTransforms
            Event::on(
                ImageTransforms::class,
                ImageTransforms::EVENT_REGISTER_IMAGE_TRANSFORMERS,
                function(RegisterComponentTypesEvent $event) {
                    $event->types[] = \deuxhuithuit\cfimages\imagetransforms\CloudflareImagesTransformer::class;
                }
            );

            Event::on(
                ImageTransform::class,
                ImageTransform::EVENT_DEFINE_BEHAVIORS,
                function(DefineBehaviorsEvent $event) {
                    $event->behaviors['cloudflare-images'] = \deuxhuithuit\cfimages\behaviors\CloudflareImagesTransformBehavior::class;
                }
            );

            // Asset
            Event::on(
                Asset::class,
                Asset::EVENT_BEFORE_GENERATE_TRANSFORM,
                function(GenerateTransformEvent $event) {
                    if ($event->asset->getVolume()->getTransformFs() instanceof  \deuxhuithuit\cfimages\fs\CloudflareImagesFs) {
                        $event->transform->setTransformer(
                            \deuxhuithuit\cfimages\imagetransforms\CloudflareImagesTransformer::class
                        );
                    }
                }
            );

            Event::on(
                Asset::class,
                Asset::EVENT_DEFINE_BEHAVIORS,
                function(DefineBehaviorsEvent $event) {
                    $event->behaviors[] = \deuxhuithuit\cfimages\behaviors\CloudflareImagesAssetBehavior::class;
                }
            );

            Event::on(
                Asset::class,
                Asset::EVENT_AFTER_SAVE,
                function(\craft\events\ModelEvent $event) {
                    /** @var \craft\elements\Asset */
                    $asset = $event->sender;
                    if (ElementHelper::isDraftOrRevision($asset)) {
                        return;
                    }
                    if ($asset->getVolume()->getTransformFs() instanceof \deuxhuithuit\cfimages\fs\CloudflareImagesFs) {
                        /** @var \deuxhuithuit\cfimages\fs\CloudflareImagesFs */
                        $fs = $asset->getVolume()->getTransformFs();
                        $fs->saveAsset($asset);
                    }
                }
            );
        });

        parent::init();
    }

    public function client(): CloudflareImagesClient
    {
        return new CloudflareImagesClient($this->settings);
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new \deuxhuithuit\cfimages\models\Settings();
    }

    protected function settingsHtml(): ?string
    {
        return \Craft::$app->getView()->renderTemplate(
            'cloudflare-images/settings',
            ['settings' => $this->getSettings()]
        );
    }
}
