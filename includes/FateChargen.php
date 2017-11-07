<?php

class FateChargen {
    public $game_id;
    public $chargen_id;
    public $sections;
    public $help;
    public $requirements;
    public $is_valid;

    public function __construct( $game_id ) {
        $this->game_id = $game_id;

        $dbr = wfGetDB(DB_SLAVE);
        $data = $dbr->select(
            array( 'c' => 'fate_chargen' ),
            array( 'c.chargen_id',
                   'c.stat_type',
                   'c.chargen_label',
                   'c.instructions',
                   'c.has_requirements',
                   'c.requirement_count' ),
            array( 'c.game_id' => $game_id ),
            __METHOD__,
            array( 'ORDER BY' => array( 'c.ordinal' ) )
        );

        $this->sections = array();
        $this->help = array();
        $this->requirements = array();
        if ($data->numRows() > 0) {
            foreach ($data as $page) {
                $section = array(
                    'chargen_id' => $page->{'chargen_id'},
                    'stat_type' => $page->{'stat_type'},
                    'chargen_label' => $page->{'chargen_label'},
                    'instructions' => $page->{'instructions'},
                    'has_requirements' => ((bool) $page->{'has_requirements'}),
                    'requirement_count' => $page->{'requirement_count'}
                );
                $this->sections[] = $section;

                $help_data = $dbr->select(
                    array( 'h' => 'fate_chargen_help' ),
                    array( 'h.parent_id',
                           'h.help_text' ),
                    array( 'h.chargen_id' => $section['chargen_id'] )
                );
                if ($help_data->numRows() > 0) {
                    $this->help[$section['chargen_id']] = array();
                    foreach ($help_data as $entry) {
                        $h = array(
                            'help_text' => $entry->{'help_text'}
                        );
                        $this->help[$section['chargen_id']][$entry->{'parent_id'}] = $h;
                    }
                }

                if ($section['has_requirements']) {
                    $require_data = $dbr->select(
                        array( 'r' => 'fate_chargen_required' ),
                        array( 'r.parent_id' ),
                        array( 'r.chargen_id' => $section['chargen_id'] )
                    );
                    if ($require_data->numRows() > 0) {
                        $this->requirements[$section['chargen_id']] = array();
                        foreach ($require_data as $req) {
                            $this->requirements[$section['chargen_id']][] = $req->{'parent_id'};
                        }
                    }
                }
            }
        }
        $this->validate();
    }

    private function validate() {
        # Add actual functionality later
        $this->is_valid = TRUE;
    }

    public function verifyRequirements( $fractal_id ) {
        $results = array(
            'error' => array()
        );

        if (!$fractal_id) {
            $results['error']['fractal_id'] = 1;
        } else {
            $fractal = new FateFractal($fractal_id);
            if (!$fractal->name) {
                $results['error']['fractal'] = 1;
            } elseif ($fractal->fractal_type != 'Character') {
                $results['error']['character'] = 1;
            } else {
                $consts = FateGameGlobals::getConstToStat();
                foreach ($this->sections as $section) {
                    if ($section['has_requirements']) {
                        $prefix = $consts[$section['stat_type']];
                        if (array_key_exists($section['chargen_id'], $this->requirements)) {
                            $other_needed = 0;
                            $form_array = $this->calculateFormArray($fractal->fate_game, $section['stat_type']);
                            foreach ($this->requirements[$section['chargen_id']] as $parent) {
                                if ($parent) {
                                    $index = array_search($parent, $form_array);
                                    $found = false;
                                    foreach($fractal->chargen as $key => $value) {
                                        if (preg_match("/^$prefix\_.+_$index\$/", $key)) {
                                            $found = true;
                                            break;
                                        }
                                    }
                                    if (!$found) {
                                        if (! is_array($results['error']['missing'][$section['stat_type']])) {
                                            $results['error']['missing'][$section['stat_type']] = array();
                                        }
                                        $results['error']['missing'][$section['stat_type']][] = $parent;
                                    }
                                } else {
                                    $other_needed++;
                                }
                            }
                            if ($other_needed) {
                                $other_found = 0;
                                for ($i = 0; $i < count($form_array); $i++) {
                                    if ($form_array[$i] && in_array($form_array[$i], $this->requirements[$section['chargen_id']])) {
                                        continue;
                                    }
                                    foreach ($fractal->chargen as $key => $value) {
                                        if (preg_match("/^$prefix\_.+_$i\$/", $key)) {
                                            $other_found++;
                                            break;
                                        }
                                    }
                                }
                                if ($other_found < $other_needed) {
                                    if (! is_array($results['error']['missing'][$section['stat_type']])) {
                                        $results['error']['missing'][$section['stat_type']] = array();
                                    }
                                    $results['error']['missing'][$section['stat_type']][] = (0 - ($other_needed - $other_found));
                                }
                            }
                        } else {
                            $count = 0;
                            foreach ($fractal->chargen as $key => $value) {
                                if (preg_match("/^$prefix/", $key)) {
                                    $count++;
                                }
                            }
                            if ($count < $section['requirement_count']) {
                                $results['error']['missing'][$section['stat_type']] = array(
                                    'count' => $count,
                                    'required' => $section['requirement_count']
                                );
                            }
                        }
                    }
                }
            }
        }

        return $results;
    }

    public function calculateFormArray( $game, $stat_type ) {
        $form_array = array();

        if ($stat_type == FateGameGlobals::STAT_ASPECT) {
            $secrets = 0;
            # Just do the dumb method and go through this three times
            foreach ($game->aspects as $aspect) {
                if ($aspect['is_secret']) {
                    $secrets++;
                    continue;
                } elseif ($aspect['is_major']) {
                    $form_array[] = $aspect['aspect_id'];
                }
            }
            foreach ($game->aspects as $aspect) {
                if (!$aspect['is_secret'] && !$aspect['is_major']) {
                    $form_array[] = $aspect['aspect_id'];
                }
            }
            if (count($form_array) + $secrets < $game->aspect_count) {
                $free = $game->aspect_count - $secrets - count($form_array);
                for ($i = 0; $i < $free; $i++) {
                    $form_array[] = NULL;
                }
            }
            foreach ($game->aspects as $aspect) {
                if ($aspect['is_secret']) {
                    $form_array[] = $aspect['aspect_id'];
                }
            }
        } elseif ($stat_type == FateGameGlobals::STAT_SKILL) {
            if ($game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_APPROACHES) {
                for ($i = 0; $i < count($game->skills); $i++) {
                    $form_array[] = NULL;
                }
            }
        } elseif ($stat_type == FateGameGlobals::STAT_STUNT) {
             for ($i = 0; $i < $game->stunt_count; $i++) {
                 $form_array[] = NULL;
             }
        }

        return $form_array;
    }
}
