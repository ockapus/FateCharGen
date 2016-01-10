-- Game consequences table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: November 26, 2015

-- Add game consequences table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_consequence (
    -- Unique ID
    game_consequence_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this consequence belongs to
    game_id int default NULL,  
    -- What label should be displayed on this consequence?
    game_consequence_label varchar(64) default NULL,
    -- What value should be displayed on this consequence?
    game_consequence_display_value int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgcs_game_id ON /*_*/fate_game_consequence (game_id);