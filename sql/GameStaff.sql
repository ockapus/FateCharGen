-- Game staff table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: June 10, 2016

-- Add game staff table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_staff (
    -- Unique ID
    game_staff_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this skill belongs to
    game_id int default NULL,
    -- Register id of the person with staff permissions for the game
    register_id int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgs_game_id ON /*_*/fate_game_staff (game_id);