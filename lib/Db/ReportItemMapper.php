<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;

use OCA\TimeTracker\AppFramework\Db\CompatibleMapper;

class ReportItemMapper extends CompatibleMapper {


    private $dbengine;
    public function __construct(IDBConnection $db) {
        $this->dbengine = 'MYSQL';
        if (strpos(get_class($db->getDatabasePlatform()),'PostgreSQL') !== FALSE){
            $this->dbengine = 'POSTGRES';
        } else if (strpos(get_class($db->getDatabasePlatform()),'Sqlite') !== FALSE){
            $this->dbengine = 'SQLITE';
        }
        parent::__construct($db, 'timetracker_work_interval');
    }

/*
    public $id;
    public $name;
    public $projectId;
    public $userUid;
    public $start;
    public $totalDuration;
    public $project;
    public $clientId;
    public $client;
*/


    public function report($user, $from, $to, $filterProjectId, $filterClientId, $filterTagId, $timegroup, $groupOn1, $groupOn2, $admin, $start, $limit ){

        $selectFields = ['min(wi.id) as id', 'sum(duration) as "totalDuration"'];

        if ($timegroup !== null) {
            $selectFields[]= "SUM(wi.cost) as cost";
        } else {
            $selectFields[] = 'wi.cost as cost';
        }

        $aggregation = true;
        if(empty($groupOn1) && empty($groupOn2) && empty($timegroup)) {
            $selectFields[] = 'min(wi.details) as "details"';
            $selectFields[] = 'min(wi.name) as "name"';
            $selectFields[] = '\'*\' as "projectId"';
            $selectFields[] = 'min(p.name) as "project"';

            $selectFields[] = '\'*\' as "clientId"';
            $selectFields[] = 'min(c.name) as "client"';
            $selectFields[] = 'min(user_uid) as "userUid"';
            $aggregation = false;
        } else {
            $selectFields[] = '\'*\' as "details"';
        }

        if (!$aggregation) {
            $selectFields[]= "start as time";
        } else {
            $selectFields[]= "min(start) as time";
        }

        if ($this->dbengine == 'POSTGRES') {
            if ($timegroup == 'week') {
                $selectFields[]= "concat(date_part('year', to_timestamp(start)), 'W', to_char(to_timestamp(start), 'IW')) as ftime";
            }elseif ($timegroup == 'year') {
                $selectFields[]= "date_part('year', to_timestamp(start)) as ftime";
            }elseif ($timegroup == 'month') {
                $selectFields[]= "to_char(to_timestamp(start), 'YYYY-MM') as ftime";
            }elseif ($timegroup == 'day') {
                $selectFields[]= "to_char(to_timestamp(start), 'YYYY-MM-DD') as ftime";
            }
        } else if ($this->dbengine == 'SQLITE') {
            if ($timegroup == 'week') {
                $selectFields[]= "strftime('%YW%W', datetime(start, 'unixepoch')) as ftime";
            }elseif ($timegroup == 'year') {
                $selectFields[]= "strftime('%Y', datetime(start, 'unixepoch')) as ftime";
            }elseif ($timegroup == 'month') {
                $selectFields[]= "strftime('%Y-%m', datetime(start, 'unixepoch')) as ftime";
            }elseif ($timegroup == 'day') {
                $selectFields[]= "strftime('%Y-%m-%d', datetime(start, 'unixepoch')) as ftime";
            }
        } else {
            if ($timegroup == 'week') {
                $selectFields[]= "CONCAT(YEAR(FROM_UNIXTIME(start)), 'W', WEEK(FROM_UNIXTIME(start))) as ftime";
            }elseif ($timegroup == 'year') {
                $selectFields[]= "YEAR(FROM_UNIXTIME(start)) as ftime";
            }elseif ($timegroup == 'month') {
                $selectFields[]= "DATE_FORMAT(FROM_UNIXTIME(start),'%Y-%m') as ftime";
            }elseif ($timegroup == 'day') {
                $selectFields[]= "DATE(FROM_UNIXTIME(start)) as ftime";
            }
        }

        if ($aggregation){
            if($groupOn1 != 'name'){
                if ($this->dbengine != 'MYSQL') {
                    $selectFields[] = '\'*\' as name';
                } else {
                    $selectFields[] = 'CASE WHEN CHAR_LENGTH(group_concat(distinct wi.name)) > 40 THEN CONCAT(SUBSTRING(group_concat(distinct wi.name), 1, 40), "...") ELSE group_concat(distinct wi.name) END as name';
                }
                //$selectFields[] = 'group_concat(distinct wi.name) as name';
            } else {
                $selectFields[] = 'wi.name as name';
            }
        }


        if ($aggregation){
            if(($groupOn1 != 'project') && ($groupOn2 != 'project')){
                $selectFields[] = '\'*\' as "projectId"';
                if ($this->dbengine == 'POSTGRES') {
                    $selectFields[] = 'string_agg(distinct p.name, \',\') as project';
                } else {
                    $selectFields[] = 'group_concat(distinct p.name) as project';
                }
            } else {

                $selectFields[] = '\'*\' as "projectId"';
                $selectFields[] = 'p.name as project';
            }


            if(($groupOn1 != 'client') && ($groupOn2 != 'client')){
                $selectFields[] = '\'*\' as "clientId"';
                if ($this->dbengine == 'POSTGRES') {
                    $selectFields[] = 'string_agg(distinct c.name, \',\') as client';
                } else {
                    $selectFields[] = 'group_concat(distinct c.name) as client';
                }

            } else {
                $selectFields[] = '\'*\' as "clientId"';
                $selectFields[] = 'c.name as client';
            }

            if(($groupOn1 != 'userUid') && ($groupOn2 != 'userUid') && $aggregation){
                if ($this->dbengine == 'POSTGRES') {
                    $selectFields[] = 'string_agg(distinct user_uid, \',\') as "userUid"';
                } else {
                    $selectFields[] = 'group_concat(distinct user_uid) as "userUid"';
                }

            } else {
                $selectFields[] = 'user_uid as "userUid"';
            }

        }

        $selectItems = implode(", ",$selectFields).
                ' FROM *PREFIX*timetracker_work_interval wi 
                    left join *PREFIX*timetracker_project p on wi.project_id = p.id 
                    left join *PREFIX*timetracker_client c on p.client_id = c.id';
        $filters = [];
        $params = [];
        if (!empty($from)){
            $filters[] = "(start > ?)";
            $params[] = $from;

        }
        if (!empty($to)){
            $filters[] = "(start < ?)";
            $params[] = $to;

        }
        if (!empty($filterProjectId)){
            $qm = [];
            $append = '';
            foreach($filterProjectId as $f){
                $qm[] = '?';
                $params[] = $f;

                if($f == null) {
                    $append = ' or wi.project_id is null ';
                }
            }
            $filters[] = '(wi.project_id in ('.implode(",",$qm).')'.$append.')';
        }
        if (!empty($filterClientId)){
            $qm = [];
            $append = '';
            foreach($filterClientId as $f){
                $qm[] = '?';
                $params[] = $f;
                if ($f == null) {
                    $append = ' or c.id is null ';
                }
            }
            $filters[] = '(c.id in ('.implode(",",$qm).')'.$append.')';
        }
        if ( (!empty($user)) && (!$admin) ){
            $filters[] = "(user_uid = ?)";
            $params[] = $user;

        }
        $group = '';
        $groups = [];
        if (!empty($timegroup)){
            // if($timegroup == 'week'){
            //     $groups[] = "YEARWEEK(start)";
            // } elseif ($timegroup == 'day') {
            //     $groups[] = "DATE(start)";
            // } elseif ($timegroup == 'month') {
            //     $groups[] = "EXTRACT(YEAR_MONTH FROM start)";
            // } elseif ($timegroup == 'year') {
            //     $groups[] = "YEAR(start)";
            // }
            $groups[] = 'ftime';
        }

        if (!empty($groupOn1)){
            if ($groupOn1 == "project" || $groupOn1 == "client" || $groupOn1 == "name" || $groupOn1 == "userUid")
               // $groups[] = $groupOn1;
               if ($groupOn1 == 'name'){
                $groups[] = 'wi.name';
               } else {
                if ($this->dbengine == 'POSTGRES') { // postgres needs quotes on names
                    $groups[] = '"'.$groupOn1.'"';
                } else {
                    $groups[] = $groupOn1;
                }
               }
        }
        if (!empty($groupOn2)){
            if ($groupOn2 == "project" || $groupOn2 == "client" || $groupOn2 == "name" || $groupOn2 == "userUid"){
                if ($groupOn2 == 'name'){
                    $groups[] = 'wi.name';
                    } else {
                    if ($this->dbengine == 'POSTGRES') { // postgres needs quotes on names
                        $groups[] = '"'.$groupOn2.'"';
                    } else {
                        $groups[] = $groupOn2;
                    }
                    }
            }
        }

        if (!empty($groups)){
            $group = "group by ".implode(",",$groups);
        } else {
            $group = "group by wi.id";
        }
        if (empty($start)){
            $start = 0;
        }
        if (empty($limit)){
            $limit = 10000;
        }
        $sql = 'SELECT '.$selectItems.' where '.implode(" and ",$filters).' '.$group;
        //var_dump($sql);
        // var_dump($params);
        return $this->findEntities($sql, $params, $limit, $start);
    }



}
