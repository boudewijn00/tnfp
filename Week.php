<?php

class Tnfp_Week {
      
    private $aOtherTypes = array('transfer','sport','school');
    
    public function getBlocksInDaysAndNights($aBlocks){
        
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
        
        return $aDays;
        
    }
    
    public function calcPlaceToStore($aDay){
        
        $iTotalStored = null;
        
        foreach($aDay AS $aBlock){
            $iTotalStored += $aBlock["minutes"];
        }
        
        return $iTotalStored;
        
    }
    
}


?>
