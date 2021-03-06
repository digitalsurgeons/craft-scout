<?php

namespace rias\scout\engines;

use Elastic\AppSearch\Client\Client;
use rias\scout\IndexSettings;
use rias\scout\ScoutIndex;

abstract class Engine
{
    /** @var ScoutIndex */
    public $scoutIndex;

    abstract public function __construct(ScoutIndex $scoutIndex, Client $client);

    abstract public function update($models);

    abstract public function delete($models);

    abstract public function flush();

    abstract public function updateSettings(IndexSettings $indexSettings);

    abstract public function getSettings(): array;

    abstract public function getTotalRecords(): int;

    public function splitObjects(array $objects): array
    {
        $objectsToSave = [];
        $objectsToDelete = [];

        foreach ($objects as $object) {
            $splittedObjects = $this->splitObject($object);

            if (count($splittedObjects) <= 1) {
                $object['distinctID'] = $object['id'];
                $objectsToSave[] = $object;
                continue;
            }

            foreach ($splittedObjects as $part => $splittedObject) {
                $splittedObject['distinctID'] = $splittedObject['id'];
                $splittedObject['id'] = "{$splittedObject['id']}_{$part}";
                $objectsToSave[] = $splittedObject;
            }

            $objectsToDelete[] = $object;
        }

        return [
            'save'   => $objectsToSave,
            'delete' => $objectsToDelete,
        ];
    }

    public function splitObject(array $data): array
    {
        $pieces = [];
        foreach ($this->scoutIndex->splitElementsOn as $splitElementOn) {
            $pieces[$splitElementOn] = [];
            if (isset($data[$splitElementOn]) && is_array($data[$splitElementOn])) {
                $pieces[$splitElementOn] = $data[$splitElementOn];
            }
        }

        $objects = [[]];
        foreach (array_filter($pieces) as $splittedBy => $values) {
            $temp = [];
            foreach ($objects as $object) {
                foreach ($values as $value) {
                    $temp[] = array_merge($object, [$splittedBy => $value]);
                }
            }
            $objects = $temp;
        }

        return array_map(function ($object) use ($data) {
            return array_merge($data, $object);
        }, $objects);
    }
}
