<?php

    /**
    *  RainFramework
    *  -------------
    *    Realized by Federico Ulfo & maintained by the Rain Team
    *    Distributed under MIT license http://www.opensource.org/licenses/mit-license.php
    */
    class Rain_Form {

        // form variable
        private $name, $action, $method, $target;
        // html generated
        private $html;
        // hidden inputs
        private $hidden;
        // flag
        private $upload_file;
        // flag
        private $tiny_mce = false, $validation = false;
        // form counter
        private static $form_counter = 0;
        // form dir
        private static $form_dir = LIBRARY_DIR;
        // layout vars
                private $layout, $layout_name;

        function __construct($action = null, $method = "get", $name = null, $target = null, $layout = "default") {

            // load language of the form
            load_lang("form");

            // add style
            Layout::addStyle("style.css", self::$form_dir . "Form/tpl/$layout/", SYSTEM_URL . self::$form_dir . "Form/tpl/$layout/");

            // add javascript
            Layout::addScript("jquery.min.js", JQUERY_DIR, JQUERY_URL);

            require_once self::$form_dir . "Form/tpl/$layout/form.php"; // include theme

            $this->action = $action;
            $this->name = $name ? $name : "form__" . self::$form_counter;
            $this->method = $method;
            $this->target = $target;
            $this->layout = $GLOBALS['form_layout'][$layout];
            $this->layout_name = $layout;
            $this->counter = 0;

            self::$form_counter++;
        }

        function open_table($name, $subtitle = null, $class = "table") {
            $name = get_msg($name);
            $this->html .= str_replace(array('{$title}', '{$subtitle}', '{$class}'), array($name, $subtitle, $class), $this->layout['openTable']);
        }

        function close_table() {
            $this->html .= $this->layout['closeTable'];
        }

        function add_item($item, $name, $title = null, $description = null, $value = null, $validation = null, $param = null, $layout = null) {

            $this->counter++;

            $this->add_validation($name, $validation);

            $title = get_msg($title);
            $description = get_msg($description);

            if (!$layout)
                $layout = in_array($item, array('textarea', 'word')) ? "row" : "layout";

            if (method_exists($this, "_" . $item)) {
                $method = "_" . $item;
                return $this->html .= $this->_layout_replace($title, $description, $this->$method($name, $value, $param), $layout);
            } else {

                $function = "form_" . $item;
                $plugins_dir = __DIR__ . "/plugins/";
                $plugin = $plugins_dir . "$item.php";

                if (function_exists($function))
                    ;
                elseif (!file_exists($plugin))
                    return $this->html .= $this->_layout_replace($title, $description, "plugins not found", $layout);
                else
                    include_once $plugin;

                return $this->html .= $this->_layout_replace($title, $description, $function($name, $value, $param, $validation, $this), $layout);
            }
        }
        
        function add_space(){
            $this->html .= '<div class="space">&nbsp;</div>';
        }

        function add_hidden($name, $value) {
            $this->hidden .= "<input type=\"hidden\" name=\"$name\" value=\"$value\"/>";
        }

        // wife comment :) nov.12.09
        // hello php...i found youuu. now i work..see? it works now...im sure it does...working...working...working. working hard. im done.  
        function add_html($html) {
            $this->counter++;
            $this->html .= str_replace(array('{$html}', '{$counter}'), array($html, $this->counter), $this->layout['html']);
        }

        //button can be a list or a string
        function add_button($button = null) {
            $this->counter++;
            if (null === $button)
                $button = get_msg("submit");

            if (is_array($button)) {
                for ($i = 0, $html = ""; $i < count($button); $i++) {
                    $class = isset($button[$i]['class']) ? $button[$i]['class'] : "button";
                    $input_name = isset($button[$i]['input_name']) ? 'name="' . $button[$i]['input_name'] . '"' : null;
                    $html .= '<button ' . $input_name . ' class="' . $class . '">' . $button[$i]['button'] . '</button> ';
                }
                $this->html .= str_replace(array('{$button}', '{$counter}'), array($html, $this->counter % 2), $this->layout['buttons']);
            }
            else
                $this->html .= str_replace(array('{$button}', '{$counter}'), array('<input type="submit" value="' . $button . '" class="button"/>', $this->counter % 2), $this->layout['buttons']);
        }

        // accepted validation method
        //required,remote,minlenght,maxlength,rangelength,min,max,range,email,url,date,dateISO,dateDE,number,numberDE,digits, creditcard,accept,equalTo
        function add_validation($name, $validation, $message = null) {

            // add the validation
            Layout::addScript("jquery.validate.min.js", JQUERY_DIR, JQUERY_URL);

            if ($validation) {

                $val = explode(",", $validation);

                foreach ($val as $validation) {

                    $array = explode("=", $validation);
                    $validation = $array[0];
                    $value = isset($array[1]) ? $array[1] : true;

                    if (!in_array($validation, explode(',', 'required,remote,minlenght,maxlength,rangelength,min,max,range,email,url,date,dateISO,dateDE,number,numberDE,digits,creditcard,accept,equalTo')))
                        echo "Validation method not found: $validation<br>";

                    $message = $message ? $message : str_replace('{$value}', $value, get_msg($validation));
                    $message = "$validation: '$message'";
                    $rule = "$validation: $value";

                    if (!isset($this->validation[$name]))
                        $this->validation[$name] = array('rules' => '', 'messages' => '');

                    $this->validation[$name]['rules'][] = $rule;
                    $this->validation[$name]['messages'][] = $message;
                    $message = null;
                }
            }
        }

        function draw($ajax = false, $return_string = false) {

            if ($ajax) {
                // add ajax jquery script
                Layout::addScript("jquery.form.min.js", JQUERY_DIR, JQUERY_URL);
                $ajax = file_get_contents(self::$form_dir . "Form/tpl/{$this->layout_name}/ajax.js");
                $ajax = str_replace('{$name}', $this->name, $ajax);
            }

            $validation = null;

            // create the validation html
            if ($this->validation) {
                if ($ajax)
                    $ajax .= ",";
                $rules = $messages = "";
                $j = 0;
                foreach ($this->validation as $name => $array) {

                    $rules .= "        $name : { ";
                    $messages .= "        $name : { ";

                    for ($i = 0; $i < count($array['rules']); $i++) {
                        $rules .= $array['rules'][$i] . ( $i + 1 < count($array['rules']) ? "," : "" );
                        $messages .= $array['messages'][$i] . ( $i + 1 < count($array['messages']) ? "," : "" );
                    }

                    $rules .= " }" . ( $j + 1 < count($this->validation) ? "," : "" ) . "\n";
                    $messages .= " }" . ( $j + 1 < count($this->validation) ? "," : "" ) . "\n";
                    $j++;
                }

                $validation = "    rules: { " . "\n" .
                        $rules . "\n" .
                        "    }, " . "\n" .
                        "    messages: { " . "\n" .
                        $messages . "\n" .
                        "    } " . "\n";
            }

            // add javascript
            $script = '$("#' . $this->name . '").validate({' . "\n" . ( $this->tiny_mce ? 'submit: function(){ tinyMCE.triggerSave() },' : null) . "\n" . $ajax . "\n" . $validation . "\n" . '    });';

            // add the javascript
            Layout::addJavascript($script, $onLoad = true);

            $html = '<div id="' . $this->name . '_loading" style="display:none;"><img src="' . URL . self::$form_dir . 'Form/tpl/' . $this->layout_name . '/img/loading.gif" alt="loading"/>Loading</div>' . "\n" .
                    '<div id="' . $this->name . '_result" style="display:none;"></div>' . "\n" .
                    '<form name="' . $this->name . '" id="' . $this->name . '" class="form" action="' . $this->action . '" method="' . $this->method . '" ' . ( $this->target ? 'target="' . $this->target . '"' : null ) . ( $this->upload_file ? ' enctype="multipart/form-data"' : '' ) . ">" . "\n" . $this->hidden . "\n" . $this->html . "\n" . "</form>" . "\n";


            if ($return_string)
                return $html; 
            else
                echo $html;
        }

        private function _text($name, $value, $param, $type = 'text') {
            $attributes = '';
            if (is_array($param))
                foreach ($param as $attr => $val)
                    $attributes .= $attr == 'disabled' ? ' disabled' : " $attr=\"$val\"";
            return "<input type=\"$type\" name=\"$name\" value=\"$value\" class=\"text\"/>";
        }

        private function _password($name, $value, $param) {
            return $this->_text($name, $value, $param, "password");
        }

        private function _file($name, $value, $param) {
            $attributes = "";
            if (is_array($param))
                foreach ($param as $attr => $val)
                    $attributes .= $attr == 'MAX_FILE_SIZE' ? $this->addHidden('MAX_FILE_SIZE', $val) : " $attr=\"$val\"";
            return "<input type=\"file\" name=\"$name\" value=\"$value\" class=\"text\"/>";
        }

        private function _textarea($name, $value, $param) {

            $param['rows'] = isset($param['rows']) ? $param['rows'] : '';
            $param['cols'] = isset($param['cols']) ? $param['cols'] : '';
            $attributes = "";
            if (is_array($param))
                foreach ($param as $attr => $val)
                    $attributes .= " $attr=\"$val\"";
            return "<textarea name=\"$name\" class=\"textarea\" $attributes>$value</textarea>";
        }

        private function _checkbox($name, $value, $param) {
            $attributes = "";
            if (is_array($param))
                foreach ($param as $attr => $val)
                    if ($attr != 'options')
                        $attributes .= " $attr=\"$val\"";
            $html = '<div>';
            if (isset($param['options']) && ($options = $param['options']))
                foreach ($options as $v => $n)
                    $html .= '<label for="checkbox' . ($name . $v) . '">' . $n . '</label><input type="checkbox" ' . ($v == $value || (is_array($value) && isset($value[$v])) ? ' checked="checked"' : null) . ' id="checkbox' . ($name . $v) . '" name="' . $name . '[' . $v . ']" ' . $attributes . ' class="crirHiddenJS"/>  &nbsp; &nbsp; ';

            $html .= '</div>';
            return $html;
        }

        private function _radio($name, $value, $param) {
            $i = 0;
            $html = '';
            foreach ($param['options'] as $val => $key) {
                $html .= "    <label for=\"radio_{$name}_{$i}\">$key</label>" . "\n" .
                        "    <input type=\"radio\" name=\"$name\" value=\"$val\" id=\"radio_{$name}_{$i}\" " . ( $val == $value ? ' checked="checked"' : "" ) . "/> &nbsp; " . "\n";
                $i++;
            }
            return $html;
        }

        private function _yes($name, $value = true) {
            return $this->_radio($name, $value, array('options' => array(true => _YES_, false => _NO_)), null);
        }

        private function _select($name, $value, $param) {

            $attributes = "";
            if (is_array($param))
                foreach ($param as $attr => $val)
                    if ($attr != 'options')
                        $attributes .= " $attr=\"$val\"";

            $options = "";
            if (is_array($param['options']))
                foreach ($param['options'] as $option_value => $option_name)
                    $options .= $value == $option_value ? "<option class=\"selected\" value=\"$option_value\" selected=\"selected\">$option_name</option>" : "<option value=\"$option_value\">$option_name</option>";

            return "<select name=\"$name\" class=\"select\" $attributes>$options</select>";
        }

        function _word($name, $value, $param) {


            Layout::addScript('jquery.webkitresize.js', JQUERY_DIR, JQUERY_URL);
            Layout::addScript("jquery.tinymce.js", self::$form_dir . "Form/plugins/tiny_mce/", SYSTEM_URL . self::$form_dir . "Form/plugins/tiny_mce/");
            $mode = isset($param['mode']) && $param['mode'] == 'simple' ? 'simple' : 'advanced';
            $css = isset($param['css']) ? ',content_css:"' . $param['css'] . '"' : null;

            if (!isset($param['rows']) and !isset($param['height']))
                $param['rows'] = $mode == 'simple' ? 8 : 18;
            $param['rows'] = isset($param['rows']) ? $param['rows'] : '';
            $param['cols'] = isset($param['cols']) ? $param['cols'] : '';

            /* autoresize */
            if ($mode == 'advanced')
            // add plugin: ,rain
                $tinymce_param = '
                    oninit: function () { $("#' . $name . '_ifr").webkitimageresize().webkittableresize().webkittdresize(); },
                    plugins: "safari,fullscreen,searchreplace,media,paste,autosave,inlinepopups,print,pagebreak,rain",
                    theme_advanced_buttons1 : "bold,italic,underline,|,justifyleft,justifycenter,justifyright,justifyfull,fontsizeselect,|,forecolor,backcolor,|,fullscreen,pagebreak",
                    theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,replace,|,bullist,numlist,|,undo,redo,|,link,unlink,rain,image,media,code,|,hr,removeformat,|,charmap",
                    theme_advanced_buttons3 : "",
                    paste_auto_cleanup_on_paste : true,
                    theme_advanced_toolbar_location: "top",
                    theme_advanced_toolbar_align: "left",
                    theme_advanced_path_location: "bottom",
                    theme_advanced_resizing : true,
                    theme_advanced_resize_horizontal : false,
                    theme_advanced_resizing_use_cookie : false,
                    valid_elements: "*[*]",
                    relative_urls : false,
                    convert_urls : false,
                    height: 400,
                    pagebreak_separator : "<!-- page break -->"' . $css;
            else
                $tinymce_param = '
                    oninit: function () { $("#' . $name . '_ifr").webkitimageresize().webkittableresize().webkittdresize(); },
                    theme_advanced_buttons1 : "bold,italic,underline, strikethrough, separator,justifyleft, justifycenter,justifyright,justifyfull",
                    theme_advanced_buttons2: "",
                    theme_advanced_buttons3: "",
                    paste_auto_cleanup_on_paste : true,
                    theme_advanced_toolbar_location : "top",
                    theme_advanced_toolbar_align : "left",
                    theme_advanced_resize_vertical : true,
                    theme_advanced_path_location: "bottom",
                    theme_advanced_resizing : true,
                    theme_advanced_resize_horizontal : false,
                    theme_advanced_resizing_use_cookie : false,
                    auto_resize : true,
                    relative_urls : false,
                    convert_urls : false,
                    valid_elements: "*[*]"' . $css;

            Layout::addJavascript('$("textarea.mce_' . $name . '").tinymce({
                                        script_url : "' . URL . self::$form_dir . 'Form/plugins/tiny_mce/tiny_mce.js",
                                        theme: "advanced",
                                        language: "' . LANG_ID . '",
                                        mode: "exact",
                                        elements: "' . $name . '",
                                        force_br_newlines: true,
                                        tab_focus: ":prev,:next",
                                        convert_fonts_to_spans: false,
                                        width: "100%",
                                        onchange_callback: function(editor) {
                                            tinyMCE.triggerSave();
                                            $("#" + editor.id).valid();
                                        },
                                        ' . $tinymce_param . '
                                    })
                                    ', $onload = true);

            $attributes = "";
            if (is_array($param))
                foreach ($param as $attr => $val)
                    if ($attr != 'css' && $attr != 'mode')
                        $attributes .= " $attr=\"$val\"";

            $this->tiny_mce = true;

            return "<textarea name=\"$name\" id=\"$name\" class=\"textarea mce_$name\" $attributes>$value</textarea>";
        }

        function _date($name, $value, $param) {
            $dateFormat = isset($param['dateFormat']) ? $param['dateFormat'] : DATE_FORMAT_SIMPLE;
            Layout::addJavascript("$('#label_$name').datepicker({yearRange:'-70:0',dateFormat: '$dateFormat'});", $onload = true);
            Layout::addScript("ui/jquery.ui.datepicker.js", JQUERY_DIR, JQUERY_URL);
            Layout::addStyle("themes/smoothness/ui.all.css", JQUERY_DIR, JQUERY_URL);
            return '<input name="' . $name . '" type="text" id="label_' . $name . '" value="' . $value . '" class="text" />';
        }

        private function _layout_replace($title, $description, $input, $layout) {
            return str_replace(array('{$title}', '{$description}', '{$input}', '{$counter}'), array($title, $description, $input, $this->counter % 2), $this->layout[$layout]);
        }

    }

    // -- end