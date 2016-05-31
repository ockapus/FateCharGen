-- Game turn order table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: May 25, 2016

-- Add game turn order table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_turn_order (
    -- Unique ID
    game_turn_order_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this skill belongs to
    game_id int default NULL,
    -- Boolean flag: is this skill used for determining order of physical or mental challenges?
    is_physical tinyint default NULL,
    -- Skill ID of the relevant skill
    skill_id int default NULL,
    -- What order should this skill be considered in when determining turn order (primary, secondary, etc)
    ordinal int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgto_game_id ON /*_*/fate_game_turn_order (game_id);