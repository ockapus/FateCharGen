-- Game stresses table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: November 26, 2015

-- Add game stresses table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_stress (
    -- Unique ID
    game_stress_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this stress track belongs to
    game_id int default NULL,
    -- Name of this stress track
    game_stress_label varchar(64) default NULL,
    -- What order should this stress track appear on a character's sheet?
    game_stress_ordinal int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgst_game_id ON /*_*/fate_game_stress (game_id);