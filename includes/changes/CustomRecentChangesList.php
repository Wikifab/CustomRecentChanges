<?php
/**
 * Created by PhpStorm.
 * User: Brendan
 * Date: 23/08/2018
 * Time: 14:21
 */
namespace CustomRecentChanges\Changes;

use HtmlArmor;
use IContextSource;
use IP;
use LogFormatter;
use MWException;
use RCCacheEntry;
use Skin;
use SpecialPage;
use TemplateParser;
use RCCacheEntryFactory;

/**
 * Constructs Recent Changes list HTML
 */
class CustomRecentChangesList extends \EnhancedChangesList
{

    protected $lastUser;


    /**
     * @param IContextSource|Skin $obj
     * @param array $filterGroups Array of ChangesListFilterGroup objects (currently optional)
     * @throws MWException
     */
    public function __construct( $obj, array $filterGroups = [] ) {
        if ( $obj instanceof Skin ) {
            // @todo: deprecate constructing with Skin
            $context = $obj->getContext();
        } else {
            if ( !$obj instanceof IContextSource ) {
                throw new MWException( 'EnhancedChangesList must be constructed with a '
                    . 'context source or skin.' );
            }

            $context = $obj;
        }

        parent::__construct( $context, $filterGroups );

        // message is set by the parent ChangesList class
        $this->cacheEntryFactory = new RCCacheEntryFactory(
            $context,
            $this->message,
            $this->linkRenderer
        );

        // Set template directory to extension template dir
        $this->templateParser = new TemplateParser(__DIR__.'/../templates');
    }

    /**
     * Returns text for the start of the tabular part of RC
     * @return string
     */
    public function beginRecentChangesList() {
        $this->rc_cache = [];
        $this->rcMoveIndex = 0;
        $this->rcCacheIndex = 0;
        $this->lastdate = null;
        $this->lastUser = null;
        $this->rclistOpen = false;

        $this->getOutput()->addModuleStyles( 'ext.CustomRecentChanges.css' );
        $this->getOutput()->addModuleScripts( 'ext.CustomRecentChanges.js' );

        return '
            <div class="rc-root-list rc-list">';
    }

    /**
     * Group logs by same action and same user
     *
     * @param RCCacheEntry $cacheEntry
     *
     * @return string
     * @throws MWException
     */
    protected function makeCacheGroupingKey( RCCacheEntry $cacheEntry ) {
        // Prefix (to group) by user login or IP
        $cacheGroupingKey = $cacheEntry->mAttribs['rc_user_text'].'_';

        $type = $cacheEntry->mAttribs['rc_type'];
        if ( $type == RC_LOG ) {
            // Group by log type
            $cacheGroupingKey .= SpecialPage::getTitleFor(
                'Log',
                $cacheEntry->mAttribs['rc_log_type']
            )->getPrefixedDBkey();
        }else{
            $title = $cacheEntry->getTitle();
            $cacheGroupingKey .= $title->getPrefixedDBkey();
        }

        return $cacheGroupingKey;
    }

    /**
     * Format a line for enhanced recentchange (aka with javascript and block of lines).
     *
     * @param RecentChange $rc
     * @param bool $watched
     * @param int $linenumber (default null)
     *
     * @return string
     * @throws MWException
     * @throws \ConfigException
     * @throws \Exception
     * @throws \FatalError
     */
    public function recentChangesLine( &$rc, $watched = false, $linenumber = null ) {

        $date = $this->getLanguage()->userDate(
            $rc->mAttribs['rc_timestamp'],
            $this->getUser()
        );

        $ret = '';

        # If it's a new day, add the headline and flush the cache
        if ( $date != $this->lastdate ) {
            # Process current cache
            $ret = $this->recentChangesBlock();
            $this->lastUser = null;
            $this->rc_cache = [];
            $ret .= '<h3 class="rc-date-header">'.$date.'</h3>';
            $this->lastdate = $date;
        }

        // Build a new recent change row
        $cacheEntry = $this->cacheEntryFactory->newFromRecentChange( $rc, $watched );
        $this->addCacheEntry( $cacheEntry );

        return $ret;
    }

    /**
     * If enhanced RC is in use, this function takes the previously cached
     * RC lines, arranges them, and outputs the HTML
     *
     * @return string
     * @throws MWException
     * @throws \ConfigException
     * @throws \Exception
     * @throws \FatalError
     */
    protected function recentChangesBlock() {
        if ( count( $this->rc_cache ) == 0 ) {
            return '';
        }

        $blockOut = '';
        foreach ( $this->rc_cache as $block ) {
            // Generate recent change block for each element
            if ( count( $block ) < 2 ) {
                $blockOut .= $this->recentChangesBlockLine( array_shift( $block ) );
            } else {
                $blockOut .= $this->recentChangesBlockGroup( $block );
            }
        }
        // Close block at the end, it's mandatory
        $blockOut .= $this->closeUserBlock();

        return '<div>' . $blockOut . '</div>';
    }

    /**
     * Enhanced RC ungrouped line.
     *
     * @param $rc
     * @return string A HTML formatted line (generated using $r)
     * @throws MWException
     * @throws \ConfigException
     */
    protected function recentChangesBlockLine( $rc ) {
        $data = [];

        $type = $rc->mAttribs['rc_type'];
        $logType = $rc->mAttribs['rc_log_type'];

        $query['curid'] = $rc->mAttribs['rc_cur_id'];

        // timestamp is not really a link here, but is called timestampLink
        // for consistency with EnhancedChangesListModifyLineData
        $data['timestamp'] = $rc->timestamp;

        # Icon
        $data['icon'] = $this->getFlagIcon($rc);

        # Title
        $data['title'] = $this->getActionText($rc);

        # Diff and hist links
        if ( $type != RC_LOG && $type != RC_CATEGORIZE ) {
            $query['action'] = 'history';
            $data["showDiffHistLinks"] = true;
            $data['diffHistLinks'] = $this->getDiffHistLinks( $rc, $query );
        }

        # Character diff
        if ( $this->getConfig()->get( 'RCShowChangedSize' ) ) {
            $cd = $this->formatCharacterDifference( $rc );
            if ( $cd !== '' ) {
                $data['diff'] = $cd;
            }
        }

        if ( $type == RC_LOG ) {
            $logFormatter = $this->getLogFormatter( $rc );
            $data['title'] = $logFormatter->getActionText();
            $data['comment'] = $logFormatter->getComment();
        } elseif ( $this->isCategorizationWithoutRevision( $rc ) ) {
            $data['comment'] = $this->insertComment( $rc );
        } else {
            $data['userLink'] = $rc->userlink;
            $data['userTalkLink'] = $rc->usertalklink;
            $data['comment'] = $this->insertComment( $rc );
            if ( $type == RC_CATEGORIZE ) {
                $data['historyLink'] = $this->getDiffHistLinks( $rc, $query );
            }
            $data['rollback'] = $this->getRollback( $rc );
        }

        # Show how many people are watching this if enabled
        $data['watchingUsers'] = $this->numberofWatchingusers( $rc->numberofWatchingusers );

        // Start html rendering
        $html = '';

        // Display if exists
        $data['showDiffHistLinks'] = isset($data['diffHistLinks']);
        $data['showComment'] = isset($data['comment']);

        // Open a user block
        $html .= $this->openOrSwitchUserBlock($rc);

        // Render view with generated data
        $html .= $this->templateParser->processTemplate('RecentChangeRow', $data);
        return $html;
    }


    /**
     * Enhanced RC group
     * @param $rcs
     * @return string
     * @throws MWException
     * @throws \ConfigException
     */
    protected function recentChangesBlockGroup( $rcs ) {
        # Collate list of users
        $userlinks = [];

        # Other properties
        $curId = 0;
        $RCShowChangedSize = $this->getConfig()->get( 'RCShowChangedSize' );

        $html = '';

        # Open User's block if not yet opened
        $html .= $this->openOrSwitchUserBlock($rcs[0]);

        # Timestamp
        $data['timestamp'] = $rcs[0]->timestamp;

        # Icon
        $data['icon'] = $this->getFlagIcon($rcs[0]);

        # Title
        $data['title'] = $this->getActionText($rcs[0]);

        $queryParams['curid'] = $curId;

        # Sub-entries
        $data['lines'] = [];
        foreach ( $rcs as $i => $rc ) {
            $line = $this->recentChangesBlockLine($rc);
            $data['lines'][] = $line;
        }

        // Further down are some assumptions that $block is a 0-indexed array
        // with (count-1) as last key. Let's make sure it is.
        $rcs = array_values( $rcs );

        if ( empty( $rcs ) || !$data['lines'] ) {
            // if we can't show anything, don't display this block altogether
            return '';
        }

        # Footer links
        $data["more"] = $this->getLogLinks($rcs, $queryParams);

        # Character difference (does not apply if only log items)
        $charDifference = false;
        if ( $RCShowChangedSize ) {
            $last = 0;
            $first = count( $rcs ) - 1;
            # Some events (like logs and category changes) have an "empty" size, so we need to skip those...
            while ( $last < $first && $rcs[$last]->mAttribs['rc_new_len'] === null ) {
                $last++;
            }
            while ( $last < $first && $rcs[$first]->mAttribs['rc_old_len'] === null ) {
                $first--;
            }

            # Get net change
            $data['diff'] = $this->formatCharacterDifference( $rcs[$first], $rcs[$last] );
        }

        $data['watching'] = $this->numberofWatchingusers( $rcs[0]->numberofWatchingusers );

        $this->rcCacheIndex++;

        $html .= $this->templateParser->processTemplate('RecentChangeGroup', $data);
        return $html;
    }


    protected function openOrSwitchUserBlock($rc){
        $html = '';
        $userLogin = $rc->getAttribute('rc_user_text');
        // If processed user's recent change is the same, no user block to open
        if($this->lastUser !== $userLogin){
            // Close the previous user block if there is a previous user
            if($this->lastUser !== null){
                $html .= $this->closeUserBlock();
            }

            // Change current user processed;
            $this->lastUser = $userLogin;

            // Open a new user block
            $html .= '
            <div class="rc-row"><img class="rc-user-avatar" src="'.$this->getUserAvatar($rc).'" alt="">
                <div class="rc-user-action">
                    <h3 class="rc-user-title">'.$this->getUserDisplayName($rc).'</h3>
            ';
        }

        return $html;
    }

    protected function closeUserBlock(){
        return '
            </div>
        </div>
        ';
    }

    /**
     * Return User login / IP or pseudonyme
     *
     * @param RCCacheEntry $rc
     * @return string
     */
    protected function getUserDisplayName(RCCacheEntry $rc){
        $html = '';

        $userName = $rc->getAttribute('rc_user_text');
        $userRealName = $rc->getAttribute('user_real_name');

        // Check if username is IP
        if (IP::isIPAddress($user)){
            $html .= $userName;

        }
        // Else, it means it's a user
        else{
            $user = User::newFromName( $userName, false );
            $url = $user->getUserPage()->getLinkURL();

            $html .= '
                <a href="'.$url.'">
                    '.(($userRealName != '') ? $userRealName : $userName).'
                </a>
            ';
        }

        // End of the link;
        return $html;
    }

    /**
     * Build User Avatar url
     *
     * @param $rc
     * @return string
     */
    protected function getUserAvatar(RCCacheEntry $rc){
        global $wgServer, $wgUploadPath;
        $userId = $rc->getAttribute('rc_user');

        // If class doesn't exists, stop
        if(!class_exists("wAvatar")) return null;

        $avatar = new \wAvatar($userId, 'l');
        return "{$wgServer}{$wgUploadPath}/avatars/{$avatar->getAvatarImage()}";
    }

    /**
     * Build action title according to his type (new, edit, delete, ...).
     *
     * @param RCCacheEntry $rc
     * @return string
     */
    protected function getActionText(RCCacheEntry $rc){
        $type = $rc->getAttribute('rc_type');

        // If this recent change has a log entry, get action text of the log
        if($type == RC_LOG){
            $formatter = LogFormatter::newFromRow( $rc->mAttribs );
            $formatter->setContext( $this->getContext() );
            $formatter->setShowUserToolLinks( false );

            return $formatter->getActionText();
        }

        # Else, print the article title with a custom action text
        if ($type == RC_NEW) {
            $titleMessageKey = 'customrecentchanges-entry-new';
        }elseif ($type == RC_EDIT) {
            $titleMessageKey = 'customrecentchanges-entry-edit';
        }else{
            $titleMessageKey = 'customrecentchanges-entry-title';
        }

        return $this->msg($titleMessageKey)->rawParams(
            $this->getArticleLink($rc, $rc->unpatrolled, $rc->watched)
        );
    }


    /**
     * @param RCCacheEntry $rc
     * @return string
     */
    protected function getIcon(RCCacheEntry $rc){
        $type = $this->getActionType($rc);

        switch($type){
            case "new":
                $color = "success";
                $icon = "plus";
                break;
            case 'edit':
                $color = "warning";
                $icon = "pencil";
                break;
            case 'delete':
                $color = "danger";
                $icon = "trash";
                break;
            default:
                $color = "primary";
                $icon = "info";
                break;
        }

        return '<span class="label label-'.$color.'"><i class="fa fa-'.$icon.'"></i></span>';
    }

    /**
     * Insert a formatted action
     *
     * @param RecentChange $rc
     * @return LogFormatter
     */
    public function getLogFormatter( $rc ) {
        $formatter = LogFormatter::newFromRow( $rc->mAttribs );
        $formatter->setContext( $this->getContext() );
        $formatter->setShowUserToolLinks( false );

        return $formatter;
    }

    /**
     * Get footer log links
     * @param $rcs
     * @param $params
     * @return string
     */
    protected function getLogLinks($rcs, $params)
    {
        # Get number of rows
        $n = count($rcs);
        $rcLog = current($rcs);

        # Stored messages translations
        static $nchanges = [];

        if (!isset($nchanges[$n])) {
            $nchanges[$n] = $this->msg('customrecentchanges-entry-footer-nchangeslink')->numParams($n)->escaped();
        }

        $links['changes'] =  '<a class="rc-toggle-list" href="#">'.$nchanges[$n].'</a>';

        # History
        // Do not display history links for logs
        if ( $rcLog->mAttribs['rc_type'] != RC_CATEGORIZE && $rcLog->mAttribs['rc_type'] != RC_LOG) {
            $params['action'] = 'history';

            $links['history'] = $this->linkRenderer->makeKnownLink(
                $rcLog->getTitle(),
                new HtmlArmor( $this->message['enhancedrc-history'] ),
                [],
                $params
            );
        }

        return implode($links, ' - ');
    }


    /**
     * Get action type to render correct icon
     *
     * @param RCCacheEntry $rc
     * @return mixed|string
     */
    protected function getFlagIcon(RCCacheEntry $rc){
        $type = $rc->getAttribute('rc_type');

        if($type == RC_NEW){
            return "new";
        }else if($type == RC_EDIT){
            return "edit";
        }else if($type == RC_LOG){
            return $rc->getAttribute('rc_log_type');
        }
    }
}