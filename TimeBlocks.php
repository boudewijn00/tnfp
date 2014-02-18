<?php

class Tnfp_TimeBlocks {
    
    public $iWeekNumber = null;                 // the weeknumber for the current week
    
    public $sCurrentPerson = null;              // the person who is the owner of the last transfer event
    public $sCurrentStamp = null;               // the current time in timestamp
    
    public $sCurrentStartStamp = null;          // start of current event in timestamp
    public $sCurrentEndStamp = null;            // end of current event in timestamp
    
    public $sCurrentEndTime = null;             // end of current event in time (H:i) format
    public $sCurrentStartTime = null;           // start of current event in time (H:i) format
    
    public $sPreviousEndStamp = null;           // the end time of the previous event in timestamp
    public $sPreviousTransferEndStamp = null;   // the end time of the previous transfer event in timestamp
    
    public $sEndOfWeekStamp = null;             // end of week time (sunday 20:00) in timestamp
    
    public $aNights = array();                  // array of nights between events
    public $aBlocks = array();                  // array of timeblocks between events
    public $iCurrentBlockPosition = 0;          // current position (int) the time block has in the blocks array
    
    public function __construct($iWeekNumber){
        
        $iWeekNumber = sprintf("%02s", $iWeekNumber);
        $this->iWeekNumber = (int) $iWeekNumber;
        
        // by default the beginning of the week is the 'end' of the last block
        $this->sPreviousEndStamp = (string) strtotime(date("Y")."W".$iWeekNumber." + 8 hour");

        $this->sCurrentStamp = (string) strtotime(date("Y-m-d H:i:s"));
        
        // the end of the week in time (required to calc time between last transfer and end of week ( = last care block time)
        $this->sEndOfWeekStamp = (string) strtotime("2013W".(sprintf("%02s", $iWeekNumber+1))." + 8 hour");
        
    }
    
    /**
     * provide events for the previous, current and next week.
     * based on these events, the time between is calculated
     * every event tagged as #transfer, will change the 'owner' of the time between
     * @param array $aEvents
     */
    public function setTimeBlocksBetweenEvents(Array $aEvents){
        
        $iEventsAmount = (int) count($aEvents["current"])+1;
        
        // determine who has the care (based on the last event of the previous week)
        $aTransferEventLastWeek = $this->getLastTransferOfWeek($aEvents["previous"]);
        if(is_array($aTransferEventLastWeek)){
            $this->sCurrentPerson = (string) $aTransferEventLastWeek["title"];
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
        
    }
    
    private function getLastTransferOfWeek($aEvents){
        
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
    
    /**
     * store the time in between two events in a block
     * blocks are placed in the aBlocks property of the Week class
     * @param string $sDiff
     * @param boolean $bWrapAroundNow
     * @param boolean $bEndOfWeek 
     */
    private function storeCareTimeInBlock($sDiff,$bWrapAroundNow,$bEndOfWeek){
        
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
    
    /**
     * place the event in a block
     * an event can be tagged as transfer, or an activity tagged as sport or school
     * @param array $aEvent 
     */
    private function storeEventInBlock($aEvent){
        
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
    
    private function calcNights($bEndOfWeek,$bWrapAroundNow,$bCareEventIsNow){
        
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
    
    private function calcNightsBetween($sPreviousTransferStamp,$sNextTransferStamp){
        
        $sDateNow = date("Y-m-d H:i", $sPreviousTransferStamp);
        $sDateNext = date("Y-m-d H:i",  $sNextTransferStamp);
        
        $sDiff = $sNextTransferStamp - $sPreviousTransferStamp;
        $fNights = $sDiff / (60*60*24);
        $iNights = round($fNights);
        return $iNights;
        
    }
    
    /**
     * if the current moment, is in this time block
     * then divide the time block in 3 parts (before, now and after)
     * @param string $sAmountOfDaysBetween
     * @param boolean $bEndOfWeek
     * @return array $aBlocks
     */
    private function splitBlockAroundNow($sAmountOfDaysBetween,$bEndOfWeek = false){
        
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
    
    /**
     * determine if the current set time (stamp) is during the day (between 08 and 20)
     * @return boolean 
     */
    private function isDuringDay(){
        
        $sCurrentHour = date("H",$this->sCurrentStamp);
        
        if($sCurrentHour >= "08" && $sCurrentHour < "20"){
            return true;
        } else {
            return false;
        }
        
    }
    
    /**
     * we only want time between 08 and 20 hour, the rest is considered as night time and cut off
     * @param string $sCurrentStartHour
     * @param string $sCurrentEndHour
     * @return boolean $bSetEvent 
     */
    private function cutOffNightTime($sCurrentStartHour,$sCurrentEndHour){
        
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
