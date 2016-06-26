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
        $updater->addExtensionTable( 'fate_game_turn_order', __DIR__ . '/sql/GameTurnOrder.sql' );
        $updater->addExtensionTable( 'fate_game_staff', __DIR__ . '/sql/GameStaff.sql' );
        
        /* Tables that define the 'fractals' for a game, where a fractal is any thing that has stats */
        $updater->addExtensionTable( 'fate_fractal', __DIR__ . '/sql/Fractal.sql' );
        $updater->addExtensionTable( 'fate_fractal_stat', __DIR__ . '/sql/FractalStat.sql' );
        $updater->addExtensionTable( 'fate_pending_stat', __DIR__ . '/sql/PendingStat.sql' );
        return true;
    }
    
    public static function addContentActions( &$skinTemplate, &$links ) {
        $user = $skinTemplate->getUser();
        $title = $skinTemplate->getTitle();
        $request = $skinTemplate->getRequest();

        $fractal_id = $request->getVal('fractal_id');
        $game_id = $request->getVal('game_id');
        
        $subs = split('/', $title->getSubpageText());
        $sub = array_pop($subs);
        
        if (1) {
            // Expand this at some point to only allow specific gms to edit specific things
            //$fractal = new FateFractal($fractal_id);
        }
        
        // Tab links for the FateStats special page
        if ($title->isSpecial( 'FateStats' ) && $fractal_id &&
            ($sub == 'Edit' || $sub == 'View' || $sub == 'ViewSheet' || $sub == 'Milestones' ))
        {            
            $fractal = new FateFractal($fractal_id);
            $view = '';
            if ($fractal->{fractal_type} == 'Character') {
                $view = SpecialPage::getTitleFor('FateStats', 'ViewSheet');
            } else {
                $view = SpecialPage::getTitleFor('FateStats', 'View');
            }
            $edit = SpecialPage::getTitleFor('FateStats', 'Edit');
            $milestone = SpecialPage::getTitleFor('FateStats', 'Milestones');
            $game = SpecialPage::getTitleFor('FateGameConfig', 'View');
            $view_class = ($sub == 'View' || $sub == 'ViewSheet' ? 'selected': false );
            $edit_class = ($sub == 'Edit' ? 'selected' : false );
            $mile_class = ($sub == 'Milestones' ? 'selected' : false );
            
            if (!$fractal->is_private || $user->isAllowed('fategm') || $fractal->fate_game->is_staff($user->getID()) || $fractal->user_id == $user->getID()) {
                $links['views'][$title->getNamespaceKey()] = array (
                    'class' => $view_class,
                    'text' => 'View',
                    'href' => $view->getFullUrl("fractal_id=$fractal_id")
                );
            }
            if ($user->isAllowed('fategm') || $fractal->fate_game->is_staff($user->getID())) {
                $links['views']['edit'] = array(
                    'class' => $edit_class,
                    'text' => 'Edit',
                    'href' => $edit->getFullUrl("fractal_id=$fractal_id")
                );
            }
            if ($user->isAllowed('fategm') || $fractal->fate_game->is_staff($user->getID()) || $fractal->user_id == $user->getID()) {
                $links['views']['milestone'] = array(
                    'class' => $mile_class,
                    'text' => 'Milestones',
                    'href' => $milestone->getFullUrl("fractal_id=$fractal_id")
                );
            }
            if ($user->isAllowed('fategm') || $fractal->fate_game->is_staff($user->getID())) {
                $links['views']['game'] = array(
                    'class' => false,
                    'text' => 'Game',
                    'href' => $game->getFullUrl("game_id=" . $fractal->{game_id})
                );
            }
        }        
        
        // Tab links for the FateGameConfig special page
        if (
            $user->isAllowed('fatestaff') &&
            $title->isSpecial('FateGameConfig') && $game_id &&
            ($sub == 'Edit' || $sub == 'View' || $sub == 'Approval') )
        {
            $view = SpecialPage::getTitleFor('FateGameConfig', 'View');
            $edit = SpecialPage::getTitleFor('FateGameConfig', 'Edit');
            $approval = SpecialPage::getTitleFor('FateGameConfig', 'Approval');
            $view_class = ($sub == 'View' ? 'selected' : false);
            $edit_class = ($sub == 'Edit' ? 'selected' : false);
            $approval_class = ($sub == 'Approval' ? 'selected' : false);
            
            $links['views']['view'] = array(
                'class' => $view_class,
                'text' => 'View',
                'href' => $view->getFullUrl("game_id=$game_id")
            );
            $links['views']['edit'] = array(
                'class' => $edit_class,
                'text' => 'Edit',
                'href' => $edit->getFullUrl("game_id=$game_id")
            );
            $links['views']['approval'] = array(
                'class' => $approval_class,
                'text' => 'Approvals',
                'href' => $approval->getFullUrl("game_id=$game_id")
            );
        }
        
        return true;
    }
}