<?php
/* Copyright (C) 2007-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2016 delcroip <delcroip@gmail.com>
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

/**
 *   	\file       dev/skeletons/skeleton_page.php
 *		\ingroup    mymodule othermodule1 othermodule2
 *		\brief      This file is an example of a php page
 *					Put here some comments
 */


// hide left menu
//$_POST['dol_hide_leftmenu']=1;
// Change this following line to use the correct relative path (../, ../../, etc)
include 'lib/includeMain.lib.php';
require_once 'lib/timesheet.lib.php';
require_once 'lib/generic.lib.php';
require_once 'class/timesheetUser.class.php';

$userId            = GETPOST('userid');

$userId=  is_object($user)?$user->id:$user;
$action             = GETPOST('action');
//should return the XMLDoc
$ajax               = GETPOST('ajax');
$xml               = GETPOST('xml');
$id               = GETPOST('id');
$offset=GETPOST('offset','int');
if($offset=='')$offset=0;
//$toDate                 = GETPOST('toDate');

$timestamp=GETPOST('timestamp');


//$userid=  is_object($user)?$user->id:$user;




// Load traductions files requiredby by page
//$langs->load("companies");
$langs->load("main");
$langs->load("projects");
$langs->load('timesheet@timesheet');


$timesheetUser= new timesheetUser($db);
/*******************************************************************
* ACTIONS
*
* Put here all code to do according to value of "action" parameter
********************************************************************/
if($action== 'submit'){
         if (isset($_SESSION['timesheetAp'][$timestamp]))
        {
           // $_SESSION['timesheetAp'][$timestamp]['tsUser']
            $tsApproved=0;
            $tsRejected=0;
            $ret=0;
            $errors=0;
            //$timesheetUser->db=$db;
           // var_dump(unserialize($timesheetUser->taskTimesheet[0])->tasklist);
            if (!empty($_POST['approval']))
            {   
                $approvals=$_POST['approval'];
                foreach($_SESSION['timesheetAp'][$timestamp]['tsUser'] as $key => $tsUser){
                    switch($approvals[$key]){
                        case 'Approved':
                           $ret=$timesheetUser->setAppoved($user,$key); 
                            if(ret<0)$errors++;
                            else $tsApproved++;
                            break;
                        case 'Rejected':
                            $ret=$timesheetUser->setRejected($user,$key);
                            if(ret<0)$errors++;
                            else $tsRejected++;
                            break;
                        case 'Submitted':
                        default:
                            break;
                                
                    }
                    
                }
                $offset-=($tsApproved+$tsRejected);       

				//$ret =postActuals($db,$user,$_POST['task'],$timestamp);
                    if($ret>0)
                    {
                        if($tsApproved)setEventMessage($langs->transnoentitiesnoconv("NumberOfTimesheetApproved").$tsApproved);
                        if($tsRejected)setEventMessage($langs->transnoentitiesnoconv("NumberOfTimesheetRejected").$tsRejected);
                        if($errors)setEventMessage($langs->transnoentitiesnoconv("NumberOfErrors").$errors);
                    }
                    else
                    {
                        if($errors==0){
                            setEventMessage($langs->transnoentitiesnoconv("NothingChanged"),'warning');
                        }else {
                            setEventMessage( $langs->transnoentitiesnoconv("InternalError").':'.$ret,'errors');
                        }
                    }        
            }else
                    setEventMessage( $langs->transnoentitiesnoconv("NothingChanged"),'warning');// shoudn't happend
        }else
                setEventMessage( $langs->transnoentitiesnoconv("InternalError"),'errors');
	

}
if(!empty($timestamp)){
       unset($_SESSION['timesheetAp'][$timestamp]);
}
$timestamp=time();
$level=5+1;
$objectArray=getTStobeApproved($level,$offset,'team',$userId);/*fixme: create function*/
$level-=1; // 
if(is_array($objectArray)){
$firstTimesheetUser=reset($objectArray);
$curUser=$firstTimesheetUser->userId;
$nextUser=$firstTimesheetUser->userId;
}
$i=0;





/***************************************************
* VIEW
*
* Put here all code to build page
****************************************************/

if($xml){
    //renew timestqmp
    ob_clean();
   header("Content-type: text/xml; charset=utf-8");
  //  echo $timesheetUser->GetTimeSheetXML($userId,5); //fixme
    exit;
}
$morejs=array("/timesheet/js/timesheetAjax.js","/timesheet/js/timesheet.js");
llxHeader('',$langs->trans('Timesheet'),'','','','',$morejs);
//calculate the week days
$Form ='';
//tmstp=time();
if(is_object($firstTimesheetUser)){
    $Form .=$firstTimesheetUser->getHTMLFormHeader($ajax);
    foreach($objectArray as $key=> $timesheetUser){
        if($firstTimesheetUser->userId==$timesheetUser->userId){
            $i++;//use for the offset
            if($i<$level){
                $timesheetUser->fetchTaskTimesheet();
        //$ret+=$this->getTaskTimeIds(); 
        //FIXME module holiday should be activated ?
                $timesheetUser->fetchUserHoliday(); 
                $Form .=$timesheetUser->userName." - ".$timesheetUser->yearWeek;
                $Form .=$timesheetUser->getHTMLHeader(false);
                $Form .=$timesheetUser->getHTMLHolidayLines(false);
                //$Form .=$timesheetUser->getHTMLTotal('totalT');
                $Form .=$timesheetUser->getHTMLtaskLines(false);
                $Form .=$timesheetUser->getHTMLTotal();/*FIXME*/
                $_SESSION['timesheetAp'][$timestamp]['tsUser'][$timesheetUser->id]=array('status'=>$timesheetUser->status,'Target'=>$timesheetUser->target);
                $Form .= '</table>';
                $Form .= '</br>'."\n";
                $Form .= '<label class="butAction"><input type="radio"  name="approval['.$timesheetUser->id.']" value="Approved" ><span>'.$langs->trans('Approved').'</span></label>'."\n";/*FIXME*/
                $Form .= '<label class="butAction"><input type="radio"  name="approval['.$timesheetUser->id.']" value="Rejected" ><span>'.$langs->trans('Rejected').'</span></label>'."\n";/*FIXME*/
                $Form .= '<label class="butAction"><input type="radio"  name="approval['.$timesheetUser->id.']" value="Submitted" checked ><span>'.$langs->trans('Submitted').'</span></label>'."\n";/*FIXME*/
                $Form .= '</br></br></br>'."\n";
            }
        }else{
            $nextUser=$timesheetUser->userId;
            break;
        }
    }
    $offset+=$i;
    $Form .=$firstTimesheetUser->getHTMLFooterAp($offset,$timestamp);
}


//Javascript
$timetype=TIMESHEET_TIME_TYPE;
//$Form .= ' <script type="text/javascript" src="timesheet.js"></script>'."\n";
$Form .= '<script type="text/javascript">'."\n\t";
$Form .='updateAll(\''.$timetype.'\');';
$Form .= "\n\t".'</script>'."\n";
// $Form .='</div>';//TimesheetPage
print $Form;

// End of page
llxFooter();
$db->close();

function getTStobeApproved($level,$offset,$role,$userid){
global $db;
        //if($role='team')
        $subId=get_subordinate($db,$userid, 2,'',$role);

        $sql ="SELECT * ";
        $sql.=" FROM ".MAIN_DB_PREFIX."timesheet_user as tu"; 
        $sql.=' WHERE tu.status="SUBMITTED" AND fk_userid in ('.implode(',',$subId).')';
        $sql.=' AND target="'.$role.'"';
        $sql.=' ORDER BY year_week_date DESC';
        $sql.=' LIMIT '.$level;
        $sql.=' OFFSET '.$offset;

        dol_syslog("timesheet::getTStobeApproved sql=".$sql, LOG_DEBUG);
     
   
    $resql=$db->query($sql);
    if ($resql)
    {
            $num = $db->num_rows($resql);
            $i = 0;
            // Loop on each record found, so each couple (project id, task id)
            while ($i < $num)
            {
                    $error=0;
                    $obj = $db->fetch_object($resql);
                   
                    $tsList[$i] = NEW timesheetUser($db,$obj->fk_userid);
                    $tsList[$i]->id    = $obj->rowid;
                    //$tsList[$i]->userId = $obj->fk_userid;
                    $tsList[$i]->year_week_date = $tsList[$i]->db->jdate($obj->year_week_date);
                    $tsList[$i]->status = $obj->status;
                    $tsList[$i]->target = $obj->target;
                    $tsList[$i]->project_tasktime_list = $obj->fk_project_tasktime_list;
                    $tsList[$i]->user_approval = $obj->fk_user_approval;
                    $tsList[$i]->date_creation = $tsList[$i]->db->jdate($obj->date_creation);
                    $tsList[$i]->date_modification = $tsList[$i]->db->jdate($obj->date_modification);
                    $tsList[$i]->user_creation = $obj->fk_user_creation;
                    $tsList[$i]->user_modification = $obj->fk_user_modification;            
                    $tsList[$i]->yearWeek=  date('Y\WW',$tsList[$i]->year_week_date);
                    $tsList[$i]->whitelistmode=2; // no impact
                    $i++;
            }
            $db->free($resql);
     
                return $tsList;

    }else
    {
            dol_print_error($db);
            return -1;
    }
}
?>
