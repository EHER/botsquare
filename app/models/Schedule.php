<?php

namespace app\models;

use \lithium\data\Model;

/**
 * Description of Schedule
 *
 * @author eher
 */
class Schedule extends Model {
	private $place;
	private $date;
	private $time;
	private $repeat;
	private $done;

	public function __toString() {
		$string = '';
		$string .= $this->getRepeat() . ' ';
		$string .= $this->getDate() . ' ';
		$string .= $this->getTime() . ' ';
		$string .= $this->getPlace();

		return $string;
	}

	public function setDate($date) {
		$this->date = $date;
	}

	public function getDate() {
		return $this->date;
	}

	public function setPlace($place) {
		$this->place = $place;
	}

	public function getPlace() {
		return $this->place;
	}

	public function setTime($time) {
		$this->time = $time;
	}

	public function getTime() {
		return $this->time;
	}

	public function setRepeat($repeat = 'Once') {
		$this->repeat = $repeat;
	}

	public function getRepeat() {
		return $this->repeat;
	}

	public function setDone($done = false) {
		$this->done = $done;
	}

	public function getDone() {
		return $this->done;
	}
}

