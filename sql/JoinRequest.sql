-- Join Game Request table setup for FateCharGen extension
-- Created originally for Alien City/Dead Authors
-- porpentine@gmail.com
-- Last Update: October 11, 2017

-- Add join game requests table
CREATE TABLE IF NOT EXISTS /*_*/fate_join_request (
    -- Unique ID
    join_request_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Game ID for potential new character
    game_id int default NULL,
    -- Registration ID of the character object
    register_id int default NULL,
    -- Date request was submitted
    request_date varbinary(14) default NULL,
    -- User ID of staffer who responded to this request
    responder_id int default NULL,
    -- When request was responded to
    response_date varbinary(14) default NULL,
    -- Boolean flag: was this request accepted, or denied?
    was_approved tinyint default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fjr_game_id ON /*_*/fate_join_request (game_id);
CREATE INDEX /*i*/fjr_register_id ON /*_*/fate_join_request (register_id);
