<?php

class FateGame {
    public $game_id;
    public $register_id;
    public $user_name;
    public $canon_name;
    public $user_id;
    public $game_name;
    public $game_description;
    public $game_status;
    public $aspect_count;
    public $aspects;
    public $skill_alternative;
    public $skill_distribution;
    public $skill_max;
    public $skill_points;
    public $skills;
    public $refresh_rate;
    public $stunt_count;
    public $stress_count;
    public $stress_tracks;
    public $use_consequences;
    public $consequences;
    public $private_sheet;
    public $use_robo_refresh;
    public $is_open_chargen;
    public $is_accepting_characters;
    public $fractals;
    public $create_date;
    public $modified_date;

    public function __construct( $game_id ) {
        $this->game_id = $game_id;

        $dbr = wfGetDB(DB_SLAVE);
        $data = $dbr->selectRow(
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
                   'g.aspect_count',
                   'g.skill_alternative',
                   'g.skill_distribution',
                   'g.skill_max',
                   'g.skill_points',
                   'g.refresh_rate',
                   'g.stunt_count',
                   'g.stress_count',
                   'g.use_consequences',
                   'g.private_sheet',
                   'g.use_robo_refresh',
                   'g.is_open_chargen',
                   'g.is_accepting_characters',
                   'g.create_date',
                   'g.modified_date' ),
            array( 'r.register_id = g.register_id',
                   'g.game_id' => $game_id )
        );

        if ($data) {
            $this->register_id = $data->{register_id};
            $this->user_name = $data->{user_name};
            $this->canon_name = $data->{canon_name};
            $this->user_id = $data->{user_id};
            $this->game_name = $data->{game_name};
            $this->game_description = $data->{game_description};
            $this->game_status = $data->{game_status};
            $this->aspect_count = $data->{aspect_count};
            $this->skill_alternative = $data->{skill_alternative};
            $this->skill_distribution = $data->{skill_distribution};
            $this->skill_max = $data->{skill_max};
            $this->skill_points = explode('|',$data->{skill_points});
            $this->refresh_rate = $data->{refresh_rate};
            $this->stunt_count = $data->{stunt_count};
            $this->stress_count = $data->{stress_count};
            $this->use_consequences = ((bool) $data->{use_consequences});
            $this->private_sheet = ((bool) $data->{private_sheet});
            $this->use_robo_refresh = ((bool) $data->{use_robo_refresh});
            $this->is_open_chargen = ((bool) $data->{is_open_chargen});
            $this->is_accepting_characters = ((bool) $data->{is_accepting_characters});
            $this->create_date = $data->{create_date};
            $this->modified_date = $data->{modified_date};

            $aspect_list = $dbr->select(
                'fate_game_aspect',
                '*',
                array( 'game_id' => $game_id ),
                __METHOD__,
                array( 'ORDER BY' => 'game_aspect_label' )
            );
            $this->aspects = array();
            $this->aspects_by_id = array();
            if ($aspect_list->numRows() > 0) {
                foreach ($aspect_list as $aspect) {
                    $asp = array(
                        'aspect_id' => $aspect->{game_aspect_id},
                        'label' => $aspect->{game_aspect_label},
                        'is_shared' => ((bool) $aspect->{is_shared}),
                        'is_major' => ((bool) $aspect->{is_major}),
                        'is_secret' => ((bool) $aspect->{is_secret})
                    );
                    $this->aspects[] = $asp;
                    $this->aspects_by_id[$aspect->{game_aspect_id}] = $asp;
                }
            }

            $skill_list = $dbr->select(
                'fate_game_skill',
                '*',
                array( 'game_id' => $game_id ),
                __METHOD__,
                array( 'ORDER BY' => 'game_skill_label' )
            );
            $this->skills = array();
            $this->skills_by_id = array();
            if ($skill_list->numRows() > 0) {
                foreach ($skill_list as $skill) {
                    $sk = array(
                        'skill_id' => $skill->{game_skill_id},
                        'label' => $skill->{game_skill_label},
                        'mode_cost' => $skill->{mode_cost},
                        'has_disciplines' => ((bool) $skill->{has_disciplines})
                    );
                    $this->skills[] = $sk;
                    $this->skills_by_id[$sk['skill_id']] = $sk;
                }
            }

            if ($this->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
                $mode_list = $dbr->select(
                    'fate_game_mode',
                    '*',
                    array( 'game_id' => $game_id ),
                    __METHOD__,
                    array( 'ORDER BY' => 'game_mode_label' )
                );
                $this->modes = array();
                $this->modes_by_id = array();
                foreach ($mode_list as $mode) {
                    $skill_mode_list = $dbr->select(
                        'fate_game_mode_skill',
                        '*',
                        array( 'game_mode_id' => $mode->{game_mode_id} )
                    );
                    $skill_list = array();
                    foreach ($skill_mode_list as $sm) {
                        $skill_list[] = $sm->{game_skill_id};
                    }
                    $mo = array(
                        'mode_id' => $mode->{game_mode_id},
                        'label' => $mode->{game_mode_label},
                        'cost' => $mode->{mode_cost},
                        'is_weird' => ((bool) $mode->{is_weird}),
                        'skill_list' => $skill_list
                    );
                    $this->modes[] = $mo;
                    $this->modes_by_id[$mo['mode_id']] = $mo;
                }
            }

            $stress_list = $dbr->select(
                'fate_game_stress',
                '*',
                array( 'game_id' => $game_id ),
                __METHOD__,
                array( 'ORDER BY' => 'game_stress_ordinal' )
            );
            $this->stress_tracks = array();
            if ($stress_list->numRows() > 0) {
                foreach ($stress_list as $stress) {
                    $st = array(
                        'label' => $stress->{game_stress_label},
                        'ordinal' => $stress->{game_stress_ordinal}
                    );
                    $this->stress_tracks[] = $st;
                }
            }

            if ($this->use_consequences) {
                $consequence_list = $dbr->select(
                    'fate_game_consequence',
                    '*',
                    array( 'game_id' => $game_id ),
                    __METHOD__,
                    array( 'ORDER BY' => 'game_consequence_display_value' )
                );
                $this->consequences = array();
                if ($consequence_list->numRows() > 0) {
                    foreach ($consequence_list as $consequence) {
                        $con = array(
                            'label' => $consequence->{game_consequence_label},
                            'display_value' => $consequence->{game_consequence_display_value}
                        );
                        $this->consequences[] = $con;
                    }
                }
            }

            $turn_list = $dbr->select(
                'fate_game_turn_order',
                '*',
                array( 'game_id' => $game_id ),
                __METHOD__,
                array( 'ORDER BY' => array( 'is_physical', 'ordinal' ) )
            );
            $this->turn_order = array();
            if ($turn_list->numRows() > 0) {
                foreach ($turn_list as $turn) {
                    if (! is_array($this->turn_order[$turn->{is_physical}]) ) {
                        $this->turn_order[$turn->{is_physical}] = array();
                    }
                    $this->turn_order[$turn->{is_physical}][$turn->{ordinal}] = $turn->{skill_id};
                }
            }

            $staff_list = $dbr->select(
                array( 's' => 'fate_game_staff',
                       'r' => 'muxregister_register' ),
                array( 'r.user_name',
                       'r.user_id',
                       'r.character_dbref',
                       'r.canon_name',
                       's.game_staff_id' ),
                array( 'r.register_id = s.register_id',
                       's.game_id' => $game_id )
            );
            $this->staff = array();
            if ($staff_list->numRows() > 0) {
                foreach ($staff_list as $staff) {
                    $this->staff[$staff->{user_id}] = $staff;
                }
            }

            $fractal_list = $dbr->select(
                array( 'f' => 'fate_fractal',
                       'r' => 'muxregister_register',
                       'p' => 'fate_pending_stat' ),
                array( 'r.user_name',
                       'r.canon_name',
                       'r.user_id',
                       'f.fractal_id',
                       'f.fractal_name',
                       'f.fractal_type',
                       'f.is_private',
                       'f.create_date',
                       'f.submit_date',
                       'f.approve_date',
                       'f.frozen_date',
                       'pending' => 'p.pending_stat_id' ),
                array( 'f.game_id' => $game_id ),
                __METHOD__,
                array( 'GROUP BY' => array ('fractal_id'),
                       'ORDER BY' => array ('fractal_type', 'fractal_name', 'canon_name' ) ),
                array( 'p' => array( 'LEFT JOIN', 'f.fractal_id = p.fractal_id' ),
                       'r' => array( 'LEFT JOIN', 'f.register_id = r.register_id' ) )
            );
            $this->fractals = array();
            $this->pending_stat_approvals = 0;
            if ($fractal_list->numRows() > 0) {
                foreach ($fractal_list as $fractal) {
                    $frac = array(
                        'fractal_id' => $fractal->{fractal_id},
                        'name' => ($fractal->{canon_name} ? $fractal->{canon_name} : $fractal->{fractal_name} ),
                        'user_name' => $fractal->{user_name},
                        'is_private' => ((bool) $fractal->{is_private} ),
                        'create_date' => $fractal->{create_date},
                        'submit_date' => $fractal->{submit_date},
                        'approve_date' => $fractal->{approve_date},
                        'frozen_date' => $fractal->{frozen_date},
                        'pending' => $fractal->{pending}
                    );
                    if (! is_array($this->fractals[$fractal->{fractal_type}])) {
                        $this->fractals[$fractal->{fractal_type}] = array();
                    }
                    $this->fractals[$fractal->{fractal_type}][] = $frac;
                    if ($fractal->{pending}) {
                        $this->pending_stat_approvals = 1;
                    }
                }
            }
        }
    }

    public function is_staff( $user_id ) {
        $found = false;
        if ($this->user_id == $user_id) {
            $found = true;
        } elseif (array_key_exists($user_id, $this->staff)) {
            $found = true;
        }

        return $found;
    }

    public function initializeCharacter( $register_id, $game_id ) {
        $dbr = wfGetDB(DB_SLAVE);
        $char = $dbr->selectRow(
            array( 'f' => 'fate_fractal' ),
            array( 'f.fractal_id' ),
            array( 'f.register_id' => $register_id )
        );

        # If fractal for this register_id already exists, then bail out
        if ($char) {
            return NULL;
        } else {
            $dbw = wfGetDB(DB_MASTER);
            $new_character = array(
                'game_id' => $game_id,
                'register_id' => $register_id,
                'fractal_type' => 'Character',
                'create_date' => $dbw->timestamp(),
                'update_date' => $dbw->timestamp()
            );
            $dbw->insert( 'fate_fractal', $new_character );
            $fractal_id = $dbw->insertId();

            # Once we have a fractal id, start setting basic stats: fate/refresh
            $fate = array(
                'fractal_id' => $fractal_id,
                'stat_type' => FateGameGlobals::STAT_FATE,
                'modified_date' => $dbw->timestamp(),
                'stat_value' => $this->refresh_rate
            );
            $dbw->insert( 'fate_fractal_stat', $fate );
            $fate['stat_type'] = FateGameGlobals::STAT_REFRESH;
            $dbw->insert( 'fate_fractal_stat', $fate );

            # Then set standard stress tracks
            foreach ($this->stress_tracks as $stress_track) {
                $stress = array(
                    'fractal_id' => $fractal_id,
                    'stat_type' => FateGameGlobals::STAT_STRESS,
                    'modified_date' => $dbw->timestamp(),
                    'stat_label' => $stress_track['label'],
                    'stat_value' => 0,
                    'stat_max_value' => $this->stress_count
                );
                $dbw->insert( 'fate_fractal_stat', $stress );
            }

            # Finally, deal with Conditions or Consequences
            # TODO: Conditions (when we fill them in everywhere else)
            if ($this->use_consequences) {
                foreach ($this->consequences as $consequence) {
                    $con = array(
                        'fractal_id' => $fractal_id,
                        'stat_type' => FateGameGlobals::STAT_CONSEQUENCE,
                        'stat_label' => $consequence['label'],
                        'stat_field' => '',
                        'stat_display_value' => $consequence['display_value'],
                        'modified_date' => $dbw->timestamp()
                    );
                    $dbw->insert( 'fate_fractal_stat', $con );
                }
            }

            # If we got here, and we successfully initialized, return our fractal_id
            return $fractal_id;
        }
    }
}
