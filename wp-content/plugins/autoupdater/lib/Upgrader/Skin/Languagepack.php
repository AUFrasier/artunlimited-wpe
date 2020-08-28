<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Upgrader_Skin_Languagepack extends Language_Pack_Upgrader_Skin
{
    /**
     * @var bool
     * @since 4.0 WordPress
     */
    public $done_footer = false;

    protected $errors = array();

    protected $translations = array();

    public function header()
    {
        if ($this->done_header) {
            return;
        }
        $this->done_header = true;
    }

    public function footer()
    {
        if ($this->done_footer) {
            return;
        }
        $this->done_footer = true;
    }

    /**
     * PD: Store errors instead of sending them to the feedback method
     *
     * @param string|WP_Error $errors
     */
    public function error($errors)
    {
        if (is_string($errors)) {
            $this->errors[$errors] = $message = $errors;
        } elseif (is_wp_error($errors)) {
            /** @var WP_Error $errors */
            $message = 'Error code: ' . $errors->get_error_code() . ', message: ' . $errors->get_error_message();

            $this->errors[$errors->get_error_code()] = $errors->get_error_message();
        } else {
            $error = var_export($errors, true);
            $message = 'Unknown error, dump: ' . $error;

            $this->errors['unknown_error'] = $error;
        }

        AutoUpdater_Log::debug($message);
    }

    /**
     * PD: Get all stored errors
     *
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * PD: Get a list of updated translations
     *
     * @return array
     */
    public function get_translations()
    {
        return $this->translations;
    }

    public function feedback($string)
    {
    }

    public function before()
    {
    }

    public function after()
    {
        /** @var object $update */
        $update = $this->language_update;
        $slug = $update->type == 'core' ? 'wordpress' : $update->slug;

        $this->translations[] = sprintf('%s %s %s %s released at %s'
            , ucfirst($update->type)
            , $slug
            , $update->version
            , $update->language
            , $update->updated
        );
    }

    public function bulk_header()
    {
    }

    public function bulk_footer()
    {
    }
}
