<?php
/**
 * SpecialPage for FateCharGen extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialFateStats extends SpecialPage {
    public function __construct() {
        parent::__construct( 'FateStats', 'fategm' );
    }

    /**
     * Show the page to the user
     *
     * @param string $sub The subpage string argument (if any).
     *  [[Special:FateStats/subpage]].
     */
    public function execute( $sub ) {
        $user = $this->getUser();
        $out = $this->getOutput();
        $request = $this->getRequest();
        $action = $request->getVal('action');
        
        $this->setHeaders();
        $out->setPageTitle( $this->msg( 'fatestats' ) );
        $out->wrapWikiMsg( '=$1=' , array( 'fatestats' ) );   
        
        if ($user->isAnon()) {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>You must be logged in to access this page.</div>");
        } elseif (!$user->isAllowed('fategm')) {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>You don't have permission to access this page.</div>");
        } else {
            if ($sub == 'View') {
                $out->addWikiText('* View a specific Stat Block');        
                $fractal_id = $request->getInt('fractal_id');
                $this->viewFractalBlock($fractal_id);
            } elseif ($sub == 'ViewSheet') {
                $out->addWikiText('* View a specific sheet');
                $this->viewFractalSheet();
            } elseif ($sub == 'Edit') {
                $out->addWikiText('* Edit a specific stat block');
            } elseif ($sub == 'Delete') {
                $out->addWikiText('* Delete a specific Fractal');
            } elseif ($sub == 'Create') {
                $result = array();
                if ($action == 'create') {
                    $result = $this->createFractal();
                }
                if (count($result) == 0 || $result['error']) {
                    $this->createFractalForm($result);
                } else {
                    // When it exists: go to the edit page
                    $this->viewFractalBlock(intval($result['msg']));
                }
            } else {
                $this->listAllFractals();
            }
        }   
    }
    
    private function createFractal() {
        $request = $this->getRequest();
        $result = array( 'error' => 0 );
    
        $new_fractal = array(
            'game_id' => $request->getVal('game_id'),
            'fractal_type' => ($request->getVal('fractal_type') == -1 ? $request->getVal('new_fractal_type') : $request->getVal('fractal_type')),
            'fractal_name' => $request->getVal('fractal_name')
        );
        // TODO: handling for is_private

        if ($new_fractal['game_id'] == 'empty') {
            $result['error'] = 1;
            $result['msg'] = 'Please select a game to create the fractal for.';
        } elseif (!$new_fractal['fractal_type']) {
            $result['error'] = 1;
            $result['msg'] = 'Please assign a type for the new fractal.';
        } elseif ($new_fractal['fractal_type'] == 'Character') {
            $result['error'] = 1;
            $result['msg'] = '"Character" is a reserved type, only available through chargen.';
        } elseif (!$new_fractal['fractal_name']) {
            $result['error'] = 1;
            $result['msg'] = 'Please supply a name for the new fractal.';
        } else {
            $dbr = wfGetDB(DB_SLAVE);
            $check_fractal = $dbr->selectRow(
                array( 'f' => 'fate_fractal' ),
                array( 'f.fractal_id' ),
                array( 'f.game_id' => $new_fractal['game_id'],
                       'f.fractal_type' => $new_fractal['fractal_type'],
                       'f.fractal_name' => $new_fractal['fractal_name'] )
            );
            if ($check_fractal) {
                $result['error'] = 1;
                $result['msg'] = 'Fractal with that name and type already exists.';
            } else {
                $dbw = wfGetDB(DB_MASTER);
                $new_fractal['create_date'] = $dbw->timestamp();
                $dbw->insert( 'fate_fractal', $new_fractal );
                $result['msg'] = $dbw->insertId();
            }
        }
        
        return $result;
    }
        
    
    private function createFractalForm( $result ) {
        $user = $this->getUser();
        $out = $this->getOutput();
        $request = $this->getRequest();
        
        // TODO: handling for is_private
        
        $form_url = $this->getPageTitle()->getSubPage('Create')->getLinkURL();
        $game_id = $request->getVal('game_id');
        $fractal_type = $request->getVal('fractal_type');
        $fractal_name = $request->getVal('fractal_name');
        $new_fractal_type = $request->getVal('new_fractal_type');
        $action = $request->getVal('action');
        
        $game_list = $this->getGameListSelect($game_id);
        $fractal_data = $this->getFractalJSArray();
        $form = <<< EOT
            <script type='text/javascript'>
                $fractal_data
                var passed_type = '$fractal_type';
                
                function updateFractalTypes() {
                    var game = document.getElementById('cfgame');
                    var type = document.getElementById('cftype');
                    while (type.options.length > 0) {
                        type.remove(0);
                    }
                    var newOption;
                    if (game.value == 'empty') {
                        newOption = document.createElement('option');
                        newOption.value = '';
                        newOption.appendChild(document.createTextNode('Select a game first'));
                        type.appendChild(newOption);
                        type.disabled = true;
                    } else {
                        if (game.value in fractalList) {
                            typeList = fractalList[game.value];
                            for (var i = 0; i < typeList.length; i++) {
                                newOption = document.createElement('option');
                                newOption.value = typeList[i];
                                newOption.appendChild(document.createTextNode(typeList[i]));
                                type.appendChild(newOption);
                            }
                        }
                        newOption = document.createElement('option');
                        newOption.value = -1;
                        newOption.appendChild(document.createTextNode('Create New Fractal Type'));
                        type.appendChild(newOption);
                        type.disabled = false;
                    }
                    if (passed_type) {
                        type.value = passed_type;
                    }
                    updateNewFractalType();
                }
                
                function updateNewFractalType() {
                    var type = document.getElementById('cftype');
                    var row = document.getElementById('new_type_row');
                    if (type.value == -1) {
                        row.style.display = 'table-row';
                    } else {
                        row.style.display = 'none';
                    }
                }
            </script>
            <form action='$form_url' method='post'>
                <input type='hidden' name='action' value='create'/>
                <fieldset>
                    <legend>Create New Fractal</legend>
                    <table>
                        <tbody>
                        <tr>
                            <td class='mw-label'><label for='cfgame'>Select Game:</label></td>
                            <td class='mw-input'>$game_list</td>
                        </tr>
                        <tr>
                            <td class='mw-label'><label for='cftype'>Fractal Type:</label></td>
                            <td class='mw-input'>
                                <select id='cftype' name='fractal_type' disabled onChange='updateNewFractalType();'>
                                    <option value=''>Select a game first</option>
                                </select>
                            </td>
                        </tr>
                        <tr id='new_type_row' style='display:none'>
                            <td class='mw-label'>&nbsp;</td>
                            <td class='mw-input'><input id='cfnewtype' name='new_fractal_type' value='$new_fractal_type' type='text' size='35' placeholder='New Fractal Type'/></td>
                        </tr>
                        <tr>
                            <td class='mw-label'><label for='cfname'>New Fractal Name:</label></td>
                            <td class='mw-input'><input id='cfname' type='text' name='fractal_name' value='$fractal_name' size='35'/></td>
                        </tr>
                    </table>
                    <span class='mw-htmlform-submit-buttons'>
                        <input class='mw-htmlform-submit' type='submit' value='Create'/>
                    </span>
                </fieldset>
            </form>
            <script type='text/javascript'>updateFractalTypes();</script>
EOT;

        if ($action && $result['error']) {
            $out->addWikiText("'''" . $result['msg'] . "'''");
        }
        $out->addHTML($form);
    }

    
    private function listAllFractals() {
        $user = $this->getUser();
        $out  = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);
        
        // TODO: integrate TablePager into this. See SpecialBlockList as framework
        
        $fractal_list = $dbr->select(
            array( 'f' => 'fate_fractal',
                   'g' => 'fate_game',
                   'r' => 'muxregister_register' ),
            array( 'r.user_name',
                   'r.canon_name',
                   'r.user_id',
                   'f.fractal_id',
                   'f.game_id',
                   'g.game_name',
                   'f.fractal_name',
                   'f.fractal_type',
                   'f.is_private',
                   'f.create_date',
                   'f.submit_date',
                   'f.approve_date',
                   'f.frozen_date' ),
            array( ),
            __METHOD__,
            array( 'ORDER BY' => array ('fractal_type', 'fractal_name', 'canon_name' ) ),
            array( 'r' => array( 'LEFT JOIN', 'f.register_id = r.register_id' ),
                   'g' => array( 'JOIN', 'f.game_id = g.game_id' ) )
        );
        
        $table = "<table class='wikitable'>".
                 "<tr><th>Fractal Name</th><th>Fractal Type</th><th>Game</th><th>Player</th><th>Status</th></tr>";
        if ($fractal_list->numRows() == 0) {
            $table .= "<tr><td colspan=100%>No Fractals Found</td></tr>";
        } else {
            foreach ($fractal_list as $fractal) {
                $subpage = "View";
                $status = "Created";
                $status_date = $fractal->create_date;
                $name = $fractal->fractal_name;
                if ($fractal->fractal_type == 'Character') {
                    $subpage = "ViewSheet";
                    $name = $fractal->canon_name;
                    if ($fractal->frozen_date) {
                        $status = "Frozen";
                        $status_date = $fractal->frozen_date;
                    } elseif ($fractal->approve_date) {
                        $status = "Approved";
                        $status_date = $fractal->approve_date;
                    } elseif ($fractal->submit_date) {
                        $status = "Submitted";
                        $status_date = $fractal->submit_date;
                    }
                }
                $table .= "<tr><td>" .
                    Linker::link($this->getPageTitle()->getSubpage($subpage), $name, array(), array( 'fractal_id' => $fractal->fractal_id ), array( 'forcearticalpath' ) ) .
                    "</td><td>" . $fractal->fractal_type . "</td><td>" .
                    Linker::link(Title::newFromText('Special:FateGameConfig')->getSubpage('View'), $fractal->game_name, array(), array( 'game_id' => $fractal->game_id ), array( 'forcearticlepath' ) ).
                    "</td><td>";
                if ($fractal->canon_name) {
                    $table .= Linker::link(Title::newFromText('User:' . $fractal->user_name), $fractal->user_name, array(), array(), array( 'forcearticlepath' ) );
                } else {
                    $table .= "&nbsp;";
                }
                $table .= "</td><td>" . $status . " on " . FateGameGlobals::getDisplayDate($status_date) . "</td></tr>";
            }
            $table .= "</table>";
        }
        $out->addHTML($table);
    }
    
    private function viewFractalBlock( $fractal_id ) {
        $user = $this->getUser();
        $out = $this->getOutput();

        if ($fractal_id) {
            $fractal = new FateFractal($fractal_id);
            $table = '';
            if ($fractal->name) {
                $table .= $fractal->getFractalBlock();
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red'>No data found for that fractal_id; please check URL and try again.</div>";
            }
            $out->addHTML($table);
        } else {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>Missing fractal_id argument; don't know which game to show.</div>");
        }
    }
    
    private function viewFractalSheet() {
        $user = $this->getUser();
        $out = $this->getOutput();
        $request = $this->getRequest();
        
        $fractal_id = $request->getInt('fractal_id');
        if ($fractal_id) {
            $fractal = new FateFractal($fractal_id);
            $table = '';
            if ($fractal->name) {
                $table .= $fractal->getFractalSheet();
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red'>No data found for that fractal_id; please check URL and try again.</div>";
            }
            $out->addHTML($table);
        } else {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>Missing fractal_id argument; don't know which game to show.</div>");
        }
    }
    
    private function getGameListSelect($game_id) {
        $user = $this->getUser();
        $dbr = wfGetDB(DB_SLAVE);
        
        $games = $dbr->select(
            array( 'g' => 'fate_game',
                   'r' => 'muxregister_register' ),
            array( 'r.register_id',
                   'g.game_id',
                   'g.game_name' ),
            array( 'r.register_id = g.register_id' )
        );
        
        $select = "<select name='game_id' id='cfgame' onchange='updateFractalTypes()'>";
        if ($games->numRows() > 0) {
            $selected = ($game_id == 'empty' || !$game_id ? 'selected' : '');
            $select .= "<option value='empty' $selected>Please select a game</option>";
            foreach ($games as $game) {
                $selected = ($game_id == $game->{game_id} ? 'selected' : '');
                $select .= "<option value='" . $game->{game_id} . "' $selected>" . $game->{game_name} . "</option>";
            }
        } else {
            $select .= "<option value=''>No valid games found; please create one before creating associated fractals.</option>";
        }
        $select .= "</select>";
        
        return $select;
    }
    
    private function getFractalJSArray() {
        $user = $this->getUser();
        $dbr = wfGetDB(DB_SLAVE);
        
        $fractals = $dbr->select(
            array( 'f' => 'fate_fractal'),
            array( 'f.fractal_type',
                   'f.game_id' ),
            array(),
            __METHOD__,
            array( 'DISTINCT',
                   'ORDER BY' => array( 'game_id', 'fractal_type' ) )
        );
        
        $js = "var fractalList = new Array();";
        if ($fractals->numRows() > 0) {
            $this_id = -1;
            $this_list = array();
            foreach ($fractals as $fractal) {
                if ($fractal->{game_id} != $this_id) {
                    if ($this_id != -1) {
                        $js .= "fractalList[$this_id] = [" . implode(',', $this_list) . "];";
                        if (count($this_list) > 0) {
                            $this_list = array();
                        }
                    }
                    $this_id = $fractal->{game_id};
                }
                // 'Character' is a protected type, handled by full chargen
                if ($fractal->{fractal_type} == 'Character') {
                    continue;
                }
                $this_list[] = "'" . $fractal->{fractal_type} . "'";
            }
            $js .= "fractalList[$this_id] = [" . implode(',', $this_list) . "];";
        }
            
        return $js;
    }
    
    protected function getGroupName() {
        return 'other';
    }
}