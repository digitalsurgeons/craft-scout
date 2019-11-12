<?php

namespace rias\scout\models;

use Craft;
use craft\base\Model;
use Exception;
use rias\scout\Scout;
use rias\scout\ScoutIndex;
use rias\scout\engines\ElasticEngine;
use Tightenco\Collect\Support\Collection;

class Settings extends Model
{
    /** @var string */
    public $pluginName = 'Scout';

    /** @var bool */
    public $sync = true;

    /** @var bool */
    public $queue = true;

    /** @var ElasticEngine[] */
    public $engines = [];

    /** @var ScoutIndex[] */
    public $indices = [];

    /* @var string */
    public $apiEndpoint = '';

    /* @var string */
    public $apiKey = '';

    /* @var int */
    public $connect_timeout = 1;

    /* @var int */
    public $batch_size = 1000;

    public function rules()
    {
        return [
            [['connect_timeout', 'batch_size'], 'integer'],
            [['sync', 'queue'], 'boolean'],
            [['apiEndpoint', 'apiKey'], 'string'],
            [['apiEndpoint', 'apiKey', 'connect_timeout'], 'required'],
        ];
    }

    public function getApiEndpoint()
    {
        return Craft::parseEnv($this->apiEndpoint);
    }

    public function getApiKey()
    {
        return Craft::parseEnv($this->apiKey);
    }

    public function getIndices(): Collection
    {
        return new Collection($this->indices);
    }

    public function getEngines(): Collection
    {
        return $this->getIndices()->map(function (ScoutIndex $scoutIndex) {
            return $this->getEngine($scoutIndex);
        });
    }

    public function getEngine(ScoutIndex $scoutIndex): ElasticEngine
    {
        if (!isset($this->engines[$scoutIndex->indexName])) {
            $this->engines[$scoutIndex->indexName] = new ElasticEngine($scoutIndex, Scout::$plugin->client);
        }

        return $this->engines[$scoutIndex->indexName];
    }
}
