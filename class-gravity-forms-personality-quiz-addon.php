<?php

if ( class_exists('GFForms') ) :

GFForms::include_addon_framework();

class GravityFormsPersonalityQuizAddon extends GFAddOn {

    protected $_version = "1.1.0";
    protected $_min_gravityforms_version = "2.0";
    protected $_slug = "gf-personality-quiz";
    protected $_path = "gravity-forms-personality-quiz-addon/class-gravity-forms-personality-quiz-addon.php";
    protected $_full_path = __FILE__;
    protected $_title = "Gravity Forms Personality Quiz Add-On";
    protected $_short_title = "Personality Quiz Add-On";
    private static $_instance;

    protected $quiz_types = array('Numeric', 'Numeric (multiple categories)', 'Multiple Choice');

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GravityFormsPersonalityQuizAddon();
        }
        return self::$_instance;
    }

    public function init(){
        parent::init();

        add_action("gform_field_standard_settings", array($this, "add_enable_checkbox"), 10, 2);
        add_action("gform_editor_js", array($this, "editor_script"));
        add_filter("gform_enqueue_scripts", array($this, "enqueue_quiz_styles"), 10, 2);
        add_filter("gform_field_content", array($this, "replace_field_labels"), 10, 5);
        add_action("gform_field_css_class", array($this, "add_class_to_quiz_questions"), 10, 3);
        add_action("gform_admin_pre_render", array($this, "add_merge_tags"));
        add_filter("gform_replace_merge_tags", array($this, "replace_merge_tags"), 10, 7);
        add_filter("gform_entry_post_save", array($this, "replace_merge_tags_in_form_fields"), 10, 2);
    }

    public function replace_merge_tags_in_form_fields( $entry, $form ) {
        $quiz_result = gform_get_meta($entry['id'], 'personality_quiz_result');

        foreach ($entry as $key => $value) {
            if( strpos($value, '{personality_quiz_result') !== false
                || strpos($value, '{personality_quiz_result_percent') !== false
                || strpos($value, '{personality_quiz_result_average') !== false){
                    $entry[$key] = GFCommon::replace_variables( $value, $form, $entry );
                    GFAPI::update_entry_field( $entry['id'], $key, $entry[$key] );
            }
        }
        return $entry;
    }

    public function get_entry_meta($entry_meta, $form_id) {
        $form = GFAPI::get_form($form_id);
        $form_settings = $this->get_form_settings($form);

        if (!empty($form_settings)) {
            if ($form_settings['quiz_type'] === "Numeric (multiple categories)") {
                foreach ($this->get_numeric_quiz_categories($form) as $category) {
                    $entry_meta['personality_quiz_result[' . $category . ']'] = array(
                        'label' => 'Quiz Result (' . $category . ')',
                        'is_numeric' => true,
                        'update_entry_meta_callback' => array($this,'update_quiz_result'),
                        'is_default_column' => true,
                        'filter' => true
                    );
                }
            } else if ($form_settings['quiz_type'] === "Numeric") {
                $entry_meta['personality_quiz_result'] = array(
                    'label' => 'Quiz Result',
                    'is_numeric' => true,
                    'update_entry_meta_callback' => array($this,'update_quiz_result'),
                    'is_default_column' => true,
                    'filter' => true
                );
            } else if ($form_settings['quiz_type'] === "Multiple Choice") {
                $entry_meta['personality_quiz_result'] = array(
                    'label' => 'Quiz Result',
                    'is_numeric' => false,
                    'update_entry_meta_callback' => array($this,'update_quiz_result'),
                    'is_default_column' => true,
                    'filter' => true
                );
            }
        }

        return $entry_meta;
    }

    public function update_quiz_result($key, $lead, $form) {
        $form_settings = $this->get_form_settings($form);
        $key = str_replace('personality_quiz_result[', '', $key);
        $key = str_replace(']', '', $key);
        $key = str_replace('personality_quiz_result', '', $key);

        if (empty($form_settings) || !in_array($form_settings['quiz_type'], $this->quiz_types)) {
            return;
        }

        if ($form_settings['quiz_type'] === "Numeric" || $form_settings['quiz_type'] === "Numeric (multiple categories)") {
            $quiz_result = $this->score_quiz_numeric($form_settings, $form['fields'], $lead, $key);
        } else {
            $quiz_result = $this->score_quiz_multiple_choice($form_settings, $form['fields'], $lead);
        }

        return $quiz_result;
    }

    protected function score_quiz_multiple_choice($form_settings, $fields, $lead) {
        $result = "";
        $quiz_questions = array();

        foreach ($fields as $field) {
            if (property_exists($field, 'enablePersonalityQuiz') && $field->enablePersonalityQuiz) {
                if ($field->type === "checkbox") {
                    foreach ($field->inputs as $input) {
                        if (array_key_exists($input['id'], $lead) && !empty($lead[$input['id']])) {
                            $quiz_questions[] = $lead[$input['id']];
                        }
                    }
                } else if ($field->type === "radio" ) {
                    if (array_key_exists($field->id, $lead) && !empty($lead[$field->id])) {
                        $quiz_questions[] = $lead[$field->id];
                    }
                }
            }
        }

        // create an array which counts the number of occurrences of each answer
        $scores = array_count_values($quiz_questions);
        // return an array of just the winner - or winners if it's a tie
        $winners = array_keys($scores, max($scores));
        // return the winner, or if it was a tie, a random winner
        shuffle($winners);
        return $winners[0];
    }

    protected function score_quiz_numeric($form_settings, $fields, $lead, $key = '') {
        $score = 0;
        foreach ($fields as $field) {
            if (property_exists($field, 'enablePersonalityQuiz') && $field->enablePersonalityQuiz) {
                if ($field->type === "checkbox") {
                    foreach ($field->inputs as $input) {
                        if (array_key_exists($input['id'], $lead)) {
                            $score += $this->extract_field_score($lead[$input['id']], $key);
                        }
                    }
                } else if ($field->type === "radio" ) {
                    if (array_key_exists($field->id, $lead)) {
                        $score += $this->extract_field_score($lead[$field->id], $key);
                    }
                }
            }
        }
        return $score;
    }

    protected function extract_field_score($value, $key = '') {
        // The score can be defined by ending the value with curly braces
        // and a number between them - e.g. {3}
        // Multiple possible; e.g. a{2},b{1}
        $values = array_map('trim', explode(',', $value));
        foreach ($values as $single_value) {
            if (preg_match('/([^\s,]*?){([\d]+)}/', $single_value, $matches)) {
                // If there's no key specified, just return the first number inside curly braces
                // If there is a key specified, return the one that matches for that key only
                if (!$key || ($key && $matches[1] === $key)) {
                    return (int)$matches[2];
                }
            }
        }

        return 0;
    }

    public function add_enable_checkbox($position, $form_id) {
        $form = GFAPI::get_form($form_id);
        $form_settings = $this->get_form_settings($form);
        if (!empty($form_settings) && in_array($form_settings['quiz_type'], $this->quiz_types)) {
            if($position == 25){
                ?>
                <li class="enable_personality_quiz field_setting">
                    <input type="checkbox" id="field_enable_personality_quiz" onclick="SetFieldProperty('enablePersonalityQuiz', this.checked);" />
                    <label for="field_enable_personality_quiz" class="inline">
                        <?php _e("Use for Personality Quiz Score", "gravityforms"); ?>
                    </label>
                </li>

                <li class="personality_quiz_shuffle field_setting">
                    <input type="checkbox" id="field_personality_quiz_shuffle" onclick="SetFieldProperty('personalityQuizShuffle', this.checked);" />
                    <label for="field_personality_quiz_shuffle" class="inline">
                        <?php _e("Shuffle Answers", "gravityforms"); ?>
                    </label>
                </li>

                <li class="personality_quiz_replace_label field_setting">
                    <label for="field_personality_quiz_replace_label">
                        <?php _e("Personality Quiz Image Label", "gravityforms"); ?>
                    </label>
                    <input type="text" id="field_personality_quiz_replace_label" />
                    <button class="button pq-label-media-upload">Upload Image</button>
                </li>

                <?php
            }
        }
    }

    public function editor_script(){
        $form_id = $_GET['id'];
        $form = GFAPI::get_form($form_id);
        $form_settings = $this->get_form_settings($form);
        if (!empty($form_settings) && in_array($form_settings['quiz_type'], $this->quiz_types)) {
            wp_enqueue_media();
            wp_enqueue_style( 'gf-personality-quiz-styles', plugins_url( 'assets/admin_quiz_styles.css' , __FILE__ ), array(), $this->_version );
            ?>
            <script type='text/javascript' src="<?php echo plugins_url( 'assets/media_upload.js' , __FILE__ ); ?>" /></script>
            <script type='text/javascript'>
                //adding setting to radio and checkbox fields
                fieldSettings["radio"] += ", .enable_personality_quiz, .personality_quiz_replace_label, .personality_quiz_shuffle";
                fieldSettings["checkbox"] += ", .enable_personality_quiz, .personality_quiz_replace_label, .personality_quiz_shuffle";

                jQuery(document).bind("gform_load_field_settings", function(event, field, form){
                    jQuery("#field_enable_personality_quiz").attr("checked", field["enablePersonalityQuiz"] == true);
                    jQuery("#field_personality_quiz_shuffle").attr("checked", field["personalityQuizShuffle"] == true);
                    jQuery("#field_personality_quiz_replace_label").val(field["personalityQuizReplaceLabel"]);
                    jQuery("#field_personality_quiz_replace_label").on('change blur keyup', function(event) {
                        SetFieldProperty('personalityQuizReplaceLabel',jQuery(this).val());
                    });
                });

                jQuery(document).bind('gform_load_field_choices', function(event, field){
                    jQuery('#field_choices .field-choice-text').before('<div class="dashicons dashicons-format-image pq-choice-media-upload"></div>');
                });
            </script>
            <?php
        }
    }

    public function form_settings_fields($form) {
        return array(
            array(
                "title"  => "Personality Quiz Settings",
                "fields" => array(
                    array(
                        "label"   => "Quiz Type",
                        "type"    => "radio",
                        "name"    => "quiz_type",
                        "tooltip" => "<strong>Numeric</strong><br />(checkboxes with point values)<br /> e.g., How ___ Are You?<hr /><strong>Multiple Choice</strong><br />(radio buttons assigned to predefined outcomes)<br /> e.g., What Kind of ___ Are You?",
                        "choices" => array(
                            array(
                                "label" => "None (disabled)"
                            ),
                            array(
                                "label" => "Numeric"
                            ),
                            array(
                                "label" => "Numeric (multiple categories)"
                            ),
                            array(
                                "label" => "Multiple Choice"
                            )
                        )
                    ),
                    array(
                        "label"   => "Include Quiz CSS",
                        "type"    => "checkbox",
                        "name"    => "include_css",
                        "choices" => array(
                            array(
                                "label" => "",
                                "name"  => "include_css"
                            )
                        )
                    )
                )
            )
        );
    }

    public function enqueue_quiz_styles($form, $is_ajax) {
        $form_settings = $this->get_form_settings($form);
        if (!empty($form_settings) && $form_settings['quiz_type'] !== "None (disabled)" && $form_settings['include_css']) {
            wp_enqueue_style( 'gf-personality-quiz-styles', plugins_url( 'assets/quiz_styles.css' , __FILE__ ), array(), $this->_version );
            wp_enqueue_script( 'gf-personality-quiz-js', plugins_url( 'assets/quiz.js' , __FILE__ ), array('jquery'), $this->_version, true );
        }
        return $form;
    }

    public function replace_field_labels($content, $field, $value, $lead_id, $form_id) {
        if (property_exists($field,'enablePersonalityQuiz') && $field->enablePersonalityQuiz && property_exists($field,'personalityQuizReplaceLabel') && $field->personalityQuizReplaceLabel) {
            $form = GFAPI::get_form($form_id);
            $form_settings = $this->get_form_settings($form);
            if (!empty($form_settings) && in_array($form_settings['quiz_type'], $this->quiz_types)) {
                $content = str_replace($field->label, $field->personalityQuizReplaceLabel, $content);
                $content = str_replace('gfield_label', 'gfield_label gfield_image_label', $content);
            }
        }

        return $content;
    }

    public function add_class_to_quiz_questions($classes, $field, $form) {
        if (property_exists($field,'enablePersonalityQuiz') && $field->enablePersonalityQuiz) {
            $form_settings = $this->get_form_settings($form);
            if (!empty($form_settings) && in_array($form_settings['quiz_type'], $this->quiz_types)) {
                $classes .= " pq-question-field";
                if ( property_exists($field,'personalityQuizShuffle') && $field->personalityQuizShuffle) {
                    $classes .= " pq-question-shuffle";
                }
            }
        }
        return $classes;
    }

    public function add_merge_tags($form) {
        $form_settings = $this->get_form_settings($form);
        ?>

        <script type="text/javascript">
            gform.addFilter("gform_merge_tags", "add_personality_quiz_merge_tags");
            function add_personality_quiz_merge_tags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option){
                <?php if (isset($form_settings['quiz_type']) && $form_settings['quiz_type'] === "Numeric (multiple categories)") : ?>
                    <?php foreach ($this->get_numeric_quiz_categories($form) as $category) : ?>
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result[<?php echo $category; ?>]}', label: 'Personality Quiz Result (<?php echo $category; ?>)' });
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result_percent[<?php echo $category; ?>]}', label: 'Personality Quiz Result Percent (<?php echo $category; ?>)' });
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result_average[<?php echo $category; ?>]}', label: 'Personality Quiz Result Average (<?php echo $category; ?>)' });
                    <?php endforeach; ?>
                <?php else: ?>
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result}', label: 'Personality Quiz Result' });
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result_percent}', label: 'Personality Quiz Result Percent' });
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result_average}', label: 'Personality Quiz Result Average' });
                <?php endif; ?>

                return mergeTags;
            }
        </script>

        <?php
        return $form;
    }

    public function replace_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
        if ( ! $entry ) {
            return $text;
        }

        $matches = [];
        if (preg_match_all('/{(personality_quiz_result\w*)(?>\[(\w*)\])?}/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = empty($match[2]) ? '' : '[' . $match[2] . ']';
                $quiz_result = gform_get_meta($entry['id'], 'personality_quiz_result' . $key);
                $text = str_replace("{personality_quiz_result{$key}}", $quiz_result, $text);

                if ($match[1] === 'personality_quiz_result_percent') {
                    $quiz_total = $this->get_quiz_total($form, $match[2]);
                    $quiz_result = round(($quiz_result / $quiz_total) * 100);
                    $text = str_replace("{personality_quiz_result_percent{$key}}", $quiz_result, $text);
                }

                if ($match[1] === 'personality_quiz_result_average') {
                    $num_questions = $this->get_num_questions($form, $match[2]);
                    $quiz_result = round($quiz_result / $num_questions);
                    $text = str_replace("{personality_quiz_result_average{$key}}", $quiz_result, $text);
                }
            }
        }

        return $text;
    }

    protected function get_quiz_total($form, $key = '') {
        $total = 0;

        foreach ($form['fields'] as $field) {
            if (property_exists($field,'enablePersonalityQuiz') && $field->enablePersonalityQuiz) {
                foreach ($field->choices as $choice) {
                    $total += (int)$this->extract_field_score($choice['value'], $key);
                }
            }
        }

        return $total;
    }

    protected function get_num_questions($form, $key = '') {
        $num_questions = 0;

        foreach ($form['fields'] as $field) {
            if (property_exists($field,'enablePersonalityQuiz') && $field->enablePersonalityQuiz) {
                if ($key) {
                    foreach ($field->choices as $choice) {
                        if (strpos($choice['value'], $key . '{') !== false) {
                            $num_questions += 1;
                            break;
                        }
                    }
                } else {
                    $num_questions += 1;
                }
            }
        }

        return $num_questions;
    }

    protected function get_numeric_quiz_categories($form) {
        $categories = [];

        foreach ($form['fields'] as $field) {
            if (!property_exists($field,'enablePersonalityQuiz') || !$field->enablePersonalityQuiz) {
                continue;
            }
            foreach ($field->choices as $choice) {
                $potential_categories = explode(',', $choice['value']);
                foreach ($potential_categories as $potential_category) {
                    if (preg_match('/([^\s,]*?){([\d]+)}/', $potential_category, $matches)) {
                        $categories[] = trim($matches[1]);
                    }
                }
            }
        }

        return array_unique($categories);
    }
}

endif;
