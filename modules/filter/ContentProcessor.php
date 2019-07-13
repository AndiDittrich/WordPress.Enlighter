<?php

namespace Enlighter\filter;

class ContentProcessor{

    // flag to indicate if shortcodes have been applied
    public $_hasContent = false;
    
    // list of active filters
    private $_filters = array();

    // stores the plugin config
    private $_config;

    // GFM Filter instance
    private $_gfmFilter;

    // short code filter instance
    private $_shortcodeFilter;

    // compatibility mode filter
    private $_compatFilter;

    // internal fragment buffer to store code
    private $_fragmentBuffer;

    public function __construct($settingsManager, $languageManager, $themeManager){
        // store local plugin config
        $this->_config = $settingsManager->getOptions();

        // initialize new fragment buffer
        $this->_fragmentBuffer = new FragmentBuffer();

        
        
        //$this->_gfmFilter = new GfmFilter($settingsUtil);
        //$this->_compatFilter = new CompatibilityModeFilter($settingsUtil);

        // array of sections which will be filtered
        
        $compatSections = array();

        // ------------------------------------
        // ---------- SHORTCODE ---------------

        if ($this->_config['shortcode-type-generic'] || $this->_config['shortcode-type-language']){
            // use modern shortcode handler ?
            if ($this->_config['shortcode-mode'] === 'modern'){
                // setup filters
                $this->_shortcodeFilter = new ShortcodeFilter($this->_config, $languageManager, $this->_fragmentBuffer);

                // list of sections which will be filtered
                $shortcodeSections = array();

                // setup sections to filter based on plugin configuration
                if ($this->_config['shortcode-filter-content']){
                    $shortcodeSections[] = 'the_content';
                }
                if ($this->_config['shortcode-filter-excerpt']){
                    $shortcodeSections[] = 'get_the_excerpt';
                }
                if ($this->_config['shortcode-filter-comment']){
                    $shortcodeSections[] = 'get_comment_text';
                }
                if ($this->_config['shortcode-filter-commentexcerpt']){
                    $shortcodeSections[] = 'get_comment_excerpt';
                }
                if ($this->_config['shortcode-filter-widget']){
                    $shortcodeSections[] = 'widget_text';
                }

                // apply filter hook to allow users to modify the list/add additional filters
                $shortcodeSections = array_unique(apply_filters('enlighter_shortcode_filters', $shortcodeSections));

                // register filter targets
                foreach ($shortcodeSections as $section){
                    $this->registerFilterTarget($this->_shortcodeFilter, $section);
                }
            }
            
            // use wordpress legacy shortcodes ?
            if ($this->_config['shortcode-mode'] === 'legacy'){
                // setup legacy shortcode handler
                $this->_shortcodeFilter = new LegacyShortcodeHandler($this->_config, $languageManager);
            }
        }

        // ------------------------------------
        // ---------- GFM ---------------------

        // gfm enabled ?
        if ($this->_config['gfm-enabled']){

            // setup filter
            $this->_gfmFilter = new GfmFilter($this->_config, $this->_fragmentBuffer);

            // list of sections which will be filtered
            $gfmSections = array();

            // use gfm in the default sections ?
            if ($this->_config['gfm-filter-content']){
                $gfmSections[] = 'the_content';
            }
            if ($this->_config['gfm-filter-excerpt']){
                $gfmSections[] = 'get_the_excerpt';
            }
            if ($this->_config['gfm-filter-comment']){
                $gfmSections[] = 'get_comment_text';
            }
            if ($this->_config['gfm-filter-commentexcerpt']){
                $gfmSections[] = 'get_comment_excerpt';
            }
            if ($this->_config['gfm-filter-widget']){
                $gfmSections[] = 'widget_text';
            }
        }

        // apply filter hook to allow users to modify the list/add additional filters
        $gfmSections = array_unique(apply_filters('enlighter_gfm_filters', $gfmSections));

        // register filter targets
        foreach ($gfmSections as $section){
            $this->registerFilterTarget($this->_gfmFilter, $section);
        }


        // ------------------------------------
        // ---------- COMPATIBILITY MODE  -----
        /*
        // use gfm in the default sections ?
        if ($this->_config['compatFilterContent']){
            $compatSections[] = 'the_content';
        }
        if ($this->_config['compatFilterExcerpt']){
            $compatSections[] = 'get_the_excerpt';
        }
        if ($this->_config['compatFilterComments']){
            $compatSections[] = 'get_comment_text';
        }
        if ($this->_config['compatFilterCommentsExcerpt']){
            $compatSections[] = 'get_comment_excerpt';
        }
        if ($this->_config['compatFilterWidgetText']){
            $compatSections[] = 'widget_text';
        }

        // apply filter hook to allow users to modify the list/add additional filters
        $compatSections = array_unique(apply_filters('enlighter_compat_filters', $compatSections));

        // register filter targets
        foreach ($compatSections as $section){
            $this->registerCompatibilityFilterTarget($section);
        }*/

        // ------------------------------------
        // ---------- RESTORE  ----------------
        
        // add content restore filters
        foreach ($this->_filters as $filter){
            $this->_fragmentBuffer->registerRestoreFilter($filter);
        }

        // ------------------------------------
        // ---------- DRI DETECTOR  -----------

        // dynamic resource incovation active ?
        if ($this->_config['dynamic-resource-invocation']){
            // EnlighterJS Code detection
            add_filter('the_content', function($content){
                // block overrides caused by multiple calls to the_content filter
                // is the filter called regular to display the content ? 
                if (!$this->_hasContent && in_the_loop() && is_main_query()){
                    // contains enlighterjs codeblocks ?
                    $this->_hasContent = (strpos($content, 'EnlighterJSRAW') !== false);
                }
                return $content;
            }, 9999, 1);
        }
    }

    // add content filter (strip + restore) to the given content section
    public function registerFilterTarget($filter, $name){
        // add content filter to first position - replaces all enlighter shortcodes with placeholders
        add_filter($name, array($filter, 'stripCodeFragments'), 0, 1);

        // filter already added to the list of restore filters ?
        if (!isset($this->_filters[$name])){
            $this->_filters[] = $name;
        }
    }

    // check if shortcodes, gfm or raw enlighterjs tags have been applied
    public function hasContent(){
        return ($this->_hasContent || ($this->_shortcodeFilter !== null && $this->_shortcodeFilter->hasContent()));
    }
}