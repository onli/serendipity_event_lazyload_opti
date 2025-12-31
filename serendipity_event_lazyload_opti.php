<?php

if (IN_serendipity !== true) {
    die ("Don't hack!");
}

@serendipity_plugin_api::load_language(dirname(__FILE__));

class serendipity_event_lazyload_opti extends serendipity_event
{
    var $title = 'lazyload';
    var $markup_elements;
    var $runcount = 0;

    function introspect(&$propbag)
    {
        global $serendipity;

        $propbag->add('name',          'lazyload optimizer');
        $propbag->add('description',   'Removes lazyload tags from early images, for a better site performance');
        $propbag->add('stackable',     false);
        $propbag->add('author',        'onli');
        $propbag->add('version',       '0.1');
        $propbag->add('requirements',  array(
            'serendipity' => '2.0',
            'php'         => '8.0'
        ));
        $propbag->add('cachable_events', array('frontend_display' => true));

        $propbag->add('event_hooks',     array('frontend_display'  => true));
        $propbag->add('groups', array('MARKUP'));

        $this->markup_elements = array(
            array(
              'name'     => 'ENTRY_BODY',
              'element'  => 'body',
            ),
            array(
              'name'     => 'EXTENDED_BODY',
              'element'  => 'extended',
            )
        );


        $conf_array = array();
        foreach($this->markup_elements as $element) {
            $conf_array[] = $element['name'];
        }
        $propbag->add('configuration', $conf_array);
    }
    
    function install()
    {
        serendipity_plugin_api::hook_event('backend_cache_entries', $this->title);
    }

    function uninstall(&$propbag)
    {
        serendipity_plugin_api::hook_event('backend_cache_purge', $this->title);
        serendipity_plugin_api::hook_event('backend_cache_entries', $this->title);
    }

    function generate_content(&$title)
    {
        $title = $this->title;
    }

    function introspect_config_item($name, &$propbag)
    {
        switch($name) {

            default:
                $propbag->add('type',        'boolean');
                $propbag->add('name',        constant($name));
                $propbag->add('description', sprintf(APPLY_MARKUP_TO, constant($name)));
                $propbag->add('default',     'true');
                break;
        }
        return true;
    }

    function event_hook($event, &$bag, &$eventData, $addData = null) {
        global $serendipity;
        $hooks = &$bag->get('event_hooks');

        if (isset($hooks[$event])) {

            switch($event) {

                case 'frontend_display':

                    if ($serendipity['view'] != 'entry') {
                        // We are not a single entry. Then we run only once, since it is highly
                        // unlikely for a later article to have an image above the fold.
                        if ($this->runcount > 0) {
                            break;
                        }
                        $this->runcount += 1;
                    }
                    
                    // check single entry for temporary disabled markups
                    if ( !($eventData['properties']['ep_disable_markup_' . $this->instance] ?? null) &&
                         @!in_array($this->instance, ($serendipity['POST']['properties']['disable_markups'] ?? []))) {
                        // yes, this markup shall be applied
                        $serendipity['lazyload_opti']['entry_disabled_markup'] = false;
                    } else {
                        // no, do not apply markup
                        $serendipity['lazyload_opti']['entry_disabled_markup'] = true;
                    }

                    foreach ($this->markup_elements as $temp) {
                        if (serendipity_db_bool($this->get_config($temp['name'], true)) && isset($eventData[$temp['element']]) &&
                            !($eventData['properties']['ep_disable_markup_' . $this->instance] ?? null) &&
                            @!in_array($this->instance, ($serendipity['POST']['properties']['disable_markups'] ?? []))) {

                            $text = $eventData[$temp['element']];
                            # Check if there is an image with lazyload in the first X characters
                            $foldarea = substr($text, 0, 2000);
                            if (str_contains($foldarea, 'loading="lazy"')) {
                                # If yes, set loading of the first image from lazy to eager
                                preg_match_all('@<!-- s9ymdb:(?<id>\d+) -->@', $text, $matches);

                                if (count($matches['id']) > 0) {
                                    $imgId = $matches['id'][0];                                    
                                    $eventData[$temp['element']] = preg_replace('@(<!-- s9ymdb:\d+ --><img[^>]+loading=["\'])lazy@', '${1}eager', $text, 1);
                                }
                            }
                        }
                    }
                    break;

                default:
                    return false;

            }
            return true;
        } else {
            return false;
        }
    }

}

/* vim: set sts=4 ts=4 expandtab : */
?>