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
                        'is_major' => ((bool) $aspect->{is_major})
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
                       'pending' => 'count(p.pending_stat_id)' ),
                array( 'f.game_id' => $game_id ),
                __METHOD__,
                array( 'ORDER BY' => array ('fractal_type', 'fractal_name', 'canon_name' ) ),
                array( 'r' => array( 'LEFT JOIN', 'f.register_id = r.register_id' ),
                       'p' => array( 'LEFT JOIN', 'f.fractal_id = p.fractal_id' ) )
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
}