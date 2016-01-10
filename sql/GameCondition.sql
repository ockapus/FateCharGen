-- Game conditions table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: November 26, 2015

-- Add game conditions table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_condition (
    -- Unique ID
    game_condition_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this condition belongs to
    game_id int default NULL,  
    -- What label should be displayed on this condition?
    game_condition_label varchar(64) default NULL,
    -- What value should be displayed on this condition?
    game_condition_display_value int default NULL,
    -- What category should this condition be displayed under?
    game_condition_category varchar(64) default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgcd_game_id ON /*_*/fate_game_condition (game_id);