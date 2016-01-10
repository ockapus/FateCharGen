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
}