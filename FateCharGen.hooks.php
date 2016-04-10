<?php
/**
 * Hooks for FateCharGen extension
 *
 * @file
 * @ingroup Extensions
 */

class FateCharGenHooks {
    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
        /* Tables that define a specific game, and what sort of stats its characters have */
        $updater->addExtensionTable( 'fate_game', __DIR__ . '/sql/Game.sql' );
        $updater->addExtensionTable( 'fate_game_aspect', __DIR__ . '/sql/GameAspect.sql' );
        $updater->addExtensionTable( 'fate_game_condition', __DIR__ . '/sql/GameCondition.sql' );
        $updater->addExtensionTable( 'fate_game_consequence', __DIR__ . '/sql/GameConsequence.sql' );
        $updater->addExtensionTable( 'fate_game_mode', __DIR__ . '/sql/GameMode.sql' );
        $updater->addExtensionTable( 'fate_game_mode_skill', __DIR__ . '/sql/GameModeSkill.sql' );
        $updater->addExtensionTable( 'fate_game_skill', __DIR__ . '/sql/GameSkill.sql' );
        $updater->addExtensionTable( 'fate_game_stress', __DIR__ . '/sql/GameStress.sql' );
        
        /* Tables that define the 'fractals' for a game, where a fractal is any thing that has stats */
        $updater->addExtensionTable( 'fate_fractal', __DIR__ . '/sql/Fractal.sql' );
        $updater->addExtensionTable( 'fate_fractal_stat', __DIR__ . '/sql/FractalStat.sql' );
        return true;
    }
    
    public static function addContentActions( &$skinTemplate, &$links ) {
        $user = $skinTemplate->getUser();
        $title = $skinTemplate->getTitle();
        $request = $skinTemplate->getRequest();

        $fractal_id = $request->getVal('fractal_id');
        
        $subs = split('/', $title->getSubpageText());
        $sub = array_pop($subs);
        
        if (1) {
            // Expand this at some point to only allow specific gms to edit specific things
            //$fractal = new FateFractal($fractal_id);
        }
        
        // Edit and View links for the FateStats special page
        if (
            $user->isAllowed('fategm') &&
            $title->isSpecial( 'FateStats' ) &&
            ($sub == 'Edit' || $sub == 'View' || $sub == 'ViewSheet' ) &&
            $fractal_id )
        {            
            $view = SpecialPage::getTitleFor('FateStats', 'View');
            $edit = SpecialPage::getTitleFor('FateStats', 'Edit');
            $view_class = ($sub == 'Edit' ? false : 'selected');
            $edit_class = ($sub == 'Edit' ? 'selected' : false );
            
            $links['views'][$title->getNamespaceKey()] = array (
                'class' => $view_class,
                'text' => 'View',
                'href' => $view->getFullUrl("fractal_id=$fractal_id")
            );
            $links['views']['edit'] = array(
                'class' => $edit_class,
                'text' => 'Edit',
                'href' => $edit->getFullUrl("fractal_id=$fractal_id")
            );
        }        
        
        return true;
    }
}