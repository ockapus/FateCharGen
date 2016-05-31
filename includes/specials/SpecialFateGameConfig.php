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
        $request = $this->getRequest();
        $action = $request->getVal('action');
        
        $this->setHeaders();
        $out->setPageTitle( $this->msg( 'fategameconfig' ) );
        $out->wrapWikiMsg( '=$1=' , array( 'fategameconfig' ) );   
        $out->addWikiMsg( 'fategameconfig-desc' );
        
        if ($user->isAnon()) {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>You must be logged in to access this page.</div>");
        } elseif (!$user->isAllowed('fategm')) {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>You don't have permission to access this page.</div>");
        } else {
            $game_id = $request->getInt('game_id');
            if ($sub == 'View') {
                $out->addWikiText('* View a specific Game');
                $this->viewSpecificGame();
            } elseif ($sub == 'Edit') {
                if ($action == 'edit') {
                    $results = $this->processEditGameForm();
                    if (count($results['error']) == 0) {
                        $this->saveGameEdits($game_id, $results);
                        $this->viewSpecificGame();
                    } else {
                        $this->editGame($results);
                    }
                } else {
                    $this->editGame();
                }
            } elseif ($sub == 'Approval') {
                if ($action == 'edit') {
                    $results = $this->processApprovalForm();
                    if (count($results['error']) == 0) {
                        $this->saveApprovals($game_id, $results);
                        $this->viewSpecificGame();
                    } else {
                        $this->viewPendingApprovals($results);
                    }
                } else {
                    $this->viewPendingApprovals();
                }
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
    
    private function saveGameEdits( $game_id, $results ) {
        $output = $this->getOutput();
        $game = new FateGame($game_id);
        $dbw = wfGetDB(DB_MASTER);
        
        foreach ($results['delete'] as $type => $data) {
            foreach ($data as $stat_id => $junk) {
                if ($type == FateGameGlobals::STAT_MODE) {
                    $dbw->delete(
                        'fate_game_mode_skill',
                        array( 'game_mode_id' => $stat_id )
                    );
                    $dbw->delete(
                        'fate_game_mode',
                        array( 'game_mode_id' => $stat_id )
                    );
                }
            }
        }
        
        foreach ($results['edit'] as $type => $data) {
            foreach ($data as $stat_id => $changes) {
                if ($type == FateGameGlobals::STAT_MODE) {
                    $cost = $game->modes_by_id[$stat_id]['cost'];
                    if ($changes['skill']) {
                        $mod_for_discipline = 0;
                        $delete_discipline = 0;
                        foreach ($changes['skill'] as $skill_id => $toggle) {
                            if ($toggle) {
                                $cost += $game->skills_by_id[$skill_id]['mode_cost'];
                                $dbw->insert(
                                    'fate_game_mode_skill',
                                    array(
                                        'game_mode_id' => $stat_id,
                                        'game_skill_id' => $skill_id
                                    )
                                );
                                if ($game->skills_by_id[$skill_id]['has_disciplines']) {
                                    $mod_for_discipline = 1;
                                }
                            } else {
                                $cost -= $game->skills_by_id[$skill_id]['mode_cost'];
                                $dbw->delete(
                                    'fate_game_mode_skill',
                                    array(
                                        'game_mode_id' => $stat_id,
                                        'game_skill_id' => $skill_id
                                    )
                                );
                                if ($game->skills_by_id[$skill_id]['has_disciplines']) {
                                    $delete_discipline = 1;
                                }
                            }
                        }
                        // Confirm whether we're modifying cost for discipline skills (eg Science)
                        if (!$mod_for_discipline) {
                            foreach ($game->modes_by_id[$stat_id]['skill_list'] as $skill_id) {
                                if ($game->skills_by_id[$skill_id]['has_disciplines'] && ! array_key_exists($skill_id, $changes['skill'])) {
                                    $mod_for_discipline = 1;
                                    break;
                                }
                            }
                        }
                        // If we deleted skill that has_disciplines, and didn't have any other skill added or existing with it, then subtract
                        if ($delete_discipline && !$mod_for_discipline) {
                            $mod_for_discipline = -1;
                        }
                        $cost += $mod_for_discipline;
                    }
                    $updates = array();
                    if ($cost != $game->modes_by_id[$stat_id]['cost']) {
                        $updates['mode_cost'] = $cost;
                    }
                    if (array_key_exists('weird', $changes)) {
                        $updates['is_weird'] = $changes['weird'];
                    }
                    if ($changes['label']) {
                        $updates['game_mode_label'] = $changes['label'];
                    }
                    if (count($updates)) {
                        $dbw->update(
                            'fate_game_mode',
                            $updates,
                            array( 'game_mode_id' => $stat_id )
                        );
                    }
                }
            }
        }
        
        foreach($results['new'] as $stat_type => $rows) {
            foreach ($rows as $row => $data) {
                if ($row == 'max') {
                    continue;
                }
                $inserts = array(
                    'game_id' => $game_id,
                    'game_mode_label' => $data['label'],
                    'is_weird' => intval($data['weird']),
                    'mode_cost' => 0
                );
                // Go through skills twice. First time: calculate cost
                $mod_for_discipline = 0;
                foreach ($data['skill'] as $skill_id => $junk) {
                    $inserts['mode_cost'] += $game->skills_by_id[$skill_id]['mode_cost'];
                    if ($game->skills_by_id[$skill_id]['has_disciplines']) {
                        $mod_for_discipline = 1;
                    }
                }
                $inserts['mode_cost'] += $mod_for_discipline;
                $dbw->insert(
                    'fate_game_mode',
                    $inserts
                );
                $mode_id = $dbw->insertId();
                // Now go through skills to set up connection to new mode
                foreach ($data['skill'] as $skill_id => $junk) {
                    $dbw->insert(
                        'fate_game_mode_skill',
                        array(
                            'game_mode_id' => $mode_id,
                            'game_skill_id' => $skill_id
                        )
                    );
                }
            }
        }
    }
    
    private function processEditGameForm() {
        $request = $this->getRequest();
        $data = $request->getValues();
        $game_id = $request->getInt('game_id');
        $game = new FateGame($game_id);
        
        $output = $this->getOutput();
        
        $results = array(
            'delete' => array(),
            'edit' => array(),
            'new' => array(),
            'error' => array(),
            'form' => $data
        );
        
        // Start by hunting deletes
        foreach ($data as $key => $value) {
            if (preg_match("/^(\d+)_delete_(\d+)$/", $key, $matches)) {
                $results['delete'][$matches[1]][$matches[2]] = 1;
            }
        }
        
        // Now look for edits
        foreach ($data as $key => $value) {
            if (preg_match("/^(\d+)_skill_(\d+)_(?!new_)(\d+)$/", $key, $matches)) {
                $type = $matches[1];
                $field = 'skill';
                $skill_id = $matches[2];
                $mode_id = $matches[3];
                
                if ($results['delete'][$type][$mode_id]) {
                    continue;
                }
                
                if (in_array($skill_id, $game->modes_by_id[$mode_id]['skill_list']) && $value) {
                    continue;
                }
                
                $results['edit'][$type][$mode_id][$field][$skill_id] = $value;
            } elseif (preg_match("/^(\d+)_([a-zA-Z]+)_(?!new_)(\d+)$/", $key, $matches)) {
                $type = $matches[1];
                $field = $matches[2];
                $id = $matches[3];
                
                // Skip if we're already deleting
                if ($results['delete'][$type][$id]) {
                    continue;
                }
                
                if ($type == FateGameGlobals::STAT_MODE) {
                    if ($field == 'label' && $game->modes_by_id[$id]['label'] == $value) {
                        continue;
                    } elseif ($field == 'weird' && $game->modes_by_id[$id]['is_weird'] == $value) {
                        continue;
                    }
                }
                
                $results['edit'][$type][$id][$field] = $value;
            }
        }
        
        // Go through modes, look for things we may have turned off
        foreach ($game->modes as $mode) {
            if ($mode['is_weird'] && !$data[FateGameGlobals::STAT_MODE . "_weird_" . $mode['mode_id']]) {
                $results['edit'][FateGameGlobals::STAT_MODE][$mode['mode_id']]['weird'] = 0;
            }
            foreach ($mode['skill_list'] as $skill_id) {
                if (!$data[FateGameGlobals::STAT_MODE . "_skill_" . $skill_id . "_" . $mode['mode_id']]) {
                    $results['edit'][FateGameGlobals::STAT_MODE][$mode['mode_id']]['skill'][$skill_id]  = 0;
                }
            }
        }
        
        
        // Finally, look for new info
        foreach ($data as $key => $value) {
            if (preg_match("/^(\d+)_skill_(\d+)_new_(\d+)$/", $key, $matches)) {
                $type = $matches[1];
                $field = 'skill';
                $skill_id = $matches[2];
                $grouping = $matches[3];
                
                $results['new'][$type][$grouping][$field][$skill_id] = $value;
                if ($results['new'][$type]['max'] < $grouping) {
                    $results['new'][$type]['max'] = $grouping;
                }
            } elseif (preg_match("/^(\d+)_([a-zA-Z]+)_new_(\d+)$/", $key, $matches)) {
                $type = $matches[1];
                $field = $matches[2];
                $grouping = $matches[3];
                
                $results['new'][$type][$grouping][$field] = $value;
                if ($results['new'][$type]['max'] < $grouping) {
                    $results['new'][$type]['max'] = $grouping;
                }
            }
        }
        foreach ($results['new'] as $type => $rows) {
            foreach ($rows as $row_index => $row) {
                if ($row_index == 'max') {
                    continue;
                }
                
                if ($type == FateGameGlobals::STAT_MODE && !$row['label']) {
                    unset($results['new'][$type][$row_index]);
                }
            }
        }
        
        return $results;
    }
    
    private function processApprovalForm() {
        $request = $this->getRequest();
        $data = $request->getValues();
        $game_id = $request->getInt('game_id');
        $game = new FateGame($game_id);
        
        $output = $this->getOutput();
        
        $results = array(
            'approve' => array(),
            'deny' => array(),
            'error' => array(),
            'form' => $data
        );
        
        foreach ($data as $key => $value) {
            if (preg_match("/^approve_(\d+)_(\d+)$/", $key, $matches)) {
                $results['approve'][$matches[1]][$matches[2]] = 1;
                
            } elseif (preg_match("/deny_(\d+)_(\d+)$/", $key, $matches)) {
                if ($data['reason_' . $matches[1] . '_' . $matches[2]]) {
                    $results['deny'][$matches[1]][$matches[2]] = $data['reason_' . $matches[1] . '_' . $matches[2]];
                } else {
                    $results['error'][$matches[1]][$matches[2]] = 1;
                }
            }
        }
        
        return $results;
    }

    private function saveApprovals( $game_id, $results ) {
        $output = $this->getOutput();
        $user = $this->getUser();
        $game = new FateGame($game_id);
        $made_update = 0;
        $dbw = wfGetDB(DB_MASTER);
        
        foreach ($results['deny'] as $fractal_id => $data) {
            foreach ($data as $pending_id => $reason) {
                $updates = array(
                    'denied_reason' => $reason,
                    'denied_id' => $user->getId(),
                    'modified_date' => $dbw->timestamp()
                );
                $dbw->update(
                    'fate_pending_stat',
                    $updates,
                    array( 'pending_stat_id' => $pending_id )
                );
            }
        }
        
        foreach ($results['approve'] as $fractal_id => $data) {
            foreach ($data as $pending_id => $flag) {
                $fractal = new FateFractal($fractal_id);
                $updates = array( 'modified_date' => $dbw->timestamp() );
                if ($fractal->pending_stats_by_id[$pending_id]->{original_stat_id}) {
                    if ($fractal->pending_stats_by_id[$pending_id]->{stat_type} == FateGameGlobals::STAT_ASPECT) {
                        $updates['stat_field'] = $fractal->pending_stats_by_id[$pending_id]->{stat_field};
                    } elseif ($fractal->pending_stats_by_id[$pending_id]->{stat_type} == FateGameGlobals::STAT_STUNT) {
                        $updates['stat_field'] = $fractal->pending_stats_by_id[$pending_id]->{stat_field};
                        $updates['stat_description'] = $fractal->pending_stats_by_id[$pending_id]->{stat_description};
                    }
                    $dbw->update(
                        'fate_fractal_stat',
                        $updates,
                        array( 'fractal_stat_id' => $fractal->pending_stats_by_id[$pending_id]->{original_stat_id} )
                    );
                } else {
                    // Save new stat
                    $updates['fractal_id'] = $fractal_id;
                    $updates['stat_type'] = $fractal->pending_stats_by_id[$pending_id]->{stat_type};
                    $updates['stat_field'] = $fractal->pending_stats_by_id[$pending_id]->{stat_field};
                    if ($fractal->pending_stats_by_id[$pending_id]->{parent_id}) {
                        $updates['parent_id'] = $fractal->pending_stats_by_id[$pending_id]->{parent_id};
                    }
                    if ($fractal->pending_stats_by_id[$pending_id]->{stat_type} == FateGameGlobals::STAT_ASPECT) {
                        if ($fractal->pending_stats_by_id[$pending_id]->{stat_label}) {
                            $updates['stat_label'] = $fractal->pending_stats_by_id[$pending_id]->{stat_label};
                        }
                    } elseif ($fractal->pending_stats_by_id[$pending_id]->{stat_type} == FateGameGlobals::STAT_STUNT) {
                        $updates['stat_description'] = $fractal->pending_stats_by_id[$pending_id]->{stat_description};
                    }
                    $dbw->insert(
                        'fate_fractal_stat',
                        $updates
                    );
                }
                $dbw->delete(
                    'fate_pending_stat',
                    array( 'pending_stat_id' => $pending_id )
                );
                $made_update = 1;
            }
        }    

        if ($made_update) {
            $dbw->update(
                'fate_fractal',
                array( 'update_date' => $dbw->timestamp() ),
                array( 'fractal_id' => $fractal_id )
            );
        }        
    }
    
    private function viewPendingApprovals( $results = array() ) {
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $game_id = $request->getInt('game_id');
        $table = '';
        if ($game_id) {
            $game = new FateGame($game_id);
            if ($game->register_id) {
                $table .= Linker::link($this->getPageTitle()->getSubPage('View'), 'Return to View', array(), array( 'game_id' => $game_id ), array( 'forcearticlepath' ) );
                $table .= $this->getApproveForm($game, $results);
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red;'>No data found for that game_id; please check URL and try again.</div>";
            }
        } else {
            $table .= "<div class='error' style='font-weight: bold; color: red'>Missing game_id argument; don't know which game to show.</div>";
        }
        $output->addHTML($table);
    }   
                 
    private function editGame( $results = array() ) {
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $game_id = $request->getInt('game_id');
        $table = '';
        if ($game_id) {
            $game = new FateGame($game_id);
            if ($game->register_id) {
                $table .= Linker::link($this->getPageTitle()->getSubPage('View'), 'Return to View', array(), array( 'game_id' => $game_id ), array( 'forcearticlepath' ) );
                $table .= $this->getEditGameForm($game, $results);
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red;'>No data found for that game_id; please check URL and try again.</div>";
            }
        } else {
            $table .= "<div class='error' style='font-weight: bold; color: red'>Missing game_id argument; don't know which game to show.</div>";
        }
        $output->addHTML($table);
    }
    
    private function getApproveForm( $game, $results ) {
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $form_url = $this->getPageTitle()->getSubPage('Approval')->getLinkURL();
        $game_id = $game->game_id;
        
        $form = '<h2>Process Approvals for: ' . $game->game_name . '</h2>';
        if ($game->pending_stat_approvals) {
            $form .= <<<EOT
                <script type='text/javascript'>
                    function toggle_check(id, check) {
                        var approve = document.getElementById('approve_' + id);
                        var deny = document.getElementById('deny_' + id);
                        if (check == approve) {
                            deny.checked = false;
                        } else {
                            approve.checked = false;
                        }
                    }
                </script>
                <form action='$form_url' method='post'>
                <input type='hidden' name='game_id' value='$game_id'/>
                <input type='hidden' name='action' value='edit'/>
EOT;

            // Did we have errors? If show, display a big warning here
            if (count($results['error']) > 0) {
                $form .= "<div class='errorbox'><strong>Approval error.</strong><br/>One or more error was found. Please correct them below, and resubmit to save approvals.</div>";
            }
            
            // TODO: Check for characters that need to be approved
            
            // Now look for individual stats that need approval
            if ($game->pending_stat_approvals) {
                $form .= "<fieldset><legend>Pending Stats</legend><table width='100%'><tbody>";
                foreach ($game->fractals['Character'] as $f) {
                    if ($f['pending']) {
                        $fractal = new FateFractal($f['fractal_id']);
                        $form .= "<tr><td>" . $fractal->getFractalBlock(1);
                        if (array_key_exists(FateGameGlobals::STAT_ASPECT, $fractal->pending_stats)) {
                            foreach ($fractal->pending_stats[FateGameGlobals::STAT_ASPECT] as $aspect) {
                                $form .= $this->getApproveAspect($aspect, $fractal->stats_by_id[$aspect->{original_stat_id}], $results);
                            }
                        }
                        if (array_key_exists(FateGameGlobals::STAT_STUNT, $fractal->pending_stats)) {
                            foreach ($fractal->pending_stats[FateGameGlobals::STAT_STUNT] as $stunt) {
                                $form .= $this->getApproveStunt($stunt, $fractal->stats_by_id[$stunt->{original_stat_id}], $results);
                            }
                        }
                        $form .= "</td></tr>";
                    }
                }       
                $form .= "</tbody></table></fieldset>";   
            }
            $form .= "<span class='mw-htmlform-submit-buttons'><input class='mw-htmlform-submit' type='submit' value='Update'/></span></form>";
        } else {
            $form .= "<div class='error' style='font-weight: bold; color: red;'>No pending approvals found for this game.</div>";
        }
        
        return $form;
    }
    
    private function getApproveStunt( $pending, $original, $results ) {
        $label = ($original ? 'Update' : 'New') . " Stunt:";
        $fields = '';
        if ($pending->{denied_reason}) {
            $fields = $this->getDenial($pending);
        } else {
            $fields = $this->getApproveFields($pending->{fractal_id}, $pending->{pending_stat_id}, $results);
        }
        $orig = '';
        if ($original) {
            $orig = "<tr><td class='mw-label'>Originally:</td><td class='mw-input'>" . $original->{stat_field} . "</td></tr>".
                    "<tr><td>&nbsp;</td><td class='mw-input'><em>" . $original->{stat_description} . "</em></td></tr>";
        }
        $form = <<<EOT
            <table>
                <tbody>
                <tr>
                    <td class='mw-label'>$label</td>
                    <td class='mw-input'>{$pending->{stat_field}}</td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td class='mw-input'><em>{$pending->{stat_description}}</em></td>
                </tr>
                $orig
                $fields
                </tbody>
            </table>
EOT;
        return $form;
    }
    
    private function getApproveAspect( $pending, $original, $results ) {
        $label = ($original ? 'Rename' : 'New') . ($pending->{stat_label} ? ' ' . $pending->{stat_label} : '') . " Aspect:";
        $fields = '';
        if ($pending->{denied_reason}) {
            $fields = $this->getDenial($pending);
        } else {
            $fields = $this->getApproveFields($pending->{fractal_id}, $pending->{pending_stat_id}, $results);
        }
        $orig = '';
        if ($original) {
            $orig = "<tr><td class='mw-label'>Originally:</td><td class='mw-input'>" . $original->{stat_field} . "</td</tr>";
        }
        $form = <<<EOT
            <table>
                <tbody>
                <tr>
                    <td class='mw-label'>$label</td>
                    <td class='mw-input'>{$pending->{stat_field}}</td>
                </tr>
                $orig
                $fields
                </tbody>
            </table>
EOT;
        return $form;
    }
    
    private function getApproveFields( $fractal_id, $pending_stat_id, $results ) {
        $id = $fractal_id . '_' . $pending_stat_id;
        $checked = ($results['form']["deny_$id"] ? 'checked' : '');
        $error = ($results['error'][$fractal_id][$pending_stat_id] ? "class='formerror'" : '');
        $fields = <<<EOT
            <tr>
                <td class='mw-label'>Approve:</td>
                <td class='mw-input'><input type='checkbox' value='1' name='approve_$id' id='approve_$id' onchange='toggle_check("$id", this);'/></td>
            </tr>
            <tr>
                <td class='mw-label'>Deny:</td>
                <td class='mw-input'><input type='checkbox' value='1' name='deny_$id' id='deny_$id' $checked onchange='toggle_check("$id", this);'/></td>
            </tr>
            <tr>
                <td class='mw-label' style='vertical-align: top'>Reason Denied:</td>
                <td class='mw-input'><textarea name='reason_$id' rows=3 cols=80 $error></textarea></td>
            </tr>
EOT;
        if ($results['error'][$fractal_id][$pending_stat_id]) {
            $fields .= "<tr><td>&nbsp;</td><td class='error'>Reason Required for Denials.</td></tr>";
        }
        return $fields;
    }
    
    private function getDenial( $pending ) {
        $denial = "<tr><td>&nbsp;</td><td><strong>Denied on " . FateGameGlobals::getDisplayDate($pending->{modified_date}) . ":</strong> " . $pending->{denied_reason} . "</td></tr>";
        return $denial;
    }
    
    private function getEditGameForm( $game, $results ) {
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $form_url = $this->getPageTitle()->getSubPage('Edit')->getLinkURL();
        $game_id = $game->game_id;
        $skill_list = $this->getGameSkillsJS( $game );
        $const = FateGameGlobals::getStatConsts();
        
        $form .= <<<EOT
            <script type='text/javascript'>
                $skill_list
                var TYPE_MODE = {$const['mode']};
                
                function addNewModeSection( id ) {
                    var check = document.getElementById('eg' + TYPE_MODE + '_label_new_' + (parseInt(id) + 1));
                    if (!check) {
                        var new_id = parseInt(id) + 1;
                        var body = document.getElementById('config_modes');
                        var row = document.createElement('tr');
                        var cell = document.createElement('td');
                        cell.className = 'mw-label';
                        var name = TYPE_MODE + '_label_new_' + new_id;
                        var label = document.createElement('label');
                        label.setAttribute('for', 'eg' + name);
                        label.appendChild(document.createTextNode('New Mode Name:'));
                        cell.appendChild(label);
                        row.appendChild(cell);
                        cell = document.createElement('td');
                        cell.className = 'mw-input';
                        var input = document.createElement('input');
                        input.setAttribute('name', name);
                        input.setAttribute('id', 'eg' + name);
                        input.setAttribute('type', 'text');
                        input.setAttribute('size', 35);
                        input.setAttribute('oninput', 'addNewModeSection(' + new_id + ');');
                        cell.appendChild(input);
                        row.appendChild(cell);
                        cell = document.createElement('td');
                        cell.className = 'mw-label';
                        name = TYPE_MODE + '_weird_new_' + new_id;
                        label = document.createElement('label');
                        label.setAttribute('for', 'eg' + name);
                        label.appendChild(document.createTextNode('Is Weird?'));
                        cell.appendChild(label);
                        row.appendChild(cell);
                        cell = document.createElement('td');
                        cell.className = 'mw-input';
                        input = document.createElement('input');
                        input.setAttribute('name', name);
                        input.setAttribute('id', 'eg' + name);
                        input.setAttribute('type', 'checkbox');
                        input.setAttribute('value', 1);
                        input.setAttribute('onchange', 'addNewModeSection(' + new_id + ');');
                        cell.appendChild(input);
                        row.appendChild(cell);
                        cell = document.createElement('td');
                        row.appendChild(cell);
                        cell = document.createElement('td');
                        row.appendChild(cell);
                        body.appendChild(row);
                        row = document.createElement('tr');
                        cell = document.createElement('td');
                        cell.colSpan = 6;
                        var skillTable = document.createElement('table');
                        var skillBody = document.createElement('tbody');
                        var skillRow;
                        var append;
                        for (var i = 0; i < skill_list.length; i++) {
                            if (i % 5 == 0) {
                                skillRow = document.createElement('tr');
                                append = 1;
                            }
                            var skillCell = document.createElement('td');
                            skillCell.className = 'mw-label';
                            var skillName = TYPE_MODE + '_skill_' + skill_list[i].id + '_new_ ' + new_id;
                            var skillLabel = document.createElement('label');
                            skillLabel.setAttribute('for', 'eg' + skillName);
                            skillLabel.appendChild(document.createTextNode(skill_list[i].label + ' (' + skill_list[i].mode_cost + ')'));
                            skillCell.appendChild(skillLabel);
                            skillRow.appendChild(skillCell);
                            skillCell = document.createElement('td');
                            skillCell.className = 'mw-input';
                            var skillInput = document.createElement('input');
                            skillInput.setAttribute('name', skillName);
                            skillInput.setAttribute('id', 'eg' + skillName);
                            skillInput.setAttribute('type', 'checkbox');
                            skillInput.setAttribute('value', 1);
                            skillInput.setAttribute('onchange', 'addNewModeSection(' + new_id + ');');
                            skillCell.appendChild(skillInput);
                            skillRow.appendChild(skillCell);
                            if (i % 5 == 4) {
                                skillBody.appendChild(skillRow);
                                append = 0;
                            }
                        }
                        if (append) {
                            skillBody.appendChild(skillRow);
                        }
                        skillTable.appendChild(skillBody);
                        cell.appendChild(skillTable);
                        row.appendChild(cell);
                        body.appendChild(row);
                    }
                }
            </script>
            <form action='$form_url' method='post'>
                <input type='hidden' name='game_id' value='$game_id'/>
                <input type='hidden' name='action' value='edit'/>
                <h2>Edit Game</h2>
EOT;

        // Did we have errors? If show, display a big warning here
        if (count($results['error']) > 0) {
            $form .= "<div class='errorbox'><strong>Editing error.</strong><br/>One or more error was found. Please correct them below, and resubmit to save edits.</div>";
        }
        
        $form .= <<<EOT
            <fieldset>
                <legend>Basic Attributes</legend>
                <table>
                    <tbody>
                    <tr>
                        <td class='mw-label'><label for='egname'>Game Name:</label></td>
                        <td class='mw-input'><input id='egname' name='game_name' value='$game->game_name' type='text' size='35'/></td>
                    </tr>
                    </tbody>
                </table>
            </fieldset>
EOT;
        
        if ($game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
            $form .= "<fieldset><legend>Configure Modes</legend><table><tbody id='config_modes'>";
            foreach ($game->modes as $mode) {
                $form .= $this->getEditModeSection($game, $mode, $results);
            }
            // TODO: Add counting!
            $form .= $this->getEditModeSection($game, array( 'newrow' => 1 ), $results);
            $form .= "</tbody></table></fieldset>";
        }
        
        $form .= "<span class='mw-htmlform-submit-buttons'><input class='mw-htmlform-submit' type='submit' value='Update'/></span></form>";
        
        return $form;
    }
    
    private function getGameSkillsJS( $game ) {
        $js = "var skill_list = new Array();";
        foreach ($game->skills as $skill) {
            $js .= "skill_list.push( { id: " . $skill['skill_id'] . ", mode_cost: " . $skill['mode_cost'] . ", label: '" . $skill['label'] . "' } );";
        }
        return $js;
    }
    
    private function getEditModeSection( $game, $mode, $results ) {
        $new_label = ($mode['mode_id'] ? '' : 'New ');
        $id_suffix = ($mode['mode_id'] ? $mode['mode_id'] : 'new_' . $mode['newrow']);
        $onchange = '';
        $oninput = '';
        $delete_cells = '';
        $modetype = FateGameGlobals::STAT_MODE;
        if (!$mode['mode_id']) {
            $onchange = "onchange='addNewModeSection(" . $mode['newrow'] . ");'";
            $oninput = "oninput='addNewModeSection(" . $mode['newrow'] . ");'";
            $delete_cells = "<td></td><td></td>"; 
        } else {
            $delete_cells = "<td class='mw-label'><label for='eg{$modetype}_delete_$id_suffix'>Delete:</label></td>".
                            "<td class='mw-input'><input type='checkbox' id='eg{$modetype}_delete_$id_suffix' name='{$modetype}_delete_$id_suffix' value='1'/></td>";
        }     
        $checked = ($mode['is_weird'] ? 'checked' : '');
        $mode_label = $mode['label'];
        $form = <<<EOT
            <tr>
                <td class='mw-label'><label for='eg{$modetype}_label_$id_suffix'>$new_label Mode Name:</label></td>
                <td class='mw-input'><input name='{$modetype}_label_$id_suffix' id='eg{$modetype}_label_$id_suffix' type='text' size='35' $oninput value='$mode_label'/></td>
                <td class='mw-label'><label for='eg{$modetype}_weird_$id_suffix'>Is Weird?</label></td>
                <td class='mw-input'><input type='checkbox' id='eg{$modetype}_weird_$id_suffix' name='{$modetype}_weird_$id_suffix' value='1' $onchange $checked/></td>
                $delete_cells
            </tr>
            <tr>
                <td colspan=6>
                <table>
                    <tbody>
EOT;
    
        $columns = 5;
        $count = 0;
        $close = 0;
        if (count($game->skills) == 0) {
            $form .= "<tr><td>No Skills yet defined; please add them before configuring modes.</td></tr>";
        } else {    
            foreach ($game->skills as $skill) {
                if ($count++ % $columns == 0) {
                    $form .= "<tr>";
                    $close = 1;
                }
                $name = $modetype . "_skill_" . $skill['skill_id'] . "_$id_suffix";
                $checked = ($mode['skill_list'] && in_array($skill['skill_id'], $mode['skill_list']) ? 'checked' : '');
                $form .= "<td class='mw-label'><label for='eg$name'>" . $skill['label'] . " (" . $skill['mode_cost'] . ")</label></td>".
                         "<td class='mw-input'><input type='checkbox' id='eg$name' name='$name' value='1' $checked $onchange/></td>";
                if ($count % $columns == 0) {
                    $form .= "</tr>";
                    $close = 0;
                }
            }
        }
        if ($close) {
            $form .= "</tr>";
        }
        
        $form .= <<<EOT
                    </tbody>
                </table>
                </td>
            </tr>
EOT;
    
        return $form;
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
                $table .= Linker::link($this->getPageTitle()->getSubPage('Edit'), 'Edit', array(), array( 'game_id' => $game_id ), array( 'forcearticlepath' ) );
                $table .= "<table>".
                          "<tr><td class='mw-label'>Game Name:</td><td colspan=3>$game->game_name</td></tr>".
                          "<tr><td class='mw-label' style='vertical-align: top'>Description:</td><td colspan=3>$game->game_description</td></tr>".
                          "<tr><td class='mw-label'>GM:</td><td>".
                          Linker::link(Title::newFromText('User:' . $game->user_name), $game->canon_name, array(), array(), array( 'forcearticlepath' ) ) .
                          "</td>".
                          "<td class='mw-label' nowrap>Game Status:</td><td>$game->game_status</td></tr>".
                          "<tr><td class='mw-label'>Created:</td><td>" . FateGameGlobals::getDisplayDate($game->create_date) . "</td>".
                          "<td class='mw-label' nowrap>Last Modified:</td><td>" . FateGameGlobals::getDisplayDate($game->modified_date) . "</td></tr>".
                          "<td class='mw-label' style='vertical-align: top' nowrap>Starting Aspects:</td><td colspan=3>$game->aspect_count Total";
                if (count($game->aspects) > 0) {
                    $table .= "<br/>";
                    $list = array();
                    foreach ($game->aspects as $aspect) {
                        $list[] = $aspect['label'] . ($aspect['is_major'] ? ' (*)' : '');
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
                    $table .= "<tr><td class='mw-label' style='vertical-align: top;' nowrap>Skill List:</td><td colspan=3>";
                    $list = array();
                    foreach ($game->skills as $skill) {
                        $list[] = $skill['label'] . ($skill['mode_cost'] !== null ? ' (' . $skill['mode_cost'] . ')' : '');
                    }
                    $table .= implode(', ', $list) . "</td></tr>";
                }
                if (count($game->modes) > 0) {
                    $table .= "<tr><td class='mw-label' style='vertical-align: top'>Defined Modes:</td><td colspan=3>".
                              "<table class='wikitable'><tr><th>Mode Name</th><th>Cost</th><th>Is Weird?</th><th>Associated Skills</th></tr>";
                    foreach ($game->modes as $mode) {
                        $skill_list = array();
                        foreach ($mode['skill_list'] as $sk) {
                            $skill_list[] = $game->skills_by_id[$sk]['label'];
                        }
                        asort($skill_list);
                        $table .= "<tr><td style='vertical-align: top'>" . $mode['label'] . "</td>".
                                  "<td style='vertical-align: top'>" . $mode['cost'] . "</td>".
                                  "<td style='vertical-align: top'>" . ($mode['is_weird'] ? 'Yes' : 'No') . "</td>".
                                  "<td style='vertical-align: top'>" . implode(', ', $skill_list) . "</td></tr>";
                    }
                    $table .= "</table></td></tr>";
                }
                $table .= "<tr><td class='mw-label' nowrap>Turn Order Skills:</td>";
                if (count($game->turn_order) > 0) {
                    $first = 1;
                    foreach ($game->turn_order as $index => $track) {
                        if (!$first) {
                            $table .= "<tr><td>&nbsp;</td>";
                        }
                        $first = 0;
                        $table .= "<td class='mw-label'><strong>" . ($index == 1 ? 'Physical' : 'Mental') . ":</strong></td>";
                        $skills = array();
                        foreach ($track as $skill_id) {
                            $skills[] = $game->skills_by_id[$skill_id]['label'];
                        }
                        $table .= "<td colspan=2>" . implode(', ', $skills) . "</td></tr>";
                    }
                } else {
                    $table .= "<td colspan=3>Undefined</td></tr>";
                }
                $table .= "<tr><td class='mw-label' nowrap>Refresh Rate:</td><td colspan=3>$game->refresh_rate</td></tr>".
                          "<tr><td class='mw-label' nowrap>Initial Stunt Slots:</td><td colspan=3>$game->stunt_count</td></tr>".
                          "<tr><td class='mw-label' nowrap>Initial Stress Boxes:</td><td colspan=3>$game->stress_count</td></tr>";
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
                $table .= "<tr><td class='mw-label'>Use Atomic Robo style Refresh (Aspect count, don't subtract stunts):</td><td colspan=3>".
                          ($game->use_robo_refresh ? 'Yes' : 'No') . "</td></tr>";
                $table .= "</table>";
                
                if(count($game->fractals) > 0) {
                    /* Handle Characters first, if they exist */
                    if (count($game->fractals['Character']) > 0) {
                        $characters = $game->fractals['Character'];
                        $table .= "<table class='wikitable'><caption>Characters<caption>".
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
                        $table .= "<table class='wikitable'><caption>$key</caption>".
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
                $table .= Linker::link(Title::newFromText('Special:FateStats')->getSubpage("Create"), 'Create New Fractal', array(), array( 'game_id' => $game_id ), array ( 'forcearticlepath' ) );
                if ($game->pending_stat_approvals) {
                    $table .= "<br/>" . Linker::link($this->getPageTitle()->getSubPage('Approval'), 'You have pending approvals', array(), array( 'game_id' => $game_id ), array( 'forcearticlepath' ) );
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
            $table .= "<table class='wikitable' >".
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