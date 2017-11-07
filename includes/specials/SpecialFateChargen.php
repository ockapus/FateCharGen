<?php
/**
 * SpecialPage for FateCharGen extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialFateChargen extends SpecialPage {
    public function __construct() {
        parent::__construct( 'FateChargen', NULL, FALSE );
    }

    private $chargen_data = array(
        FateGameGlobals::STAT_ASPECT => array(
            'fields' => array(
                'field' => array( 'label' => 'Free Aspect', size => 35 )
            )
        ),
        FateGameGlobals::STAT_SKILL => array(
            'fields' => array(
                'parent' => array( 'type' => 'select', 'label' => '#ladder' )
            )
        ),
        FateGameGlobals::STAT_STUNT => array(
            'fields' => array(
                'field' => array( 'label' => 'Title', 'size' => 35),
                'description' => array( 'label' => 'Description', 'type' => 'textarea', 'rows' => 3, 'cols' => 80 )
            )
        )
    );

    /**
     * Show the page to the user
     *
     * @param string $sub The subpage string argument (if any).
     *  [[Special:FateStats/subpage]].
     */
    # TODO: standardize interface/structure
    public function execute( $sub ) {
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();

        $this->setHeaders();
        $output->setPageTitle( $this->msg( 'fatechargen' ) );
        $output->addModules( 'ext.FateCharGen.styles' );

        if ($user->isAnon()) {
            $output->addHTML("<div class='error' style='font-weight: bold; color: red'>You must be logged in to access this page.</div>");
        } else {
            $action = $request->getVal('action');
            if ($action == 'section') {
                if ($request->wasPosted()) {
                    $results = $this->processChargenSection();
                    if (count($results['error']) == 1 && count($results['error']['global']) == 0) {
                        $this->saveChargenSection($results);
                    } else {
                        $this->viewChargenSection($results);
                    }
                } else {
                    $this->viewChargenSection();
                }
            } elseif ($action == 'review') {
                if ($request->wasPosted()) {
                    $results = $this->processChargenReview();
                    if (count($results['error']) == 0) {
                        $this->saveChargenReview($results);
                    } else {
                        $this->viewChargenReview($results);
                    }
                } else {
                    $this->viewChargenReview();
                }
            } else {
                $this->listAvailableCharacters();
            }
        }
    }

    private function processChargenReview() {
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();
        $data = $request->getValues();

        $results = array(
            'error' => array(),
            'form' => $data
        );

        $fractal_id = $request->getVal('fractal_id');
        if (!$fractal_id) {
            $results['error'][] = 'fractal_id';
        } else {
            $fractal = new FateFractal($fractal_id);
            if (!$fractal->name) {
                $results['error'][] = 'fractal';
            } elseif ($fractal->user_id != $user->getID()) {
                $results['error'][] = 'user';
            } elseif ($fractal->submit_date) {
                $results['error'][] = 'submit';
            } else {
                $verification = $fractal->fate_game->chargen->verifyRequirements($fractal_id);
                if (count($verification['error']) > 0) {
                    $results['error'][] = 'verify';
                }
            }
        }

        return $results;
    }

    private function processChargenSection() {
        $user = $this->getUser();
        $output = $this->getOutput();
        $request = $this->getRequest();
        $data = $request->getValues();

        $results = array(
            'error' => array(
                'global' => array()
            ),
            'form' => $data
        );

        $fractal_id = $request->getVal('fractal_id');
        if (!$fractal_id) {
            $results['error']['global'][] = 'fractal_id';
        } else {
            $fractal = new FateFractal($fractal_id);
            if (!$fractal->name) {
                $results['error']['global'][] = 'fractal';
            } elseif ($fractal->user_id != $user->getID()) {
                $results['error']['global'][] = 'user';
            } elseif ($fractal->submit_date) {
                $results['error']['global'][] = 'submit';
            }

            # Above errors present people from hacking the form, and proper error messages
            # Will be displayed by viewChargenSection. Specific-to-form errors below this point
            $section_id = $request->getVal('section_id');
            $section = $fractal->fate_game->chargen->sections[$section_id - 1];
            if ($section['stat_type'] == FateGameGlobals::STAT_SKILL) {
                if ($fractal->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_APPROACHES) {
                    $found = array();
                    foreach ($data as $key => $value) {
                        if (!$value) {
                            continue;
                        }
                        if (preg_match("/^skill_parent_\d+$/", $key, $matches)) {
                            $found[$value] ++;
                            if ($found[$value] > 1) {
                                $results['error']['global'][] = 'duplicate_skill';
                                break;
                            }
                        }
                    }
                }
            }

            # If we didn't find any errors, look to see if we have form fields we need to deal with
            if (count($results['error']) == 1 && count($results['error']['global']) == 0) {
                $consts = FateGameGlobals::getConstToStat();
                $prefix = $consts[$section['stat_type']];
                foreach ($data as $key => $value) {
                    if (preg_match("/^{$prefix}_/", $key)) {
                        if ($value) {
                            if (array_key_exists($key, $fractal->chargen)) {
                                if ($fractal->chargen[$key]['value'] != $value) {
                                    if (! array_key_exists('update', $results)) {
                                        $results['update'] = array();
                                    }
                                    $results['update'][$key] = $value;
                                }
                            } else {
                                if (! array_key_exists('new', $results)) {
                                    $results['new'] = array();
                                }
                                $results['new'][$key] = $value;
                            }
                        } else {
                            if (array_key_exists($key, $fractal->chargen)) {
                                if (! array_key_exists('delete', $results)) {
                                    $results['delete'] = array();
                                }
                                $results['delete'][] = $key;
                            }
                        }
                    }
                }
            }
        }

        return $results;
    }

    private function saveChargenReview( $results ) {
        $output = $this->getOutput();

        $dbw = wfGetDB(DB_MASTER);
        $dbw->update(
            'fate_fractal',
            array( 'submit_date' => $dbw->timestamp(),
                   'update_date' => $dbw->timestamp() ),
            array( 'fractal_id' => $results['form']['fractal_id'] )
        );

        $output->addHTML("<div class='successbox'>Character submitted to staff for review</div>");
        $this->listAvailableCharacters();
    }

    private function saveChargenSection( $results ) {
        $output = $this->getOutput();
        $request = $this->getRequest();

        $fractal_id = $request->getVal('fractal_id');
        $section_id = $request->getVal('section_id');
        $fractal = new FateFractal($fractal_id);

        $dbw = wfGetDB(DB_MASTER);
        if (array_key_exists('new', $results)) {
            foreach ($results['new'] as $key => $value) {
                $insert = array(
                    'fractal_id' => $fractal_id,
                    'field_name' => $key,
                    'field_value' => $value
                );
                $dbw->insert( 'fate_chargen_stat', $insert);
            }
        }
        if (array_key_exists('update', $results)) {
            foreach ($results['update'] as $key => $value) {
                $update = array( 'field_value' => $value );
                $dbw->update(
                    'fate_chargen_stat',
                    $update,
                    array( 'chargen_stat_id' => $fractal->chargen[$key]['id'] )
                );
            }
        }
        if (array_key_exists('delete', $results)) {
            foreach ($results['delete'] as $key) {
                $dbw->delete(
                    'fate_chargen_stat',
                    array( 'chargen_stat_id' => $fractal->chargen[$key]['id'] )
                );
            }
        }

        if ($section_id + 1 <= count($fractal->fate_game->chargen->sections)) {
            $request->setVal('section_id', $section_id + 1);
            $output->addHTML($this->getChargenSection($fractal, array()));
        } else {
            $request->unsetVal('section_id');
            $this->viewChargenReview();
        }
    }

    private function viewChargenReview( $results = array() ) {
        $output = $this->getOutput();
        $request = $this->getRequest();
        $user = $this->getUser();

        $text = '';
        $fractal_id = $request->getVal('fractal_id');
        if (!$fractal_id) {
            $text .= "<div class='error'>Missing fractal_id argument.</div>";
        } else {
            $fractal = new FateFractal($fractal_id);
            if (!$fractal->{'name'}) {
                $text .= "<div class='error'>No data found for that fractal_id.</div>";
            } elseif ($fractal->{'user_id'} != $user->getID()) {
                $text .= "<div class='error'>This fractal_id does not appear to be one of your characters.</div>";
            } else {
                $text .= $this->getChargenReview($fractal, $results);
            }
        }

        $output->addHTML($text);
    }

    private function viewChargenSection( $results = array() ) {
        $output = $this->getOutput();
        $request = $this->getRequest();
        $user = $this->getUser();

        $text = '';
        $fractal_id = $request->getInt('fractal_id');
        if (!$fractal_id) {
            $text .= "<div class='error'>Missing fractal_id argument.</div>";
        } else {
            $fractal = new FateFractal($fractal_id);
            if (!$fractal->{'name'}) {
                $text .= "<div class='error'>No data found for that fractal_id.</div>";
            } elseif ($fractal->{'user_id'} != $user->getID()) {
                $text .= "<div class='error'>This fractal_id does not appear to be one of your characters.</div>";
            } elseif ($fractal->{'submit_date'}) {
                $text .= "<div class='error'>You have already submitted this character for review.</div>";
            } else {
                $text .= $this->getChargenSection($fractal, $results);
            }
        }

        $output->addHTML($text);
    }

    private function getChargenReview( $fractal, $results ) {
        $game = $fractal->fate_game;
        $chargen = $game->chargen;

        $text .= "<h2>Character Generation for " . $game->game_name . "</h2>";
        $verification = $chargen->verifyRequirements($fractal->fractal_id);
        $submit = '';
        if ($fractal->submit_date) {
            $submit .= "<div>Character has been submitted to staff, awaiting approval.</div>";
        } else {
            if (count($verification['error']) > 0) {
                if (array_key_exists('missing', $verification['error'])) {
                    $submit .= "<div class='error'>Character is missing required stats:<ul>";
                    $labels = FateGameGlobals::getStatLabels();
                    foreach ($verification['error']['missing'] as $stat_type => $data) {
                        if (array_key_exists('count', $data)) {
                            $submit .= "<li>" . $data['required'] . " " . $labels[$stat_type] . " required during chargen, only found " . $data['count'] . ".</li>";
                        } else {
                            $submit .= "<li>Following " . $labels[$stat_type] . " must be set: ";
                            $list = array();
                            $other = 0;
                            foreach ($data as $parent_id) {
                                if ($parent_id < 0) {
                                    $other = abs($parent_id);
                                } else {
                                    if ($stat_type == FateGameGlobals::STAT_ASPECT) {
                                        $list[] .= $game->aspects_by_id[$parent_id]['label'];
                                    }
                                }
                            }
                            if ($other) {
                                $list[] .= "At least $other more not otherwise required.";
                            }
                            $submit .= implode(', ', $list) . "</li>";
                        }
                    }
                    $submit .= "</ul>Please correct before submitting for approval.</div>";
                }
            } else {
                $submit .= "<span class='mw-htmlform-submit-buttons'><input class='mw-htmlform-submit' type='submit' value='Submit for Approval'/></span>";
            }
        }

        $form_url = $this->getPageTitle()->getLinkURL();
        $stat_block = $fractal->getChargenFractalBlock();
        $text .= <<< EOT
            <form action="$form_url" method="post">
                <input type='hidden' name='action' value='review'/>
                <input type='hidden' name='fractal_id' value="{$fractal->fractal_id}"/>
                <fieldset>
                    <legend>Review Character Stats</legend>
                    $stat_block
                    $submit
                </fieldset>
            </form>
EOT;

    if (!$fractal->submit_date) {
        $text .= $this->getJumpLinks($fractal, 0);
    }

    return $text;
    }

    private function getChargenSection( $fractal, $results ) {
        $game = $fractal->{'fate_game'};
        $chargen = $game->{'chargen'};
        $request = $this->getRequest();

        #$text = "<pre>" . print_r($game, true) . "</pre>";

        $text .= "<h2>Character Generation for " . $game->{'game_name'} . "</h2>";
        $section_id = $request->getVal('section_id', 1);
        if ($section_id < 1 || $section_id > count($chargen->{'sections'})) {
            $text .= "<div class='error'>Section_id out of valid range; please check URL and try again.</div>";
        } else {
            $section = $chargen->{'sections'}[$section_id - 1];
            $form_url = $this->getPageTitle()->getLinkURL();

            $error_messages = array( 'global' => '' );
            if (array_key_exists('error', $results)) {
                foreach ($results['error'] as $error => $info) {
                    if ($error == 'global') {
                        foreach ($info as $global_error) {
                            if ($global_error == 'duplicate_skill') {
                                $error_messages['global'] = "<tr><td class='error' colspan='100%'>" . $game->skill_alternative . " can only be asasigned a single value</td></tr>";
                            }
                        }
                    }
                }
            }

            $form_core = '';
            $form_array = $chargen->calculateFormArray($game, $section['stat_type']);
            $required_count = 0;
            # If we're doing skils as approaches or pyramid, we're going to need to track some things
            $last_label = '';
            $points = array_count_values($game->skill_points);
            rsort($points);
            $max_per_point = $points[0];
            $this_row = 0;
            $consts = FateGameGlobals::getConstToStat();
            for ($i = 0; $i < count($form_array); $i++) {
                $required = 0;
                if ($section['has_requirements']) {
                    if (!$section['requirement_count']) {
                        if (array_key_exists($section['stat_type'], $chargen->requirements) && $form_array[$i] != 0 && in_array($form_array[$i], $chargen->requirements[$section['stat_type']])) {
                            $required = 1;
                        }
                    } elseif ($required_count < $section['requirement_count']) {
                        $required = 1;
                        $required_count++;
                    }
                }
                foreach ($this->chargen_data[$section['stat_type']]['fields'] as $field => $data) {
                    $name = $consts[$section['stat_type']] . '_' . $field . '_' . $i;
                    $type = 'text';
                    if ($data['type']) {
                        $type = $data['type'];
                    }
                    $label = $data['label'];
                    if ($label == '#ladder') {
                        $label = FateGameGlobals::getLadder()[$game->skill_points[$i]] . " (+" . $game->skill_points[$i] . ")";
                    }
                    if ($form_array[$i]) {
                        if ($section['stat_type'] == FateGameGlobals::STAT_ASPECT) {
                            $label = $game->aspects_by_id[$form_array[$i]]['label'];
                        }
                    }
                    if ($required) {
                        $label .= " <span style='color: red'>*</span>";
                    }
                    $value = '';
                    if ($results && array_key_exists($name, $results['form'])) {
                        $value = $results['form'][$name];
                    } elseif (array_key_exists($name, $fractal->chargen)) {
                        $value = $fractal->chargen[$name]['value'];
                    }

                    $input = '';
                    if ($type == 'select') {
                        if ($section['stat_type'] == FateGameGlobals::STAT_SKILL) {
                            $input = $this->getSkillSelect($name, $value, $fractal);
                        }
                    } elseif ($type == 'textarea') {
                        $input = "<textarea id='cg$name' name='$name' rows='{$data['rows']}' cols='{$data['cols']}'>$value</textarea>";
                    } else {
                        $value = htmlspecialchars($value, ENT_QUOTES);
                        $input = "<input type='$type' id='cg$name' name='$name' size='{$data['size']}' value='$value'/>";
                    }

                    if ($section['stat_type'] == FateGameGlobals::STAT_SKILL) {
                        if ($label != $last_label) {
                            if ($this_row < $max_per_point && $i > 0) {
                                for ($j = $this_row; $j < $max_per_point; $j++) {
                                    $form_core .= "<td>&nbsp;</td>";
                                }
                                $form_core .= "</tr>";
                                $this_row = 0;
                            }
                            $last_label = $label;
                            $form_core .= "<tr><td class='mw-label' nowrap><label for='cg$name'>$label</label></td>";
                        }
                        $form_core .= "<td class='mw-input'>$input</td>";
                        $this_row++;
                    } else {
                        $form_core .= "<tr><td class='mw-label' nowrap><label for='cg$name'>$label</label></td>".
                                      "<td class='mw-input'>$input</td></tr>";
                    }
                }
                if ($section['stat_type'] != FateGameGlobals::STAT_SKILL) {
                    if (array_key_exists($section['stat_type'], $chargen->help) && array_key_exists($form_array[$i], $chargen->help[$section['stat_type']])) {
                        $form_core .= "<tr><td>&nbsp;</td><td style='font-style: italic'>" . $chargen->help[$section['stat_type']][$form_array[$i]]['help_text'] . "</td></tr>";
                    }
                }
            }
            if ($section['stat_type'] == FateGameGlobals::STAT_SKILL) {
                # Debug here
                if ($this_row < $max_per_point) {
                    $form_core .= "<td>&nbsp;</td>";
                }
                $form_core .= "</tr>";
            }
            if ($section['has_requirements']) {
                $form_core .= "<tr><td colspan='100%' style='color: red;'>* - Required for Chargen</td></tr>";
                if (!$section['requirement_count'] && array_key_exists($section['stat_type'], $chargen->requirements)) {
                    $count = array_count_values($chargen->requirements[$section['stat_type']]);
                    if (array_key_exists(0, $count)) {
                        $form_core .= "<tr><td colspan='100%' style='color: red;'>&nbsp; &nbsp; In addition to labeled fields, " . $count[0] . " other field(s) are required in chargen.</td></tr>";
                    }
                }
            }
            # If we're a type where we do help after the form, do those here if they have an id
            if ($section['stat_type'] == FateGameGlobals::STAT_SKILL && array_key_exists(FateGameGlobals::STAT_SKILL, $chargen->help)) {
                foreach ($chargen->help[FateGameGlobals::STAT_SKILL] as $id => $data) {
                    if ($id) {
                        $form_core .= "<tr><td class='mw-label' nowrap style='font-weight: bold; font-style: italic'>" .
                                      $game->skills_by_id[$id]['label'] . "</td>" .
                                      "<td colspan='$max_per_point' style='font-style: italic'>" . $data['help_text'] .
                                      "</td></tr>";
                    }
                }
            }
            # Now, either way, go through and display any help that's general (id = 0)
            if (array_key_exists($section['stat_type'], $chargen->help) && array_key_exists(0, $chargen->help[$section['stat_type']])) {
                if ($section['stat_type'] != FateGameGlobals::STAT_SKILL) {
                    $max_per_point = 1;
                }
                $form_core .= "<tr><td>&nbsp;</td><td colspan='$max_per_point' style='font-style: italic'>" .
                              $chargen->help[$section['stat_type']][0]['help_text'] . "(" . $max_per_point . ")</td></tr>";
            }

            $text .= <<< EOT
                <form action="$form_url" method="post">
                    <input type='hidden' name='action' value='section'/>
                    <input type='hidden' name='fractal_id' value="{$fractal->{'fractal_id'}}"/>
                    <input type='hidden' name='section_id' value="$section_id"/>
                    <fieldset>
                        <legend>{$section['chargen_label']}</legend>
                        <div>{$section['instructions']}</div>
                        <table>
                            <tbody>
                                {$error_messages['global']}
                                $form_core
                            </tbody>
                        </table>
                        <span class='mw-htmlform-submit-buttoms'>
                            <input class='mw-htmlform-submit' type='submit' value='Save and Continue'/>
                        </span>
                    </fieldset>
                </form>
EOT;

        }
        $text .= $this->getJumpLinks($fractal, $section_id);

        return $text;
    }

    private function getSkillSelect( $name, $value, $fractal ) {
        $select = "<select id='cg$name' name='$name'>";
        $selected = ($value == '' ? 'selected' : '');
        $select .= "<option value='' $selected>Select One</option>";
        foreach ($fractal->fate_game->skills as $skill) {
            $selected = ($value == $skill['skill_id'] ? 'selected' : '');
            $select .= "<option value='"  . $skill['skill_id'] . "' $selected>" . $skill['label'] . "</option>";
        }
        $select .= "</select>";

        return $select;
    }

    private function getJumpLinks( $fractal, $section_id ) {
        $chargen = $fractal->fate_game->chargen;

        $links = array();
        for ($i = 0; $i < count($chargen->{'sections'}); $i++) {
            if ($section_id - 1 == $i) {
                $links[] = $chargen->sections[$i]['chargen_label'];
            } else {
                $links[] = Linker::link($this->getPageTitle(),  $chargen->sections[$i]['chargen_label'], array(), array( 'fractal_id' => $fractal->{'fractal_id'}, 'action' => 'section', 'section_id' => $i + 1 ), array( 'forcearticalpath' ) );
            }
        }
        if ($section_id == 0) {
            $links[] = "Final Review";
        } else {
            $links[] =  Linker::link($this->getPageTitle(),  "Final Review", array(), array( 'fractal_id' => $fractal->{'fractal_id'}, 'action' => 'review' ), array( 'forcearticalpath' ) );
        }
        $text .= "<div>Jump To: " . implode(" | ", $links) . "</div>";
        return $text;
    }

    private function listAvailableCharacters() {
        $user = $this->getUser();
        $output = $this->getOutput();

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
                   'f.submit_date' ),
            array( 'user_id' => $user->getID(),
                   'fractal_type' => 'Character',
                   'f.approve_date is NULL' ),
            __METHOD__,
            array( 'ORDER BY' => array('fractal_type', 'fractal_name', 'canon_name' ) ),
            array( 'r' => array( 'LEFT JOIN', 'f.register_id = r.register_id' ),
                   'g' => array( 'JOIN', 'f.game_id = g.game_id' ) )
        );

        $table = "<table class='wikitable'>".
                 "<tr><th>Character Name</th><th>Game</th><th>Status</th></tr>";
        if ($fractal_list->numRows() == 0) {
            $table .= "<tr><td colspan=100%>No Chargen Characters Found</td></tr>";
        } else {
            foreach ($fractal_list as $fractal) {
                $status = "Ready for Chargen";
                $action = 'section';
                $status_date = $fractal->create_date;
                $name = $fractal->canon_name;
                if ($fractal->submit_date) {
                    $status = "Submitted for Approval";
                    $status_date = $fractal->submit_date;
                    $action = 'review';
                }
                $table .= "<tr><td>" .
                    Linker::link($this->getPageTitle(), $name, array(), array( 'fractal_id' => $fractal->fractal_id, 'action' => $action ), array( 'forcearticalpath' ) ) .
                    "</td>";
                $table .= "<td>" . $fractal->game_name . "</td>" .
                          "<td>" . $status . " on " . FateGameGlobals::getDisplayDate($status_date) . "</td></tr>";
            }
        }
        $table .= "</table>";
        $output->addHTML($table);
    }
}
