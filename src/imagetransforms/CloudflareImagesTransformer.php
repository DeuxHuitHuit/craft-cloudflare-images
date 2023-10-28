<?php

namespace deuxhuithuit\cfimages\imagetransforms;

use craft\base\Component;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\models\ImageTransform;

use deuxhuithuit\cfimages\behaviors\CloudflareImagesTransformBehavior;
use deuxhuithuit\cfimages\fs\CloudflareImagesFs;

class CloudflareImagesTransformer extends Component implements ImageTransformerInterface
{
    public function generateFlexibleVariant(ImageTransform|CloudflareImagesTransformBehavior $imageTransform)
    {
        $transform = [];
        if ($imageTransform->width) {
            $transform[] = "width={$imageTransform->width}";
        }
        if ($imageTransform->height) {
            $transform[] = "height={$imageTransform->height}";
        }
        if ($imageTransform->mode) {
            $transform[] = "fit={$imageTransform->mode}";
        }
        if ($imageTransform->position) {
            $transform[] = "gravity={$imageTransform->position}";
        }
        if ($imageTransform->quality) {
            $transform[] = "quality={$imageTransform->quality}";
        }
        if ($imageTransform->gamma) {
            $transform[] = "gamma={$imageTransform->gamma}";
        }
        if ($imageTransform->format) {
            $transform[] = "format={$imageTransform->format}";
        }
        if ($imageTransform->compression) {
            $transform[] = "compression={$imageTransform->compression}";
        }
        if ($imageTransform->rotate) {
            $transform[] = "rotate={$imageTransform->rotate}";
        }
        if ($imageTransform->background) {
            $transform[] = "background={$imageTransform->background}";
        }
        if (empty($transform)) {
            return '';
        }
        return '/' . implode(',', $transform);
    }

    public function getTransformUrl(Asset $asset, ImageTransform|CloudflareImagesTransformBehavior $imageTransform, bool $immediately): string
    {
        $fs = $asset->getVolume()->getFs();
        $isCloudflareImageFs = $fs instanceof CloudflareImagesFs;
        $flexibleVariant = $this->generateFlexibleVariant($imageTransform);

        if (!$isCloudflareImageFs) {
            return "/cdn-cgi/image{$flexibleVariant}/" . $asset->getUrl();
        }

        return $asset->cloudflareImagesUrl() . $flexibleVariant;
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {
        // TODO...
    }
}
