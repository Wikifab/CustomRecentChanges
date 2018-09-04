<?php
/**
 * Created by PhpStorm.
 * User: Brendan
 * Date: 04/09/2018
 * Time: 12:09
 */

namespace RecentChanges;



use Parser;
use RecentChanges\Specials\SpecialRecentChanges;
use Title;
use WikiPage;

class RecentChangesHooks
{

    /**
     * @param $parser
     * @throws \MWException
     */
    public static function onParserFirstCallInit(Parser &$parser){
        $parser->setFunctionHook('rc', 'RecentChangesHooks::renderChangesList');
    }

    /**
     * @param $parser
     * @param $limit
     * @param $days
     * @param $namespace
     * @return array
     * @throws \MWException
     */
    public static function renderChangesList($parser, $limit, $days, $namespace){
        $params = [
            'limit' => $limit,
            'days' => $days,
            'namespace' => $namespace
        ];

        $title = Title::newFromText("Special:RecentChanges");
        $page = WikiPage::factory($title);

        $page->getContent()->getWikitextForTransclusion();

        return array($html, 'noparse' => true, 'isHTML' => true);
    }
}