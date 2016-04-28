-- Pending fractal stats table setup for FateCharGen extension
-- Created originally for aliencity.org
-- porpentine@gmail.com
-- Last Update: April 27, 2016

-- Add pending fractal stat table
CREATE TABLE IF NOT EXISTS /*_*/fate_pending_stat (
    -- Unique ID
    pending_stat_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
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
    -- For Modes, the id of the game level definition 
    parent_id int default NULL,
    -- When was this stat last modified?
    modified_date varbinary(14) default NULL,
    -- Has this stat been denied? If so, why?
    denied_reason text default NULL,
    -- User ID of the staff member who denied this requested stat
    denied_id int default NULL,
    -- ID of original stat, if this is an update
    original_stat_id int default NULL 
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fps_fractal_id ON /*_*/fate_pending_stat (fractal_id);
