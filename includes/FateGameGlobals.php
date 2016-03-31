<?php

class FateGameGlobals {
    const SKILL_DISTRIBUTION_PYRAMID = 1;
    const SKILL_DISTRIBUTION_COLUMNS = 2;
    const SKILL_DISTRIBUTION_MODES = 3;
    const SKILL_DISTRIBUTION_APPROACHES = 4;

    const STAT_ASPECT = 1;
    const STAT_SKILL = 2;
    const STAT_STUNT = 3;
    const STAT_STRESS = 4;
    const STAT_CONDITION = 5;
    const STAT_CONSEQUENCE = 6;
    const STAT_FATE = 7;
    const STAT_REFRESH = 8;
    const STAT_MODE = 9;
    
    function getSkillDistributionArray() {
        $array = array();
        $array[self::SKILL_DISTRIBUTION_PYRAMID] = 'Pyramid';
        $array[self::SKILL_DISTRIBUTION_COLUMNS] = 'Columns';
        $array[self::SKILL_DISTRIBUTION_MODES] = 'Modes';
        $array[self::SKILL_DISTRIBUTION_APPROACHES] = 'Approaches';
    
        return $array;
    }
    
    function getLadder() {
        $array = array();
        $array[-2] = 'Terrible';
        $array[-1] = 'Poor';
        $array[0]  = 'Mediocre';
        $array[1]  = 'Average';
        $array[2]  = 'Fair';
        $array[3]  = 'Good';
        $array[4]  = 'Great';
        $array[5]  = 'Superb';
        $array[6]  = 'Fantastic';
        $array[7]  = 'Epic';
        $array[8]  = 'Legendary';
        
        return $array;
    }
    
    function getConditionCategories() {
        $array = array();
        $array[1] = 'Fleeting';
        $array[2] = 'Sticky';
        $array[4] = 'Lasting';
        
        return $array;
    }
    
    function getModeLevels() {
        $array = array();
        $array[0] = 'Trained';
        $array[1] = 'Focused';
        $array[2] = 'Specialized';
        
        return $array;
    }
    
    function getStatLabels() {
        $array = array();
        $array[self::STAT_ASPECT] = 'Aspects';
        $array[self::STAT_SKILL] = 'Skills';
        $array[self::STAT_STUNT] = 'Stunts';
        $array[self::STAT_STRESS] = 'Stresses';
        $array[self::STAT_CONDITION] = 'Conditions';
        $array[self::STAT_CONSEQUENCE] = 'Consequences';
        $array[self::STAT_FATE] = 'Fate Points';
        $array[self::STAT_REFRESH] = 'Refresh Rate';
        $array[self::STAT_MODE] = 'Modes';
        
        return $array;
    }
    
    function getStatConsts() {
        $array = array();
        $array['aspect'] = self::STAT_ASPECT;
        $array['skill'] = self::STAT_SKILL;
        $array['stunt'] = self::STAT_STUNT;
        $array['stress'] = self::STAT_STRESS;
        $array['condition'] = self::STAT_CONDITION;
        $array['consequence'] = self::STAT_CONSEQUENCE;
        $array['fate'] = self::STAT_FATE;
        $array['refresh'] = self::STAT_REFRESH;
        $array['mode'] = self::STAT_MODE;
        
        return $array;
    }

    function getDisplayDate($wfDate) {
        $date = ($wfDate ? date('D, d M Y H:i:s', wfTimestamp(TS_UNIX, $wfDate)) : '&mdash;');
        return $date;
    }
}