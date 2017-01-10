<?php
/**
 * SpecialPage for FateCharGen extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialFateStats extends SpecialPage {
    public function __construct() {
        parent::__construct( 'FateStats' );
    }

    private $edit_data = array(
        FateGameGlobals::STAT_ASPECT => array(
            'fields' => array(
                'label' => array( 'label' => 'Label (Optional)', 'size' => 35 ),
                'field' => array( 'label' => 'Aspect', 'size' => 35 )
            ),
            'required' => array( 'field' ),
            'unique' => 0
        ),
        FateGameGlobals::STAT_MODE => array(
            'fields' => array(
                'field' => array( 'label' => 'Mode', 'type' => 'select' ),
                'value' => array( 'label' => 'Rating', 'size' => 1 )
            ),
            'required' => array( 'field', 'value' ),
            'unique' => 0
        ),
        // NOTE: format data for skills is for non-mode games only; modes require unique handling
        FateGameGlobals::STAT_SKILL => array(
            'fields' => array(
                'label' => array( 'label' => 'Skill', 'type' => 'select' ),
                'value' => array( 'label' => 'Rating', 'size' => 1 )
            ),
            'required' => array( 'label', 'value' ),
            'unique' => 0
        ),
        FateGameGlobals::STAT_MOOK => array(
            'fields' => array(
                'field' => array( 'label' => 'Skill', 'size' => 35 ),
                'value' => array( 'label' => 'Rating', 'type' => 'select' )
            ),
            'required' => array( 'field', 'value' ),
            'unique' => 0
        ),
        FateGameGlobals::STAT_STUNT => array(
            'fields' => array(
                'field' => array( 'label' => 'Title', 'size' => 35 ),
                'description' => array( 'label' => 'Description', 'type' => 'textarea', 'rows' => 3, 'cols' => 80 ),
                'is_secret' => array( 'label' => 'Is Secret', 'type' => 'checkbox', 'toggle' => 1 )
            ),
            'required' => array( 'field', 'description' ),
            'unique' => 0
        ),
        FateGameGlobals::STAT_CONSEQUENCE => array(
            'fields' => array(
                'label' => array( 'label' => 'Label', 'size' => 35 ),
                'display_value' => array( 'label' => 'Modifier', 'size' => 1 ),
                'field' => array( 'label' => 'Aspect (Optional)', 'size' => 35 )
            ),
            'required' => array( 'label', 'display_value' ),
            'unique' => 0
        ),
        FateGameGlobals::STAT_CONDITION => array(
            'fields' => array(
                'label' => array( 'label' => 'Label', 'size' => 35 ),
                'display_value' => array( 'label' => 'Category', 'type' => 'select' ),
                'value' => array( 'label' => 'Checked Boxes', 'size' => 1  ),
            ),
            'required' => array( 'label', 'display_value', 'value' ),
            'unique' => 0
        ),
        FateGameGlobals::STAT_STRESS => array(
            'fields' => array(
                'label' => array( 'label' => 'Track Name', 'size' => 35 ),
                'max_value' => array( 'label' => 'Maximum Value', 'size' => 1 ),
                'value' => array( 'label' => 'Checked Boxes', 'size' => 1 )
            ),
            'required' => array( 'label', 'max_value', 'value' ),
            'unique' => 0
        ),
        FateGameGlobals::STAT_FATE => array(
            'fields' => array(
                'value' => array( 'label' => 'Current Fate Points', 'size' => 1 ) 
            ),
            'required' => array( 'value' ),
            'unique' => 1
        ),
        FateGameGlobals::STAT_REFRESH => array(
            'fields' => array(
                'value' => array( 'label' => 'Fate Refresh', 'size' => 1 ) 
            ),
            'required' => array( 'value' ),
            'unique' => 1
        )
    );

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
        
        //$out->addHelpLink('http://aliencity.org/wiki/Extension:FateCharGen', true);
        
        if ($user->isAnon()) {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>You must be logged in to access this page.</div>");
        } else {
            $fractal_id = $request->getInt('fractal_id');
            $sheet = $request->getInt('sheet');
            if ($sub == 'View') {
                $this->viewFractalBlock($fractal_id);
            } elseif ($sub == 'ViewSheet') {
                $this->viewFractalSheet($fractal_id);
            } elseif ($sub == 'Edit') {
                $results = array();
                if ($action == 'edit') {
                    $results = $this->processFractalEditForm();
                    if (count($results['error']) == 0) {
                        $this->saveFractalEdits($fractal_id, $results);
                        $results = $this->cleanResults($results);
                    }
                }
                $this->editFractal($fractal_id, $sheet, $results);
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
                    $this->editFractal(intval($result['msg']),0);
                }
            } elseif ($sub == 'Milestones') {
                $results = array();
                if ($request->wasPosted()) {
                    $results = $this->processMilestoneForm($fractal_id);
                    if (count($results['error']) == 0) {
                        $this->saveMilestoneResults($fractal_id, $results);
                    }
                    $this->viewMilestones($fractal_id, $results);
                } else {
                    $this->viewMilestones($fractal_id);
                }
            } else {
                $this->listCharacters();
            }
        }   
    }
    
    private function saveMilestoneResults( $fractal_id, $results ) {
        $output = $this->getOutput();
        $fractal = new FateFractal($fractal_id);
        $dbw = wfGetDB(DB_MASTER);
        
        foreach ($results as $form_type => $data) {
            if ($form_type == 'form' || $form_type == 'error') {
                continue;
            }
            foreach ($data as $stat_type => $rows) {
                foreach ($rows as $row => $info) {
                    $sql_data = array(
                        'fractal_id' => $fractal_id,
                        'stat_type' => $stat_type,
                        'modified_date' => $dbw->timestamp()
                    );
                    foreach ($info as $field => $value) {
                        $sql_data['stat_' . $field] = $value;
                    }
                    if ($form_type == 'pending') {
                        // Clear denied reason, just in case it's set
                        $sql_data['denied_reason'] = '';
                        $sql_data['denied_id'] = '';
                        $dbw->update(
                            'fate_pending_stat',
                            $sql_data,
                            array( 'pending_stat_id' => $row )
                        );
                    } else {
                        if ($form_type == 'named') {
                            $sql_data['stat_label'] = $fractal->fate_game->aspects_by_id[$row]['label'];
                            $sql_data['parent_id'] = $row;
                        }
                        $dbw->insert(
                            'fate_pending_stat',
                            $sql_data
                        );
                    }
                }
            }
        }
    }
    
    private function processMilestoneForm( $fractal_id ) {
        $request = $this->getRequest();
        $data = $request->getValues();
        $fractal = new FateFractal($fractal_id);
        $output = $this->getOutput();
        
        $results = array(
            'new' => array(),
            'named' => array(),
            'pending' => array(),
            'error' => array(),
            'form' => $data
        );
        
        foreach ($data as $key => $value) {
            $value = trim($value);
            if (preg_match("/^(\d+)_([a-z]+)_([a-z]+)_(\d+)$/", $key, $matches)) {
                // If it was a pending stat hasn't changed, don't bother
                if ($matches[2] == 'pending' && $fractal->pending_stats_by_id[$matches[4]]->{'stat_' . $matches[3]} == $value) {
                    continue;
                }
                if (!$value) {
                    continue;
                }
                $results[$matches[2]][$matches[1]][$matches[4]][$matches[3]] = $value;
            }
        }
        
        // If we have pending options and we didn't see them, flag as errors
        foreach ($fractal->pending_stats as $type => $array) {
            foreach ($array as $pending) {
                // Skip any where we can't edit them anyway
                if (!$pending->{denied_reason}) {
                    continue;
                }
                // Now see if something was required was erased
                if (array_key_exists($type, $results['pending'])) {
                    if (!$data[$pending->{stat_type} . '_pending_field_' . $pending->{pending_stat_id}]) {
                        $results['error']['pending'][$pending->{stat_type}][$pending->{pending_stat_id}]['field'] = 1;
                    } elseif ($pending->{stat_type} == FateGameGlobals::STAT_STUNT) {
                        if (!$data[$pending->{stat_type} . '_pending_description_' . $pending->{pending_stat_id}]) {
                            $results['error']['pending'][$pending->{stat_type}][$pending->{pending_stat_id}]['description'] = 1;
                        }
                    }
                } else {
                    $results['error']['pending'][$pending->{stat_type}][$pending->{pending_stat_id}]['all'] = 1;
                }
            }
        }
        
        // Now go through the new stunts, make sure we got descriptions and fields both
        if (array_key_exists(FateGameGlobals::STAT_STUNT, $results['new'])) {
            foreach ($results['new'][FateGameGlobals::STAT_STUNT] as $row => $array) {
                $error = 0;
                if (!array_key_exists('field', $array)) {
                    $error = 1;
                    $results['error']['new'][FateGateGlobals::STAT_STUNT][$row]['field'] = 1;
                } elseif (!array_key_exists('description', $array)) {
                    $error = 1;
                    $results['error']['new'][FateGameGlobals::STAT_STUNT][$row]['description'] = 1;
                }
                if ($error) {
                    unset($results['new'][FateGameGlobals::STAT_STUNT][$row]);
                }
            }
        }
        
        return $results;
    }
    
    private function viewMilestones( $fractal_id, $results = array() ) {
        $user = $this->getUser();
        $out = $this->getOutput();
        
        $table = '';
        if ($fractal_id) {
            $fractal = new FateFractal($fractal_id);
            if ($fractal->name) {
                if ($fractal->user_id == $user->getID() || $fractal->fate_game->is_staff($user->getID()) || $user->isAllowed('fategm')) {
                    $table .= $this->getMilestoneForm($fractal, $results);
                } else {
                    $table .= "<div class='error' style='font-weight: bold; color: red'>You don't have permission to view milestones for this character.</div>";
                }
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red'>No data found for that fractal_id; please check URL and try again.</div>";
            }
        } else {
            $table .= "<div class='error' style='font-weight: bold; color: red'>Missing fractal_id argument; don't know which fractal to show.</div>";
        }
        $out->addHTML($table);
    }
    
    private function getMilestoneForm( $fractal, $results ){
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $form_url = $this->getPageTitle()->getSubPage('Milestones')->getLinkURL();
        $fractal_id = $fractal->fractal_id;
        
        $form = '<h2>Milestone Advancements for ' . $fractal->name . '</h2>';
        $form .= <<<EOT
            <script type='text/javascript'>
            </script>
            <form action='$form_url' method='post'>
            <input type='hidden' name='fractal_id' value='$fractal_id'/>
EOT;

        // Did we have errors? If show, display a big warning here
        if (count($results) > 0) {
            if (count($results['error']) > 0) {
                $form .= "<div class='errorbox'><strong>Submission error.</strong><br/>One or more required fields seem to be missing. Please correct them below, and resubmit to save edits.</div>";
            } else {
                $form .= "<div class='successbox'><strong>Milestones submitted.</strong></div>";
            }
        }
        
        // If this isn't our character, make sure we label that
        if ($fractal->user_id != $user->getID()) {
            $form .= "<div class='errorbox'><strong>WARNING: This character does not belong to you. Milestones visible for troubleshooting purposes only.</strong></div>";
        }
        
        $form .= $fractal->getFractalBlock(1);
        
        $submit = 0;
        // Start by checking for post-chargen 'holes'
        if (count($fractal->stats[FateGameGlobals::STAT_ASPECT]) < $fractal->fate_game->aspect_count) {
            $submit = 1;
            $remaining = $fractal->fate_game->aspect_count - count($fractal->stats[FateGameGlobals::STAT_ASPECT]);
            $form .= "<fieldset><legend>$remaining Remaining Aspects</legend><table><tbody>";
            // Go through labeled Aspects first
            foreach ($fractal->fate_game->aspects as $aspect) {
                $found = 0;
                if (array_key_exists(FateGameGlobals::STAT_ASPECT, $fractal->stats)) {
                    foreach ($fractal->stats[FateGameGlobals::STAT_ASPECT] as $fractal_aspect) {
                        if ($fractal_aspect->{parent_id} == $aspect['aspect_id']) {
                            $found = 1;
                            break;
                        }
                    }
                }
                if (!$found) {
                    $remaining--;
                    $pending = new stdClass();
                    if (array_key_exists(FateGameGlobals::STAT_ASPECT, $fractal->pending_stats)) {    
                        foreach ($fractal->pending_stats[FateGameGlobals::STAT_ASPECT] as $pending_aspect) {
                            if ($pending_aspect->{parent_id} == $aspect['aspect_id']) {
                                $pending = $pending_aspect;
                                break;
                            }
                        }
                    }
                    $form .= $this->getMilestoneRow(FateGameGlobals::STAT_ASPECT, $results, $aspect, $pending);
                }
            }
            
            // Then do the other Aspects
            if ($remaining) {
                if(array_key_exists(FateGameGlobals::STAT_ASPECT, $fractal->pending_stats)) {
                    foreach ($fractal->pending_stats[FateGameGlobals::STAT_ASPECT] as $pending_aspect) {
                        if ($pending_aspect->{parent_id} || $pending_aspect->{original_stat_id}) {
                            continue;
                        }
                        $remaining--;
                        $form .= $this->getMilestoneRow(FateGameGlobals::STAT_ASPECT, $results, array(), $pending_aspect);
                    }
                }
                while ($remaining > 0) {
                    $form .= $this->getMilestoneRow(FateGameGlobals::STAT_ASPECT, $results, array(), new StdClass(), $remaining--);
                }
            }
                
            
            $form .= "</tbody></table></fieldset>";
        }
        
        if (count($fractal->stats[FateGameGlobals::STAT_STUNT]) < $fractal->fate_game->stunt_count) {
            $submit = 1;
            $remaining = $fractal->fate_game->stunt_count - count($fractal->stats[FateGameGlobals::STAT_STUNT]);
            $form .= "<fieldset><legend>$remaining Remaining Initial Stunts</legend><table><tbody>";
            // See if we have any pending first
            if (array_key_exists(FateGameGlobals::STAT_STUNT, $fractal->pending_stats)) {
                foreach ($fractal->pending_stats[FateGameGlobals::STAT_STUNT] as $pending_stunt) {
                    $remaining--;
                    $form .= $this->getMilestoneRow(FateGameGlobals::STAT_STUNT, $results, array(), $pending_stunt);
                }
            }
            // Then do the leftovers
            while ($remaining > 0) {
                $form .= $this->getMilestoneRow(FateGameGlobals::STAT_STUNT, $results, array(), new StdClass(), $remaining--);
            }
            $form .= "</tbody></table></fieldset>";
        }

        if ($submit) {
            $form .= "<input type='submit' value='Submit'/>";
        } else {
            $form .= "<div class='error' style='font-weight: bold; color: red;'>No pending milestones found for this character.</div>";
        }
        $form .= "</form>";
        
        return $form;
    }
    
    private function getMilestoneRow( $stat_type, $results, $game_stat, $pending_stat, $counter = 0 ) {
        $pending = 0;
        if ($pending_stat->{pending_stat_id}) {
            $pending = 1;
        }
        $label = '';
        if ($stat_type == FateGameGlobals::STAT_ASPECT) {
            $label = ($game_stat['label'] ? $game_stat['label'] : 'Other') . " Aspect:";
        } elseif ($stat_type == FateGameGlobals::STAT_STUNT) {
            $label = 'New Stunt:';
        }
        $id = $stat_type . '_';
        $row_type = 'new';
        $group = $counter;
        if ($pending) {
            $id .= 'pending_field_' . $pending_stat->{pending_stat_id};
            $row_type = 'pending';
            $group = $pending_stat->{pending_stat_id};
        } elseif ($game_stat['aspect_id']) {
            $id .= 'named_field_' . $game_stat['aspect_id'];
            $row_type = 'named';
            $group = $game_stat['aspect_id'];
        } else {
            $id .= 'new_field_' . $counter;
        }
        $value = ($pending ? htmlspecialchars($pending_stat->{stat_field}, ENT_QUOTES) : '');
        if (count($results) > 0 && array_key_exists($id, $results['form'])) {
            $value = htmlspecialchars($results['form'][$id], ENT_QUOTES);
        }
        $disabled = '';
        $message = '';
        if ($pending) {
            if ($pending_stat->{denied_reason}) {
                $message = "Denied by " . $pending_stat->{denied_user} . " on " . FateGameGlobals::getDisplayDate($pending_stat->{modified_date}) .
                           " for: <em>" . $pending_stat->{denied_reason} . "</em>";
            } else {
                $message = "Pending approval; submitted on " . FateGameGlobals::getDisplayDate($pending_stat->{modified_date});
                $disabled = 'disabled';
            }
        }
        $row = "<tr><td class='mw-label'><label for='$id'>$label</label></td>".
               "<td class='mw-input'><input type='text' id='$id' name='$id' value='$value' size='35' $disabled/></tr>";
        if ($results['error'][$row_type][$stat_type][$group]['all'] || $results['error'][$row_type][$stat_type][$group]['field']) {
            $row .= "<tr><td>&nbsp;</td><td class='error'>Field required.</td></tr>";
        }
        if ($stat_type == FateGameGlobals::STAT_STUNT) {
            $id = $stat_type . '_' . ($pending ? 'pending_description_' . $pending_stat->{pending_stat_id} : 'new_description_' . $counter);
            $value = ($pending ? htmlspecialchars($pending_stat->{stat_description}, ENT_QUOTES) : '');
            if (count($results) > 0 && array_key_exists($id, $results['form'])) {
                $value = htmlspecialchars($results['form'][$id], ENT_QUOTES);
            }
            $row .= "<tr><td class='mw-label'><label for='$id'>Description:</label></td>".
                    "<td class='mw-input'><textarea name='$id' id='$id' rows=3 cols=80 $disabled>$value</textarea></td></tr>";
            if ($results['error'][$row_type][$stat_type][$group]['all'] || $results['error'][$row_type][$stat_type][$group]['description']) {
                $row .= "<tr><td>&nbsp;</td><td class='error'>Field required.</td></tr>";
            }
        }
        if ($message) {
            $row .= "<tr><td>&nbsp;</td><td class='success'>$message</td></tr>";
        }
        return $row;
    }
    
    private function cleanResults( $results ) {
        $results['form'] = array();
        foreach ($results['new']  as $type => $array) {
            $results['new'][$type]['max'] = 1;
        }
        return $results;
    }
    
    private function saveFractalEdits( $fractal_id, $results ) {
        $fractal = new FateFractal($fractal_id);
        $update_modeskills = 0;
        $made_update = 0;
        $dbw = wfGetDB(DB_MASTER);
        
        foreach ($results['delete'] as $stat_id => $junk) {
            if ($fractal->stats_by_id[$stat_id]->{stat_type} == FateGameGlobals::STAT_MODE) {
                $update_modeskills = 1;
            }
            $res = $dbw->delete(
                'fate_fractal_stat',
                array( 'fractal_stat_id' => $stat_id )
            );
            $made_update = 1;
        }
        
        foreach ($results['edit'] as $stat_id => $changes ) {
            $updates = array(
                'modified_date' => $dbw->timestamp()
            );
            if ($fractal->stats_by_id[$stat_id]->{stat_type} == FateGameGlobals::STAT_MODE) {
                $update_modeskills = 1;
            }
            foreach ($changes as $field => $change) {
                if ($fractal->stats_by_id[$stat_id]->{stat_type} == FateGameGlobals::STAT_MODE && $field == 'field') {
                    $updates['stat_field'] = $fractal->fate_game->modes_by_id[$change]['label'];
                    $updates['parent_id'] = $change;
                } elseif ($field == 'is_secret' || $field == 'parent_id') {
                    $updates[$field] = $change;
                } else {
                    $updates['stat_' . $field] = $change;
                }
            }
            $dbw->update(
                'fate_fractal_stat',
                $updates,
                array( 'fractal_stat_id' => $stat_id )
            );
            $made_update = 1;
        }
        
        foreach ($results['new'] as $stat_type => $rows) {
            foreach ($rows as $row => $data) {
                if ($row == 'max') {
                    continue;
                }
                $inserts = array(
                    'fractal_id' => $fractal_id,
                    'stat_type' => $stat_type,
                    'modified_date' => $dbw->timestamp()
                );
                if ($stat_type == FateGameGlobals::STAT_MODE) {
                    $update_modeskills = 1;
                    $inserts['parent_id'] = $data['field'];
                    $inserts['stat_value'] = $data['value'];
                    $inserts['stat_field'] = $fractal->fate_game->modes_by_id[$data['field']]['label'];
                } else {
                    foreach ($data as $field => $value) {
                        if ($field == 'parent_id' || $field == 'is_secret') {
                            $inserts[$field] = $value;
                        } else {
                            $inserts['stat_' . $field] = $value;
                        }
                    }
                    if ($stat_type == FateGameGlobals::STAT_SKILL && $fractal->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
                        $inserts['is_discipline'] = 1;
                    }
                }
                $dbw->insert(
                    'fate_fractal_stat',
                    $inserts
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
        
        if ($update_modeskills) {
            $fractal->resetModeSkills();
        }
    }
    
    private function processFractalEditForm() {
        $request = $this->getRequest();
        $output = $this->getOutput();
        $data = $request->getValues();
        $fractal_id = $request->getInt('fractal_id');
        $fractal = new FateFractal($fractal_id);
        
        $results = array(
            'delete' => array(),
            'edit' => array(),
            'new' => array(),
            'error' => array(),
            'form' => $data
        );
     
        // Start by hunting for deletes
        foreach ($data as $key => $value) {
            if (preg_match("/^delete_(\d+)$/", $key, $matches)) {
                $results['delete'][$matches[1]] = 1;
            }
        }
        
        // Now look for edits
        foreach ($data as $key => $value) {
            if (preg_match("/^(\d+)_(?!new_)(\w+)_(\d+)$/", $key, $matches)) {
                $stat_type = $matches[1];
                $field = $matches[2];
                $stat_id = $matches[3];
                $value = trim($value);

                // If we're already deleting this, then skip
                if ($results['delete'][$stat_id]) {
                    continue;
                 
                // See if anything changed; no need to make an update if nothing did
                } else if ($fractal->stats_by_id[$stat_id]->{'stat_' . $field} == $value) {
                    continue;
                    
                // If we're looking at a mode's field, compare the parent_id
                } else if ($stat_type == FateGameGlobals::STAT_MODE && $field == 'field' && $fractal->stats_by_id[$stat_id]->{'parent_id'} == $value) {
                    continue;
                
                // Compare parent_id for skill labels as well
                // TODO: Handling for customized skill names
                } else if ($stat_type == FateGameGlobals::STAT_SKILL && $field == 'label' && $fractal->stats_by_id[$stat_id]->{'parent_id'} == $value) {
                    continue;
                
                // If we're looking at an empty value, and it was required here, flag it an error
                } else if (!$value && in_array($field, $this->edit_data[$stat_type]['required'])) {
                    // EXCEPTION:
                    // If we're doing Approaches, we can set the value to 0
                    if (! ($fractal->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_APPROACHES && $field == 'value' && $stat_type == FateGameGlobals::STAT_SKILL && $value === '0')) {
                        $results['error']['required'][$stat_id][] = $field;
                        continue;
                    }
                }
                
                // If we're here, we found a change; save it to make an update
                if ($stat_type == FateGameGlobals::STAT_SKILL && $field == 'label') {
                    $results['edit'][$stat_id][$field] = $fractal->fate_game->skills_by_id[$value]['label'];
                    $results['edit'][$stat_id]['parent_id'] = $value;
                } else {
                    $results['edit'][$stat_id][$field] = $value;
                }
            }
        }
        
        // Edits, part two: look for fields that are 'toggled', which may have been set to clear (and so not in data list)
        foreach ($this->edit_data as $stat_type => $stat_info) {
            foreach ($stat_info['fields'] as $field => $field_data) {
                if ($field_data['toggle'] == 1 && is_array($fractal->stats[$stat_type])) {
                    foreach($fractal->stats[$stat_type] as $stat) {
                        if ($results['delete'][$stat->{fractal_stat_id}] == 1) {
                            continue;
                        }
                        if ($stat->{$field} == 1 and ! array_key_exists($data, $stat_type . '_' . $field . '_' . $stat->{fractal_stat_id})) {
                            $results['edit'][$stat->{fractal_stat_id}][$field] = 0;
                        }
                    }
                }
            }
        }
        
        // Now look for new entries
        foreach ($data as $key => $value) {
            if (preg_match("/^(\d+)_new_(\w+)_(\d+)$/", $key, $matches)) {
                $stat_type = $matches[1];
                $field = $matches[2];
                $grouping = $matches[3];
                if ($stat_type == FateGameGlobals::STAT_SKILL && $field == 'label') {
                    $results['new'][$stat_type][$grouping][$field] = $fractal->fate_game->skills_by_id[$value]['label'];
                    $results['new'][$stat_type][$grouping]['parent_id'] = $value;
                } else {
                    $results['new'][$stat_type][$grouping][$field] = $value;
                }
                // Record how many dynamically created rows we had, so we can recreate them all
                if ($results['new'][$stat_type]['max'] < $grouping) {
                    $results['new'][$stat_type]['max'] = $grouping;
                }
            }
        }
        
        // If we're editing a character, check for edited or new aspects to see if we need to set parent_id
        if ($fractal->fractal_type == 'Character') {
            foreach ($data as $key => $value) {
                if (preg_match("/^select_(\d+)_new_(\w+)_(\d+)$/", $key, $matches) && $value != 0) {
                    $stat_type = $matches[1];
                    $field = $matches[2];
                    $grouping = $matches[3];
                    $results['new'][$stat_type][$grouping]['parent_id'] = $value;
                    if ($value != 0) {
                        $results['new'][$stat_type][$grouping]['label'] = $fractal->fate_game->aspects_by_id[$value]['label'];
                    }
                } else if (preg_match("/^select_(\d+)_(?!new_)(\w+)_(\d+)$/", $key, $matches)) {
                    $stat_type = $matches[1];
                    $field = $matches[2];
                    $stat_id = $matches[3];
                    $value = trim($value);

                    // If we're already deleting this, then skip
                    if ($results['delete'][$stat_id]) {
                        continue;
                    
                    // If parent ids match, this didn't change
                    } else if ($fractal->stats_by_id[$stat_id]->{'parent_id'} == $value) {
                        continue;
                        
                    // Otherwise, update things appropriately
                    } else {
                        $results['edit'][$stat_id]['parent_id'] = $value;
                        if ($value != 0) {
                            $results['edit'][$stat_id]['label'] = $fractal->fate_game->aspects_by_id[$value]['label'];
                        }
                    }
                }
            }
        }
        
        // Once organized, see if this looks like it may just be cruft we can ignore
        // Add 'preset' array to stat_data hash?
        foreach ($results['new'] as $stat_type => $rows) {
            foreach ($rows as $row_index => $row) {
                if ($row_index == 'max') {
                    continue;
                }
                $required_count = 0;
                foreach ($this->edit_data[$stat_type]['required'] as $required) {
                    if ($row[$required] != '') {
                        $required_count++;
                    }
                }
                if ($required_count != count($this->edit_data[$stat_type]['required'])) {
                    unset($results['new'][$stat_type][$row_index]);
                }
            }
        }
     
        return $results;
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
        } elseif ($new_fractal['fractal_type'] == 'Setting') {
            $result['error'] = 1;
            $result['msg'] = '"Setting" is a reserved type, unique to a single fractal for each game.';
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
                $new_fractal['update_date'] = $dbw->timestamp();
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
                        </tbody>
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

    
    private function listCharacters() {
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
            array( 'user_id' => $user->getID(),
                   'fractal_type' => 'Character' ),
            __METHOD__,
            array( 'ORDER BY' => array ('fractal_type', 'fractal_name', 'canon_name' ) ),
            array( 'r' => array( 'LEFT JOIN', 'f.register_id = r.register_id' ),
                   'g' => array( 'JOIN', 'f.game_id = g.game_id' ) )
        );
        
        $table = "<table class='wikitable'>".
                 "<tr><th>Character Name</th><th>Game</th><th>Status</th></tr>";
        if ($fractal_list->numRows() == 0) {
            $table .= "<tr><td colspan=100%>No Characters Found</td></tr>";
        } else {
            foreach ($fractal_list as $fractal) {
                $subpage = "ViewSheet";
                $status = "Created";
                $status_date = $fractal->create_date;
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
                $table .= "<tr><td>" .
                    Linker::link($this->getPageTitle()->getSubpage($subpage), $name, array(), array( 'fractal_id' => $fractal->fractal_id ), array( 'forcearticalpath' ) ) .
                    "</td><td>";
                if ($user->isAllowed('fatestaff')) {
                    $table .= Linker::link(Title::newFromText('Special:FateGameConfig')->getSubpage('View'), $fractal->game_name, array(), array( 'game_id' => $fractal->game_id ), array( 'forcearticlepath' ) );
                } else {
                    $table .= $fractal->game_name;
                }
                $table .= "</td><td>" . $status . " on " . FateGameGlobals::getDisplayDate($status_date) . "</td></tr>";
            }
            $table .= "</table>";
        }
        $out->addHTML($table);
    }
    
    private function editFractal( $fractal_id, $sheet, $results = array() ) {
        $user = $this->getUser();
        $out = $this->getOutput();
        
        $table = '';
        if ($fractal_id) {
            $fractal = new FateFractal($fractal_id);
            if ($fractal->name) {
                if ($user->isAllowed('fategm') || $fractal->fate_game->is_staff($user->getID())) {
                    $table .= $this->getFractalEditForm($fractal, $sheet, $results);
                } else {
                    $table .= "<div class='error' style='font-weight: bold; color: red'>You do not have permission to edit this fractal.</div>";
                }
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red'>No data found for that fractal_id; please check URL and try again.</div>";
            }
        } else {
            $table .= "<div class='error' style='font-weight: bold; color: red'>Missing fractal_id argument; don't know which fractal to show.</div>";
        }
        $out->addHTML($table);
    }
    
    private function getFractalEditForm( $fractal, $sheet, $results ) {
        $user = $this->getUser();
        $out = $this->getOutput();
        $request = $this->getRequest();
        
        $form_url = $this->getPageTitle()->getSubPage('Edit')->getLinkURL();
        $fractal_id = $fractal->fractal_id;
        $read_only = ''; 
        if ($fractal->user_name) {
            $read_only = 'readonly';
        }
        // TODO: if a character, display basic attributes some other non-form way
        
        $const = FateGameGlobals::getStatConsts();
        $condition_categories = $this->getConditionCategoriesJS();
        $game_modes = '';
        $skills_array = '';
        $mode_levels = '';
        $mook_levels = $this->getMookLevelsJS();
        $game_aspects = $this->getGameAspectsJS($fractal);
        if ($fractal->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
            $game_modes = $this->getGameModesJS($fractal);
            $mode_levels = $this->getModeLevelsJS();
        } else {
            $game_skills = $this->getGameSkillsJS($fractal);
        }
        $form .= <<<EOT
            <script type='text/javascript'>
                var TYPE_CONDITION = {$const['condition']};
                var TYPE_MODE = {$const['mode']};
                var TYPE_SKILL = {$const['skill']};
                var TYPE_MOOK = {$const['mook']};
                var TYPE_ASPECT = {$const['aspect']};
                var FRACTAL_TYPE = '{$fractal->fractal_type}';
                $condition_categories
                $game_modes
                $mode_levels
                $game_skills
                $mook_levels
                $game_aspects
                
                var edit_data = new Array();
                edit_data[{$const['aspect']}] = {
                    fields: { 
                        label: { label: 'Label (Optional)', size: 35 },
                        field: { label: 'Aspect', size: 35 }
                    },
                    required: [ 'field' ],
                    unique: 0
                };
                edit_data[{$const['mode']}] = {
                    fields: {
                        field: { label: 'Mode', type: 'select' },
                        value: { label: 'Rating', size: 1 }
                    },
                    required: [ 'field', 'value' ],
                    unique: 0
                };
                edit_data[{$const['skill']}] = {
                    fields: {
                        label: { label: 'Skill', type: 'select' },
                        value: { label: 'Rating', size: 1 },
                    },
                    required: [ 'label', 'value' ],
                    unique: 0
                };
                edit_data[{$const['mook']}] = {
                    fields: {
                        field: { label: 'Skill', size: 35 },
                        value: { label: 'Rating', type: 'select' }
                    },
                    required: [ 'field', 'value' ],
                    unique: 0
                };
                edit_data[{$const['stunt']}] = {
                    fields: {
                        field: { label: 'Title', size: 35 },
                        description: { label: 'Description', type: 'textarea', rows: 3, cols: 80 },
                        is_secret: { label: 'Is Secret', type: 'checkbox' }
                    },
                    required: [ 'field', 'description' ],
                    unique: 0
                };
                edit_data[{$const['consequence']}] = {
                    fields: {
                        label: { label: 'Label', size: 35 },
                        display_value: { label: 'Modifier', size: 1 },
                        field: { label: 'Aspect (Optional)', size: 35 }
                    },
                    required: [ 'label', 'display_value' ],
                    unique: 0
                };
                edit_data[{$const['condition']}] = {
                    fields: {
                        label: { label: 'Label', size: 35 },
                        display_value: { label: 'Category', type: 'select' },
                        value: { label: 'Checked Boxes', size: 1 }
                    },
                    required: [ 'label', 'display_value', 'value' ],
                    unique: 0
                };
                edit_data[{$const['stress']}] = {
                    fields: {
                        label: { label: 'Track Name', size: 35 },
                        max_value: { label: 'Maximum Value', size: 1 },
                        value: { label: 'Checked Boxes', size: 1 }
                    },
                    required: [ 'label', 'max_value', 'value' ],
                    unique: 0
                };
                edit_data[{$const['fate']}] = {
                    fields: {
                        value: { label: 'Current Fate Points', size: 1 }
                    },
                    required: [ 'value' ],
                    unique: 1
                };
                edit_data[{$const['refresh']}] = {
                    fields: {
                        value: { label: 'Fate Refresh', size: 1 }
                    },
                    required: [ 'value' ],
                    unique: 1
                };
            
                function createLabelCell(field_info, name) {
                    var cell = document.createElement('td');
                    cell.className = 'mw-label';
                    var label = document.createElement('label');
                    label.setAttribute('for', 'ef' + name);
                    label.appendChild(document.createTextNode(field_info.label + ':'));
                    cell.appendChild(label);
                    return cell;
                }
                
                function createInputSelect(field_info, name, stat_type, new_id, list, mode_skill_label = '', mode_value = 0) {
                    var select = document.createElement('select');
                    var optionArray = new Array();
                    select.setAttribute('id', 'ef' + name);
                    select.setAttribute('name', name);
                    if (mode_value) {
                        select.setAttribute('onchange', 'addNewRow(' + stat_type + ',' + new_id + ',"' + mode_skill_label + '",' + mode_value + ');');
                    } else {
                        select.setAttribute('onchange', 'addNewRow(' + stat_type + ',' + new_id + ');');
                    }
                    for (var i in list) {
                        var option = document.createElement('option');
                        option.setAttribute('value', i);
                        option.appendChild(document.createTextNode(list[i]));
                        if (mode_value) {
                            optionArray.push(option);
                        } else {
                            select.appendChild(option);
                        }
                    }
                    // Push-and-pop off a stack to get options for mode skills in the proper order
                    if (mode_value) {
                        optionArray.reverse();
                        for (var i in optionArray) {
                            select.appendChild(optionArray[i]);
                        }
                    }
                    return select;
                }
                
                function getModeLevelList(mode_value) {
                    var newList = new Array();
                    for (var i in modeLevels) {
                        newList[parseInt(i) + mode_value] = modeLevels[i] + ' (+' + i + ')';
                    }
                    return newList;
                }
                
                function createAspectSelectCell(field_info, name, stat_type, new_id) {
                    var cell = document.createElement('td');
                    cell.className = 'mw-input';
                    var select = createInputSelect(field_info, name, stat_type, new_id, gameAspects);
                    select.setAttribute('id', 'select_ef' + name);
                    select.setAttribute('name', 'select_' + name);
                    select.setAttribute('onchange', 'addNewRow(' + stat_type + ',' + new_id + '); toggleAspectLabel("ef' + name + '");');
                    cell.appendChild(select);
                    return cell;
                }
                
                function createInputCell(field_info, name, stat_type, new_id, mode_skill_label, mode_value) {
                    var cell = document.createElement('td');
                    cell.className = 'mw-input';
                    if (stat_type == TYPE_CONDITION && field_info.type == 'select') {
                        cell.appendChild(createInputSelect(field_info, name, stat_type, new_id, conditions));
                    } else if (stat_type == TYPE_MODE && field_info.type == 'select') {
                        cell.appendChild(createInputSelect(field_info, name, stat_type, new_id, modeList));
                    } else if (stat_type == TYPE_SKILL && field_info.type == 'select') {
                        cell.appendChild(createInputSelect(field_info, name, stat_type, new_id, skillList));
                    } else if (stat_type == TYPE_MOOK && field_info.type == 'select') {
                        cell.appendChild(createInputSelect(field_info, name, stat_type, new_id, mookLevels));
                    } else if (stat_type == TYPE_SKILL && field_info.type == 'rankselect') {
                        modeLevelList = getModeLevelList(mode_value);
                        cell.appendChild(createInputSelect(field_info, name, stat_type, new_id, modeLevelList, mode_skill_label, mode_value));
                    } else if (field_info.type == 'checkbox') {
                        var checkbox = document.createElement('input');
                        checkbox.setAttribute('type', 'checkbox');
                        checkbox.setAttribute('id', 'ef' + name);
                        checkbox.setAttribute('name', name);
                        checkbox.setAttribute('value', 1);
                        checkbox.setAttribute('onchange', 'addNewRow(' + stat_type + ',' + new_id + ');');
                        cell.appendChild(checkbox);
                    } else if (field_info.type == 'textarea') {
                        var textbox = document.createElement('textarea');
                        textbox.setAttribute('id', 'ef' + name);
                        textbox.setAttribute('name', name);
                        textbox.setAttribute('oninput', 'addNewRow(' + stat_type + ',' + new_id + ');');
                        textbox.setAttribute('rows', field_info.rows);
                        textbox.setAttribute('cols', field_info.cols);
                        cell.appendChild(textbox);
                    } else {
                        var input = document.createElement('input');
                        input.setAttribute('id', 'ef' + name);
                        input.setAttribute('name', name);
                        input.setAttribute('type', 'text');
                        input.setAttribute('size', field_info.size);
                        if (mode_value) {
                            input.setAttribute('oninput', 'addNewRow(' + stat_type + ',' + new_id + ',"' + mode_skill_label + '",' + mode_value + ');');
                        } else {
                            input.setAttribute('oninput', 'addNewRow(' + stat_type + ',' + new_id + ');');
                        }
                        cell.appendChild(input);
                    }
                    return cell;
                }
                
                // Creates extra spaces, to match with delete functionality
                function emptyCell() {
                    var cell = document.createElement('td');
                    cell.appendChild(document.createTextNode('\u00A0'));
                    return cell;
                }
            
                function addNewRow(stat_type, id, mode_skill_label = '', mode_value = 0) {
                    var check = document.getElementById('ef' + stat_type + '_new_' + edit_data[stat_type].required[0] + '_' + (parseInt(id) + 1));
                    if (!check) {
                        var fields;
                        if (mode_value) {
                            fields = {
                                label: { label: mode_skill_label, size: 35 },
                                value: { label: 'Ranking', type: 'rankselect', mode_value: mode_value }
                            };
                        } else {
                            fields = edit_data[stat_type].fields;
                        }
                        var new_id = parseInt(id) + 1;
                        var new_body = document.getElementById('newstat_' + stat_type);
                        var body = document.getElementById('stat_' + stat_type);
                        
                        var row = document.createElement('tr');
                        for (var field in fields) {
                            var input_name = stat_type + '_new_' + field + '_' + new_id;
                            row.appendChild(createLabelCell(fields[field], input_name));
                            if (field == 'label' && FRACTAL_TYPE == 'Character' && stat_type == TYPE_ASPECT) {
                                row.appendChild(createAspectSelectCell(fields[field], input_name, stat_type, new_id));
                            }
                            row.appendChild(createInputCell(fields[field], input_name, stat_type, new_id, mode_skill_label, mode_value));
                        }
                        row.appendChild(emptyCell());
                        row.appendChild(emptyCell());
                        new_body.appendChild(row);
                    }
                }
                
                function toggleAspectLabel(id) {
                    var select = document.getElementById('select_' + id);
                    var label = document.getElementById(id);
                    if (select.value == 0) {
                        label.style.display = 'inline-block';
                    } else {
                        label.style.display = 'none';
                    }
                }
            </script>
            <style type='text/css'>
                .formerror { border: 2px solid #cc0000; }
            </style>
            <form action='$form_url' method='post'>
                <input type='hidden' name='fractal_id' value='$fractal_id'/>
                <input type='hidden' name='sheet' value='$sheet'/>
                <input type='hidden' name='action' value='edit'/>
                <h2>Edit Fractal</h2>
EOT;

        // Did we have errors? If show, display a big warning here
        if (count($results) > 0) {
            if (count($results['error']) > 0) {
                $form .= "<div class='errorbox'><strong>Editing error.</strong><br/>One or more required fields seem to have been cleared in existing stats. Please correct them below, and resubmit to save edits.</div>";
            } else {
                $form .= "<div class='successbox'><strong>Stat updates saved.</strong></div>";
            }
        }
        
        $form .= <<<EOT
                <fieldset>
                    <legend>Basic Attributes</legend>
                    <table>
                        <tbody>
                        <tr>
                            <td class='mw-label'><label for='efname'>Fractal Name:</label></td>
                            <td class='mw-input'><input id='efname' $read_only name='fractal_name' value='$fractal->name' type='text' size='35'/></td>
                        </tr>
                        <tr>
                            <td class='mw-label'><label form='eftype'>Fractal Type:</label></td>
                            <td class='mw-input'><input id='eftype' $read_only name='fractal_type' value='$fractal->fractal_type' type='text' size='35'/></td>
                        </tr>
                        </tbody>
                    </table>
                </fieldset>
EOT;

        // Dynamically create the rest of this mess
        $stat_labels = FateGameGlobals::getStatLabels();
        foreach ($this->edit_data as $stat_id => $stat_data) {
            if ($stat_id == FateGameGlobals::STAT_CONDITION && $fractal->fate_game->use_consequences) {
                continue;
            } elseif ($stat_id == FateGameGlobals::STAT_CONSEQUENCE && !$fractal->fate_game->use_consequences) {
                continue;
            } elseif ($stat_id == FateGameGlobals::STAT_MODE && $fractal->fate_game->skill_distribution != FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
                continue;
            }
            
            $form .= "<fieldset><legend>" . $stat_labels[$stat_id]. "</legend>";
            
            if ($stat_id == FateGameGlobals::STAT_SKILL && $fractal->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
                $form .= $this->getModeSkillSection($stat_id, $fractal, $results);
            } else {
                $form .= "<table><tbody id='stat_" . $stat_id . "'>";
                $rows = array();
                if (array_key_exists($stat_id, $fractal->stats)) {
                    foreach ($fractal->stats[$stat_id] as $stat) {
                        $row = $this->getEditStatRow($stat_id, $stat_data, $stat, $fractal, $results);
                        $rows[] = $row;
                    }
                }
                foreach ($rows as $r) {
                    $form .= $r;
                }
                if (!$stat_data['unique'] || ($stat_data['unique'] && count($fractal->stats[$stat_id]) == 0)) {
                    $form .= "</tbody></table><h4>Add New ". $stat_labels[$stat_id] . "</h4><table><tbody id='newstat_" . $stat_id . "'>";
                    $count = (count($results) > 0 ? $results['new'][$stat_id]['max'] : 1);
                    for ($i = 1; $i <= $count; $i++) {
                        $form .= $this->getEditStatRow($stat_id, $stat_data, array(), $fractal, $results, $i);
                    }
                }
                $form .= "</tbody></table>";
            }
            $form .= "</fieldset>";
        }
            
        $form .= <<<EOT
                <span class='mw-htmlform-submit-buttons'>
                    <input class='mw-htmlform-submit' type='submit' value='Update'/>
                </span>
            </form>
EOT;

        return $form;
    }
    
    private function getModeSkillSection( $stat_id, $fractal, $results ) {
        if (array_key_exists(FateGameGlobals::STAT_MODE, $fractal->stats)) {
            $modeskill_data = array(
                'fields' => array(
                    'label' => '',
                    'value' => array( 'label' => 'Ranking', 'type' => 'rankselect' )
                ),
                'modeskill' => 1,
                'unique' => 0
            );
            
            $ladder = FateGameGlobals::getLadder();
            $levels = array_reverse(FateGameGlobals::getModeLevels(), true);
            $form .= "<table>";
            foreach ($fractal->stats[FateGameGlobals::STAT_MODE] as $mode) {
                $has_discipline = 0;
                $parent_skill = '';
                foreach ($fractal->fate_game->modes_by_id[$mode->{'parent_id'}]['skill_list'] as $sk) {
                    if ($fractal->fate_game->skills_by_id[$sk]['has_disciplines']) {
                        $has_discipline = 1;
                        $parent_skill = $fractal->fate_game->skills_by_id[$sk];
                        break;
                    }
                }
                $modeskill_data['mode_value'] = $mode->{stat_value};
                $modeskill_data['mode_id'] = $mode->{fractal_stat_id};
                $form .= "<thead><tr><td colspan=4><h4>Mode: " . $ladder[$mode->{stat_value}] . " (+" . $mode->{stat_value} . ") " . $mode->{stat_field} . "</h4></td></tr></thead><tbody id='stat_" . $stat_id . "'>";
                $disciplines = array();
                $modeskill_data['fields']['label'] = array( 'label' => '', 'type' => 'display' );
                for ($i = 2; $i >= 0; $i--) {
                    foreach ($fractal->skills_by_mode[$mode->{fractal_stat_id}]['skills_by_level'][$i] as $skill) {
                        if ($skill->{is_discipline}) {
                            $disciplines[] = $skill;
                            continue;
                        }
                        $form .= $this->getEditStatRow($stat_id, $modeskill_data, $skill, $fractal, $results);
                    }
                }
                if ($has_discipline) {
                    $modeskill_data['fields']['label'] = array( 'size' => 35 );
                    if (count($disciplines) > 0) {
                        $modeskill_data['fields']['label']['label'] = $parent_skill['label'] . " Discipline";
                        foreach ($disciplines as $discipline) {
                            $form .= $this->getEditStatRow($stat_id, $modeskill_data, $discipline, $fractal, $results);
                        }
                    }
                    // For now, assume these aren't unique. Add handling later if necessary
                    $modeskill_data['fields']['label']['label'] = "New " . $parent_skill['label'] . " Discipline";
                    $form .= "</tbody><tbody id='newstat_" . $stat_id . "'>";
                    $count = (count($results) > 0 ? $results['new'][$stat_id]['max'] : 1);
                    for ($i = 1; $i <= $count; $i++) {
                        $form .= $this->getEditStatRow($stat_id, $modeskill_data, array(), $fractal, $results, $i);
                    }
                }
                $form .= "</tbody>";
            }
            $form .= "</table>";
        } else {
            $form .= "<h4>Please define Modes for this fractal first, before stats can be edited.</h4>";
        }
        return $form;
    }
    
    private function getConditionCategoriesJS() {
        $array = FateGameGlobals::getConditionCategories();
        $js = 'var conditions = new Array();';
        foreach ($array as $key => $value) {
            $js .= "conditions[$key] = '$value';";
        }
        return $js;
    }
    
    private function getGameModesJS( $fractal ) {
        $js = "var modeList = new Array();";
        foreach ($fractal->fate_game->modes as $mode) {
            $js .= "modeList[" . $mode['mode_id'] . "] = '" . $mode['label'] . "';";
        }
        return $js;
    }
    
    // TODO: Consolidate this function with function in SpecialFateGameConfig? - move to FateGame?
    private function getGameSkillsJS( $fractal ) {
        $js = "var skillList = new Array();";
        foreach ($fractal->fate_game->skills as $skill) {
            $js .= "skillList[" . $skill['skill_id'] . "] = '" . $skill['label'] . "';";
        }
        return $js;
    }
    
    private function getModeLevelsJS() {
        $array = array_reverse(FateGameGlobals::getModeLevels(), true);
        $js = "var modeLevels = new Array();";
        foreach ($array as $key => $value) {
            $js .= "modeLevels[$key] = '$value';";
        }
        return $js;
    }
    
    private function getMookLevelsJS() {
        $array = FateGameGlobals::getMookLevels();
        $js = "var mookLevels = new Array();";
        foreach ($array as $key => $value) {
            $js .= "mookLevels[$key] = '$value';";
        }
        return $js;
    }
    
    private function getGameAspectsJS( $fractal ) {
        $js = "var gameAspects = new Array();";
        foreach ($fractal->fate_game->aspects as $aspect) {
            $js .= "gameAspects[" . $aspect['aspect_id'] . "] = '" . $aspect['label'] . "';";
        }
        $js .= "gameAspects[0] = 'Use a Custom Label';";
        return $js;
    }
    
    private function getEditStatRow( $stat_id, $stat_data, $stat, $fractal, $results, $new_count = 0 ) {
        $new = '';
        $oninput = '';
        $onchange = '';
        $onchange_aspect = '';
        $fate_game = $fractal->fate_game;
        if (is_array($stat)) {
            $new = 'new_';
            if (!$stat_data['unique']) {
                if ($stat_data['modeskill']) {
                    $oninput = "oninput='addNewRow($stat_id, $new_count, \"" . $stat_data['fields']['label']['label'] ."\", " . $stat_data['mode_value'] . ");'";
                    $onchange = "onchange='addNewRow($stat_id, $new_count, \"" . $stat_data['fields']['label']['label'] . "\", " . $stat_data['mode_value'] . ");'";
                } else {
                    $oninput = "oninput='addNewRow($stat_id, $new_count);'";
                    $onchange = "onchange='addNewRow($stat_id, $new_count);'";
                    $onchange_aspect = "onchange='addNewRow($stat_id, $new_count);";
                }
            }
        }
        $row = '<tr>';
        foreach ($stat_data['fields'] as $field => $field_data) {
            $value = htmlspecialchars($stat->{'stat_' . $field}, ENT_QUOTES);
            $checked = '';
            // Think about better handling for this later
            if ($field == 'is_secret') {
                $checked = ($stat->{$field} == 1 ? 'checked' : '');
            }
            $error = '';
            if (!is_array($stat) && $results['error']['required'][$stat->{fractal_stat_id}] && in_array($field,$results['error']['required'][$stat->{fractal_stat_id}])) {
                $error = "class='formerror'";
            }
            $name = $stat_id . '_' . $new . $field . '_' . (is_array($stat) ? $new_count : $stat->{fractal_stat_id});
            $onchange_aspect .= ($onchange_aspect ? '' : "onchange='" ) . "toggleAspectLabel(\"ef$name\");'";
            $row .= "<td class='mw-label'><label for='ef$name'>" . $field_data['label'] . ($field_data['type'] == 'display' ? '' : ':')  . "</label></td>";
            // If we have results, then show values that were submitted
            if (count($results) > 0 && $field_data['type'] != 'display' && array_key_exists($name, $results['form'])) {
                if ($stat_id == FateGameGlobals::STAT_MODE && $field_data['type'] == 'select') {
                    $value = $fate_game->modes_by_id[$results['form'][$name]]['label'];
                } else {
                    $value = htmlspecialchars($results['form'][$name], ENT_QUOTES);
                }
            }
            if ($stat_id == FateGameGlobals::STAT_CONDITION && $field_data['type'] == 'select') {
                $conditions = FateGameGlobals::getConditionCategories();
                $row .= "<td class='mw-input'><select id='ef$name' name='$name' $onchange $error>";
                foreach ($conditions as $key => $category) {
                    $selected = ($value == $key ? 'selected' : '');
                    $row .= "<option value='$key' $selected>$category</option>";
                }
                $row .= "</select></td>";
            } elseif ($stat_id == FateGameGlobals::STAT_MODE && $field_data['type'] == 'select') {
                $row .= "<td class='mw-input'><select id='ef$name' name='$name' $onchange $error>";
                foreach ($fate_game->modes as $mode) {
                    $selected = ($value == $mode['label'] ? 'selected' : '');
                    $row .= "<option value='" . $mode['mode_id'] . "' $selected>" . $mode['label'] . "</option>";
                }
                $row .= "</select></td>";
            } elseif ($stat_id == FateGameGlobals::STAT_SKILL && $field_data['type'] == 'select') {
                $row .= "<td class='mw-input'><select id='ef$name' name='$name' $onchange $error>";
                foreach ($fate_game->skills as $skill) {
                    $selected = ($value == $skill['label'] ? 'selected' : '');
                    $row .= "<option value='" . $skill['skill_id'] . "' $selected>" . $skill['label'] . "</option>";
                }
                $row .= "</select></td>";
            } elseif ($stat_id == FateGameGlobals::STAT_MOOK && $field_data['type'] == 'select') {
                $levels = FateGameGlobals::getMookLevels();
                $row .= "<td class='mw-input'><select id='ef$name' name='$name' $onchange $error>";
                foreach ($levels as $rating => $label) {
                    $selected = ($value == $rating ? 'selected' : '');
                    $row .= "<option value='$rating' $selected>$label</option>";
                }
                $row .= "</select></td>";
            } elseif ($stat_id == FateGameGlobals::STAT_SKILL && $field_data['type'] == 'rankselect') {
                $levels = array_reverse(FateGameGlobals::getModeLevels(), true);
                $row .= "<td class='mw-input'><select id='ef$name' name='$name' $onchange $error>";
                foreach ($levels as $rating => $label) {
                    $total = $rating + $stat_data['mode_value'];
                    $selected = ($total == $value ? 'selected' : '');
                    $row .= "<option value='$total' $selected>$label (+$rating)</option>";
                }
                $row .= "</select>";
                if ($new) {
                    $hidden_name = $stat_id . '_' . $new . 'mode_' . (is_array($stat) ? $new_count : $stat->{fractal_stat_id});
                    $row .= "<input type='hidden' name='$hidden_name' value='" . $stat_data['mode_id'] . "'/>";
                }                    
                $row .= "</td>";
            } elseif ($field_data['type'] == 'display') {
                $row .= "<td class='mw-input'>$value</td>";
            } elseif ($field_data['type'] == 'checkbox') {
                $row .= "<td class='mw-input'><input $error type='checkbox' id='ef$name' name='$name' value='1' $checked $onchange/></td>";
            } elseif ($field_data['type'] == 'textarea') {
                $row .= "<td class='mw-input'><textarea $error id='ef$name' name='$name' $onchange rows=" . $field_data['rows'] . " cols=" . $field_data['cols'] . ">$value</textarea></td>";
            } else {
                $aspect_display = '';
                if ($stat_id == FateGameGlobals::STAT_ASPECT && $fractal->fractal_type == 'Character' && $field =='label') {
                    $row .= "<td class='mw-input'><select id='select_ef$name' name='select_$name' $onchange_aspect $error>";
                    $found = 0;
                    foreach ($fractal->fate_game->aspects as $aspect) {
                        $selected = '';
                        if ($stat->{'parent_id'} == $aspect['aspect_id']) {
                            $selected = 'selected';
                            $found = 1;
                        }
                        $row .= "<option value='" . $aspect['aspect_id'] . "' $selected>" . $aspect['label'] . "</option>";
                    }
                    $selected = (! $found ? 'selected' : '');
                    $aspect_display = ($found ? "style='display:none;'" : '');
                    $row .= "<option value='0' $selected>Use a Custom Label</option>";
                    $row .= "</select></td>";
                }
                $row .= "<td class='mw-input'><input $error id='ef$name' name='$name' type='text' size='" . $field_data['size'] . "' value='$value' $oninput $aspect_display/></td>";
            }
        }
        if ($stat_data['modeskill'] && $stat_data['fields']['label']['label'] == '') {
            $row .= $this->getDeleteCells(array(), $results);
        } else {
            $row .= $this->getDeleteCells($stat, $results);
        }
        $row .= '</tr>';
        
        if (!is_array($stat) && $results['error']['required'][$stat->{fractal_stat_id}]) {
            $row .= $this->getFormErrorCells($stat_data, $results['error']['required'][$stat->{fractal_stat_id}]);
        }
        
        return $row;
    }
    
    private function getFormErrorCells($stat_data, $errors) {
        $row .= '<tr>';
        foreach ($stat_data['fields'] as $field => $field_data) {
            if (!in_array($field, $errors)) {
                $row .= "<td>&nbsp;</td><td>&nbsp;</td>";
            } else {
                $row .= "<td>&nbsp;</td><td class='error'>Field Required.</td>";
            }
        }
        $row .= "<td>&nbsp;</td><td>&nbsp;</td></tr>";
        
        return $row;
    }
    
    private function getDeleteCells( $stat, $results ) {
        $cells = '';
        if (is_array($stat)) {
            $cells .= "<td>&nbsp;</td><td>&nbsp;</td>";
        } else {
            $name = 'delete_' . $stat->{fractal_stat_id};
            $checked = '';
            if (count($results) > 0 && $results['form'][$name] == 1) {
                $checked = 'checked';
            }
            $cells .= "<td class='mw-label'><label for='ef$name'>Delete:</label></td>".
                      "<td class='mw-input'><input type='checkbox' value='1' name='$name' id='ef$name' $checked/></td>";
        }
        return $cells;
    }
    
    private function viewFractalBlock( $fractal_id ) {
        $user = $this->getUser();
        $out = $this->getOutput();

        if ($fractal_id) {
            $fractal = new FateFractal($fractal_id);
            $table = '';
            if ($fractal->name) {
                if (!$fractal->is_private || $user->isAllowed('fategm') || $fractal->fate_game->is_staff($user->getID()) || $fractal->user_id == $user->getID()) {
                    $table .= $fractal->getFractalBlock();
                } else {
                    $table .= "<div class='error' style='font-weight: bold; color: red'>You do not have permission to view this fractal's stats.</div>";
                }
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red'>No data found for that fractal_id; please check URL and try again.</div>";
            }
            $out->addHTML($table);
        } else {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>Missing fractal_id argument; don't know which fractal to show.</div>");
        }
    }
    
    private function viewFractalSheet( $fractal_id ) {
        $user = $this->getUser();
        $out = $this->getOutput();
        
        if ($fractal_id) {
            $fractal = new FateFractal($fractal_id);
            $table = '';
            if ($fractal->name) {
                if (!$fractal->is_private || $user->isAllowed('fategm') || $fractal->fate_game->is_staff($user->getID()) || $fractal->user_id == $user->getID()) {
                    $table .= $fractal->getFractalSheet();
                } else {
                    $table .= "<div class='error' style='font-weight: bold; color: red'>You do not have permission to view this fractal's stats.</div>";
                }
            } else {
                $table .= "<div class='error' style='font-weight: bold; color: red'>No data found for that fractal_id; please check URL and try again.</div>";
            }
            $out->addHTML($table);
        } else {
            $out->addHTML("<div class='error' style='font-weight: bold; color: red'>Missing fractal_id argument; don't know which fractal to show.</div>");
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
                // 'Setting' is also a protected type, used to store game world-level aspects
                if ($fractal->{fractal_type} == 'Setting') {
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