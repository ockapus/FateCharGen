<?php

class FateFractal {
    public $fractal_id;
    public $fate_game;
    public $user_id;
    public $user_name;
    public $name;
    public $fractal_type;
    public $is_private;
    public $create_date;
    public $submid_date;
    public $approve_date;
    public $frozen_date;
    public $stats;
    
    // TODO: Implement handling for Conditions
    
    public function __construct( $fractal_id ) {
        $this->fractal_id = $fractal_id;
        
        $dbr = wfGetDB(DB_SLAVE);
        $data = $dbr->selectRow(
            array( 'f' => 'fate_fractal',
                   'r' => 'muxregister_register' ),
            array( 'r.user_name',
                   'r.canon_name',
                   'r.user_id',
                   'f.game_id',
                   'f.fractal_name',
                   'f.fractal_type',
                   'f.is_private',
                   'f.create_date',
                   'f.submit_date',
                   'f.approve_date',
                   'f.frozen_date' ),
            array( 'f.fractal_id' => $fractal_id ),
            __METHOD__,
            array(),
            array( 'r' => array( 'LEFT JOIN', 'f.register_id = r.register_id' ) )
        );
        
        if ($data) {
            $this->fate_game = new FateGame( $data->{game_id} );
            $this->user_id = $data->{user_id};
            $this->user_name = $data->{user_name};
            $this->name = ( $data->{canon_name} ? $data->{canon_name} : $data->{fractal_name} );
            $this->fractal_type = $data->{fractal_type};
            $this->is_private = ((bool) $data->{is_private} );
            $this->create_date = $data->{create_date};
            $this->submit_date = $data->{submit_date};
            $this->approve_date = $data->{approve_date};
            $this->frozen_date = $data->{frozen_date};
            
            $stat_data = $dbr->select(
                'fate_fractal_stat',
                '*',
                array( 'fractal_id' => $fractal_id ),
                __METHOD__,
                array( 'ORDER BY' => array( 'stat_type', 'stat_label', 'stat_field' ) )
            );
            $this->stats = array();
            $this->stats_by_id = array();
            if ($stat_data->numRows() > 0) {
                foreach ($stat_data as $stat) {
                    if (! is_array($this->stats[$stat->{stat_type}]) ) {
                        $this->stats[$stat->{stat_type}] = array();
                    }
                    // Add info about major aspects while we're here
                    if ($stat->{stat_type} == FateGameGlobals::STAT_ASPECT) {
                        if ($stat->{parent_id}) {
                            $stat->{is_major} = $this->fate_game->aspects_by_id[$stat->{parent_id}]['is_major'];
                        } else {
                            $stat->{is_major} = false;
                        }
                    }
                    $this->stats_by_id[$stat->{fractal_stat_id}] = $stat;
                    $this->stats[$stat->{stat_type}][] = $stat;
                    if ($this->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES && $stat->{stat_type} == FateGameGlobals::STAT_MODE) {
                        if (! is_array($this->skills_by_mode)) {
                            $this->skills_by_mode = array();
                        }
                        $this->skills_by_mode[$stat->{fractal_stat_id}] = array(
                            'mode_value' => $stat->{stat_value},
                            'skills_by_level' => array(
                                0 => array(),
                                1 => array(),
                                2 => array()
                            )
                        );
                    }
                }
            }
            
            $pending_data = $dbr->select(
                'fate_pending_stat',
                '*',
                array( 'fractal_id' => $fractal_id ),
                __METHOD__,
                array( 'ORDER BY' => array( 'stat_type', 'stat_label', 'stat_field' ) )
            );
            $this->pending_stats = array();
            $this->pending_stats_by_id = array();
            if ($pending_data->numRows() > 0) {
                foreach ($pending_data as $stat) {
                    if (! is_array($this->pending_stats[$stat->{stat_type}])) {
                        $this->pending_stats[$stat->{stat_type}] = array();
                    }
                    if ($stat->{denied_id}) {
                        $stat->{denied_user} = User::newFromId($stat->{denied_id})->getName();
                    }
                    $this->pending_stats[$stat->{stat_type}][] = $stat;
                    $this->pending_stats_by_id[$stat->{pending_stat_id}] = $stat;
                }
            }
            
            /* Do re-sorting as necessary for various types */
            if (array_key_exists(FateGameGlobals::STAT_ASPECT, $this->stats)) {
                @usort($this->stats[FateGameGlobals::STAT_ASPECT], function($a, $b) {
                    if ($a->{is_major} == $b->{is_major}) {
                        if ($a->{stat_label} && $b->{stat_label}) {
                            return strcmp($a->{stat_label}, $b->{stat_label});
                        } elseif ($a->{stat_label}) {
                            return -1;
                        } elseif ($b->{stat_label}) {
                            return 1;
                        } else {
                            return strcmp($a->{stat_field}, $b->{stat_field});
                        }
                    } else {
                        return ($a->{is_major} < $b->{is_major} ? 1 : -1);
                    }
                });
            }
            if (array_key_exists(FateGameGlobals::STAT_CONSEQUENCE, $this->stats)) {
                @usort($this->stats[FateGameGlobals::STAT_CONSEQUENCE], function($a, $b) {
                    if ($a->{stat_display_value} == $b->{stat_display_value}) {
                        return strcmp($a->{stat_field}, $b->{stat_field});
                    } else {
                        return ($a->{stat_display_value} < $b->{stat_display_value} ? -1 : 1 );
                    }
                });
            }
            if (array_key_exists(FateGameGlobals::STAT_MODE, $this->stats)) {
                @usort($this->stats[FateGameGlobals::STAT_MODE], function($a, $b) {
                    if ($a->{stat_value} == $b->{stat_value}) {
                        return 0;
                    } else {
                        return ($b->{stat_value} < $a->{stat_value} ? -1 : 1 );
                    }
                });
                if (array_key_exists(FateGameGlobals::STAT_SKILL, $this->stats)) {
                    foreach ($this->stats[FateGameGlobals::STAT_SKILL] as $skill) {
                        $mode = $skill->{stat_mode};
                        $level = $skill->{stat_value} - $this->skills_by_mode[$mode]['mode_value'];
                        $this->skills_by_mode[$mode]['skills_by_level'][$level][] = $skill;
                    }
                }
            }
            if (array_key_exists(FateGameGlobals::STAT_SKILL, $this->stats)) {
                @usort($this->stats[FateGameGlobals::STAT_SKILL], function($a, $b) {
                    if ($a->{stat_value} == $b->{stat_value}) {
                        return 0;
                    } else {
                        return ($a->{stat_value} < $b->{stat_value} ? 1 : -1);
                    }
                });
            }
        }
    }
    
    public function resetModeSkills() {
        if ($this->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
            $dbr = wfGetDB(DB_SLAVE);
            $dbw = wfGetDB(DB_MASTER);
         
            // Delete Current skills
            $dbw->delete(
                'fate_fractal_stat',
                array( 'fractal_id' => $this->fractal_id,
                       'stat_type' => FateGameGlobals::STAT_SKILL )
            );
            
            // Get modes
            $modes = $dbr->select(
                'fate_fractal_stat',
                '*',
                array( 'fractal_id' => $this->fractal_id,
                       'stat_type' => FateGameGlobals::STAT_MODE )
            );
            
            // Get skills per mode
            $new_skills = array();
            $levels = array();
            if ($modes->numRows() > 0) {
                foreach ($modes as $mode) {
                    $levels[] = $mode->{stat_value};
                    foreach ($this->fate_game->modes_by_id[$mode->{parent_id}]['skill_list'] as $skill_id) {
                        $new_skills[$mode->{stat_value}][] = array( 'id' => $skill_id, 'value' => 0, 'mode' => $mode->{fractal_stat_id} );
                    }
                }
            }
            
            // Promote skills
            sort($levels);
            $sub = $levels;
            foreach ($levels as $level) {
                array_shift($sub);
                if (count($sub) == 0) {
                    break;
                }
                foreach ($sub as $s) {
                    foreach ($new_skills[$level] as $base_index => $base_skill) {
                        foreach ($new_skills[$s] as $next_index => $next_skill) {
                            if ($base_skill['id'] == $next_skill['id']) {
                                $new_skills[$s][$next_index]['value'] += $base_skill['value'] + 1;
                                unset($new_skills[$level][$base_index]);
                                continue;
                            }
                        }
                    }
                }
            }
            
            // Save new skills
            foreach ($new_skills as $mode_level => $skill_list) {
                foreach ($skill_list as $skill) {
                    $inserts = array( 
                        'fractal_id' => $this->fractal_id,
                        'stat_type' => FateGameGlobals::STAT_SKILL,
                        'stat_label' => $this->fate_game->skills_by_id[$skill['id']]['label'],
                        'stat_value' => ($mode_level + $skill['value']),
                        'stat_mode' => $skill['mode']
                    );
                    $dbw->insert(
                        'fate_fractal_stat',
                        $inserts
                    );
                }
            }
        }
    }
    
    public function getFractalBlock( $collapse = 0 ) {
        $class = '';
        $table_class = '';
        if ($collapse) {
            $class = "class='mw-collapsible mw-collapsed'";
            $table_class = "class='mw-collapsible-content'";
        }
        $block = <<< EOT
            <style type='text/css'>
                #fractalblock { border: 2px solid #295079; width: auto; margin: 0 0 0.5em 0p; padding: 5px 15px; }
                #fractalblock .name { font-family: Tahoma, Geneva, sans-serif; font-weight: bold; text-align: center; font-size: 18px; text-transform: uppercase; }
                #fractalblock .section_label { vertical-align: top; font-weight: bold; white-space: nowrap; }
                #fractalblock .stunt_name { font-weight: bold; }
                #fractalblock .aspect_field { font-weight: bold; font-style: italic; }
            </style>
            <div id='fractalblock' $class>
            <div class='name'>$this->name</div>
            <table width='100%' $table_class>
EOT;
        if (array_key_exists(FateGameGlobals::STAT_ASPECT, $this->stats)) {
            $last_label = '';
            $first = 1;
            
            foreach ($this->stats[FateGameGlobals::STAT_ASPECT] as $aspect) {
                $this_label = ($aspect->{stat_label} ? $aspect->{stat_label} : 'Other Aspects');
                if ($this_label != $last_label) {
                    $last_label = $this_label;
                    if (!$first) {
                        $block .= "</td></tr>";
                    } else {
                        $first = 0;
                    }
                    $block .= "<tr><td class='section_label'>" . $this_label . ":</td><td class='aspect_field'>";
                } else {
                    $block .= ", ";
                }
                $block .= $aspect->{stat_field};
            }
            if ($last_label) {
                $block .= "</td></tr>";
            }
        }
        
        if (array_key_exists(FateGameGlobals::STAT_SKILL, $this->stats)) {
            $ladder = FateGameGlobals::getLadder();
            $last_label = '';
            $first = 1;
            if ($this->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
                $block .= "<tr><td class='section_label'>Modes:</td><td>";
                foreach ($this->stats[FateGameGlobals::STAT_MODE] as $mode) {
                    $mode_label = $ladder[$mode->{stat_value}] . " (+" . $mode->{stat_value} . ") " . $mode->{stat_field};
                    $skill_list = array();
                    for ($i = 2; $i >= 0; $i--) {
                        foreach ($this->skills_by_mode[$mode->{fractal_stat_id}]['skills_by_level'][$i] as $skill) {
                            $skill_list[] = "+" . $skill->{stat_value} . " " . $skill->{stat_label};
                        }
                    }
                    if (!$first) {
                        $block .= "<br/>";
                    } else {
                        $first = 0;
                    }
                    $block .= "$mode_label (" . implode(', ', $skill_list) . ")";
                }
                $block .= "</td></tr>";
            } else {
                $block .= "<tr><td class='section_label'>Skills:</td><td>";
                foreach ($this->stats[FateGameGlobals::STAT_SKILL] as $skill) {
                    $this_label = $ladder[$skill->{stat_value}];
                    if ($this_label != $last_label) {
                        $last_label = $this_label;
                        if (!$first) {
                            $block .= "<br/>";
                        } else {
                            $first = 0;
                        }
                        $block .= $this_label . " (+" . $skill->{stat_value} . "): ";
                    } else {
                        $block .= ", ";
                    }
                    $block .= $skill->{stat_label};
                }
                if ($last_label) {
                    $block .= "</td></tr>";
                }
            }
        }
        
        if (array_key_exists(FateGameGlobals::STAT_STUNT, $this->stats)) {
            $block .= "<tr><td class='section_label'>Stunts:</td><td>";
            foreach ($this->stats[FateGameGlobals::STAT_STUNT] as $stunt) {
                $block .= "<span class='stunt_name'>" . $stunt->{stat_field} . ".</span> " . 
                          $stunt->{stat_description} . "<br/>\n";
            }
            $block .= "</td></tr>";
        }
        
        if (array_key_exists(FateGameGlobals::STAT_STRESS, $this->stats)) {
            $block .= "<tr><td class='section_label'>Stress:</td><td>".
                      "<table width=100%><tr>";
            foreach ($this->stats[FateGameGlobals::STAT_STRESS] as $stress) {
                $block .= "<td>" . $stress->{stat_label} . ': ';
                for ($box = 1; $box <= $stress->{stat_max_value}; $box++) {
                    if ($stress->{stat_value} >= $box) {
                        $block .= "&#x2612";
                    } else {
                        $block .= "&#x2610;";
                    }
                }
                $block .= "</td>";
            }
            $block .= "</tr></table></td></tr>";
        }
        
        $block .= "</table></div>";
        return $block;
    }
    
    public function getFractalSheet() {
        $sheet = <<< EOT
            <style type='text/css'>
                #charsheet h2 {  
                    margin: 0;
                    font-weight: normal;
                    position: relative;
                    font-size: 18px;
                    line-height: 20px;
                    background: #295079;
                    padding: 5px 15px;
                    color: white;
                    border-radius: 0 10px 0 0;
                    font-family: Tahoma, Geneva, sans-serif;
                }
                
                #charsheet .modes h3 { color: #295079; font-family: Tahoma, Geneva, sans-serif; text-align: right; vertical-align: middle; padding: 0px; margin: 0px; }
                #charsheet .modes h4 { color: #295079; font-family: Tahoma, Geneva, sans-serif; white-space: nowrap; padding: 0px; }
                #charsheet .modes { width: 100%; border-spacing: 0px; margin-bottom: 0.5em; }
                #charsheet .modes td { padding: 3px; }
                #charsheet .modes span { font-weight: bold; }
                #charsheet .modes tr:nth-child(even) { background-color: #BDD1EC; }

                #charsheet .block { border: 2px solid #295079; width: auto; margin: 0 0 0.5em 0;padding: 5px 15px; position: relative; }
                #charsheet .block.consequence { margin-left: 1em; }
                #charsheet .block.stress { margin-left: 1em; float: left; margin-right: .5em; font-family: monospace; font-size: 22px; font-weight: bold;  }
                #charsheet .block.consequence .value, #charsheet .block.stress .value { position: absolute; font-family: Tahoma, Geneva, sans-serif; font-size: 28px; font-weight: bold; left: -13px; top: -4px; color: #295079}
                #charsheet .block .block_label { position: absolute; font-family: Tahoma, Geneva, sans-serif;  margin: 0px; padding: 0px; font-weight: bold; font-size: 10px; line-height: 10px; top: 0px; left: 2px; color: #999999;}

                .skill_label { vertical-align: middle; text-align: right; font-size: 18px; font-family: Tahoma, Geneva, sans-serif;  }
                .fate { font-family: monospace; font-size: 22px; font-weight: bold; text-align: center; }
                .name { font-size: 20px; font-family: Tahoma, Geneva, sans-serif; }

                #charsheet .approaches .block { padding: 5px 8px; margin: 0 10px 0 5px;}
            </style>
            <div id='charsheet'>
            <div class='name'>$this->name</div>
EOT;

        if (array_key_exists(FateGameGlobals::STAT_ASPECT, $this->stats)) {
            $sheet .= "<H2>ASPECTS</h2>\n";
            foreach ($this->stats[FateGameGlobals::STAT_ASPECT] as $aspect) {
                $sheet .= "<div class='block'>";
                if ($aspect->{stat_label}) {
                    $sheet .= "<div class='block_label'>" . $aspect->{stat_label} . "</div>";
                }
                $sheet .= $aspect->{stat_field} . "</div>\n";
            }
        }
        
        if (array_key_exists(FateGameGlobals::STAT_SKILL, $this->stats)) {
            $sheet .= "<H2>" . strtoupper($this->fate_game->skill_alternative ? $this->fate_game->skill_alternative : 'SKILLS') . "</H2>";
            if ($this->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_APPROACHES) {
                $sheet .= "<table class='approaches'>";
                $rows = array();
                $count = 0;
                foreach ($this->stats[FateGameGlobals::STAT_SKILL] as $skill) {
                    $rows[$count] .= "<td class='skill_label'><p>" . $skill->{stat_label} . "</p></td>".
                                     "<td><div class='block'>+" . $skill->{stat_value} . "</div></td>";
                    $count++;
                    $count %= 2;
                }
                $sheet .= "<tr>" . $rows[0] . "</tr><tr>" . $rows[1] . "</tr></table>";
            } elseif ($this->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_MODES) {
                $ladder = FateGameGlobals::getLadder();
                $levels = array_reverse(FateGameGlobals::getModeLevels(), true);
                $sheet .= "<table class='modes'><tr><td>&nbsp;</td>";
                foreach ($this->stats[FateGameGlobals::STAT_MODE] as $mode) {
                    $mode_label = strtoupper($ladder[$mode->{stat_value}] . " (+" . $mode->{stat_value} . ") " . $mode->{stat_field});
                    $sheet .= "<td><h4>$mode_label</h4></td>";
                }
                $sheet .= "</tr>";
                foreach ($levels as $level => $label) {
                    $sheet .= "<tr><td><h3>" . strtoupper($label) . "</h3></td>";
                    foreach ($this->stats[FateGameGlobals::STAT_MODE] as $mode) {
                        $this_mode = $this->skills_by_mode[$mode->{fractal_stat_id}];
                        if (count($this_mode['skills_by_level'][$level]) > 0) {
                            $level_label = $ladder[$this_mode['mode_value'] + $level] . " (+" . ($this_mode['mode_value'] + $level) . "):";
                            $label_list = array();
                            foreach ($this_mode['skills_by_level'][$level] as $skill) {
                                $label_list[] = $skill->{stat_label};
                            }
                            $sheet .= "<td><span>$level_label</span> " . implode(", ", $label_list) . "</td>";
                        } else {
                            $sheet .= "<td>&nbsp;</td>";
                        }
                    }
                    $sheet .= "</tr>";
                }
                $sheet .= "</table>";
            }
            /* Other Skill Distributions eventually go here */
        }
        
        if (array_key_exists(FateGameGlobals::STAT_STUNT, $this->stats)) {
            $sheet .= "<H2>STUNTS</H2>\n<div class='block'><dl>";
            foreach ($this->stats[FateGameGlobals::STAT_STUNT] as $stunt) {
                $sheet .= "<dt>" . $stunt->{stat_field} . "</dt>".
                          "<dd>" . $stunt->{stat_description} . "</dd>";
            }
            $sheet .= "</dl></div>\n";
        }
            
        if (array_key_exists(FateGameGlobals::STAT_CONSEQUENCE, $this->stats)) {
            $sheet .= "<H2>CONSEQUENCES</H2>\n";
            foreach ($this->stats[FateGameGlobals::STAT_CONSEQUENCE] as $consequence) {
                $sheet .= "<div class='block consequence'><div class='value'>" . $consequence->{stat_display_value} .
                          "</div><div class='block_label'>" . $consequence->{stat_label} . "</div>" .
                          ($consequence->{stat_field} ? $consequence->{stat_field} : '&nbsp;') . "</div>\n";
            }
        }
        
        $stress_table = '';
        $fate_table = '';
        if (array_key_exists(FateGameGlobals::STAT_STRESS, $this->stats)) {
            $stress_table .= '<table>';
            foreach ($this->stats[FateGameGlobals::STAT_STRESS] as $stress) {
                $stress_table .= "<tr><td><h2>" . strtoupper($stress->{stat_label}) . " STRESS</h2></td></tr><tr><td>";
                for ($label = 1; $label <= $stress->{stat_max_value}; $label++) {
                    $stress_table .= "<div class='block stress'><div class='value'>$label</div>" .
                                     ($stress->{stat_value} >= $label ? 'X' : '&nbsp;') . "</div>\n";
                }
                $stress_table .= "</td></tr>";
            }
            $stress_table .= "</table>";
        }
        
        if (array_key_exists(FateGameGlobals::STAT_FATE, $this->stats) || array_key_exists(FateGameGlobals::STAT_REFRESH, $this->stats)) {
            $fate_table .= "<table><tr><td><h2>FATE POINTS</h2></td></tr><tr><td>";
            if (array_key_exists(FateGameGlobals::STAT_REFRESH, $this->stats)) {
                $refresh = $this->stats[FateGameGlobals::STAT_REFRESH][0];
                $fate_table .= "<div class='block fate'><div class='block_label'>Refresh</div>" . $refresh->{stat_value} . "</div>\n";
            }
            if (array_key_exists(FateGameGlobals::STAT_FATE, $this->stats)) {
                $fate = $this->stats[FateGameGlobals::STAT_FATE][0];
                $fate_table .= "<div class='block fate'><div class='block_label'>Current</div>" . $fate->{stat_value} . "</div>\n";
            }
            $fate_table .= "</td></tr></table>";
        }
        
        if ($stress_table || $fate_table) {
            $sheet .= "<table><tr>";
            if ($stress_table) {
                $sheet .= "<td valign='top'>" . $stress_table . "</td>";
            }
            if ($fate_table) {
                $sheet .= "<td valign='top'>" . $fate_table . "</td>";
            }
            $sheet .= "</tr></table>";
        }
        
        $sheet .= "</div>";
        return $sheet;
    }
}