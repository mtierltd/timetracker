<?php

namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A pinned timer: starts a timer with its name, project and tags in one click.
 *
 * @method void setUserUid(string $userUid)
 * @method void setName(string $name)
 * @method void setProjectId(?int $projectId)
 * @method int|null getProjectId()
 * @method void setTagIds(string $tagIds)
 * @method void setSortOrder(int $sortOrder)
 * @method void setCreatedAt(int $createdAt)
 */
class PinnedTimer extends Entity {

    public $id;
    public $userUid;
    public $name;
    public $projectId;
    public $tagIds;
    public $sortOrder;
    public $createdAt;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('userUid', 'string');
        $this->addType('name', 'string');
        $this->addType('projectId', 'integer');
        $this->addType('tagIds', 'string');
        $this->addType('sortOrder', 'integer');
        $this->addType('createdAt', 'integer');
    }

    /**
     * The stored tag ids, as integers.
     *
     * @return int[]
     */
    public function getTagIdList(): array {
        return self::parseTagIds($this->tagIds);
    }

    /**
     * Tag ids from "1,2,3" or [1, 2, 3]: positive integers, without duplicates.
     *
     * @param string|array|null $value
     * @return int[]
     */
    public static function parseTagIds($value): array {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $v) {
            if (is_numeric($v) && (int)$v > 0) {
                $ids[(int)$v] = (int)$v;
            }
        }
        return array_values($ids);
    }
}
