<?php
/**
 * Created by PhpStorm.
 * User: Brendan
 * Date: 29/08/2018
 * Time: 14:59
 */

namespace RecentChanges\Changes;

class RCCacheEntry extends \RCCacheEntry {
    public $curlink;
    public $difflink;
    public $lastlink;
    public $link;
    public $timestamp;
    public $unpatrolled;
    public $useravatar;
    public $userlink;
    public $usertalklink;
    public $watched;
    public $mAttribs;
    public $mExtra;

    /**
     * @param \RecentChange $rc
     * @return RCCacheEntry
     */
    static function newFromParent( $rc ) {
        $rc2 = new RCCacheEntry;
        $rc2->mAttribs = $rc->mAttribs;
        $rc2->mExtra = $rc->mExtra;

        return $rc2;
    }

}