<?php
/**
 * SpecialPage for FateCharGen extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialFateGameConfig extends SpecialPage {
    public function __construct() {
        parent::__construct( 'FateGameConfig', 'fategm' );
    }

    /**
     * Show the page to the user
     *
     * @param string $sub The subpage string argument (if any).
     *  [[Special:FateGameConfig/subpage]].
     */
    public function execute( $sub ) {
        $user = $this->getUser();
        $out = $this->getOutput();
        
        $this->setHeaders();
        $out->setPageTitle( $this->msg( 'fategameconfig' ) );
        $out->wrapWikiMsg( '=$1=' , array( 'fategameconfig' ) );   
        $out->addWikiMsg( 'fategameconfig-desc' );
        
        if ($user->isAnon()) {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>You must be logged in to access this page.</div>");
        } elseif (!$user->isAllowed('fategm')) {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>You don't have permission to access this page.</div>");
        } else {
            if ($sub == 'View') {
                $out->addWikiText('* View a specific Game');
                $this->viewSpecificGame();
            } elseif ($sub == 'Edit') {
                $out->addWikiText('* Edit a specific Game');
            } elseif ($sub == 'Create') {
                $out->addWikiText('* Create a New Game');
            } elseif ($sub == 'Delete') {
                $out->addWikiText('* Delete a specific Game');
            } else {
                $out->addWikiText('* List All Games');
                $this->listAllGames();
            }
        }   
    }
    
    private function viewSpecificGame() {
        $user = $this->getUser();
        $out  = $this->getOutput();
        $request = $this->getRequest();
        
        $game_id = $request->getInt('game_id');
        if ($game_id) {
            $game = new FateGame($game_id);
            $table = '';
            if ($game->register_id) {
                $distribution = FateGameGlobals::getSkillDistributionArray();
                $table .= "<table>".
                          "<tr><td class='mw-label'>Game Name:</td><td colspan=3>$game->game_name</td></tr>".
                          "<tr><td class='mw-label'>Description:</td><td colspan=3>$game->game_description</td></tr>".
                          "<tr><td class='mw-label'>GM:</td><td>".
                          Linker::link(Title::newFromText('User:' . $game->user_name), $game->canon_name, array(), array(), array( 'forcearticlepath' ) ) .
                          "</td>".
                          "<td class='mw-label'>Game Status:</td><td>$game->game_status</td></tr>".
                          "<tr><td class='mw-label'>Created:</td><td>" . FateGameGlobals::getDisplayDate($game->create_date) . "</td>".
                          "<td class='mw-label'>Last Modified:</td><td>" . FateGameGlobals::getDisplayDate($game->modified_date) . "</td></tr>".
                          "<td class='mw-label' style='vertical-align: top'>Starting Aspects:</td><td colspan=3>$game->aspect_count Total";
                if (count($game->aspects) > 0) {
                    $table .= "<br/>";
                    $list = array();
                    foreach ($game->aspects as $aspect) {
                        $list[] = $aspect['label'];
                    }
                    $table .= implode(', ', $list);
                }
                $table .= "</td></tr>".
                          "<td class='mw-label' style='vertical-align: top'>Skills:</td><td colspan=3>".
                          "<table><tr><td class='mw-label'>Distribution Method:</td><td>" . $distribution[$game->skill_distribution] . "</td></tr>";
                if ($game->skill_alternative) {
                    $table .= "<tr><td class='mw-label'>Alternative Label:</td><td>$game->skill_alternative</td></tr>";
                }
                $table .= "<tr><td class='mw-label'>Max Starting Skill:</td><td>+$game->skill_max</td></tr>".
                          "<tr><td class='mw-label'>Starting Points:</td><td>" . implode(', ', $game->skill_points) . "</td></tr></table></td></tr>";
                if (count($game->skills) > 0) {
                    $table .= "<tr><td class='mw-label'>Skill List:</td><td>";
                    $list = array();
                    foreach ($game->skills as $skill) {
                        $list[] = $skill['label'] . ($skill['mode_cost'] ? ' (' . $skill['mode_cost'] . ')' : '');
                    }
                    $table .= implode(', ', $list) . "</td></tr>";
                }
                $table .= "<tr><td class='mw-label'>Refresh Rate:</td><td colspan=3>$game->refresh_rate</td></tr>".
                          "<tr><td class='mw-label'>Initial Stunt Slots:</td><td colspan=3>$game->stunt_count</td></tr>".
                          "<tr><td class='mw-label'>Initial Stress Boxes:</td><td colspan=3>$game->stress_count</td></tr>";
                if (count($game->stress_tracks) > 0) {
                    $table .= "<tr><td class='mw-label'>Stress Tracks:</td><td colspan=3>";
                    $list = array();
                    foreach ($game->stress_tracks as $track) {
                        $list[] = $track['label'];
                    }
                    $table .= implode(', ', $list) . "</td></tr>";
                }
                if ($game->use_consequences) {
                    $table .= "<tr><td class='mw-label'>Consequences:</td><td colspan=3>";
                    $list = array();
                    foreach ($game->consequences as $consequence) {
                        $list[] = $consequence['label'] . ' (' . $consequence['display_value'] . ')';
                    }
                    $table .= implode(', ', $list) . "</td></tr>";
                } else {
                    $table .= "<tr><td class='mw-label'>Conditions:</td><td colspan=3></td></tr>";
                }     
                $table .= "<tr><td class='mw-label'>Private Sheets:</td><td colspan=3>" . ($game->private_sheet ? 'Yes' : 'No') . "</td></tr>";
                $table .= "</table>";
                
                if(count($game->fractals) > 0) {
                    /* Handle Characters first, if they exist */
                    if (count($game->fractals['Character']) > 0) {
                        $characters = $game->fractals['Character'];
                        $table .= "<table border=1 cellspacing=3 cellpadding=2><caption>Characters<caption>".
                                  "<tr><th>Character Name</th><th>Wiki Name</th><th>Status</th></tr>";
                        foreach ($characters as $character) {
                            $table .= "<tr><td>" . 
                                      Linker::link(Title::newFromText('Special:FateStats')->getSubpage("ViewSheet"), $character[name], array(), array( 'fractal_id' => $character[fractal_id] ), array ( 'forcearticlepath' ) ) .
                                      "</td><td>".
                                      Linker::link(Title::newFromText('User:' . $character[user_name]), $character[user_name], array(), array(), array( 'forcearticlepath' ) ) .
                                      "</td><td>";
                            if ($character[frozen_date]) {
                                $table .= 'Frozen on ' . FateGameGlobals::getDisplayDate($character[frozen_date]);
                            } elseif ($character[approve_date]) {
                                $table .= 'Approved on ' . FateGameGlobals::getDisplayDate($character[approve_date]);
                            } elseif ($character[submit_date]) {
                                $table .= 'Submitted on ' . FateGameGlobals::getDisplayDate($character[submit_date]);
                            } else {
                                $table .= 'Created on ' . FateGameGlobals::getDisplayDate($character[create_date]);
                            }
                            $table .= "</td></tr>";
                        }
                        $table .= "</table>";
                    }
                    /* Now look to see if there are any other fractals, and display appropriately */
                    foreach ($game->fractals as $key => $array) {
                        if ($key == 'Character') {
                            continue;
                        }
                        $table .= "<table border=1 cellspacing=3 cellpadding=2><caption>$key</caption>".
                                  "<tr><th>Name</th><th>Is Private?</th><th>Created On</th>";
                        foreach ($array as $fractal) {
                            $table .= "<tr><td>" .
                                      Linker::link(Title::newFromText('Special:FateStats')->getSubpage("View"), $fractal[name], array(), array( 'fractal_id' => $fractal[fractal_id] ), array ( 'forcearticlepath' ) ) .
                                      "</td><td>" . ($fractal[is_private] ? 'Yes' : 'No' ) . "</td>".
                                      "<td>" . FateGameGlobals::getDisplayDate($fractal[create_date]) . "</td></tr>";
                        }
                        $table .= "</table>";
                    }
                }  
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red'>No data found for that game_id; please check URL and try again.</div>";
            }
            $out->addHTML($table);
        } else {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>Missing game_id argument; don't know which game to show.</div>");
        }
        
    }
    
    private function listAllGames() {
        $user = $this->getUser();
        $out = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);
        
        $games = $dbr->select(
            array( 'g' => 'fate_game',
                   'r' => 'muxregister_register' ),
            array( 'r.register_id',
                   'r.user_name',
                   'r.canon_name',
                   'r.user_id',
                   'g.game_id',
                   'g.game_name',
                   'g.game_description',
                   'g.game_status',
                   'g.create_date',
                   'g.modified_date'),
            array( 'r.register_id = g.register_id' ),
            __METHOD__,
            array( 'ORDER BY' => 'g.game_name' )
        );
        
        $table = '';
        if ($games->numRows() == 0) {
            $table .= "<div>No games are currently set up.</div>";
        } else {
            $table .= "<table border=1 cellspacing=3 cellpadding=3>".
                      "<tr><th>Game Name</th><th>Game Master</th><th>Description</th><th>Status</th><th>Created</th><th>Last Modified</th></tr>";
            foreach ($games as $game) {
                $table .= "<tr><td valign='top'>";
                if ($user->getID() == $game->{user_id} || $user->isAllowed('fategm')) {
                    $table .= Linker::link($this->getPageTitle()->getSubpage("View"), $game->{game_name}, array(), array( 'game_id' => $game->{game_id} ), array ( 'forcearticlepath' ) );
                } else {
                    $table .= $game->{game_name};
                }
                $table .= "</td><td valign='top'>".
                          Linker::link(Title::newFromText('User:' . $game->{user_name}), $game->{canon_name}, array(), array(), array( 'forcearticlepath' ) ) .
                          "</td>".
                          "<td>" . $game->{game_description} . "</td><td valign='top'>" . $game->{game_status} . "</td>".
                          "<td>" . FateGameGlobals::getDisplayDate($game->{create_date}) . "</td>".
                          "<td>" . FateGameGlobals::getDisplayDate($game->{modified_date}) . "</td>".
                          "</tr>";
            }
            $table .= "</table>";
        }
        
        $out->addHTML($table);
    }
    
    protected function getGroupName() {
        return 'other';
    }
}