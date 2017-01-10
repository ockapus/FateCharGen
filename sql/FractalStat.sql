-- Fractal stats table setup for FateCharGen extension
-- Created originally for aliencity.org
-- porpentine@gmail.com
-- Last Update: December 11, 2016

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
    -- Is this stat part of a shared fractal? If so, what is the ID?
    shared_fractal_id int default NULL,
    -- Boolean flag: when using the mode system, this is a discipline of a primary skill (ie Engineering, for Science)
    is_discipline tinyint default NULL,
    -- Boolean flag: should this stat be hidden, even if sheets are visible? For now, interface only for stunts 
    is_secret tinyint default NULL,
    -- Id of the game level definition for various stats (modes, aspects with labels, skills, etc)
    parent_id int default NULL,
    -- When was this stat last modified?
    modified_date varbinary(14) default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ffs_fractal_id ON /*_*/fate_fractal_stat (fractal_id);