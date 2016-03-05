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
                $this->viewFractalBlock();
            } elseif ($sub == 'ViewSheet') {
                $out->addWikiText('* View a specific sheet');
                $this->viewFractalSheet();
            } elseif ($sub == 'Edit') {
                $out->addWikiText('* Edit a specific stat block');
            } elseif ($sub == 'Create') {
                $out->addWikiText('* Create a New Fractal');
                $this->createFractal();
            } elseif ($sub == 'Delete') {
                $out->addWikiText('* Delete a specific Fractal');
            } else {
                $out->addWikiText('* List All Fractals');
                $this->listAllFractals();
            }
        }   
    }
    
    private function createFractal() {
        $user = $this->getUser();
        $out = $this->getOutput();
        $request = $this->getRequest();
        
        $form_url = $this->getPageTitle()->getSubpage("Create")->getLinkURL();
        $fractal_name = $request->getVal('new_fractal');
        $form = <<< EOT
            <form action='$form_url' method='post'>
                <fieldset>
                    <legend>Create New Fractal</legend>
                    <input type='text' name='new_fractal' size='35'/>
                    <input type='submit' value='Create'/>
                </fieldset>
            </form>
EOT;

        $out->addHTML($form);
        if ($fractal_name) {
            $out->addWikiText("* $fractal_name");
        }
    }

    
    private function listAllFractals() {
        $user = $this->getUser();
        $out  = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);
        
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
        
        $table = "<table border=1 cellspacing=3 cellpadding=3>".
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
    
    private function viewFractalBlock() {
        $user = $this->getUser();
        $out = $this->getOutput();
        $request = $this->getRequest();
        
        $fractal_id = $request->getInt('fractal_id');
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
    
    protected function getGroupName() {
        return 'other';
    }
}