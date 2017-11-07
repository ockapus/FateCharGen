-- Chargen help table setup for FateCharGen
-- Originally created for Alien City/Dead Authors
-- porpentine@gmail.com
-- Last Update: October 24, 2017

-- Add chargen help table
CREATE TABLE IF NOT EXISTS /*_*/fate_chargen_help (
    -- Unique ID
    help_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Chargen section this help text belongs to
    chargen_id int DEFAULT NULL,
    -- The id of the game level stat definition this applies to
    parent_id int default NULL,
    -- Text that we want to display as help for this stat
    help_text text default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fch_chargen_id ON /*_*/fate_chargen_help (chargen_id);
