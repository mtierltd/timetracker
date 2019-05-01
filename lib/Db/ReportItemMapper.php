<?php
// db/authormapper.php

namespace OCA\TimeTracker\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class ReportItemMapper extends Mapper {

    public function __construct(IDBConnection $db) {
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
        
        $selectFields = ['min(wi.id) as id', 'sum(duration) as totalDuration'];
        if(empty($timegroup)){
            $selectFields[]= "DATE_FORMAT(FROM_UNIXTIME(min(start)),'%Y-%m-%d') as time";
        } elseif ($timegroup == 'week') {
            $selectFields[]= "STR_TO_DATE(CONCAT(YEARWEEK(FROM_UNIXTIME(start)),' Monday'), '%x%v %W') as time";
        }elseif ($timegroup == 'day') {
            $selectFields[]= "DATE_FORMAT(FROM_UNIXTIME(start),'%Y-%m-%d') as time";
        }elseif ($timegroup == 'year') {
            $selectFields[]= 'YEAR(FROM_UNIXTIME(start)) as time';
        }elseif ($timegroup == 'month') {
            $selectFields[]= "DATE_FORMAT(FROM_UNIXTIME(start),'%Y-%m') as time";
        }
        if(($groupOn1 != 'name') && ($groupOn2 != 'name')){
            $selectFields[] = 'MIN(wi.name) as name';
        } else {
            $selectFields[] = 'wi.name as name';
        }
        if(($groupOn1 != 'project') && ($groupOn2 != 'project')){
            $selectFields[] = 'MIN(wi.project_id) as projectId';
            $selectFields[] = 'MIN(p.name) as project';
        } else {
            $selectFields[] = 'MIN(wi.project_id) as projectId';
            $selectFields[] = 'p.name as project';
        }

        if(($groupOn1 != 'client') && ($groupOn2 != 'client')){
            $selectFields[] = 'MIN(c.id) as clientId';
            $selectFields[] = 'MIN(c.name) as client';
        } else {
            $selectFields[] = 'MIN(c.id) as clientId';
            $selectFields[] = 'c.name as client';
        }

        if(($groupOn1 != 'userUid') && ($groupOn2 != 'userUid')){
            $selectFields[] = 'MIN(user_uid) as userUid';
            
        } else {
            $selectFields[] = 'user_uid as userUid';
        }

        $selectItems = implode(", ",$selectFields).
                ' FROM `oc_timetracker_work_interval` wi 
                    left join `oc_timetracker_project` p on wi.project_id = p.id 
                    left join `oc_timetracker_client` c on p.client_id = c.id';
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
                    $append = ' or p.client_id is null ';
                }
            }
            $filters[] = '(p.client_id in ('.implode(",",$qm).')'.$append.')';
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
            $groups[] = 'time';
        }
        
        if (!empty($groupOn1)){
            if ($groupOn1 == "project" || $groupOn1 == "client" || $groupOn1 == "name" || $groupOn1 == "userUid")
                $groups[] = $groupOn1;
                if (!empty($groupOn2)){
                    if ($groupOn2 == "project" || $groupOn2 == "client" || $groupOn2 == "name" || $groupOn2 == "userUid"){
                        $groups[] = $groupOn2;
                    }
                }
        }
        if (!empty($groups)){
            $group = "group by ".implode(",",$groups);
        }
        if (empty($start)){
            $start = 0;
        }
        if (empty($limit)){
            $limit = 10000;
        }
        $sql = 'SELECT '.$selectItems.' where '.implode(" and ",$filters).' '.$group. ' order by time desc';
        return $this->findEntities($sql, $params, $limit, $start);
    }

    

}