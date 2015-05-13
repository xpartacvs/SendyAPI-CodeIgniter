<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Sendyci {
    private $key;
    private $host;
    private $timeout;
    
    private $listid;
    
    public function __construct() {
        $this->_check_compatibility();
        
        $CI =& get_instance();
        $CI->load->config('sendyci');
        
        if ($CI->config->item('sendy_key')!==FALSE) $this->key = $CI->config->item('sendy_key'); else throw new Exception('Undefined API Key');
        if ($CI->config->item('sendy_host')!==FALSE) $this->host = $CI->config->item('sendy_host'); else throw new Exception('Undefined Host');
        
        $this->timeout = ($CI->config->item('sendy_listid')!==FALSE) ? $CI->config->item('sendy_listid') : '';
        $this->timeout = ($CI->config->item('sendy_timeout')!==FALSE) ? $CI->config->item('sendy_timeout') : 120;
    }
    
    private function _check_compatibility() {
        if (!extension_loaded('curl')) throw new Exception('There are missing dependant extensions - please ensure cURL module are installed');
    }
    
    private function _curl_execute($type, $values) {
        //error checking
        if (empty($type)) throw new Exception("Required config parameter [type] is not set or empty", 1);
        if (empty($values)) throw new Exception("Required config parameter [values] is not set or empty", 1);

        //Global options for return
        $return_options = array(
            'list' => $this->listid,
            'boolean' => 'true'
        );

        //Merge the passed in values with the options for return
        $content = array_merge($values, $return_options);

        //build a query using the $content
        $postdata = http_build_query($content);

        $ch = curl_init($this->host .'/'. $type);

        // Settings to disable SSL verification for testing (leave commented for production use)
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        $result = curl_exec($ch);
        curl_close($ch);
        unset($ch);
        
        return $result;
    }
    
    public function set_list_id($id) {
        $this->listid = $id;
    }
    
    public function get_list_id() {
        return $this->listid;
    }
    
    public function subscribe($name,$email) {
        $type = 'subscribe';
        
        //Post fields
        $values = array(
            'name' => $name,
            'email' => $email
        );
        
        //Send the subscribe
        $result = strval($this->_curl_execute($type, $values));

        // Convert result
        $ret = strtolower(strval($result));
        unset($result);
        
        //Handle ret
        if (strcasecmp($ret, '1')==0) {
            $response = array(
                'status' => true,
                'message' => 'Subscribed.'
            );
        } else if (strcasecmp($ret,'already subscribed.')==0) {
            $response = array(
                'status' => true,
                'message' => 'Already subscribed.'
            );
        } else {
            $response = array(
                'status' => false,
                'message' => $ret
            );
        }
        
        return $response;
    }
    
    public function unsubscribe($email) {
        $type = 'unsubscribe';

        //Send the unsubscribe
        $result = strval($this->_curl_execute($type, array('email' => $email)));

        // Convert result
        $ret = strval($result);
        unset($result);
        
        //Handle ret
        if (strcmp($ret, '1')==0) {
            $response = array(
                'status' => true,
                'message' => 'Unsubscribed.'
            );
        } else {
            $response = array(
                'status' => false,
                'message' => $ret
            );
        }
        
        return $response;
    }
    
    public function get_status($email) {
        $type = 'api/subscribers/subscription-status.php';

        //Send the request for status
        $result = $this->_curl_execute($type, array(
            'email' => $email,
            'api_key' => $this->key,
            'list_id' => $this->listid
        ));
        
        // Convert result
        $ret = strtolower(strval($result));
        unset($result);

        //Handle the results
        if (strcasecmp($ret, 'subscribed')==0) {
            $response = array(
                'status' => 1,
                'message' => $ret
            );
        } else if (strcasecmp($ret, 'unsubscribed')==0) {
            $response = array(
                'status' => -1,
                'message' => $ret
            );
        } else if (strcasecmp($ret, 'unconfirmed')==0) {
            $response = array(
                'status' => -2,
                'message' => $ret
            );
        } else if (strcasecmp($ret, 'bounced')==0) {
            $response = array(
                'status' => -3,
                'message' => $ret
            );
        } else if (strcasecmp($ret, 'soft bounced')==0) {
            $response = array(
                'status' => -4,
                'message' => $ret
            );
        } else if (strcasecmp($ret, 'complained')==0) {
            $response = array(
                'status' => -5,
                'message' => $ret
            );
        } else {
            $response = array(
                'status' => 0,
                'message' => $ret
            );
        }
        
        return $response;
    }
    
    public function get_list_count($list = "") {
        $type = 'api/subscribers/active-subscriber-count.php';

        //if a list is passed in use it, otherwise use $this->list_id
        if (empty($list)) $list = $this->listid;

        //handle exceptions
        if (empty($list)) throw new Exception("method [subcount] requires parameter [list] or [$this->listid] to be set.", 1);

        //Send request for subcount
        $result = $this->_curl_execute($type, array(
            'api_key' => $this->key,
            'list_id' => $list
        ));

        //Handle the results
        if (is_numeric($result)) {
            return array(
                'status' => true,
                'message' => $result
            );
        }

        //Error
        return array(
            'status' => false,
            'message' => $result
        );
    }
}
