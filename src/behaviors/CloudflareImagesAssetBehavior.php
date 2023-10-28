<?php

namespace deuxhuithuit\cfimages\behaviors;

use deuxhuithuit\cfimages\Filename;
use yii\base\Behavior;

class CloudflareImagesAssetBehavior extends Behavior
{
    /**
     * @var the id of the file
     */
    public string $cfId = '';

    /**
     * @return string
     */
    public function cloudflareImagesUrl(): string
    {
        $parts = Filename::toParts($this->owner->filename);
        return "https://imagedelivery.net/{$parts['account']}/{$parts['id']}";
    }
}
