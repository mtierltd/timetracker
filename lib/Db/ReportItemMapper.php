<?php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\Service\UserTimeZone;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

/**
 * Builds report rows from work intervals.
 *
 * The matching work intervals are read with one portable SQL query, and all
 * grouping happens in PHP. That way every database returns exactly the same
 * report: time groups follow the user's time zone (including daylight saving
 * time), weeks are ISO 8601 weeks ("2025-W01"), and aggregated name, project,
 * client and user columns are built the same way everywhere.
 */
class ReportItemMapper extends CompatibleMapper {

    /** Aggregated name lists are shortened to this many characters, plus "..." */
    public const MAX_NAME_LIST_LENGTH = 40;

    private const GROUPABLE = ['name', 'project', 'client', 'userUid'];

    private $userTimeZone;

    public function __construct(IDBConnection $db, UserTimeZone $userTimeZone) {
        parent::__construct($db, 'timetracker_work_interval');
        $this->userTimeZone = $userTimeZone;
    }

    /**
     * @param string $user only report this user's entries, unless $admin
     * @param int|string|null $from only entries starting after this unix timestamp
     * @param int|string|null $to only entries starting before this unix timestamp
     * @param array $filterProjectId project ids; a null element also matches entries without project
     * @param array|string $filterClientId client ids; a null element also matches entries without client
     * @param mixed $filterTagId unused
     * @param string|null $timegroup '', 'day', 'week', 'month' or 'year'
     * @param string|null $groupOn1 '', 'name', 'project', 'client' or 'userUid'
     * @param string|null $groupOn2 same as $groupOn1
     * @param bool $admin report all users' entries
     * @param int|null $start offset into the resulting rows
     * @param int|null $limit maximum number of resulting rows (default 10000)
     * @param \DateTimeZone|null $timeZone time zone for time groups; default: the logged-in user's
     * @return ReportItem[]
     */
    public function report($user, $from, $to, $filterProjectId, $filterClientId, $filterTagId, $timegroup, $groupOn1, $groupOn2, $admin, $start, $limit, ?\DateTimeZone $timeZone = null) {
        $timeZone = $timeZone ?? $this->userTimeZone->get();
        $timegroup = in_array($timegroup, ['day', 'week', 'month', 'year'], true) ? $timegroup : '';
        $groupOn1 = in_array($groupOn1, self::GROUPABLE, true) ? $groupOn1 : '';
        $groupOn2 = in_array($groupOn2, self::GROUPABLE, true) ? $groupOn2 : '';

        $rows = $this->fetchIntervals($user, $from, $to, $filterProjectId, $filterClientId, $admin);

        if ($timegroup === '' && $groupOn1 === '' && $groupOn2 === '') {
            $items = array_map(fn ($row) => $this->singleItem($row), $rows);
        } else {
            $items = $this->aggregate($rows, $timegroup, $groupOn1, $groupOn2, $timeZone);
        }

        $start = empty($start) ? 0 : (int)$start;
        $limit = empty($limit) ? 10000 : (int)$limit;
        return array_slice($items, $start, $limit);
    }

    /**
     * The work intervals matching the filters, oldest first.
     */
    private function fetchIntervals($user, $from, $to, $filterProjectId, $filterClientId, $admin): array {
        $filters = [];
        $params = [];
        if (!empty($from)) {
            $filters[] = '(wi.start > ?)';
            $params[] = (int)$from;
        }
        if (!empty($to)) {
            $filters[] = '(wi.start < ?)';
            $params[] = (int)$to;
        }
        foreach ([[$filterProjectId, 'wi.project_id'], [$filterClientId, 'c.id']] as [$ids, $column]) {
            if (empty($ids) || !is_array($ids)) {
                continue;
            }
            $values = array_values(array_filter($ids, fn ($id) => $id !== null && $id !== ''));
            $orNull = count($values) !== count($ids) ? " or $column is null" : '';
            $in = $values ? "$column in (" . implode(',', array_fill(0, count($values), '?')) . ')' : '1 = 0';
            $filters[] = "($in$orNull)";
            foreach ($values as $v) {
                $params[] = (int)$v;
            }
        }
        if (!empty($user) && !$admin) {
            $filters[] = '(wi.user_uid = ?)';
            $params[] = $user;
        }

        $sql = 'SELECT wi.id, wi.name, wi.details, wi.start, wi.duration, wi.cost, wi.user_uid,'
            . ' p.name AS project_name, c.name AS client_name'
            . ' FROM *PREFIX*timetracker_work_interval wi'
            . ' LEFT JOIN *PREFIX*timetracker_project p ON wi.project_id = p.id'
            . ' LEFT JOIN *PREFIX*timetracker_client c ON p.client_id = c.id'
            . ($filters ? ' WHERE ' . implode(' AND ', $filters) : '')
            . ' ORDER BY wi.start ASC, wi.id ASC';

        $result = $this->db->executeQuery($sql, $params);
        $rows = $result->fetchAll();
        $result->closeCursor();
        return $rows;
    }

    /**
     * One report row per work interval (no grouping).
     */
    private function singleItem(array $row): ReportItem {
        return ReportItem::fromRow([
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'details' => $row['details'],
            'projectId' => '*',
            'clientId' => '*',
            'project' => $row['project_name'],
            'client' => $row['client_name'],
            'userUid' => $row['user_uid'],
            'time' => (string)$row['start'],
            'totalDuration' => (int)$row['duration'],
            'cost' => $row['cost'] === null ? null : (int)$row['cost'],
        ]);
    }

    /**
     * Group the rows by time group and up to two columns, in order of each
     * group's first entry.
     *
     * @return ReportItem[]
     */
    private function aggregate(array $rows, string $timegroup, string $groupOn1, string $groupOn2, \DateTimeZone $timeZone): array {
        $groupedBy = array_values(array_filter([$groupOn1, $groupOn2]));
        $groups = [];
        foreach ($rows as $row) {
            $values = [
                'name' => $row['name'],
                'project' => $row['project_name'],
                'client' => $row['client_name'],
                'userUid' => $row['user_uid'],
            ];
            $ftime = $timegroup !== '' ? self::timeGroupLabel((int)$row['start'], $timegroup, $timeZone) : null;
            $key = json_encode([$ftime, ...array_map(fn ($g) => $values[$g], $groupedBy)]);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => (int)$row['id'],
                    'time' => (int)$row['start'],
                    'ftime' => $ftime,
                    'totalDuration' => 0,
                    'cost' => 0,
                    'name' => [], 'project' => [], 'client' => [], 'userUid' => [],
                ];
            }
            $group = &$groups[$key];
            $group['id'] = min($group['id'], (int)$row['id']);
            $group['time'] = min($group['time'], (int)$row['start']);
            $group['totalDuration'] += (int)$row['duration'];
            $group['cost'] += (int)$row['cost'];
            foreach (self::GROUPABLE as $column) {
                // distinct values in order of appearance, like a database's group_concat(distinct ...)
                if ($values[$column] !== null && !in_array($values[$column], $group[$column], true)) {
                    $group[$column][] = $values[$column];
                }
            }
            unset($group);
        }

        $items = [];
        foreach ($groups as $group) {
            $list = fn ($column) => $group[$column] ? implode(',', $group[$column]) : null;
            $name = $list('name');
            if (!in_array('name', $groupedBy, true) && $name !== null && mb_strlen($name) > self::MAX_NAME_LIST_LENGTH) {
                $name = mb_substr($name, 0, self::MAX_NAME_LIST_LENGTH) . '...';
            }
            $item = [
                'id' => $group['id'],
                'name' => $name,
                'details' => '*',
                'projectId' => '*',
                'clientId' => '*',
                'project' => $list('project'),
                'client' => $list('client'),
                'userUid' => $list('userUid'),
                'time' => (string)$group['time'],
                'totalDuration' => $group['totalDuration'],
                'cost' => $group['cost'],
            ];
            if ($group['ftime'] !== null) {
                $item['ftime'] = $group['ftime'];
            }
            $items[] = ReportItem::fromRow($item);
        }
        return $items;
    }

    /**
     * The label of the time group a moment falls into, in the given time zone:
     * day "2025-01-31", week "2025-W05" (ISO 8601), month "2025-01", year "2025".
     */
    public static function timeGroupLabel(int $timestamp, string $timegroup, \DateTimeZone $timeZone): string {
        $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timeZone);
        switch ($timegroup) {
            case 'week':
                return $date->format('o-\WW');
            case 'month':
                return $date->format('Y-m');
            case 'year':
                return $date->format('Y');
            default:
                return $date->format('Y-m-d');
        }
    }
}
