<?php

if (!defined('MUXREGISTER_VERSION')) {
    die( "Extension <a href=\"http://aliencity.org/wiki/Extension:MuxRegister\">MuxRegister</a> must be installed for Fate Character Generation to run." );
} elseif ( function_exists( 'wfLoadExtension' ) ) {       
    wfLoadExtension( 'FateCharGen' );
    // Keep i18n globals so mergeMessageFileList.php doesn't break
    $wgMessagesDirs['FateCharGen'] = __DIR__ . '/i18n';
    $wgExtensionMessagesFiles['FateCharGenAlias'] = __DIR__ . '/FateCharGen.i18n.alias.php';

    wfWarn(
        'Deprecated PHP entry point used for FateCharGen extension. Please use wfLoadExtension ' .
        'instead, see https://www.mediawiki.org/wiki/Extension_registration for more details.'
    );
    return true;
} else {
    die( 'This version of the FateCharGen extension requires MediaWiki 1.25+' );
}