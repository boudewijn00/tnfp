<?php

class Tnfp_Week {
    
    public $iWeekNumber = null;
    
    public $sCurrentStamp = null;
    
    public $sCurrentPerson = null;
    public $sCurrentStartStamp = null;
    public $sCurrentEndStamp = null;
    
    public $sCurrentEndTime = null;
    public $sCurrentStartTime = null;
    
    public $sThisPreviousPerson = null;
    
    public $sPreviousEndStamp = null;
    public $sPreviousTransferEndStamp = null;
    public $sEndOfWeekStamp = null;
    
    public $aNights = array();
    public $aDays = array();
    public $aBlocks = array();
    public $iCurrentBlockPosition = 0;
    
    public $sCalendarId = "";
    
    private $aOtherTypes = array('transfer','sport','school');
    
    public function __construct($iWeekNumber){
        
        $iWeekNumber = sprintf("%02s", $iWeekNumber);
        $this->iWeekNumber = $iWeekNumber;
        
        // by default the beginning of the week is the 'end' of the last block
        $this->sPreviousEndStamp = strtotime(date("Y")."W".$iWeekNumber." + 8 hour");

        $this->sCurrentStamp = strtotime(date("Y-m-d H:i:s"));
        
        // the end of the week in time (required to calc time between last transfer and end of week ( = last care block time)
        $this->sEndOfWeekStamp = strtotime("2013W".(sprintf("%02s", $iWeekNumber+1))." + 8 hour");
        
    }
    
    public function getBlocks($aEvents,$sCalendarId){
        
        $this->sCalendarId = $sCalendarId;
        
        $iEventsAmount = count($aEvents["current"])+1;
        
        // determine who has the care (based on the last event of the previous week)
        $aTransferEventLastWeek = $this->getLastTransferOfWeek($aEvents["previous"]);
        if(is_array($aTransferEventLastWeek)){
            $this->sCurrentPerson = $aTransferEventLastWeek["title"];
        } else {
            $this->sCurrentPerson = null;
        }
        
        // loop through amount of care blocks
        for($i = 0; $i<$iEventsAmount; $i++){
            
            $bEndOfWeek = false;
            $bSetEvent = false;
            $bWrapAroundNow = false;
            $bCareEventIsNow = false;
            $iNights = null;
            
            // calc the duration of the care part (between last event and this event)
            if(isset($aEvents["current"][$i])){
            
                $this->sCurrentStartStamp = strtotime($aEvents["current"][$i]["start_datetime"]);
                $this->sCurrentEndStamp = strtotime($aEvents["current"][$i]["end_datetime"]);
                
                $this->sCurrentStartTime = date("H:i",$this->sCurrentStartStamp);
                $this->sCurrentEndTime = date("H:i",$this->sCurrentEndStamp);
                
                // extract hours, we need this to cut off night time later
                $sCurrentStartHour = date("H",$this->sCurrentStartStamp);
                $sCurrentEndHour = date("H",$this->sCurrentEndStamp);
                
                // we dont use night time
                $bSetEvent = $this->cutOffNightTime($sCurrentStartHour,$sCurrentEndHour);
                
                if($bSetEvent){
                    
                    // calculate the care event duration
                    $sDiff = $this->sCurrentStartStamp - $this->sPreviousEndStamp;   
                 
                // event not in day range
                } else {
                    continue;
                }
            
            // if there is no transfer event; take the end of the week time (to calc the duration of care block)
            } else {
                
                // difference between the end of the week, and the last event
                $sDiff = $this->sEndOfWeekStamp - $this->sPreviousEndStamp;
                $bEndOfWeek = true;
                  
            }
            
            // is the current time in this care event
            if($this->sCurrentStamp > $this->sPreviousEndStamp && $this->sCurrentStamp < $this->sCurrentStartStamp){

                $bCareEventIsNow = true;

                if($this->isDuringDay()){
                    $bWrapAroundNow = true;
                }
                
            }
            
            $this->storeCareTimeInBlock($sDiff,$bWrapAroundNow,$bEndOfWeek);
            
            // events (transfer, sport, school)
            if($bSetEvent){
                $this->storeEventInBlock($aEvents["current"][$i]);
            }
            
            // store amount of nights for care period in now or in future
            if(($bWrapAroundNow || $bCareEventIsNow || 
                ($this->sPreviousTransferEndStamp >= strtotime("2013W".$this->iWeekNumber." + 8 hour") 
                && $this->sPreviousTransferEndStamp >= $this->sCurrentStamp && ($bEndOfWeek || $aEvents["current"][$i]["text"] == "#transfer")))){
                
                //$this->calcNights($bEndOfWeek, $bWrapAroundNow, $bCareEventIsNow);
                
            }
            
            if(isset($aEvents["current"][$i])){
                
                // set the new block end time
                $this->sPreviousEndStamp = $this->sCurrentEndStamp;
                
                // save separate the end stamp from the previous transfer event
                if($aEvents["current"][$i]["text"] == "#transfer"){

                    // set the new block end time
                    $this->sPreviousTransferEndStamp = $this->sCurrentEndStamp;

                }
                
            }
            
            
             
        }
        
        return $this->aBlocks; 
        
    }
    
    protected function storeCareTimeInBlock($sDiff,$bWrapAroundNow,$bEndOfWeek){
        
        // subtract the night part (we dont show that)
        $sAmountOfDaysBetween = round($sDiff / (60*60*24));
        $sCurrentBlockMinutesWithNights = round(abs($sDiff) / 60,2);
        $sCurrentBlockMinutes = $sCurrentBlockMinutesWithNights - ($sAmountOfDaysBetween * 720);

        // store the previous period between this transfer and the last as care
        if($sCurrentBlockMinutes > 0){

            // split block in 3 around now
            if($bWrapAroundNow){

                $aCurrentBlocks = $this->splitBlockAroundNow($sAmountOfDaysBetween,$bEndOfWeek);

                foreach($aCurrentBlocks AS $aCurrentBlock){

                    // store the care event
                    $this->aBlocks[$this->iCurrentBlockPosition]["minutes"] = $aCurrentBlock["minutes"];                       
                    $this->aBlocks[$this->iCurrentBlockPosition]["type"] = $aCurrentBlock["type"];
                    $this->aBlocks[$this->iCurrentBlockPosition]["person"] = Model_User::getUserGender($aCurrentBlock["person"]);                      
                    $this->iCurrentBlockPosition++;

                }  

            // nothing to split
            } else {

                // store the care event
                $this->aBlocks[$this->iCurrentBlockPosition]["minutes"] = $sCurrentBlockMinutes;
                $this->aBlocks[$this->iCurrentBlockPosition]["type"] = "care";
                $this->aBlocks[$this->iCurrentBlockPosition]["person"] = Model_User::getUserGender($this->sCurrentPerson);
                $this->iCurrentBlockPosition++;

            }

        }

        
    }
    
    public function storeEventInBlock($aEvent){
        
        // calculate the transfer duration
        $sBlockMinutes = round(abs($this->sCurrentEndStamp - $this->sCurrentStartStamp) / 60,2);

         $sLocation = "";
        if(key_exists("location", $aEvent)){
            $sLocation = $aEvent["location"];
        }

        $sTitle = $aEvent["title"];
        
        if($aEvent["text"] == "#transfer"){

            $this->sPreviousPerson = $this->sCurrentPerson;
            $this->sCurrentPerson = $aEvent["title"];
            $sType = "transfer";

        } elseif($aEvent["text"] == "#sport"){
            $sType = "sport";
            $this->aBlocks[$this->iCurrentBlockPosition]["person"] = Model_User::getUserGender($this->sCurrentPerson);
        } elseif($aEvent["text"] == "#school"){
            $sType = "school";
            $this->aBlocks[$this->iCurrentBlockPosition]["person"] = Model_User::getUserGender($this->sCurrentPerson);
        }

        // store the transfer event 
        $this->aBlocks[$this->iCurrentBlockPosition]["minutes"] = $sBlockMinutes;
        $this->aBlocks[$this->iCurrentBlockPosition]["type"] = $sType; 
        $this->aBlocks[$this->iCurrentBlockPosition]["start"] = $this->sCurrentStartTime; 
        $this->aBlocks[$this->iCurrentBlockPosition]["end"] = $this->sCurrentEndTime; 
        $this->aBlocks[$this->iCurrentBlockPosition]["location"] = $sLocation;
        $this->aBlocks[$this->iCurrentBlockPosition]["title"] = $sTitle;

        $this->iCurrentBlockPosition++;
        
    }
    
    public function processBlocksInDaysAndNights($aBlocks){
        
        $aDays = array();
        $aNights = array();
        $k = 0;
        
        for($i = 0; $i<7; $i++){
            
            $aDays[$i] = array();
            $j = 0;    
            
            foreach($aBlocks AS $iBlock => $aBlock){

                $iTotalStored = $this->calcPlaceToStore($aDays[$i]);
                $iPlaceToStore = (12 * 60) - $iTotalStored;
                
                if($iPlaceToStore > 0){

                    // calc how much minutes left to store in this day
                    if($aBlock["minutes"] < $iPlaceToStore){

                        $aBlock = array_shift($aBlocks);
                        //unset($aBlocks[0]);
                        $sMinutes = $aBlock["minutes"]; 

                    } else {

                        $sMinutes = $iPlaceToStore;
                        $aBlocks[0]["minutes"] -= $iPlaceToStore;

                    }
                    
                    // store minutes, type and location
                    $aDays[$i][$j]["minutes"] = $sMinutes;
                    $aDays[$i][$j]["type"] = $aBlock["type"];
                    
                    if(in_array($aBlock["type"],$this->aOtherTypes)){
                        
                        $aDays[$i][$j]["location"] = $aBlock["location"];
                        $aDays[$i][$j]["title"] = $aBlock["title"];
                        $aDays[$i][$j]["start"] = $aBlock["start"];
                        $aDays[$i][$j]["end"] = $aBlock["end"];
                        
                    }

                    // store the person
                    if(isset($aBlock["person"])){
                        $aDays[$i][$j]["person"] = $aBlock["person"];
                    } else {
                        $aDays[$i][$j]["person"] = "";
                    }
                    
                    $j++;

                } else {
                    break;
                }
                
                
                
            }
                          
        }
        
        $this->aDays = $aDays;
        
    }
    
    public function calcPlaceToStore($aDay){
        
        $iTotalStored = null;
        
        foreach($aDay AS $aBlock){
            $iTotalStored += $aBlock["minutes"];
        }
        
        return $iTotalStored;
        
    }
    
    protected function calcNights($bEndOfWeek,$bWrapAroundNow,$bCareEventIsNow){
        
        $iNights = null;
        
        if($bEndOfWeek){

            $aTransferEventNextWeek = $this->getFirstTransferOfWeek(sprintf("%02s", $this->iWeekNumber+1));
            $sNextStartStamp = strtotime($aTransferEventNextWeek["start_datetime"]);

            // from now, or the end of the previous transfer event
            $sBeginStamp = ($bWrapAroundNow || $bCareEventIsNow) ? $this->sCurrentStamp : $this->sPreviousTransferEndStamp;

            // nights from now or end of last transfer of week, till first transfer of next week
            $iNights = $this->calcNightsBetween($sBeginStamp,$sNextStartStamp);
            $sNightPerson = $this->sCurrentPerson;

        } elseif($bWrapAroundNow || $bCareEventIsNow){

            // nights from now till start of next transfer
            $iNights = $this->calcNightsBetween($this->sCurrentStamp,$this->sCurrentStartStamp);
            $sNightPerson = $this->sPreviousPerson;
            
        } elseif(!empty($this->sPreviousTransferEndStamp)) {
            $iNights = $this->calcNightsBetween($this->sPreviousTransferEndStamp,$this->sCurrentStartStamp);
            $sNightPerson = $this->sPreviousPerson;
        }

        // if there is a night amount to store
        if($iNights > 0){
            $this->aNights[] = array("nights" => $iNights, "person" => $sNightPerson);
        }
        
    }
    
    public function calcNightsBetween($sPreviousTransferStamp,$sNextTransferStamp){
        
        $sDateNow = date("Y-m-d H:i", $sPreviousTransferStamp);
        $sDateNext = date("Y-m-d H:i",  $sNextTransferStamp);
        
        $sDiff = $sNextTransferStamp - $sPreviousTransferStamp;
        $fNights = $sDiff / (60*60*24);
        $iNights = round($fNights);
        return $iNights;
        
    }
    
    public function getFirstTransferOfWeek($iWeekNumber,$iAttempt = 0){
        
        // goes back maximum 3 weeks
        if($iAttempt < 3){
        
            $oCalendar = new Model_Calendar($this->sCalendarId);
            $aEvents = $oCalendar->getWeekEvents($iWeekNumber);

            $iEventAmount = count($aEvents);
            
            if($iEventAmount > 0){
                
                for($i = 0; $i <= $iEventAmount; $i++){
                    if(isset($aEvents[$i])){
                        if($aEvents[$i]["text"] == "#transfer"){
                            $aTransferEvent = $aEvents[$i];
                            return $aTransferEvent;
                        }
                    }
                }
                
            }
                
            if($iEventAmount == 0){
                $iAttempt++;
                $iWeekNumber--;
                $aTransferEvent = $this->getFirstTransferOfWeek($iWeekNumber,$iAttempt);
            }

        
        } else {
            return false;
        }
        
    }
    
    public function getLastTransferOfWeek($aEvents){
        
        
        $iEventAmount = count($aEvents);

        if($iEventAmount > 0){

            for($i = $iEventAmount; $i > 0; $i--){
                if($aEvents[$i-1]["text"] == "#transfer"){
                    $aTransferEvent = $aEvents[$i-1];
                    return $aTransferEvent;
                }
            }

        }

        return false;
        
    }
    
    public function splitBlockAroundNow($sAmountOfDaysBetween,$bEndOfWeek = false){
        
        $aBlocks = array();
        
        // the difference between now and the end of the previous event
        $sFirstDiff = $this->sCurrentStamp - $this->sPreviousEndStamp;
        $sFirstDaysBetween = round($sFirstDiff / (60*60*24));
        
        // if were calcing towards the end of the week, remove the last 4 hour of the week (20:00 - 24:00)
        if($bEndOfWeek){
            $sEndOfWeekStamp = strtotime("2013W".($this->iWeekNumber+1));
            $sThirdDiff = $sEndOfWeekStamp - $this->sCurrentStamp;
            $sThirdDiff -= (4 * 3600);
        
        // the difference between the start of the current event and the now
        } else {
            $sThirdDiff = $this->sCurrentStartStamp - $this->sCurrentStamp;
        }
        
        // slice 30 minutes off (use for now event)
        $sThirdDiff -= (10 * 60);
        $sThirdDaysBetween = round($sThirdDiff / (60*60*24));
        
        // get the first difference in minutes (minus the minutes for the nights)
        $aBlocks[1]["minutes"] = round(abs($sFirstDiff) / 60,2) - ($sFirstDaysBetween * 720);
        $aBlocks[1]["type"] = "care";
        $aBlocks[1]["person"] = $this->sCurrentPerson;
        
        $aBlocks[2]["minutes"] = 10;
        $aBlocks[2]["type"] = "now";
        $aBlocks[2]["person"] = $this->sCurrentPerson;
        
        // get the third difference in minutes (minus the minutes for the nights)
        $aBlocks[3]["minutes"] = round(abs($sThirdDiff) / 60,2) - ($sThirdDaysBetween * 720);
        $aBlocks[3]["type"] = "care";
        $aBlocks[3]["person"] = $this->sCurrentPerson;
        
        return $aBlocks;
        
    }
    
    protected function isDuringDay(){
        
        $sCurrentHour = date("H",$this->sCurrentStamp);
        
        if($sCurrentHour >= "08" && $sCurrentHour < "20"){
            return true;
        } else {
            return false;
        }
        
    }
    
    /**
     * we only want time between 08 and 20 hour, the rest is considered as night time and cut off
     * @param type $sCurrentStartHour
     * @param type $sCurrentEndHour
     * @return boolean 
     */
    protected function cutOffNightTime($sCurrentStartHour,$sCurrentEndHour){
        
        $bSetEvent = false;
        
        // cut off everything before 08 and after 20 o'clock
        if($sCurrentStartHour >= "08" && $sCurrentEndHour <= "20"){  
            $bSetEvent = true;
        } elseif($sCurrentEndHour > "08" && $sCurrentEndHour < "20"){
            $this->sCurrentStartStamp = strtotime(date("Y-m-d",$this->sCurrentStartStamp). "+ 8 hour");
            $bSetEvent = true;
        } elseif($sCurrentStartHour > "08" && $sCurrentStartHour < "20"){
            $this->sCurrentEndStamp = strtotime(date("Y-m-d",$this->sCurrentEndStamp)."+ 20 hour");
            $bSetEvent = true;
        }
        
        return $bSetEvent;
        
    }
    
    
}


?>
