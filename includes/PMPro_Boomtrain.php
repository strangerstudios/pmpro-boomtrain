<?php

class PMPro_Boomtrain {

    public $username;
    public $password;
    public $apikey;
    public $token;
    public $root = 'https://api.boomtrain.com';
    public $headers;
    public $error;

    function __construct() {

        //setup api
        $this->setOptions();
        $this->setToken();
    }

    function setToken() {

        $url = $this->root . '/tokens';

        $fields = array(
            'username' => $this->username
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        $r = curl_exec($ch);
        curl_close($ch);

        if($r) {
            $r = json_decode($r);
            if(!empty($r->token)) {
                $this->token = $r->token;
                return true;
            }
            $this->error = "$r->type: $r->message";
        }
        return false;
    }

    function setOptions() {
        $options = get_option( 'pmprobt_options' );

        if ( empty( $options ) || empty( $options['tracking_code'])
            || empty( $options['username'])
            || empty( $options['password']) ) {
            return false;
        }

        //set username, password, apikey
        $this->username = $options['username'];
        $this->password = $options['password'];
        $this->apikey = preg_replace( '/^.*n\/([a-z0-9]*).*$/', '$1', $options['tracking_code'] );

        //set x-app-id header for later
        $this->headers = array(
            "x-app-id:$this->apikey"
        );

        return true;
    }

    function trackEvent($type, $email, $fields = null) {

        if(empty($type) || empty($email))
            return false;

        $url = 'https://events.boomtrain.com/event/track';

        $ch = curl_init();

        $headers = $this->headers;
        $headers[] = 'Content-Type: application/json';

        $postfields = array(
            'type' => $type,
            'email' => $email
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $r = curl_exec($ch);
//        $info = curl_getinfo($ch);

        curl_close($ch);

        if($r == 'OK')
            return true;
        elseif(!empty($r))
            $this->error = $r['type'] . ': ' . $r['message'];
        return false;
    }

    function updatePerson($user_id, $fields = array()) {

        if(empty($user_id))
            return false;

        //get persons API URL
        $url = $this->root .= '/persons';

        //get user
        $user = get_userdata($user_id);

        //allow filter for custom fields, etc.
        $fields = apply_filters('pmprobt_update_person_fields', $fields);

        //build attributes array
        $atts = array();
        foreach($fields as $key=>$value) {
            $atts[] = array(
                'op' => 'replace',
                'name' => $key,
                'value' => $value
            );
        }

        $ch = curl_init();

        //setup postfields
        $postfields = array(
            'bsin' => '',
            'appMemberId' => "$user_id",
            'attributes' => $atts
        );

        fb($postfields, 'postfields');

        $headers = $this->headers;
        $headers[] = "Content-Type: application/json";

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $r = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        fb($r, 'r');
        fb($info, 'info');

        if($r == 'OK')
            return true;
        elseif(!empty($r)) {
            $r = json_decode($r);
            $this->error = "$r->type: $r->message";
        }

        return false;
    }
    
    function getPerson($email) {

        if(empty($email))
            return false;

        //get url
        $url = $this->root . '/person?email=' . $email;

        $ch = curl_init();

        $headers = $this->headers;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $r = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $person = json_decode($r);

        if(!empty($person))
            return $person;
        elseif(!empty($r))
            $this->error = "$r->type: $r->message";
        return false;
    }
}