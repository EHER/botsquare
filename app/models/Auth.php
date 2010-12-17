<?php

namespace app\models;

/**
 * Description of Auth
 *
 * @author eher
 */
class Auth {
    private $login;
	private $password;

	public function  __toString() {
		$string = '';
		$string .= $this->getLogin();
		$string .= ':';
		$string .= $this->getPassword();
		return $string;
	}

	public function setLogin($login) {
	 $this->login = $login;
	}

	public function setPassword($password) {
	 $this->password = $password;
	}

		public function getLogin() {
	 return $this->login;
	}

	public function getPassword() {
	 return $this->password;
	}


}
