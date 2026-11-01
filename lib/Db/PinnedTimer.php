<?php
namespace OCA\TimeTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserUid(string $userUid)
 * @method void setName(string $name)
 * @method void setProjectId(?int $projectId)
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
     * @return int[]
     */
    public function getTagIdList(): array {
        return PinnedTimer::parseTagIds($this->tagIds);
    }

    /**
     * Turn "3,7", [3, "7"] or null into a sorted list of unique positive ids.
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
                $ids[(int)$v] = true;
            }
        }
        $ids = array_keys($ids);
        sort($ids);
        return $ids;
    }

    public function toApi(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'projectId' => $this->projectId,
            'tagIds' => $this->getTagIdList(),
            'sortOrder' => $this->sortOrder,
            'createdAt' => $this->createdAt,
        ];
    }
}
