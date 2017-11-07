-- Chargen table setup for FateCharGen
-- Originally created for Alien City/Dead Authors
-- porpentine@gmail.com
-- Last Update: October 15, 2017

-- Add chargen table
CREATE TABLE IF NOT EXISTS /*_*/fate_chargen (
    -- Unique ID
    chargen_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID that this chargen section belongs to
    game_id int default NULL,
    -- What order this section should be displayed in
    ordinal int default NULL,
    -- What stat type is set in this section?
    stat_type int default NULL,
    -- What to label this section?
    chargen_label varchar(64) default NULL,
    -- Basic instruction block to display at the top of the page
    instructions text default NULL,
    -- Boolean flag: do you have to fill out some number or set of stats for this section?
    has_requirements tinyint default NULL,
    -- How many of this stat type do you have to set to be valid? If this is 0
    -- but has_requirements is true, then reference chargen_required table
    requirement_count int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fc_game_id ON /*_*/fate_chargen (game_id);
