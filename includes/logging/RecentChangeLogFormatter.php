<?php
/**
 * Created by PhpStorm.
 * User: Brendan
 * Date: 27/08/2018
 * Time: 18:16
 */

namespace RecentChanges\Logging;


class RecentChangeLogFormatter extends \LogFormatter
{
    /**
     * Returns a key to be used for formatting the action sentence.
     * Default is logentry-TYPE-SUBTYPE for modern logs. Legacy log
     * types will use custom keys, and subclasses can also alter the
     * key depending on the entry itself.
     * @return string Message key
     */
    protected function getMessageKey() {
        $type = $this->entry->getType();
        $subtype = $this->entry->getSubtype();

        return "rc-logentry-$type-$subtype";
    }
}