<?php

namespace deuxhuithuit\cfimages\models;

use craft\base\Model;
use craft\helpers\App;

class Settings extends Model
{
    public string $accountId = '';
    public string $accountHash = '';
    public string $apiToken = '';

    public function defineRules(): array
    {
        return [
            [['accountId', 'apiToken', 'accountHash'], 'required'],
        ];
    }

    public function getAccountId(): string
    {
        return App::parseEnv($this->accountId);
    }

    public function getAccountHash(): string
    {
        return App::parseEnv($this->accountHash);
    }

    public function getApiToken(): string
    {
        return App::parseEnv($this->apiToken);
    }
}
