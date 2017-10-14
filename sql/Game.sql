-- Game table setup for FateCharGen extension
-- Created originally for Alien City
-- porpentine@gmail.com
-- Last Update: October 7, 2017

-- Add primary game table
CREATE TABLE IF NOT EXISTS /*_*/fate_game (
    -- Unique ID
    game_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Registration ID -> ties characters together with their wiki logins, via the MuxRegister extension
    -- In this case, a link to the character object who is the 'owner' of the game
    register_id int default NULL,
    -- Name of the game
    game_name varchar(128) default NULL,
    -- Brief description of the game: setting, power level, etc
    game_description text default NULL,
    -- Even more brief description of current game status -- in development, accepting players, etc
    game_status varchar(128) default NULL,
    -- How many aspects to characters get through chargen?
    aspect_count int default NULL,
    -- If not null, use this as the alternative name for skills -- Approaches, Assets, ets
    skill_alternative varchar(64) default NULL,
    -- What distribution method are we using for skills?
    skill_distribution tinyint default NULL,
    -- What is the skill cap for starting characters in chargen?
    skill_max int default NULL,
    -- If using columns or approaches, how many skill points do characters get to distribute in chargen?
    -- Can be single number, or can be |-deliniated list of values to assign
    -- (For instance, with basic FAE Approaches: 3|2|2|1|1
    skill_points varchar(128) default NULL,
    -- What is the default starting Refresh rate?
    refresh_rate int default NULL,
    -- How many stunts do characters get for free in chargen (without burning refresh)
    stunt_count int default NULL,
    -- How many boxes do characters start with for default stress tracts?
    stress_count int default NULL,
    -- Boolean flag: should chargen use consequences for characters in this game? If not, chargen will use Conditions
    use_consequences tinyint default NULL,
    -- Boolean flag: are character sheets public or private for this game?
    private_sheet tinyint default NULL,
    -- Boolean flag: use Atomic Robo model -- refresh = aspect count, don't subtract for stunts
    use_robo_refresh tinyint default NULL,
    -- Boolean flag: is chargen open to all comers, or do you have to make a request for staff?
    is_open_chargen tinyint default NULL,
    -- Boolean flag: is this game accepting new characters? Won't show on 'join game' list if not
    is_accepting_characters tinyint default NULL,
    -- When was this game created
    create_date varbinary(14) default NULL,
    -- When were settings last modified?
    modified_date varbinary(14) default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fg_register_id ON /*_*/fate_game (register_id);
