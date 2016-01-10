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
            } elseif ($sub == 'Delete') {
                $out->addWikiText('* Delete a specific Fractal');
            } else {
                $out->addWikiText('* List All Fractals');
            }
        }   
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