-- Game mode table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: November 26, 2015

-- Add game mode table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_mode (
    -- Unique ID
    game_mode_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this mode belongs to
    game_id int default NULL,
    -- Name of this mode
    game_mode_label varchar(64) default NULL,
    -- What's the cost for characters using this mode?
    mode_cost int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgm_game_id ON /*_*/fate_game_mode (game_id);