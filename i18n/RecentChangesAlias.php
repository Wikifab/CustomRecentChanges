<?php
/**
 * Created by PhpStorm.
 * User: Brendan
 * Date: 22/08/2018
 * Time: 17:27
 */

$specialPageAliases = [];
$magicWords = [];

/**
 * Page name aliases
 */
$specialPageAliases['en'] = [
    'DokitRecentChanges' => ['DokitRecentChanges', 'Dokit_Recent_Changes']
];

$specialPageAliases['fr'] = [
    'DokitRecentChanges' => ['DokitModificationsRécentes', 'Dokit_Modifications_Récentes']
];

/**
 * Parser function magic words
 */
$magicWords['en'] = [
    'rc' => [0, 'rc'],
];


