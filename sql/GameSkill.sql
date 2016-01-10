-- Game skills table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: November 26, 2015

-- Add game skills table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_skill (
    -- Unique ID
    game_skill_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this skill belongs to
    game_id int default NULL,
    -- Name of this skill
    game_skill_label varchar(64) default NULL,
    -- If we're using the mode system (Atomic Robo), how much does this skill cost for weird modes?
    mode_cost int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgsk_game_id ON /*_*/fate_game_skill (game_id);