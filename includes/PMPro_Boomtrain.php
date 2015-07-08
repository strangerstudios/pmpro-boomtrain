<?php

class PMPro_Boomtrain {

    public $username;
    public $password;
    public $apikey;
    public $token;
    public $root = 'https://api.boomtrain.com';
    public $headers;

    public function __construct() {

        //setup api
        $this->setOptions();
        $this->setToken();
    }

    public function setToken() {

        $ch = curl_init();

        $url = $this->root . '/tokens';

        $fields = array(
            'username' => $this->username
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        $r = curl_exec($ch);

        if($r) {
            $r = json_decode($r);
            $this->token = $r->token;
        }

        curl_close($ch);
        return true;
    }

    public function setOptions() {
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

    public function trackEvent($type = null, $fields = array()) {

        //TODO: track bt_signup (PUT, POST, GET not allowed?)
        if(empty($type))
            return false;

        //get event API URL
        $url = str_replace('api', 'events', $this->root);
        $url .= "/$type/track";

        $ch = curl_init();

        $headers = $this->headers;
        $headers[] = 'Content-Type: application/json';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $r = curl_exec($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        return true;
    }

    public function updatePerson($email, $fields = array()) {

        if(empty($email))
            return false;

        //get persons API URL
        $url = $this->root .= '/persons';

        //get user
        $user = get_user_by('email', $email);

        //allow filter for custom fields, etc.
        $fields = apply_filters('pmprobt_update_person_fields', $fields);

        //build attributes array
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
            'email' => '',
            'bsin' => '',
            'app_member_id' => "$user->ID",
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'attributes' => $atts
        );

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

        curl_close($ch);
    }
}