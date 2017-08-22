<?php
/* Copyright (C) 2017 delcroip <pmpdelcroix@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
define('TIMESHEET_MAX_TTA_APPROVAL',100);
define('TIMESHEET_GROUP_OTHER_AP',"week");
include 'core/lib/includeMain.lib.php';
require_once 'core/lib/timesheet.lib.php';
require_once 'core/lib/generic.lib.php';
require_once 'class/Task_time_approval.class.php';

/*******************************************************************
* ACTIONS
*
* Put here all code to do according to value of "action" parameter
********************************************************************/
$apflows=array_slice(str_split(TIMESHEET_APPROVAL_FLOWS),1);
$userId=  is_object($user)?$user->id:$user;

$roles=array(0=> 'team', 1=> 'project',2=>'customer',3=>'supplier',4=>'other');
// find the Role //FIX ME SHOW ONLY if he has right
$role         = GETPOST('role');
if(!$role){
    $role_key=array_search('1',array_slice ($apflows,1)); // search other than team
    if($role_key===false){
        header("location:timesheetAp.php");
    }else{
        $role=$roles[$role_key];
    }
}
// end find the role
// get other param
$action             = GETPOST('action');
if(!is_numeric($offset))$offset=0;
$print=(GETPOST('optioncss')=='print')?true:false;
$current=  GETPOST('target','int');
$token=  GETPOST('token');
if($current==NULL)$current='0';
//handle submission
if($action=='submit'){
    if (isset($_SESSION['task_timesheet'][$token]))
    {
        // $_SESSION['timesheetAp'][$token]['tsUser']
        $tsApproved=0;
        $tsRejected=0;
        $ret=0;
        $errors=0;
        $count=0;
        //$task_timesheet->db=$db;
        if (!empty($_POST['approval']))
        {
            $task_timesheet= new Task_time_approval($db);
            $approvals=$_POST['approval'];
            $notes=$_POST['note'];
            
            $update=false;
            foreach($_SESSION['task_timesheet'][$token] as $id => $role){
                $count++;
                $task_timesheet->fetch($id);
                if($notes[$id]!=$task_timesheet->note){                   
                    $task_timesheet->note=$notes[$id];
                    $update=true;
                }
                switch(uniordHex($approvals[$id])){
                    case '2705'://Approved':
                       $ret=$task_timesheet->Approved($user,$role); 
                        if($ret<0)$errors++;
                        else $tsApproved++;
                        break;
                    case '274C'://'Rejected':
                        $ret=$task_timesheet->challenged($user,$role);
                        if($ret<0)$errors++;
                        else $tsRejected++;
                        break;
                    case '2753': // ? submitted
                        if($update)$task_timesheet->update($user);
                    default:
                        break;

                }

            }
            if(($tsRejected+$tsApproved)>0){
               $current--;
            }
          // $offset-=($tsApproved+$tsRejected);       

                           //$ret =postActuals($db,$user,$_POST['task'],$token);
 
                if($tsApproved)setEventMessage($langs->transnoentitiesnoconv("NumberOfTimesheetApproved").$tsApproved);
                if($tsRejected)setEventMessage($langs->transnoentitiesnoconv("NumberOfTimesheetRejected").$tsRejected);
                if($errors)setEventMessage($langs->transnoentitiesnoconv("NumberOfErrors").$errors);

                if($errors==0 && $tsApproved==0 && $tsRejected==0){
                    setEventMessage($langs->transnoentitiesnoconv("NothingChanged"),'warning');
                }
            }        
        }else{
            setEventMessage( $langs->transnoentitiesnoconv("NothingChanged"),'warning');// shoudn't happend
        }
    }



/***************************************************
* PREP VIEW
*
* Put here all code to build page
****************************************************/
$subId=($user->admin)?'all':get_subordinate($db,$userId, 1,array($userId),$role); //FIx ME for other role
$tasks=implode(',', array_keys(get_task($db, $userId)));
if($tasks=="")$tasks=0;
$selectList=getSelectAps($subId,$tasks,$role);
if($current>=count($selectList))$current=0;
// number of TS to show
$level=intval(TIMESHEET_MAX_TTA_APPROVAL);
//define the offset
$offset=0;
/*
if( is_array($selectList)&& count($selectList)){
        if($current>=count($selectList))$current=0;
        $offset=0;
        for($i=0;$i<$current;$i++){
            $offset+= $selectList[$i]['count'];
        }
        $level=$selectList[$i]['count'];
}*/
// get the TTA to show
$objectArray=getTStobeApproved($current,$selectList);

$token=  getToken();


if(is_array($objectArray)){
    // SAVE THE ARRAY IN THE SESSION FOR CHECK UPON SUBMIT
    foreach($objectArray as $object){
        $_SESSION['task_timesheet'][$token][$object->appId]=$role;
    }
}

/***************************************************
* VIEW
*
* Put here all code to build page
****************************************************/
$head=($print)?'<style type="text/css" >@page { size: A4 landscape;marks:none;margin: 1cm ;}</style>':'';
$morejs=array();
$morejs=array("/timesheet/core/js/timesheet.js?v2.0");
llxHeader($head,$langs->trans('Timesheet'),'','','','',$morejs);
//calculate the week days
showTimesheetApTabs($role);
echo '<div id="'.$role.'" class="tabBar">';
//FIXME Approve/reject/leave all buton

    if(!$print) echo getHTMLNavigation($role,$optioncss, $selectList,$current);
    //FIXME
    // form header
    echo '<form action="?action=submit" method="POST" name="OtherAp" id="OtherAp">';
    echo '<input type="hidden" name="token" value="'.$token.'"/>';
    echo '<input type="hidden" name="role" value="'.$role.'"/>';
    echo '<input type="hidden" name="target" value="'.($current+1)."\"/>\n";
    // table hearder
    echo "\n<table id=\"ApTable\" class=\"noborder\" width=\"100%\">\n";
    //rows
    getHTMLRows($objectArray);
    // table footer
    echo "\n</table>";
    echo '<div class="tabsAction">';
    echo '<input type="submit" class="butAction" name="Send" value="'.$langs->trans('Submit').'/'.$langs->trans('Next')."\" />\n";
    //form footer
    echo '</div>';
    echo "\n</form>";

echo '</div>';
llxFooter();


/***************************************************
* FUNCTIONS
*
* Put here all code of funcitons
****************************************************/
/*
 * function to print the timesheet navigation header
 * 
 *  @param    string              	$yearWeek            year week like 2015W09
 *  @param     int              	$whitelistmode        whitelist mode, shows favoite o not 0-whiteliste,1-blackliste,2-non impact
 *  @param     object             	$form        		form object
 *  @return     string                                         HTML
 */
function getHTMLNavigation($role,$optioncss, $selectList,$current=0){
	global $langs,$db;
        
        $htmlSelect='<select name="target">';
        foreach($selectList as $key => $element){
            $htmlSelect.=' <option value="'.$key.'" '.(($current==$key)?'selected':'').'>'.$element['label'].'</option>';
        }
            
        $htmlSelect.='</select>';    
        
        $form= new Form($db);
        $Nav=  '<table class="noborder" width="50%">'."\n\t".'<tr>'."\n\t\t".'<th>'."\n\t\t\t";
        if($current!=0){
            $Nav.= '<a href="?action=goTo&target='.($current-1); 
            $Nav.=  '&role='.($role);
            if ($optioncss != '')$Nav.=   '&amp;optioncss='.$optioncss;
            $Nav.=  '">  &lt;&lt; '.$langs->trans("Previous").' </a>'."\n\t\t";
        }
        $Nav.="</th>\n\t\t<th>\n\t\t\t";
	$Nav.=  '<form name="goTo" action="?action=goTo&role='.$role.'" method="POST" >'."\n\t\t\t";
        $Nav.=   $langs->trans("GoTo").': '.$htmlSelect."\n\t\t\t";;
	$Nav.=  '<input type="submit" value="Go" /></form>'."\n\t\t</th>\n\t\t<th>\n\t\t\t";
	if($current<count($selectList)){
            $Nav.=  '<a href="?action=goTo&target='.($current+1);
            $Nav.=  '&role='.($role);
            if ($optioncss != '') $Nav.=   '&amp;optioncss='.$optioncss;
            $Nav.=  '">'.$langs->trans("Next").' &gt;&gt; </a>';
        }
        $Nav.="\n\t\t</th>\n\t</tr>\n </table>\n";
        return $Nav;
}

/* Funciton to fect timesheet to be approuved.
    *  @param    int              	$current            current item of the select
    *  @param    int              	$selectList        list of the item showed in the navigation select
    *  @return   array(task_timesheet)                     result
    */    
function getTStobeApproved($current,$selectList){ // FIXME use the list tab as input and tta->fetch()
    global $db;
    if((!is_array($selectList) || !is_array($selectList[$current]['idList'])))return array();

    $listTTA=array();
    foreach($selectList[$current]['idList'] as $idTTA){
        $TTA= new Task_time_approval($db);
        $TTA->fetch($idTTA);
        $listTTA[]=$TTA;
    }
    return $listTTA;
}
 /*
 * function to get the Approval elible for this user
 * 
  *  @param    object           	$db             database objet
 *  @param    array(int)/int        $userids    	array of manager id 
  *  @return  array (int => String)  				array( ID => userName)
 */
function getSelectAps($subId, $tasks, $role){
    if((!is_array($subId) || !count($subId)) && $subId!='all' )return array();
    global $db,$langs;
   /* if(TIMESHEET_APPROVAL_BY_WEEK==1){
        $sql='SELECT COUNT(ts.date_start) as nb,ts.date_start as id,';
        $sql.=" DATE_FORMAT(ts.date_start,'".$langs->trans('Week')." %u (%m/%Y)') as label";
        $sql.=' FROM '.MAIN_DB_PREFIX.'project_task_time_approval as ts'; 
        $sql.=' JOIN '.MAIN_DB_PREFIX.'user as usr on ts.fk_userid= usr.rowid ';

    }else if(TIMESHEET_APPROVAL_BY_WEEK==0){
        $sql='SELECT COUNT(ts.fk_userid) as nb,ts.fk_userid as id,';
        $sql.=" CONCAT(usr.firstname,' ',usr.lastname) as label";
        $sql.=' FROM '.MAIN_DB_PREFIX.'project_task_time_approval as ts'; 
        $sql.=' JOIN '.MAIN_DB_PREFIX.'user as usr on ts.fk_userid= usr.rowid '; 
    }else{
        $sql="SELECT COUNT(ts.fk_userid) as nb,CONCAT(DATE_FORMAT(ts.date_start,' %m/%Y'),usr.firstname,' ',usr.lastname) as id,";
        $sql.=" CONCAT(usr.firstname,' ',usr.lastname,DATE_FORMAT(ts.date_start,' %m/%Y')) as label";
        $sql.=' FROM '.MAIN_DB_PREFIX.'project_task_time_approval as ts'; 
        $sql.=' JOIN '.MAIN_DB_PREFIX.'user as usr on ts.fk_userid= usr.rowid ';         
    }*/
    $sql="SELECT COUNT(ts.rowid) as nb, ";
  //  if(TIMESHEET_GROUP_OTHER_AP=="week"){
        $sql.=" CONCAT(ts.date_start, '-',pjt.`ref`) as id,";
        $sql.=" CONCAT(pjt.title, DATE_FORMAT(ts.date_start,'- ".$langs->trans('Week')." %v (%m/%Y) #')COLLATE utf8_unicode_ci) as label,";
        
/*    }else{
        $sql.=" CONCAT(DATE_FORMAT(ts.date_start,'%m/%Y'), '-',pjt.`ref`) as id,";
        $sql.=" CONCAT(pjt.title,' (', DATE_FORMAT(ts.date_start,'%m/%Y'),')') as label,";
    }*/
    $sql.=" GROUP_CONCAT(ts.rowid SEPARATOR ',') as idList";
    $sql.=' FROM '.MAIN_DB_PREFIX.'project_task_time_approval as ts'; 
    $sql.=' JOIN '.MAIN_DB_PREFIX.'projet_task as tsk on ts.fk_projet_task= tsk.rowid ';
    $sql.=' JOIN '.MAIN_DB_PREFIX.'projet as pjt on tsk.fk_projet=pjt.rowid ';
    $sql.=' WHERE (ts.status="SUBMITTED" OR ts.status="UNDERAPPROVAL" OR ts.status="CHALLENGED" )'; 
    $sql.=' AND recipient="'.$role.'"';
    if($subId!='all'){
        $sql.=' AND ts.fk_userid in ('.implode(',',$subId).')';
        if($role=='project'){
            $sql.=' AND tsk.rowid in ('.$tasks.') ';
        }
    }
    $sql.=' group by id ORDER BY id DESC, label '; 
    dol_syslog('timesheetAp::getSelectAps ', LOG_DEBUG);
    $list=array();
    $resql=$db->query($sql);
    
    if ($resql)
    {
        $i=0;
        $j=0;
        $num = $db->num_rows($resql);
        while ( $i<$num)
        {
            $obj = $db->fetch_object($resql);
            
            if ($obj)
            {
                $j=1;
                $nb=$obj->nb;
                $idsList=explode(',',$obj->idList);
                
                // split the nb in x line to avoid going over the max approval
                while($nb>TIMESHEET_MAX_TTA_APPROVAL){
                    $custIdList=  array_slice($idsList, $nb-TIMESHEET_MAX_TTA_APPROVAL, TIMESHEET_MAX_TTA_APPROVAL);
                    $list[]=array("id"=>$obj->id,"idList"=>$custIdList,"label"=>$obj->label.' ('.$j."/".ceil($obj->nb/TIMESHEET_MAX_TTA_APPROVAL).')',"count"=>TIMESHEET_MAX_TTA_APPROVAL);
                    $nb-=TIMESHEET_MAX_TTA_APPROVAL;
                    $j++;
                }
                $custIdList=  array_slice($idsList, 0, $nb);
                // at minimum a row shoud gnerate one option
                $list[]=array("id"=>$obj->id,"idList"=>$custIdList,"label"=>$obj->label.$obj->nb,' '.(($obj->nb>TIMESHEET_MAX_TTA_APPROVAL)?'('.$j.'/'.ceil($obj->nb/TIMESHEET_MAX_TTA_APPROVAL).')':''),"count"=>$nb);
            }
            $i++;
        }

    }
    else
    {
        $error++;
        dol_print_error($db);
        $list= array();
    }
      //$select.="\n";
      return $list;
 }
 
 function  getHTMLRows($objectArray){
     global $langs;
     $headers=array('Approval','Note','Tasks','User');
     if(!is_array($objectArray) || !is_object($objectArray[0])) return -1;
    echo '<tr class="liste_titre">';
    echo '<th>'.$langs->trans('Approval').'</th>';
    echo '<th>'.$langs->trans('Note').'</th>';
    echo '<th>'.$langs->trans('Task').'</th>';
    echo '<th>'.$langs->trans('User').'</th>';
    $weeklength=round(($objectArray[0]->date_end_approval-$objectArray[0]->date_start_approval)/SECINDAY);
    for ($i=0;$i<$weeklength;$i++)
    {
        $curDay=$objectArray[0]->date_start_approval+ SECINDAY*$i;
        echo"\t".'<th width="60px" style="text-align:center;" >'.$langs->trans(date('l',$curDay)).'<br>'.dol_print_date($curDay,'day')."</th>\n";
    }
    echo "<tr>\n";    
     foreach($objectArray as $key=> $object){
 //        $object->getTaskInfo();
         $object->getActuals();
         echo '<tr>';
           echo $object->getFormLine( $key,$headers,0,'-1'); 
         echo "<tr>\n";
     }
 }
 
 function uniordHex($u) {
    return strtoupper(bin2hex(iconv('UTF-8', 'UCS-2BE', $u)));
     /*
    $k = mb_convert_encoding($u, 'UCS-2LE', 'UTF-8');
    $k1 = ord(substr($k, 0, 1));
    $k2 = ord(substr($k, 1, 1));
    return dechex($k2 ).dechex($k1);*/
} 