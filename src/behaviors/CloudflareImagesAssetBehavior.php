<?php

namespace deuxhuithuit\cfimages\behaviors;

use deuxhuithuit\cfimages\Filename;
use yii\base\Behavior;

class CloudflareImagesAssetBehavior extends Behavior
{
    /**
     * @return string
     */
    public function cloudflareImagesUrl(): string
    {
        try {
            $parts = Filename::toParts($this->owner->filename);
            return "https://imagedelivery.net/{$parts['account']}/{$parts['id']}";
        } catch (\Exception $e) {
            return '';
        }
    }
}
