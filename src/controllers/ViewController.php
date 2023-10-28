<?php

namespace deuxhuithuit\cfimages\controllers;

use craft\web\Controller;

class ViewController extends Controller
{
    public function actionIndex(string $file)
    {
        $this->requireCpRequest();
        $filename = \basename($file);
        $imageId = \deuxhuithuit\cfimages\Filename::toId($filename);
        $client = \deuxhuithuit\cfimages\Plugin::getInstance()->client();
        $image = $client->getImage($imageId);
        $content = $client->getImageStream($imageId)->getContents();
        $this->response->format = \craft\web\Response::FORMAT_RAW;
        $this->response->headers->set('Content-Type', $image['meta']['mimetype']);
        $this->response->headers->set('Content-Length', $image['meta']['size']);
        $this->response->data = $content;
        return $this->response;
    }
}
