-- Fractal stats table setup for FateCharGen extension
-- Created originally for aliencity.org
-- porpentine@gmail.com
-- Last Update: December 3, 2015

-- Add primary fractal stat table
CREATE TABLE IF NOT EXISTS /*_*/fate_fractal_stat (
    -- Unique ID
    fractal_stat_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Fractal this stat belongs to
    fractal_id int default NULL,
    -- What type of stat is this? 
    stat_type int default NULL,
    -- Label for this stat
    stat_label varchar(128) default NULL,
    -- Field for this stat
    stat_field varchar(128) default NULL,
    -- Display value for this stat
    stat_display_value int default NULL,
    -- Current value for this stat
    stat_value int default NULL,
    -- Maximum value for this stat 
    stat_max_value int default NULL,
    -- Description field for this stat
    stat_description text default NULL,
    -- Mode this skill belongs to -- fractal_stat_id
    stat_mode int default NULL,
    -- Category this Condition belongs to
    stat_category varchar(128) default NULL,
    -- Is this stat part of a shared fractal? If so, what is the ID?
    shared_fractal_id int default NULL,
    -- What order should this stat be displayed in compared with others in the same section?
    stat_ordinal int default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ffs_fractal_id ON /*_*/fate_fractal_stat (fractal_id);