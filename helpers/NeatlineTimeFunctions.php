<?php
/**
 * NeatlineTime helper functions
 */

/**
 * Return specific field for a timeline record.
 *
 * @since 1.0
 * @param string
 * @param array $options
 * @param NeatlineTimeTimeline|null
 * @return string
 */
function timeline($fieldname, $options = array(), $timeline = null)
{

    $timeline = $timeline ? $timeline : get_current_record('neatline_time_timeline');

    $fieldname = strtolower($fieldname);
    $text = $timeline->$fieldname;

    if(isset($options['snippet'])) {
        $text = nls2p(snippet($text, 0, (int)$options['snippet']));
    }

    if ($fieldname == 'query') {
        $text = unserialize($text);
    }

    return $text;

}

/**
 * Returns a link to a specific timeline.
 *
 * @since 1.0
 * @param string HTML for the text of the link.
 * @param array Attributes for the <a> tag. (optional)
 * @param string The action for the link. Default is 'show'.
 * @param NeatlineTimeTimeline|null
 *
 * @return string HTML
 **/
function link_to_timeline($text = null, $props = array(), $action = 'show', $timeline = null)
{

    $timeline = $timeline ? $timeline : get_current_record('neatline_time_timeline');

    $text = $text ? $text : $timeline->title;

    $route = 'neatline-time/timelines/'.$action.'/'.$timeline->id;
    $uri = url($route);
    $props['href'] = $uri;

    return '<a ' . tag_attributes($props) . '>' . $text . '</a>';

}

/**
 * Queues JavaScript and CSS for NeatlineTime in the page header.
 *
 * @since 1.0
 * @return void.
 */
function queue_timeline_assets()
{
    $headScript = get_view()->headScript();
    $headScript->appendFile(src('neatline-time-scripts.js', 'javascripts'));

    // Check useInternalJavascripts in config.ini.
    $config = Zend_Registry::get('bootstrap')->getResource('Config');
    $useInternalJs = isset($config->theme->useInternalJavascripts)
            ? (bool) $config->theme->useInternalJavascripts
            : false;

    if ($useInternalJs) {
        $timelineVariables = 'Timeline_ajax_url="'.src('simile-ajax-api.js', 'javascripts/simile/ajax-api').'"; '
                           . 'Timeline_urlPrefix="'.dirname(src('timeline-api.js', 'javascripts/simile/timeline-api')).'/"; '
                           . 'Timeline_parameters="bundle=true";';

        $headScript->appendScript($timelineVariables);
        $headScript->appendFile(src('timeline-api.js', 'javascripts/simile/timeline-api'));
    } else {
        $headScript->appendFile('http://api.simile-widgets.org/timeline/2.3.1/timeline-api.js?bundle=true');
    }

    $headScript->appendScript('SimileAjax.History.enabled = false; window.jQuery = SimileAjax.jQuery');

    queue_css_file('neatlinetime-timeline');
}

/**
 * Returns the URI for a timeline's json output.
 *
 * @since 1.0
 * @param NeatlineTimeTimeline|null
 * @return string URL the items output uri for the neatlinetime-json output.
 */
function neatlinetime_json_uri_for_timeline($timeline = null)
{
    $timeline = $timeline ? $timeline : get_current_record('neatline_time_timeline');
    $route = 'neatline-time/timelines/items/'.$timeline->id.'?output=neatlinetime-json';
    return url($route);
}

/**
 * Construct id for container div.
 *
 * @since 1.0
 * @param NeatlineTimeTimeline|null
 * @return string HTML
 */
function neatlinetime_timeline_id($timeline = null)
{
    $timeline = $timeline ? $timeline : get_current_record('neatline_time_timeline');
    return text_to_id(html_escape($timeline->title) . ' ' . $timeline->id, 'neatlinetime');
}

/**
 * Returns a string detailing the parameters of a given query array.
 *
 * @param array A search array. If null, the function will check the front
 * controller for any parameters.
 * @return string HTML
 */
function neatlinetime_display_search_query($query = null)
{
    $html = '';

    if ($query === null) {
        $query = Zend_Controller_Front::getInstance()->getRequest()->getParams();
    }

    if (!empty($query)) {
        $db = get_db();

        $displayList = '';
        $displayArray = array();

        foreach ($query as $key => $value) {
            $filter = $key;
            if($value != null) {
                $displayValue = null;
                switch ($key) {
                    case 'type':
                        $filter = 'Item Type';
                        $itemtype = $db->getTable('ItemType')->find($value);
                        $displayValue = $itemtype->name;
                    break;

                    case 'collection':
                        $collection = $db->getTable('Collection')->find($value);
                        $displayValue = $collection->name;
                    break;

                    case 'user':
                        $user = $db->getTable('User')->find($value);
                        $displayValue = $user->Entity->getName();
                    break;

                    case 'public':
                    case 'featured':
                        $displayValue = $value ? __('Yes') : __('No');
                    break;

                    case 'search':
                    case 'tags':
                    case 'range':
                        $displayValue = $value;
                    break;
                }
                if ($displayValue) {
                    $displayArray[$filter] = $displayValue;
                }
            }
        }

        foreach($displayArray as $filter => $value) {
            $displayList .= '<li class="'.text_to_id($filter).'">'.__(ucwords($filter)).': '.$value.'</li>';
        }

        if(array_key_exists('advanced', $query)) {
            $advancedArray = array();

            foreach ($query['advanced'] as $i => $row) {
                if (!$row['element_id'] || !$row['type']) {
                    continue;
                }
                $elementID = $row['element_id'];
                $elementDb = $db->getTable('Element')->find($elementID);
                $element = $elementDb->name;
                $type = $row['type'];
                $terms = $row['terms'];
                $advancedValue = $element . ' ' . $type;
                if ($terms) {
                    $advancedValue .= ' "' . $terms . '"';
                }
                $advancedArray[$i] = $advancedValue;
            }
            foreach($advancedArray as $advancedKey => $advancedValue) {
                $displayList .= '<li class="advanced">' . $advancedValue . '</li>';
            }
        }

        if (!empty($displayList)) {
            $html = '<div class="filters">'
                  . '<ul id="filter-list">'
                  . $displayList
                  . '</ul>'
                  . '</div>';
        }
    }
    return $html;
}

/**
 * Converts the advanced search output into acceptable input for findBy().
 *
 * @see Omeka_Db_Table::findBy()
 * @param array $query HTTP query string array
 * @return array Array of findBy() parameters
 */
function neatlinetime_convert_search_filters($query) {

    $params = array();

    foreach($query as $paramName => $paramValue) {
        if (is_string($paramValue) && trim($paramValue) == '') {
            continue;
        }

        switch($paramName) {
            case 'user':
                if (is_numeric($paramValue)) {
                    $params['user'] = $paramValue;
                }
            break;

            case 'public':
            case 'featured':
            case 'random':
            case 'hasImage':
                $params[$paramName] = is_true($paramValue);
            break;

            case 'recent':
                if (!is_true($paramValue)) {
                    $params['recent'] = false;
                }
            break;

            case 'tag':
            case 'tags':
                $params['tags'] = $paramValue;
            break;

            case 'search':
                $params['search'] = $paramValue;
                //Don't order by recent-ness if we're doing a search
                unset($params['recent']);
            break;

            case 'advanced':
                //We need to filter out the empty entries if any were provided
                foreach ($paramValue as $k => $entry) {
                    if (empty($entry['element_id']) || empty($entry['type'])) {
                        unset($paramValue[$k]);
                    }
                }
                if (count($paramValue) > 0) {
                    $params['advanced_search'] = $paramValue;
                }
            break;

            default:
                $params[$paramName] = $paramValue;
            break;

        }
    }

    return $params;
}

/**
 * Displays random featured timelines
 *
 * @param int Maximum number of random featured timelines to display.
 * @return string HTML
 */
function neatlinetime_display_random_featured_timelines($num = 1) {
  $html = '';

  $timelines = get_db()->getTable('NeatlineTimeTimeline')->findBy(array('random' => 1, 'featured' => 1), $num);

  if ($timelines) {
    foreach ($timelines as $timeline) {
      $html .= '<h3>' . link_to_timeline(null, array(), 'show', $timeline) . '</h3>'
        . '<div class="description timeline-description">'
        . timeline('description', array('snippet' => 150), $timeline)
        . '</div>';
    }
    return $html;
  }
}

/**
 * Returns a string for neatline_json 'classname' attribute for an item.
 *
 * Default fields included are: 'item', item type name, all DC:Type values.
 *
 * Output can be filtered using the 'neatlinetime_item_class' filter.
 *
 * @return string
 */
function neatlinetime_item_class($item = null) {
    $classArray = array('item');

    if ($itemTypeName = metadata($item, 'item_type_name')) {
        $classArray[] = text_to_id($itemTypeName);
    }

    if ($dcTypes = metadata($item, array('Dublin Core', 'Type'), array('all' => true))) {
        foreach ($dcTypes as $type) {
            $classArray[] = text_to_id($type);
        }
    }

    $classAttribute = implode(' ', $classArray);
    $classAttribute = apply_filters('neatlinetime_item_class', $classAttribute);
    return $classAttribute;
}

/**
 * Generates an ISO-8601 date from a date string
 *
 * @see Zend_Date
 * @return string ISO-8601 date
 */
function neatlinetime_convert_date($date) {
  if (preg_match('/^\d{4}$/', $date) > 0) {
      return false;
  }

  $newDate = null;
  try {
    $newDate = new Zend_Date($date, Zend_Date::ISO_8601);
  } catch (Exception $e) {
      try {
          $newDate = new Zend_Date($date);
      } catch (Exception $e) {
      }
  }

  if (is_null($newDate)) {
      $date_out = false;
  } else {
      $date_out = $newDate->get('c');
      $date_out = preg_replace('/^(-?)(\d{3}-)/', '${1}0\2',   $date_out);
      $date_out = preg_replace('/^(-?)(\d{2}-)/', '${1}00\2',  $date_out);
      $date_out = preg_replace('/^(-?)(\d{1}-)/', '${1}000\2', $date_out);
  }
  return $date_out;

}

/**
 * Returns the HTML for an item search form
 *
 * This was copied with modifications from 
 * application/helpers/ItemFunctions.php in the Omeka source.
 *
 * @param array $props
 * @param string $formActionUri
 * @return string
 */
function neatlinetime_items_search_form($props=array(), $formActionUri = null)
{
    //return get_view()->partial(
        //'timelines/query-form.php',
        //array(
            //'isPartial'      => true,
            //'formAttributes' => $props,
            //'formActionUri'  => $formActionUri
        //)
    //);
    return get_view()->partial(
        'items/search-form.php',
        array(
            'formAttributes' => $props,
            'formActionUri'  => $formActionUri
        )
    );
}

/**
 * Generates a form select populated by all elements and element sets.
 * 
 * @param string The NeatlineTime option name. 
 * @return string HTML.
 */
function neatlinetime_option_select($name = null) {

  if ($name) {
    return get_view()->formSelect(
                    $name,
                    neatlinetime_get_option($name),
                    array(),
                    get_table_options('Element', null, array(
                        'record_types' => array('Item', 'All'),
                        'sort' => 'alphaBySet')
                    )
                );

  }

    return false;

}

/**
 * Gets the value for an option set in the neatlinetime option array.
 *
 * @param string The NeatlineTime option name. 
 * @return string
 */
function neatlinetime_get_option($name = null) {

  if ($name) {
    $options = get_option('neatlinetime');
    $options = unserialize($options);
    return $options[$name];
  }

  return false;

}

/**
 * Returns the value of an element set in the NeatlineTime config options.
 *
 * @param string The NeatlineTime option name.
 * @param array An array of options.
 * @param Item
 * @return string|array|null
 */
function neatlinetime_get_item_text($optionName, $options = array(), $item = null) {

    $element = get_db()->getTable('Element')->find(neatlinetime_get_option($optionName));

    return metadata($item, array($element->getElementSet()->name, $element->name), $options);

}
