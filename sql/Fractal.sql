-- Fractal table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: April 29, 2016

-- Add primary fractal table
CREATE TABLE IF NOT EXISTS /*_*/fate_fractal (
    -- Unique ID
    fractal_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Name of this fractal; if a character, use their canon name instead
    fractal_name varchar(128) default NULL,
    -- game ID this Fractal belongs to
    game_id int default NULL,
    -- Register ID this Fractal belongs to, if a character
    register_id int default NULL,
    -- arbitrary fractal type
    fractal_type varchar(32) default NULL,
    -- Boolean flag: stats for this fractal should only be displayed to owner or GM
    is_private tinyint default NULL,

    -- TODO:
    -- Figure out how to represent one fractal 'owning' another, both singular and shared
    -- Possibly a whole separate table

    -- When was this fractal created
    create_date varbinary(14) default NULL,
    -- When was this fractcal submitted for review
    submit_date varbinary(14) default NULL,
    -- When was this fractal marked as approved?
    approve_date varbinary(14) default NULL,
    -- When was this fractal marked as frozen?
    frozen_date varbinary(14) default NULL,
    -- When was the last time stats on this fractal were updated?
    update_date varbinary(14) default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ff_game_id ON /*_*/fate_fractal (game_id);
CREATE INDEX /*i*/ff_register_id ON /*_*/fate_fractal (register_id);