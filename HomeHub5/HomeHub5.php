<?php

require_once 'Utils.php';

// This class deals specifically with the BT Home Hub 5 and its logging in setup

class HomeHub5 extends Utils {

	// Change this to match your router settings
	const BASE_PAGE = '/index.cgi';
	
	// The ID's of specific pages, this list is far from exhaustive (valid for version 4.7.5.1.83.8.173.1.6)
	const LOGIN = 9145;
	
	const SETTINGS_WIRELESS = 9101;
	const SETTINGS_ACCESS_CONTROL = 9113;
	const SETTINGS_PORT_FORWARDING = 9105;
	const SETTINGS_HUB_LIGHTS = 9128;
	const SETTINGS_BROADBAND = 9114;
	const SETTINGS_ADMIN_PASSWORD = 9123;
	
	const ADVANCED_SETTINGS_CONTINUE = 9099;
	const ADVANCED_WIRELESS_PAGE_24G = 9102;
	const ADVANCED_WIRELESS_PAGE_5G = 9103;
	const ADVANCED_WIRELESS_PAGE_WPS = 9104;
	const ADVANCED_BROADBAND_INTERNET = 9117;
	const ADVANCED_BROADBAND_CONNECTION = 9118;
	const ADVANCED_BROADBAND_VPN = 9119;
	const ADVANCED_BROADBAND_DYNAMIC_DNS = 9121;
	const ADVANCED_BROADBAND_IPV6_STATUS = 9122;
	
	const HOMENETWORK_DEVICES = 9130;
	const HOMENETWORK_SMART_SETUP = 9135;
	const HOMENETWORK_IP_ADDRESS = 9136;
	const HOMENETWORK_IPV6_CONFIGURATION = 9138;
	const HOMENETWORK_DHCP_TABLE = 9137;
	
	const FIREWALL_PORT_FORWARDING = 9106;
	const FIREWALL_UPNP = 9109;
	const FIREWALL_DMZ = 9110;
	const FIREWALL_IPV6_PINHOLES = 9111;
	const FIREWALL_CONFIGURATION = 9112;
	
	const SYSTEM_RESTART = 9124;
	const SYSTEM_BACKUP_RESTORE = 9125;
	const SYSTEM_RESET = 9126;
	
	const TROUBLESHOOTING_HELP_AND_ADVICE = 9139;
	const TROUBLESHOOTING_HELPDESK = 9140;
	const TROUBLESHOOTING_EVENT_LOG = 9141;
	
	// The password and IP class variables
	private $password;
	private $router_ip;
	
	// The cookie file location
	private $cookie_file;
	
	/**
	 * Set the cookie file that the login process will use
	 * 
	 * @param string $location Cookie location
	 */
	public function setCookieFile($location) {
		$this->cookie_file = $location;
	}
	
	/**
	 * Set the password for the router
	 * 
	 * @param string $password The router password
	 */
	public function setPassword($password) {
		$this->password = $password;
	}
	
	/**
	 * Set the IP address of the router
	 * 
	 * @param string $address The IP address
	 */
	public function setRouterIP($address) {
		$this->router_ip = $address;
	}		
		
	/**
	 * Attempt to retrieve a new cookie ID 
	 * 
	 * @throws Exception
	 */
	public function login() {
		// Make sure that we have a password set
		if (empty($this->password)) {
			throw new Exception ('No password set - use setPassword($password)');
			return false;
		}		
		
		// Make sure we have a router IP address
		if (empty($this->router_ip)) {
			throw new Exception ('No router IP set - use setRouterIP($address)');
			return false;
		}
		
		// Open a cookie file
		if (is_null($this->cookie_file)) {
			throw new Exception ('No cookie file set - use setCookieFile($location)');
		}
		
		$get_output = $this->sendGet('http://'.$this->router_ip.self::BASE_PAGE.'?active_page='.self::LOGIN, array(), $this->cookie_file);
				
		if ($get_output) {					
			// Use a regex to get the auth_key out of the page
			// Could have parsed the DOM, but the HTML looked pretty ropey and this was the quickest way
			if (preg_match('/name="auth_key" value="(?<auth_key>\d*)"/si', $get_output['body'], $auth_match)) {
				$auth_key = $auth_match['auth_key'];
			} else {
				// If we can't get an auth key then give up
				throw new Exception ('Failed to get auth key '.print_r($get_output, true));
				return false;
			}
									
			// We have everything we need to attempt to make a POST request
			$md5_pass = strtolower(md5($this->password.$auth_key));

			// The POST fields
			$fields = array (
				'active_page' => self::LOGIN,
				'mimic_button_field' => 'submit_button_login_submit: ..',
				'post_id' => 0,
				'md5_pass' => $md5_pass,
				'auth_key' => $auth_key
			);			
					
			// Make the POST request
			$post_output = $this->sendPost('http://'.$this->router_ip.self::BASE_PAGE, $fields, array(), self::HTTP_REQUEST_STANDARD, $this->cookie_file);
						
			// If the POST completes successfully...
			if ($post_output['http_code'] == 302) {
				// We have a redirect, but that is not indicative of a successful login
				// Check to see if we're being redirected back to the page we originally requested
				$redirect_url = urldecode($post_output['info']['redirect_url']);
				$redirect_url_parts = parse_url($redirect_url);
				parse_str($redirect_url_parts['query'], $query_parts);
				
				// Check to see if the page we're being redirected to is the login page
				if ((int) $query_parts['active_page'] != (int) self::LOGIN) {
					return true;
				} else {
					return false;
				}
			}
		} else {
			// If we can't get any response from the hub, give up
			throw new Exception ('No response');
			return false;
		}
		
		// If anything falls through then it failed
		return false;
	}
	
	/**
	 * Get a specific page by a specific page ID
	 * 
	 * @param int $page_id 4 digit page ID
	 * @throws Exception
	 * @return boolean|Ambigous <boolean, multitype:mixed string multitype:string multitype:Ambigous <multitype:> mixed   >
	 */
	public function getPage ($page_id) {	
		// Make sure we have a page to get
		if (empty($page_id) || !is_numeric($page_id)) {
			throw new Exception ('No page ID set, or invalid page ID');
			return false;
		}
		
		// Check to see if the router IP
		if (empty($this->router_ip)) {
			throw new Exception ('No router IP set - use setRouterIP($address)');
			return false;
		}
		
		// Check that we have a cookie file
		if (empty($this->cookie_file)) {
			throw new Exception ('No cookie file set - use setCookieFile($location)');
		}
		
		// Make a request for the page, and return the contents
		$get_output = $this->sendGet('http://'.$this->router_ip.self::BASE_PAGE.'?active_page='.$page_id, array(), $this->cookie_file);

		if ($get_output) {
			if (preg_match('/<!-- Page\((?<page_id>\d*?)\)=/', $get_output['body'], $matches)) {
				$found_page_id = $matches['page_id'];
				if ($found_page_id == self::LOGIN) {
					// Delete the current cookie file and recreate it
					unlink($this->cookie_file);
					touch($this->cookie_file);
					
					// Login, and the retry the page
					if (!$this->login()) {
						throw new Exception ('Failed to login');
					}
					
					// Get the page again
					$get_output = $this->sendGet('http://'.$this->router_ip.self::BASE_PAGE.'?active_page='.$page_id, array(), $this->cookie_file);
				}
			} else {
				throw new Exception ('Failed to get page ID');
			}
		}
		
		// Check to see if the returned page ID is what we expected, if not, we'll log in first, then try it again
		return $get_output;
	}
	
	/**
	*	Attempt to find the uptime figure in the page, and return it
	*
	*	@param string $body The body of the document
	*	@return int Uptime in seconds
	**/
	public function getUptime($body) {
		// Looks for the wait variable and extracts digits before a semi colon
		if (preg_match('/wait = (?<uptime>\d*?);/si', $body, $matches)) {
			return (int) $matches['uptime'];
		} else {
			return false;
		}
	}
	
	/**
	*   Attempt to find the data usage figures, and return them as megabytes
	*
	*	@param string $body The body of the document
	*	@return array The received and transmitted values
	**/
	public function getDataUsage($body) {
		// Looks for the data sent/received row and gets the text before the end of the cell tag
		if (preg_match('%11. Data sent/received:</td>.*?>(?<data>.*?)</%si', $body, $matches)) {
			// Explode the results and check that we have the correct number of items
			$e = explode(' / ', $matches['data']);
			if (count($e) == 2) {				
				// We'll check the last two characters, and if they match GB, we'll multiply the result
				// otherwise we'll just remove the MB and return
				foreach ($e as &$v) {
					$p = explode(' ', $v);
					if ($p[1] == 'GB') {
						$p[0] *= 1024;
					}
					$v = $p[0];
				}
				
				// We'll return as an object rather than an array to keep it clean for Restler
				$return = new stdClass();
				$return->sent = (float) $e[0];
				$return->received = (float) $e[1];
				
				return $return;
			}
			return false;
		} else {
			return false;
		}
	}
	
	/**
	 * Attempt to find the version number and last update date
	 * 
	 * @param string $body The body of the document
	 * @return stdClass|boolean
	 */
	public function getVersion($body) {
		// Get the Home Hub version, and the date it was last updated
		if (preg_match('%Software version (?<version>.*?) Last updated (?<date>.*?)</%', $body, $matches)) {
			// We'll return as an object rather than an array to keep it clean in Restler
			$return = new stdClass();
			$return->version = $matches['version'];
			$return->update_date = $matches['date'];
			
			return $return;
		}
		
		// We failed to get the result we were expecting
		return false;
	}
}