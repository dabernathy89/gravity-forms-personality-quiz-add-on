<?php
/*
Plugin Name: Gravity Forms Personality Quiz Add-On
Description: Create personality quizzes with Gravity Forms.
Version: 0.1
Author: Daniel Abernathy
Author URI: http://www.danielabernathy.com
License: GPLv3

    Copyright 2014 Daniel Abernathy (email : dabernathy89@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (class_exists("GFForms")) {
    GFForms::include_addon_framework();

    class GravityFormsPersonalityQuizAddon extends GFAddOn {

        protected $_version = "0.1";
        protected $_min_gravityforms_version = "1.8.7";
        protected $_slug = "gf-personality-quiz";
        protected $_path = "gravity-forms-personality-quiz-addon/class-gravity-forms-personality-quiz-addon.php";
        protected $_full_path = __FILE__;
        protected $_title = "Gravity Forms Personality Quiz Add-On";
        protected $_short_title = "Personality Quiz Add-On";

        public function init(){
            parent::init();

            add_action("gform_field_standard_settings", array($this, "add_enable_checkbox"), 10, 2);
            add_action("gform_editor_js", array($this, "editor_script"));
            add_filter("gform_entry_meta", array($this, "quiz_result_meta"), 10, 2);
            add_filter("gform_enqueue_scripts", array($this, "enqueue_quiz_styles"), 10, 2);
            add_filter("gform_field_content", array($this, "replace_field_labels"), 10, 5);
            add_action("gform_field_css_class", array($this, "add_class_to_quiz_questions"), 10, 3);
            add_action("gform_admin_pre_render", array($this, "add_merge_tags"));
            add_filter("gform_replace_merge_tags", array($this, "replace_merge_tags"), 10, 7);
        }

        public function quiz_result_meta($entry_meta, $form_id) {
            $form = GFAPI::get_form($form_id);
            $form_settings = $this->get_form_settings($form);

            if (!empty($form_settings)) {
                if ($form_settings['quiz_type'] === "Numeric") {
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

            if (empty($form_settings) || !in_array($form_settings['quiz_type'], array('Numeric', 'Multiple Choice'))) {
                return;
            }

            if ($form_settings['quiz_type'] === "Numeric") {
                $quiz_result = $this->score_quiz_numeric($form_settings, $form['fields'], $lead);
            } else {
                $quiz_result = $this->score_quiz_multiple_choice($form_settings, $form['fields'], $lead);
            }

            return $quiz_result;
        }

        protected function score_quiz_multiple_choice($form_settings, $fields, $lead) {
            $result = "";
            $quiz_questions = array();

            foreach ($fields as $field) {
                if (array_key_exists('enablePersonalityQuiz', $field) && $field['enablePersonalityQuiz']) {
                    if ($field['type'] === "checkbox") {
                        foreach ($field['inputs'] as $input) {
                            if (array_key_exists($input['id'], $lead) && !empty($lead[$input['id']])) {
                                $quiz_questions[] = $lead[$input['id']];
                            }
                        }
                    } else if ($field['type'] === "radio" ) {
                        if (array_key_exists($field['id'], $lead) && !empty($lead[$field['id']])) {
                            $quiz_questions[] = $lead[$field['id']];
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

        protected function score_quiz_numeric($form_settings, $fields, $lead) {
            $score = 0;
            foreach ($fields as $field) {
                if (array_key_exists('enablePersonalityQuiz', $field) && $field['enablePersonalityQuiz']) {
                    if ($field['type'] === "checkbox") {
                        foreach ($field['inputs'] as $input) {
                            if (array_key_exists($input['id'], $lead)) {
                                $score += intval($lead[$input['id']]);
                            }
                        }
                    } else if ($field['type'] === "radio" ) {
                        if (array_key_exists($field['id'], $lead)) {
                            $score += intval($lead[$field['id']]);
                        }
                    }
                }
            }
            return $score;
        }

        public function add_enable_checkbox($position, $form_id) {
            $form = GFAPI::get_form($form_id);
            $form_settings = $this->get_form_settings($form);
            if (!empty($form_settings) && in_array($form_settings['quiz_type'], array('Numeric', 'Multiple Choice'))) {
                if($position == 25){
                    ?>
                    <li class="enable_personality_quiz field_setting">
                        <input type="checkbox" id="field_enable_personality_quiz" onclick="SetFieldProperty('enablePersonalityQuiz', this.checked);" />
                        <label for="field_enable_personality_quiz" class="inline">
                            <?php _e("Use for Personality Quiz Score", "gravityforms"); ?>
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
            if (!empty($form_settings) && in_array($form_settings['quiz_type'], array('Numeric', 'Multiple Choice'))) {
                wp_enqueue_media();
                wp_enqueue_style( 'gf-personality-quiz-styles', plugins_url( 'assets/admin_quiz_styles.css' , __FILE__ ), array(), $this->_version );
                ?>
                <script type='text/javascript' src="<?php echo plugins_url( 'assets/media_upload.js' , __FILE__ ); ?>" /></script>
                <script type='text/javascript'>
                    //adding setting to fields of type "text"
                    fieldSettings["radio"] += ", .enable_personality_quiz, .personality_quiz_replace_label";
                    fieldSettings["checkbox"] += ", .enable_personality_quiz, .personality_quiz_replace_label";

                    //binding to the load field settings event to initialize the checkbox
                    jQuery(document).bind("gform_load_field_settings", function(event, field, form){
                        jQuery("#field_enable_personality_quiz").attr("checked", field["enablePersonalityQuiz"] == true);
                        jQuery("#field_personality_quiz_replace_label").val(field["personalityQuizReplaceLabel"]);
                        jQuery("#field_personality_quiz_replace_label").on('change blur keyup', function(event) {
                            field["personalityQuizReplaceLabel"] = jQuery(this).val();
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
            if (array_key_exists('enablePersonalityQuiz', $field) && $field['enablePersonalityQuiz'] && array_key_exists('personalityQuizReplaceLabel', $field) && $field['personalityQuizReplaceLabel']) {
                $form = GFAPI::get_form($form_id);
                $form_settings = $this->get_form_settings($form);
                if (!empty($form_settings) && in_array($form_settings['quiz_type'], array('Numeric', 'Multiple Choice'))) {
                    $content = str_replace($field['label'], $field['personalityQuizReplaceLabel'], $content);
                    $content = str_replace('gfield_label', 'gfield_label gfield_image_label', $content);
                }
            }

            return $content;
        }

        public function add_class_to_quiz_questions($classes, $field, $form) {
            if (array_key_exists('enablePersonalityQuiz', $field) && $field['enablePersonalityQuiz']) {
                $form_settings = $this->get_form_settings($form);
                if (!empty($form_settings) && in_array($form_settings['quiz_type'], array('Numeric', 'Multiple Choice'))) {
                    $classes .= " pq-question-field";
                }
            }
            return $classes;
        }

        public function add_merge_tags($form) {
            ?>

            <script type="text/javascript">
                gform.addFilter("gform_merge_tags", "add_merge_tags");
                function add_merge_tags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option){
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result}', label: 'Personality Quiz Result' });
                    mergeTags["custom"].tags.push({ tag: '{personality_quiz_result_percent}', label: 'Personality Quiz Result Percent' });

                    return mergeTags;
                }
            </script>

            <?php
            return $form;
        }

        public function replace_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {

            if(strpos($text, '{personality_quiz_result}') !== false) {
                $quiz_result = gform_get_meta($entry['id'], 'personality_quiz_result');
                $text = str_replace('{personality_quiz_result}', $quiz_result, $text);
            }

            if(strpos($text, '{personality_quiz_result_percent}') !== false) {
                $quiz_result = (int)gform_get_meta($entry['id'], 'personality_quiz_result');
                $quiz_total = $this->get_quiz_total($form);
                $quiz_result = round(($quiz_result / $quiz_total) * 100);
                $text = str_replace('{personality_quiz_result_percent}', $quiz_result, $text);
            }

            return $text;
        }

        protected function get_quiz_total($form) {
            $total = 0;

            foreach ($form['fields'] as $field) {
                if (array_key_exists('enablePersonalityQuiz', $field) && $field['enablePersonalityQuiz']) {
                    foreach ($field['choices'] as $choice) {
                        $total += (int)$choice['value'];
                    }
                }
            }

            return $total;
        }

    }

    new GravityFormsPersonalityQuizAddon();
}