<?php

namespace rias\scout\engines;

use Elastic\AppSearch\Client\Client;
use Elastic\OpenApi\Codegen\Exception\NotFoundException;
use Elastic\AppSearch\Client\Exception\ApiRateExceededException;
use craft\base\Element;
use rias\scout\IndexSettings;
use rias\scout\ScoutIndex;
use Tightenco\Collect\Support\Arr;
use Tightenco\Collect\Support\Collection;

class ElasticEngine extends Engine
{
    /** @var \Elastic\AppSearch\Client\Client */
    protected $client;

    /** @var \rias\scout\ScoutIndex */
    public $scoutIndex;

    public function __construct(ScoutIndex $scoutIndex, Client $client)
    {
        $this->scoutIndex = $scoutIndex;
        $this->client = $client;

        try {
            $this->client->getEngine($this->scoutIndex->indexName);
        } catch (NotFoundException $e) {
            $this->client->createEngine($this->scoutIndex->indexName);
        }
    }

    public function update($elements)
    {
        $elements = new Collection(Arr::wrap($elements));

        $elements = $elements->filter(function (Element $element) {
            return get_class($element) === $this->scoutIndex->elementType;
        });

        if ($elements->isEmpty()) {
            return;
        }

        $objects = $this->transformElements($elements);

        if (!empty($objects)) {
            try {
                $indexingResults = $this->client->indexDocuments($this->scoutIndex->indexName, $objects);
            } catch (ApiRateExceededException $e) {
                sleep($e->getRetryAfter());
                $indexingResults = $this->client->indexDocuments($this->scoutIndex->indexName, $objects);
            }

            // No exception is thrown by the AppSearch object for us, so
            // we need to look through the result array and check for
            // errors ourselves and throw one.
            foreach ($indexingResults as $result) {
                if (count($result['errors'])) {
                    // TODO: Make this a scoped exception.
                    throw new \Exception(
                        'Encountered errors during indexing:'
                        . PHP_EOL
                        . print_r($result['errors'], true)
                    );
                }
            }
        }
    }

    public function delete($elements)
    {
        $elements = new Collection(Arr::wrap($elements));

        $objectIds = $elements->map(function ($object) {
            if ($object instanceof Element) {
                return $object->id;
            }

            return $object['distinctID'] ?? $object['id'];
        })->unique()->values()->all();

        if (!empty($objectIds)) {
            $this->client->deleteDocuments($this->scoutIndex->indexName, $objectIds);
        }
    }

    public function flush()
    {
        $this->client->deleteEngine($this->scoutIndex->indexName);
        $this->client->createEngine($this->scoutIndex->indexName);
    }

    public function updateSettings(IndexSettings $indexSettings)
    {
        throw new \Exception('Not implemented');
        // $this->client->updateSchema($this->scoutIndex->indexName, $indexSettings->settings);
    }

    public function getSettings(): array
    {
        // return $this->client->getSchema($this->scoutIndex->indexName);
        return [];
    }

    public function getTotalRecords(): int
    {
        return (int) 0;
    }

    private function transformElements(Collection $elements): array
    {
        $objects = $elements->map(function (Element $element) {
            /** @var \rias\scout\behaviors\SearchableBehavior $element */
            if (empty($searchableData = $element->toSearchableArray($this->scoutIndex))) {
                return;
            }

            return array_merge(
                ['id' => $element->id],
                $searchableData
            );
        })->filter()->values()->all();

        if (empty($this->scoutIndex->splitElementsOn)) {
            return $objects;
        }

        $result = $this->splitObjects($objects);

        $this->delete($result['delete']);

        $objects = $result['save'];

        return $objects;
    }
}
