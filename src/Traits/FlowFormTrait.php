<?php

namespace Base\Traits;
use Base\Subscriber\FlowFormSubscriber;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

trait FlowFormTrait
{
    public static $flowSessions = [];
    public static $flowCallbacks = [];

    public static function getTokenID($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'flow_form'      => true,
            'flow_form_name' => '_flow_token'
        ]);
    }

    public static function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options["flow_form"]) return;

        $step = self::getStep($options);
        $token = self::getToken($options);

        $builder->add($options['flow_form_name'], HiddenType::class, [
                    'mapped' => false,
                    "attr" => ["value" => $token."#". $step]
                ]);

        $flowFormId  = $options['flow_form_id'] ?? "";
        if(isset(self::$flowCallbacks[$flowFormId]) && isset(self::$flowCallbacks[$flowFormId][$step]))
            call_user_func(self::$flowCallbacks[$flowFormId][$step], $builder, $options);
    }

    public static function hasSession(array $options, Session $session = null)
    {
        $session = self::bindSession($options, $session);

        if(!$session->has("_flow_form"))
            $session->set("_flow_form", []);

        $flowFormId  = $options['flow_form_id'] ?? "";
        $flowForm    = $session->get("_flow_form");
        return array_key_exists($flowFormId, $flowForm);
    }

    public static function bindSession(array $options, Session $session = null): Session
    {
        $flowFormId  = $options['flow_form_id'] ?? "";
        if($session) self::$flowSessions[$flowFormId] = $session;

        if (!isset(self::$flowSessions[$flowFormId]))
            throw new Exception("Bind session to Type first using Type::bindSession(array, Session)");

        return self::$flowSessions[$flowFormId];
    }

    public static function setSession(array $options, array $entry, Session $session = null)
    {
        $session = self::bindSession($options, $session);

        $flowFormId            = $options['flow_form_id'] ?? "";
        $flowForm              = $session->get("_flow_form");
        $flowForm[$flowFormId] = $entry;

        $session->set("_flow_form", $flowForm);
    }

    public static function getSession(array $options, Session $session = null)
    {
        $session = self::bindSession($options, $session);
        if (!self::hasSession($options)) return [];

        $flowFormId  = $options['flow_form_id'] ?? "";
        $flowForm    = $session->get("_flow_form");
        return $flowForm[$flowFormId];
    }

    public static function appendSessionPost(array $options)
    {
        $formSession = self::getSession($options);

        if(!array_key_exists("POST", $formSession)) $formSession["POST"] = [];
        $formSession["POST"] = array_merge($formSession["POST"], $_POST);

        self::setSession($options, $formSession);
    }

    public static function appendSessionFiles(array $options)
    {
        $formSession = self::getSession($options);

        if(!array_key_exists("FILES", $formSession)) $formSession["FILES"] = [];
        foreach($_FILES as $key => $file) {

            $_FILES[$key]["tmp_name"] = stream_get_meta_data(tmpfile())['uri'];
            move_uploaded_file($file["tmp_name"], $_FILES[$key]["tmp_name"]);
        }

        $formSession["FILES"] = array_merge($formSession["FILES"], $_FILES);

        self::setSession($options, $formSession);
    }

    public static function appendSessionExtras(array $options, $extras)
    {
        $formSession = self::getSession($options);

        if(!array_key_exists("EXTRAS", $formSession)) $formSession["EXTRAS"] = [];
        $formSession["EXTRAS"] = array_merge($formSession["EXTRAS"], $extras);

        self::setSession($options, $formSession);
    }

    public static function killSession(array $options, Session $session = null)
    {
        $session = self::bindSession($options, $session);

        if(!self::hasSession($options)) return false;
        $flowFormId  = $options['flow_form_id'] ?? "";
        $flowForm = $session->get("_flow_form");

        // Delete temporary files
        $formFiles = $flowForm[$flowFormId]["FILES"] ?? [];
        foreach($formFiles as $file) {

            // Check if really a tmp file
            if( str_starts_with($file["tmp_name"], sys_get_temp_dir()) )
                unlink($file["tmp_name"]);
        }

        // Delete corresponding formId
        unset($flowForm[$flowFormId]);
        $session->set("_flow_form", $flowForm);

        return true;
    }

    // Form flow methods
    public static function removeAllSteps(array $options)
    {
        $flowFormId  = $options['flow_form_id'] ?? "";
        self::$flowCallbacks[$flowFormId] = [];
    }
    public static function addStep(array $options, $callback)
    {
        $flowFormId  = $options['flow_form_id'] ?? "";
        self::$flowCallbacks[$flowFormId][] = $callback;
    }

    public static function addConfirmStep(array $options)
    {
        return self::addStep($options, function (FormBuilderInterface $builder, array $options) {});
    }

    public static function getPreviousStep(array $options)
    {
        $step = self::getStep($options);
        return ($step > 0) ? $step - 1 : 0;
    }

    public static function getStepMax(array $options)
    {
        $flowFormId  = $options['flow_form_id'] ?? "";
        return count(self::$flowCallbacks[$flowFormId]);
    }

    public static function getNextStep(array $options)
    {
        $step = self::getStep($options);
        $stepMax = self::getStepMax($options);
        return ($step < $stepMax) ? $step + 1 : $stepMax;
    }

    public static function setStep(array $options, $step) {

        $token = self::getToken($options);

        $name  = $options['flow_form_name'] ?? "";
        if(empty($name)) throw new Exception("Unexpected option provided for \"flow_form_name\"");

        $_POST[$name] = $token . "#" . $step;
    }

    public static function getStep(array $options)
    {
        $name  = $options['flow_form_name'] ?? "";
        if (empty($name)) throw new Exception("Unexpected option provided for \"flow_form_name\"");

        $token = $_POST[$name] ?? "";
        if (!empty($token) && preg_match("/(.*)#([0-9]*)/", $token, $matches)) return (int) $matches[2];

        return 0;
    }

    public static function getToken(array $options)
    {
        $name  = $options['flow_form_name'] ?? "";
        $token = $_POST[$name] ?? "";

        if (!empty($token) && preg_match("/(.*)#([0-9]*)/", $token, $matches)) return $matches[1];

        return self::getTokenID();
    }
}
