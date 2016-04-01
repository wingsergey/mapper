<?php

namespace Tarantool\Mapper\Schema;

use Tarantool\Client;
use Tarantool\Mapper\Contracts;
use Tarantool\Schema\Space;
use Tarantool\Schema\Index;

class Schema implements Contracts\Schema
{
    protected $client;
    protected $spaceSpace;
    protected $indexSpace;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->spaceSpace = new Space($client, Space::VSPACE);
        $this->indexSpace = new Space($client, Space::VINDEX);
    }

    protected $spaceId = [];
    public function getSpaceId($space)
    {
        if (!array_key_exists($space, $this->spaceId)) {
            $response = $this->spaceSpace->select([$space], Index::SPACE_NAME);
            $data = $response->getData();
            if (!empty($data)) {
                $this->spaceId[$space] = $data[0][0];
            }
        }
        if (array_key_exists($space, $this->spaceId)) {
            return $this->spaceId[$space];
        }
    }

    public function getSpaceName($spaceId)
    {
        if (!in_array($spaceId, $this->spaceId)) {
            $response = $this->spaceSpace->select([$spaceId], 0);
            $data = $response->getData();
            if (!empty($data)) {
                $this->spaceId[$data[0][2]] = $spaceId;
            }
        }

        if (in_array($spaceId, $this->spaceId)) {
            return array_search($spaceId, $this->spaceId);
        }
    }

    public function hasSpace($space)
    {
        return $this->getSpaceId($space) !== null;
    }

    public function makeSpace($space)
    {
        $this->client->evaluate("box.schema.space.create('$space')");
    }

    public function getIndexId($space, $index)
    {
        $response = $this->indexSpace->select([$this->getSpaceId($space), $index], Index::INDEX_NAME);
        $data = $response->getData();

        if (!empty($data)) {
            return $data[0][0];
        }
    }

    public function hasIndex($space, $index)
    {
        $spaceId = $this->getSpaceId($space);
        $response = $this->indexSpace->select([$spaceId, $index], Index::INDEX_NAME);

        return !empty($response->getData());
    }

    public function listIndexes($space)
    {
        $result = [];
        $response = $this->indexSpace->select([$this->getSpaceId($space)], Index::INDEX_NAME);

        foreach ($response->getData() as $row) {
            $result[$row[2]] = [];
            foreach ($row[5] as $f) {
                $result[$row[2]][] = $f[0];
            }
        }

        return $result;
    }

    public function makeIndex($space, $index, array $arguments)
    {
        $config = [];
        foreach ($arguments as $k => $v) {
            if (is_array($v)) {
                // convert to lua array
                $v = str_replace(['[', ']'], ['{', '}'], json_encode($v));
            }
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $config[] = $k.' = '.$v;
        }
        $config = '{'.implode(', ', $config).'}';
        $this->client->evaluate("box.space.$space:create_index('$index', $config)");
    }
}
