<?php

namespace deuxhuithuit\cfimages\behaviors;

use yii\base\Behavior;

class CloudflareImagesTransformBehavior extends Behavior
{
    public ?float $gamma = null;
    public ?string $compression = null;
    public ?string $rotate = null;
    public ?string $background = null;
}
