-- Game aspects table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: April 24, 2016

-- Add game aspects table
CREATE TABLE IF NOT EXISTS /*_*/fate_game_aspect (
    -- Unique ID
    game_aspect_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID this aspect belongs to
    game_id int default NULL,
    -- What label should be displayed for this chargen aspect?
    game_aspect_label varchar(64) default NULL,
    -- Boolean: should this aspect be marked as belonging to a shared resource of some sort?
    is_shared tinyint default NULL,
    -- Boolean: is this aspect a 'secret', to be hidden even if sheets are public in this game?
    is_secret tinyint default NULL,
    -- Boolean: is this aspect one that can only be changed during a major milestone?
    is_major tinyint default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fga_game_id ON /*_*/fate_game_aspect (game_id);