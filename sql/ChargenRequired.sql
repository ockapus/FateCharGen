-- Chargen requirements table setup for FateCharGen
-- Originally created for Alien City/Dead Authors
-- porpentine@gmail.com
-- Last Update: October 15, 2017

-- Add chargen requirements table
CREATE TABLE IF NOT EXISTS /*_*/fate_chargen_required (
    -- Unique ID
    required_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Chargen section this requirement belongs to
    chargen_id int DEFAULT NULL,
    -- The id of the game level stat definition that's required here
    parent_id int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fcr_chargen_id ON /*_*/fate_chargen_required (chargen_id);
