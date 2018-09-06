<?php

namespace CustomRecentChanges\Specials;

use FormOptions;
use Html;
use MediaWiki\MediaWikiServices;
use RecentChange;
use TitleValue;
use Wikimedia\Rdbms\ResultWrapper;
use CustomRecentChanges\Changes\CustomRecentChangesList;
use Xml;

/**
 * A special page that lists last changes made to the wiki
 *
 * @ingroup SpecialPage
 */


class SpecialCustomRecentChanges extends \SpecialRecentChanges
{
    // @codingStandardsIgnoreStart Needed "useless" override to change parameters.
    public function __construct()
    {
        parent::__construct('CustomRecentChanges');
    }



    /**
     * User is needed
     * Add join condition to parent class to get user
     * @param $tables
     * @param $fields
     * @param $conds
     * @param $query_options
     * @param $join_conds
     * @param FormOptions $opts
     * @return bool|\Wikimedia\Rdbms\IResultWrapper|ResultWrapper
     */
    public function doMainQuery($tables, $fields, $conds, $query_options,
                                $join_conds, FormOptions $opts ) {

        $tables[] = 'user';
        $fields[] = 'user_real_name';
        $join_conds['user'] = ['LEFT JOIN', 'rc_user_text=user_name'];

        return parent::doMainQuery($tables, $fields, $conds, $query_options, $join_conds, $opts);
    }


    /**
     * Set the text to be displayed above the changes
     *
     * @param FormOptions $opts
     * @param int $numRows Number of rows in the result to show after this header
     * @throws \ConfigException
     * @throws \MWException
     */
    public function doHeader( $opts, $numRows ) {
        $defaults = $opts->getAllValues();
        $nondefaults = $opts->getChangedValues();

        // Start HTML and open options bar
        $html = Html::openElement('form', ['class' => 'rc-options', 'method' => 'GET']);

        // Generate dropdowns options, it replaces link filters
        $html .= $this->optionsPanel($defaults, $nondefaults);

        // Generate filter links
        $html .= Html::openElement('div',['class' => 'rc-namespaces']);
        $html .= Html::element('span', ['class' => 'rc-options-label'], $this->msg('customrecentchanges-namespace-label')->text());
        $html .= $this->namespaceFilter($defaults, $nondefaults, $numRows);
        $html .= Html::closeElement('div');

        // Close options bar
        $html .= Html::closeElement('form');

        $this->getOutput()->addHTML($html);
    }

    /**
     * Generate dropdowns options
     * @param $defaults
     * @param $nondefaults
     * @param null $rows
     * @return string
     * @throws \ConfigException
     * @throws \MWException
     */
    public function optionsPanel($defaults, $nondefaults, $rows = null){
        $options = $defaults + $nondefaults;
        $config = $this->getConfig();

        // Generate dropdowns options
        # Sort days for display
        $days = $config->get( 'RCLinkDays' );
        $day = $options['days'];
        $days = array_combine($days, $days);

        # Sort limits for display
        $limits = $config->get( 'RCLinkLimits' );
        $limit = $options['limit'];
        $limits = array_combine($limits, $limits);


        // Create form for dropdown
        $daySelect = new \HTMLSelectField([
            'fieldname' => 'days',
            'name' => 'days',
            'options' => $days
        ]);

        $limitSelect = new \HTMLSelectField([
            'fieldname' => 'limit',
            'name' => 'limit',
            'options' => $limits
        ]);

        # Output HTML
        return '
        <div class="rc-dropdowns">
            <span class="rc-dropdowns-sentence">'
                .$this->msg('rclinks')->rawParams($limitSelect->getInputHTML($limit), $daySelect->getInputHTML($day), '')->parse().'
            </span>
        </div>
        ';
    }

    /**
     * Generate dummy javascript links to filter by namespaces
     *
     * @param array $defaults
     * @param array $nondefaults
     * @param int $numRows
     * @return string
     */
    public function namespaceFilter($defaults, $nondefaults, $numRows)
    {
        $namespaces = $this->getNamespacesList();

        // Replace (Main) namespace and all default value
        $namespaces = [
            'all' => $this->msg('customrecentchanges-namespace-all')->text(),
            0 => $this->msg('customrecentchanges-namespace-main')->text()
        ] + $namespaces;

        $selected = isset($nondefaults['namespace']) ? $nondefaults['namespace'] : 'all';

        foreach ($namespaces as $key => $namespace) {
            $translation = $this->msg('customrecentchanges-namespace-'.strtolower($namespace));

            // If the namespace has been translated, show translation
            if($translation->exists()) $namespace = $translation->text();

            // Generate option
            $links[] = Html::linkButton($namespace, ['data-id' => $key, 'href' => '#']);
        }

        $html = Html::openElement('ul', ['class' => 'rc-namespaces-links']);
        $html .= '<li>'.implode($links, '</li><li>').'</li>';
        $html .= Html::closeElement('ul');

        return $html;
    }


    /**
     * Remove useless namespaces from filter list according to the configuration
     *
     * @return array
     */
    public function getNamespacesList()
    {
        global $wgRCNamespacesList, $wgRCNamespacesListIgnored;
        global $wgContLang;

        $namespaces = $wgContLang->getFormattedNamespaces();
        $result = [];

        if (is_array($wgRCNamespacesList)) {
            // Only return requested namespaces
            foreach ($wgRCNamespacesList as $ns) {
                $result[$ns] = $namespaces[$ns];
            }
        }
        else if (is_array($wgRCNamespacesListIgnored)){
            // Remove ignored namespaces
            $result = $namespaces;
            foreach ($wgRCNamespacesListIgnored as $ns) {
                unset($result[$ns]);
            }
        }else{
            // Return all namespaces
            $result = $namespaces;
        }

        return $result;
    }


    /**
     * Build and output the actual changes list.
     *
     * @param ResultWrapper $rows Database rows
     * @param FormOptions $opts
     * @throws \ConfigException
     * @throws \Exception
     * @throws \FatalError
     * @throws \MWException
     */
    public function outputChangesList($rows, $opts){
        $limit = $opts['limit'];

        // Get settings, should we show counter view
        $showWatcherCount = $this->getConfig()->get( 'RCShowWatchingUsers' ) && $this->getUser()->getOption( 'shownumberswatching' );
        $userShowHiddenCats = $this->getUser()->getBoolOption( 'showhiddencats' );

        $watcherCache = [];

        $dbr = $this->getDB();

        $counter = 1;
        $list = new CustomRecentChangesList($this->getContext(), $this->filterGroups);
        $list->initChangesListRows( $rows );

        // Start list
        $rclistOutput = $list->beginRecentChangesList();

        // For each row
        foreach ( $rows as $obj ) {
            if ( $limit == 0 ) {
                break;
            }
            $rc = RecentChange::newFromRow( $obj );

            # Skip CatWatch entries for hidden cats based on user preference
            if (
                $rc->getAttribute( 'rc_type' ) == RC_CATEGORIZE &&
                !$userShowHiddenCats &&
                $rc->getParam( 'hidden-cat' )
            ) {
                continue;
            }

            $rc->counter = $counter++;
            # Check if the page has been updated since the last visit
            if ( $this->getConfig()->get( 'ShowUpdatedMarker' )
                && !empty( $obj->wl_notificationtimestamp )
            ) {
                $rc->notificationtimestamp = ( $obj->rc_timestamp >= $obj->wl_notificationtimestamp );
            } else {
                $rc->notificationtimestamp = false; // Default
            }
            # Check the number of users watching the page
            $rc->numberofWatchingusers = 0; // Default
            if ( $showWatcherCount && $obj->rc_namespace >= 0 ) {
                if ( !isset( $watcherCache[$obj->rc_namespace][$obj->rc_title] ) ) {
                    $watcherCache[$obj->rc_namespace][$obj->rc_title] =
                        MediaWikiServices::getInstance()->getWatchedItemStore()->countWatchers(
                            new TitleValue( (int)$obj->rc_namespace, $obj->rc_title )
                        );
                }
                $rc->numberofWatchingusers = $watcherCache[$obj->rc_namespace][$obj->rc_title];
            }

            $changeLine = $list->recentChangesLine( $rc, !empty( $obj->wl_user ), $counter );
            if ( $changeLine !== false ) {
                $rclistOutput .= $changeLine;
                --$limit;
            }
        }
        $rclistOutput .= $list->endRecentChangesList();

        if ( $rows->numRows() === 0 ) {
            $this->outputNoResults();
            if ( !$this->including() ) {
                $this->getOutput()->setStatusCode( 404 );
            }
        } else {
            $this->getOutput()->addHTML( $rclistOutput );
        }
    }
}