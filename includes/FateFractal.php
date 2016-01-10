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
                array( 'ORDER BY' => array( 'stat_type', 'stat_ordinal', 'stat_field' ) )
            );
            $this->stats = array();
            if ($stat_data->numRows() > 0) {
                foreach ($stat_data as $stat) {
                    if (! is_array($this->stats[$stat->{stat_type}]) ) {
                        $this->stats[$stat->{stat_type}] = array();
                    }
                    $this->stats[$stat->{stat_type}][] = $stat;
                }
            }
            
            /* Do re-sorting as necessary for various types */
            if (array_key_exists(FateGameGlobals::STAT_CONSEQUENCE, $this->stats)) {
                @usort($this->stats[FateGameGlobals::STAT_CONSEQUENCE], function($a, $b) {
                    if ($a->{stat_display_value} == $b->{stat_display_value}) {
                        return strcmp($a->{stat_field}, $b->{stat_field});
                    } else {
                        return ($a->{stat_display_value} < $b->{stat_display_value} ? -1 : 1 );
                    }
                });
            }
            if (array_key_exists(FateGameGlobals::STAT_SKILL, $this->stats) && 
                ($this->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_PYRAMID || 
                 $this->fate_game->skill_distribution == FateGameGlobals::SKILL_DISTRIBUTION_COLUMNS ||
                 !($this->stats[FateGameGlobals::STAT_SKILL][0]->{stat_mode} ||
                  $this->stats[FateGameGlobals::STAT_SKILL][0]->{stat_ordinal}))) {
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
    
    public function getFractalBlock() {
        $block = <<< EOT
            <style type='text/css'>
                #fractalblock { border: 2px solid #295079; width: auto; margin: 0 0 0.5em 0p; padding: 5px 15px; }
                #fractalblock .name { font-family: Tahoma, Geneva, sans-serif; font-weight: bold; text-align: center; font-size: 18px; text-transform: uppercase; }
                #fractalblock .section_label { vertical-align: top; font-weight: bold; white-space: nowrap; }
                #fractalblock .stunt_name { font-weight: bold; }
                #fractalblock .aspect_field { font-weight: bold; font-style: italic; }
            </style>
            <div id='fractalblock'>
            <div class='name'>$this->name</div>
            <table width='100%'>
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