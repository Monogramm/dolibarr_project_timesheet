<?php
/* 
 * Copyright (C) 2015 delcroip <delcroip@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */


require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

class TimesheetReport 
{
    public $db;
    public $projectid;
    public $userid;
    public $name;
    
    public function __construct($db) 
	{
            $this->db = $db;
	}
        
    public function initBasic($projectid,$userid,$name) 
	{
            $this->projectid =$projectid; // coul
            $this->userid =$userid; // coul
            $this->name =$name; // coul
	}
/* Function to generate array for the resport
 * @param   date    $startDay   start date for the query
 * @param   date    $stopDay   start date for the query
 * @param   string  $sqltail    sql tail after the where
 * @return array()
 */   
    public function getReportArray($startDay,$stopDay, $sqltail){
        $resArray=array();
        $sql='SELECT prj.rowid as projectid, usr.rowid as userid, tsk.rowid as taskid,';
        if($db->type!='pgsql'){
            $sql.= ' MAX(prj.title) as projecttitle,MAX(prj.ref) as projectref, MAX(CONCAT(usr.firstname,\', \',usr.lastname)) as username,';
            $sql.= ' MAX(tsk.ref) as taskref, MAX(tsk.label) as tasktitle,';
        }else{
            $sql.= ' prj.title as projecttitle,prj.ref as projectref, CONCAT(usr.firstname,\' - \',usr.lastname) as username,';
            $sql.= ' tsk.ref as taskref, tsk.label as tasktitle,';
        }
        $sql.= ' ptt.task_date, SUM(ptt.task_duration) as duration ';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'projet_task_time as ptt ';
        $sql.= ' JOIN '.MAIN_DB_PREFIX.'projet_task as tsk ON tsk.rowid=fk_task ';
        $sql.= ' JOIN '.MAIN_DB_PREFIX.'projet as prj ON prj.rowid= tsk.fk_projet ';
        $sql.= ' JOIN '.MAIN_DB_PREFIX.'user as usr ON ptt.fk_user= usr.rowid ';      
        if(!empty($this->userid)){
            $sql.='WHERE ptt.fk_user=\''.$this->userid.'\' ';
        }else{

            $sql.='WHERE tsk.fk_projet=\''.$this->projectid.'\' ';
        }

         if(!empty($startDay))$sql.='AND task_date>=\''.$this->db->idate($startDay).'\'';
          if(!empty($stopDay))$sql.= ' AND task_date<=\''.$this->db->idate($stopDay).'\'';
         $sql.=' GROUP BY usr.rowid, ptt.task_date,tsk.rowid, prj.rowid ';
        if(!empty($sqltail)){
            $sql.=$sqltail;
        }
        dol_syslog("timesheet::userreport::tasktimeList", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
                $numTaskTime = $this->db->num_rows($resql);
                $i = 0;

                // Loop on each record found,
                while ($i < $numTaskTime)
                {

                    $error=0;
                    $obj = $this->db->fetch_object($resql);
                    $resArray[$i]=array('projectId' =>$obj->projectid,
                                'projectLabel' =>$obj->projectref.' - '.$obj->projecttitle,
                                'taskId' =>$obj->taskid,
                                'taskLabel' =>$obj->taskref.' - '.$obj->tasktitle,
                                'date' =>$obj->task_date,
                                'duration' =>$obj->duration,
                                'userid' =>$obj->userid,
                                'userName' =>$obj->username);

                    $i++;

                }
                $this->db->free($resql);
                return $resArray;
        }else
        {
                dol_print_error($this->db);
                return array();
        }
        
    }
        
        
    /*
    *  Function to generate HTML for the report
    * @param   date    $startDay   start date for the query
    * @param   date    $stopDay   start date for the query
    * @param   string   $mode       specify the query type
    * @param   
    * @param   string  $sqltail    sql tail after the where
    * @return string   

      * mode layout PTD project/task /day , PDT, DPT
      * periodeTitle give a name to the report
      * timemode show time using day or hours (==0)
      */
    public function getHTMLreport($startDay,$stopDay,$mode,$short,$periodTitle,$hoursperdays,$reportfriendly=0){
    // HTML buffer
    global $langs;
    $lvl1HTML='';
    $lvl3HTML='';
    $lvl2HTML='';
    // partial totals
    $lvl3Total=0;
    $lvl2Total=0;
    $lvl1Total=0;
    
    $Curlvl1=0;
    $Curlvl2=0;
    $Curlvl3=0;

    //mode 1, PER USER
    //get the list of user
    //get the list of task per user
    //sum user
    //mode 2, PER TASK
    //list of task
    //list of user per 
    $title=array('projectLabel'=>'Project','date'=>'Day','taskLabel'=>'Tasks','userName'=>'User');
    $titleWidth=array('4'=>'120','7'=>'200');
    $sqltail='';

    switch ($mode) {
        case 'PDT': //project  / task / Days //FIXME dayoff missing               
            $sqltail='ORDER BY prj.rowid,ptt.task_date,tsk.rowid ASC   ';
            //title
            $lvl1Title='projectLabel';
            $lvl2Title='date';
            $lvl3Title='taskLabel';
            //keys
            $lvl1Key='projectId';
            $lvl2Key='date';
            break;
        case 'DPT'://day /project /task
            $sqltail='ORDER BY ptt.task_date,prj.rowid,tsk.rowid ASC   ';
            //title
            $lvl1Title='date';
            $lvl2Title='projectLabel';
            $lvl3Title='taskLabel';
            //keys
            $lvl1Key='date';
            $lvl2Key='projectId';
            break;
        case 'PTD'://day /project /task
            $sqltail='ORDER BY prj.rowid,tsk.rowid,ptt.task_date ASC   ';
            //title
            $lvl1Title='projectLabel';
            $lvl2Title='taskLabel';
            $lvl3Title='date';
            //keys
            $lvl1Key='projectId';
            $lvl2Key='taskId';
            break;
        case 'UDT': //project  / task / Days //FIXME dayoff missing
                
            $sqltail='ORDER BY usr.rowid,ptt.task_date,tsk.rowid ASC   ';
            //title
            $lvl1Title='userName';
            $lvl2Title='date';
            $lvl3Title='taskLabel';
            //keys
            $lvl1Key='userId';
            $lvl2Key='date';
            break;
        
        case 'DUT'://day /project /task
            $sqltail='ORDER BY ptt.task_date,usr.rowid,tsk.rowid ASC   ';
            //title
            $lvl1Title='date';
            $lvl2Title='userName';
            $lvl3Title='taskLabel';
            //keys
            $lvl1Key='date';
            $lvl2Key='userId';
            break;
        case 'UTD'://day /project /task
            $sqltail=' ORDER BY usr.rowid,tsk.rowid,ptt.task_date ASC   ';
            //title
            $lvl1Title='userName';
            $lvl2Title='taskLabel';
            $lvl3Title='date';
            //keys
            $lvl1Key='userId';
            $lvl2Key='taskId';
            break;

        default:
            break;
    }
            
            $resArray=$this->getReportArray($startDay, $stopDay, $sqltail);
            $numTaskTime=count($resArray);

        if($numTaskTime>0) 
        {       
           // current

        if($reportfriendly){
            //$HTMLRes='<br><div class="titre">'.$this->name.', '.$periodTitle.'</div>';
            $HTMLRes.='<table class="noborder" width="100%">';
            $HTMLRes.='<tr class="liste_titre"><th>'.$langs->trans('Name');
            $HTMLRes.='</th><th>'.$langs->trans($title[$lvl1Title]).'</th><th>';
            $HTMLRes.=$langs->trans($title[$lvl2Title]).'</th>';
            $HTMLRes.='<th>'.$langs->trans($title[$lvl3Title]).'</th>';
            $HTMLRes.='<th>'.$langs->trans('Duration').':'.$langs->trans('hours').'</th>';
            $HTMLRes.='<th>'.$langs->trans('Duration').':'.$langs->trans('Days').'</th></tr>';
            foreach($resArray as $key => $item)
            {
               $HTMLRes.= '<tr class="oddeven" align="left"><th width="200px">'.$this->name.'</th>';
               $HTMLRes.= '<th '.(isset($titleWidth[$lvl1Title])?'width="'.$titleWidth[$lvl1Title].'"':'' ).'>'.$item[$lvl1Title].'</th>';
               $HTMLRes.='<th '.(isset($titleWidth[$lvl2Title])?'width="'.$titleWidth[$lvl2Title].'"':'' ).'>'.$item[$lvl2Title].'</th>';
               $HTMLRes.='<th '.(isset($titleWidth[$lvl3Title])?'width="'.$titleWidth[$lvl3Title].'"':'' ).'>'.$item[$lvl3Title].'</th>';
               $HTMLRes.='<th width="70px">'.$this->formatTime($item['duration'],0).'</th>';
               $HTMLRes.='<th width="70px">'.$this->formatTime($item['duration'],$hoursperdays).'</th></tr>';
            } 
            $HTMLRes.='</table>';
        }else   
        {
        foreach($resArray as $key => $item)
        {
            

            if(($resArray[$Curlvl2][$lvl2Key]!=$resArray[$key][$lvl2Key])
                    ||($resArray[$Curlvl1][$lvl1Key]!=$resArray[$key][$lvl1Key]))
            {
                $lvl2HTML.='<tr class="oddeven" align="left"><th></th><th>'
                        .$resArray[$Curlvl2][$lvl2Title].'</th>';
                if(!$short)$lvl2HTML.='<th></th>';
                $lvl2HTML.='<th>'.$this->formatTime($lvl3Total,0).'</th>';
                $lvl2HTML.='<th>'.$this->formatTime($lvl3Total,$hoursperdays).'</th></tr>';
                $lvl2HTML.=$lvl3HTML;
                $lvl3HTML='';
                $lvl2Total+=$lvl3Total;
                $lvl3Total=0;
                $Curlvl2=$key;
                if(($resArray[$Curlvl1][$lvl1Key]!=$resArray[$key][$lvl1Key]))
                {
                    $lvl1HTML.='<tr class="oddeven" align="left"><th >'
                            .$resArray[$Curlvl1][$lvl1Title].'</th><th></th>';
                    if(!$short)$lvl1HTML.='<th></th>';
                    $lvl1HTML.='<th>'.$this->formatTime($lvl2Total,0).'</th>';
                    $lvl1HTML.='<th>'.$this->formatTime($lvl2Total,$hoursperdays).'</th></tr>';
                    $lvl1HTML.=$lvl2HTML;
                    $lvl2HTML='';
                    $lvl1Total+=$lvl2Total;
                    $lvl2Total=0;   
                    $Curlvl1=$key;
                }
            }
            if(!$short)
            {
                $lvl3HTML.='<tr class="oddeven" align="left"><th></th><th></th><th>'
                    .$resArray[$key][$lvl3Title].'</th><th>';
                $lvl3HTML.=$this->formatTime($item['duration'],0).'</th><th>';
                $lvl3HTML.=$this->formatTime($item['duration'],$hoursperdays).'</th></tr>';                
               /*
                if($hoursperdays==0)
                {
                    $lvl3HTML.=date('G:i',mktime(0,0,$resArray[$key]['duration'])).'</th></tr>';
                }else{
                    $lvl3HTML.=$resArray[$key]['duration']/3600/$hoursperdays.'</th></tr>';
                }*/
            }
            $lvl3Total+=$resArray[$key]['duration'];
            

        }
       //handle the last line 
        $lvl2HTML.='<tr class="oddeven" align="left"><th></th><th>'
                .$resArray[$Curlvl2][$lvl2Title].'</th>';
        if(!$short)$lvl2HTML.='<th></th>';
        $lvl2HTML.='<th>'.$this->formatTime($lvl3Total,0).'</th>';
        $lvl2HTML.='<th>'.$this->formatTime($lvl3Total,$hoursperdays).'</th></tr>';
        $lvl2HTML.=$lvl3HTML;
        $lvl2Total+=$lvl3Total;
        $lvl1HTML.='<tr class="oddeven" align="left"><th >'
                .$resArray[$Curlvl1][$lvl1Title].'</th><th></th>';
        if(!$short)$lvl1HTML.='<th></th>';
        $lvl1HTML.='<th>'.$this->formatTime($lvl2Total,0).'</th>';
        $lvl1HTML.='<th>'.$this->formatTime($lvl2Total,$hoursperdays).'</th></tr>';
        $lvl1HTML.=$lvl2HTML;
        $lvl1Total+=$lvl2Total;
        // make the whole result
         $HTMLRes='<br><div class="titre">'.$this->name.', '.$periodTitle.'</div>';
         $HTMLRes.='<table class="noborder" width="100%">';
         $HTMLRes.='<tr class="liste_titre"><th>'.$langs->trans($title[$lvl1Title]).'</th><th>'
                .$langs->trans($title[$lvl2Title]).'</th>';
         $HTMLRes.=(!$short)?'<th>'.$langs->trans($title[$lvl3Title]).'</th>':'';
         $HTMLRes.='<th>'.$langs->trans('Duration').':'.$langs->trans('hours').'</th>';
         $HTMLRes.='<th>'.$langs->trans('Duration').':'.$langs->trans('Days').'</th></tr>';
            
         $HTMLRes.='<tr class="liste_titre">'.((!$short)?'<th></th>':'').'<th colspan=2> TOTAL</th>';
         $HTMLRes.='<th>'.$this->formatTime($lvl1Total,0).'</th>';
         $HTMLRes.='<th>'.$this->formatTime($lvl1Total,$hoursperdays).'</th></tr>';
        $HTMLRes.=$lvl1HTML;
        $HTMLRes.='</table>';
        } // end else reportfiendly
      } // end is numtasktime




    return $HTMLRes;

    }
    
    private function formatTime($duration,$hoursperdays)
    {
        if($hoursperdays==0)
        {
            $TotalSec=$duration%60;
            $TotalMin=(($duration-$TotalSec)/60)%60;
            $TotalHours=$TotalHours=($duration-$TotalMin*60- $TotalSec)/3600;
            return $TotalHours.':'.sprintf("%02s",$TotalMin);
        }else
        {
            $totalDay=$duration/3600/$hoursperdays;
            return strval($totalDay);
            
        }

    }
    
 }   
