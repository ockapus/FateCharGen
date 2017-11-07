-- Chargen pending stats table setup for FateCharGen
-- Originally created for Alien City/Dead Authors
-- porpentine@gmail.com
-- Last Update: October 24, 2017

-- Add chargen pending stats table
CREATE TABLE IF NOT EXISTS /*_*/fate_chargen_stat (
    -- Unique ID
    chargen_stat_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Character that this pending stat was set for
    fractal_id int DEFAULT NULL,
    -- Form name for the field we're storing a pending stat for
    field_name varchar(64) default NULL,
    -- Whatever value we got from this field
    field_value text default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fgs_fractal_id ON /*_*/fate_chargen_stat (fractal_id);
