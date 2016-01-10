-- Game mode skills table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: November 26, 2015

-- Add game mode skills table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_mode_skill (
    -- Unique ID
    game_mode_skill_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Mode ID this skill belongs to
    game_mode_id int default NULL,
    -- Skill ID for the skill that comes with this mode
    game_skill_id int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgms_mode_id ON /*_*/fate_game_mode_skill (game_mode_id);